<?php

namespace App\Observers;

use App\Models\Loan;
use App\Models\Collateral;
use Carbon\Carbon;

class LoanObserver
{
    /**
     * Handle the Loan "updated" event.
     */
    public function updated(Loan $loan): void
    {
        // If loan status changed to completed, return all collaterals
        if ($loan->isDirty('status') && $loan->status === 'completed') {
            $loan->collaterals()
                ->where('status', 'Held')
                ->update([
                    'status' => 'Returned',
                    'end_date' => Carbon::now(),
                ]);
        }

        // Optional: If loan status changed back from completed to something else (e.g. voided transaction)
        // We might want to keep it as Returned unless the user manually reverts it, 
        // but for now, let's just handle the completion logic as requested.
    }
}
