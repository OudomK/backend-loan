<?php

namespace Tests\Feature;

use App\Http\Controllers\CustomerHistoryController;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RepaymentTransaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use ReflectionMethod;
use Tests\TestCase;

class CustomerHistoryAllocationTimingTest extends TestCase
{
    public function test_child_row_uses_the_repayment_transaction_date_instead_of_allocation_created_at(): void
    {
        $payment = new Payment();
        $payment->forceFill([
            'id' => 10,
            'payment_number' => 7,
            'principal_amount' => 75,
            'interest_amount' => 135,
            'fee_amount' => 0,
            'penalty_amount' => 0,
            'total_paid' => 110,
            'payment_date' => '2026-06-11',
            'payment_method' => 'Cash',
            'repayment_transaction_id' => 501,
            'updated_at' => '2026-08-01 10:00:00',
        ]);

        $allocation = new PaymentAllocation();
        $allocation->forceFill([
            'id' => 100,
            'payment_id' => 10,
            'repayment_transaction_id' => 501,
            'amount_applied' => 110,
            'fee_applied' => 0,
            'interest_applied' => 110,
            'principal_applied' => 0,
            'penalty_applied' => 0,
            // This technical timestamp is 51 days after the due date and must
            // never replace the customer's accounting transaction date.
            'created_at' => '2026-08-01 10:00:00',
            'updated_at' => '2026-08-01 10:00:00',
        ]);
        $payment->setRelation('allocations', new EloquentCollection([$allocation]));

        $transaction = new RepaymentTransaction();
        $transaction->forceFill([
            'id' => 501,
            'repayment_type' => 'Partial',
            'transaction_date' => '2026-06-11',
        ]);

        $method = new ReflectionMethod(CustomerHistoryController::class, 'mapPaymentToArray');
        $currentOutstanding = 1000.0;
        $result = $method->invokeArgs(app(CustomerHistoryController::class), [
            $payment,
            collect(),
            collect([501 => $transaction]),
            &$currentOutstanding,
            [],
            10,
            Carbon::parse('2026-08-12')->startOfDay(),
        ]);

        $this->assertSame('2026-06-11', $result['allocations'][0]['transaction_date']);
        $this->assertSame('0', $result['allocations'][0]['alloc_on_time_label']);
    }
}
