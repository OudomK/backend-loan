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
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

        $alreadyPaidPrincipal = BorrowingRepayment::query()
            ->where('borrowing_id', $borrowingId)
            ->when($record, fn($q) => $q->where('id', '!=', $record->id))
            ->sum('principal_paid');

        $remainingPrincipal = round((float) $borrowing->amount - (float) $alreadyPaidPrincipal, 2);
        if ($principal > $remainingPrincipal + 0.001) {
            throw ValidationException::withMessages([
                'principal_paid' => 'Principal paid exceeds remaining principal balance.',
            ]);
        }

        $totalPaid = round($principal + $interest + $penalty, 2);
        $balanceAfter = round(max((float) $borrowing->amount - ((float) $alreadyPaidPrincipal + $principal), 0), 2);

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

        $paidPrincipal = (float) BorrowingRepayment::query()
            ->where('borrowing_id', $borrowingId)
            ->sum('principal_paid');

        $borrowing->status = ($paidPrincipal + 0.001 >= (float) $borrowing->amount)
            ? 'completed'
            : 'active';

        $borrowing->saveQuietly();
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
