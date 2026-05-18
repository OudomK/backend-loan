<?php

namespace App\Filament\Resources\BorrowingRepayments;

use App\Filament\Resources\BorrowingRepayments\Pages\CreateBorrowingRepayment;
use App\Filament\Resources\BorrowingRepayments\Pages\EditBorrowingRepayment;
use App\Filament\Resources\BorrowingRepayments\Pages\ListBorrowingRepayments;
use App\Filament\Resources\BorrowingRepayments\Schemas\BorrowingRepaymentForm;
use App\Filament\Resources\BorrowingRepayments\Tables\BorrowingRepaymentsTable;
use App\Models\Borrowing;
use App\Models\BorrowingRepayment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BorrowingRepaymentResource extends Resource
{
    protected static ?string $model = BorrowingRepayment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Fund Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Borrowing Repayments';

    protected static ?string $modelLabel = 'Borrowing Repayment';

    protected static ?string $pluralModelLabel = 'Borrowing Repayments';

    public static function form(Schema $schema): Schema
    {
        return BorrowingRepaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BorrowingRepaymentsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['borrowing.lender', 'receivedByUser']);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBorrowingRepayments::route('/'),
            'create' => CreateBorrowingRepayment::route('/create'),
            'edit' => EditBorrowingRepayment::route('/{record}/edit'),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function normalizeRepaymentData(array $data, ?BorrowingRepayment $record = null): array
    {
        $borrowingId = (int) ($data['borrowing_id'] ?? $record?->borrowing_id ?? 0);
        $borrowing = Borrowing::query()->find($borrowingId);

        if (!$borrowing) {
            throw ValidationException::withMessages([
                'borrowing_id' => 'Borrowing account is required.',
            ]);
        }

        $principal = round((float) ($data['principal_paid'] ?? 0), 2);
        $interest = round((float) ($data['interest_paid'] ?? 0), 2);
        $penalty = round((float) ($data['penalty_paid'] ?? 0), 2);

        if (($principal + $interest + $penalty) <= 0) {
            throw ValidationException::withMessages([
                'principal_paid' => 'At least one amount (principal/interest/penalty) must be greater than zero.',
            ]);
        }

        $currentStatus = strtolower((string) ($data['payment_status'] ?? $record?->payment_status ?? 'confirmed'));
        $alreadyPaidPrincipal = static::confirmedRepaymentsQuery($borrowingId, $record)->sum('principal_paid');

        $projectedConfirmedPrincipal = $alreadyPaidPrincipal
            + ($currentStatus === 'confirmed' ? $principal : 0.0);

        if ($projectedConfirmedPrincipal > ((float) $borrowing->amount + 0.001)) {
            throw ValidationException::withMessages([
                'principal_paid' => 'Principal paid exceeds remaining principal balance.',
            ]);
        }

        $totalPaid = round($principal + $interest + $penalty, 2);
        $balanceAfter = round(max((float) $borrowing->amount - $projectedConfirmedPrincipal, 0), 2);

        if (blank($data['receipt_no'] ?? null)) {
            $data['receipt_no'] = static::generateUniqueReceiptNo();
        }

        $data['principal_paid'] = $principal;
        $data['interest_paid'] = $interest;
        $data['penalty_paid'] = $penalty;
        $data['total_paid'] = $totalPaid;
        $data['balance_after_payment'] = $balanceAfter;
        $data['received_by'] = $data['received_by'] ?? auth()->id();
        $data['payment_status'] = $data['payment_status'] ?? 'confirmed';

        return $data;
    }

    public static function syncBorrowingStatus(?int $borrowingId): void
    {
        if (!$borrowingId) {
            return;
        }

        $borrowing = Borrowing::query()->find($borrowingId);
        if (!$borrowing) {
            return;
        }

        $paidPrincipal = (float) static::confirmedRepaymentsQuery($borrowingId)->sum('principal_paid');

        $borrowing->status = ($paidPrincipal + 0.001 >= (float) $borrowing->amount)
            ? 'completed'
            : 'active';

        $borrowing->saveQuietly();

        activity('borrowings')
            ->performedOn($borrowing)
            ->withProperties([
                'paid_principal' => round($paidPrincipal, 2),
                'status' => $borrowing->status,
            ])
            ->log('Synchronized borrowing status from repayments');
    }

    public static function rebuildBorrowingSchedules(?int $borrowingId): void
    {
        if (!$borrowingId) {
            return;
        }

        DB::transaction(function () use ($borrowingId): void {
            $borrowing = Borrowing::query()
                ->with(['schedules' => fn($query) => $query->orderBy('installment_no')])
                ->find($borrowingId);

            if (!$borrowing || $borrowing->schedules->isEmpty()) {
                return;
            }

            $schedules = $borrowing->schedules->values();

            foreach ($schedules as $schedule) {
                $schedule->principal_paid = 0;
                $schedule->interest_paid = 0;
                $schedule->penalty_paid = 0;
                $schedule->status = 'pending';
                $schedule->paid_date = null;
                $schedule->last_payment_date = null;
            }

            $runningConfirmedPrincipal = 0.0;

            $repayments = static::confirmedRepaymentsQuery($borrowingId)
                ->orderBy('payment_date')
                ->orderBy('id')
                ->get();

            foreach ($repayments as $repayment) {
                $principalLeft = round((float) $repayment->principal_paid, 2);
                $interestLeft = round((float) $repayment->interest_paid, 2);
                $penaltyLeft = round((float) $repayment->penalty_paid, 2);
                $firstScheduleId = null;

                foreach ($schedules as $schedule) {
                    if ($principalLeft <= 0.001 && $interestLeft <= 0.001 && $penaltyLeft <= 0.001) {
                        break;
                    }

                    $principalOutstanding = round(max((float) $schedule->principal_due - (float) $schedule->principal_paid, 0), 2);
                    $interestOutstanding = round(max((float) $schedule->interest_due - (float) $schedule->interest_paid, 0), 2);

                    if (
                        $firstScheduleId === null
                        && ($principalOutstanding > 0.001 || $interestOutstanding > 0.001 || $penaltyLeft > 0.001)
                    ) {
                        $firstScheduleId = (int) $schedule->id;
                    }

                    if ($penaltyLeft > 0.001 && ($principalOutstanding > 0.001 || $interestOutstanding > 0.001)) {
                        $schedule->penalty_paid = round((float) $schedule->penalty_paid + $penaltyLeft, 2);
                        $penaltyLeft = 0.0;
                    }

                    if ($interestLeft > 0.001 && $interestOutstanding > 0.001) {
                        $interestApplied = min($interestLeft, $interestOutstanding);
                        $schedule->interest_paid = round((float) $schedule->interest_paid + $interestApplied, 2);
                        $interestLeft = round($interestLeft - $interestApplied, 2);
                    }

                    if ($principalLeft > 0.001 && $principalOutstanding > 0.001) {
                        $principalApplied = min($principalLeft, $principalOutstanding);
                        $schedule->principal_paid = round((float) $schedule->principal_paid + $principalApplied, 2);
                        $principalLeft = round($principalLeft - $principalApplied, 2);
                    }

                    if (
                        (float) $schedule->principal_paid > 0.001
                        || (float) $schedule->interest_paid > 0.001
                        || (float) $schedule->penalty_paid > 0.001
                    ) {
                        $schedule->last_payment_date = $repayment->payment_date;
                    }

                    $totalPaidExcludingPenalty = round((float) $schedule->principal_paid + (float) $schedule->interest_paid, 2);
                    if ($totalPaidExcludingPenalty >= (float) $schedule->total_due - 0.001) {
                        $schedule->status = 'paid';
                        $schedule->paid_date = $repayment->payment_date;
                    } elseif (
                        $totalPaidExcludingPenalty > 0.001
                        || (float) $schedule->penalty_paid > 0.001
                    ) {
                        $schedule->status = 'partially_paid';
                    }
                }

                $runningConfirmedPrincipal = round($runningConfirmedPrincipal + (float) $repayment->principal_paid, 2);
                $repayment->schedule_id = $firstScheduleId;
                $repayment->balance_after_payment = round(max((float) $borrowing->amount - $runningConfirmedPrincipal, 0), 2);
                $repayment->saveQuietly();
            }

            foreach ($schedules as $schedule) {
                $schedule->saveQuietly();
            }

            activity('borrowings')
                ->performedOn($borrowing)
                ->withProperties([
                    'schedule_count' => $schedules->count(),
                    'confirmed_repayment_count' => $repayments->count(),
                ])
                ->log('Rebuilt borrowing repayment schedule allocations');
        });
    }

    private static function confirmedRepaymentsQuery(
        int $borrowingId,
        ?BorrowingRepayment $exceptRecord = null
    ): EloquentBuilder {
        return BorrowingRepayment::query()
            ->where('borrowing_id', $borrowingId)
            ->where('payment_status', 'confirmed')
            ->when($exceptRecord, fn(EloquentBuilder $query) => $query->where('id', '!=', $exceptRecord->id));
    }

    private static function generateUniqueReceiptNo(): string
    {
        $datePart = now()->format('Ymd');

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = sprintf(
                'BR-%s-%s',
                $datePart,
                Str::upper(Str::random(6))
            );

            if (!BorrowingRepayment::withTrashed()->where('receipt_no', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'BR-' . $datePart . '-' . (string) Str::uuid();
    }
}
