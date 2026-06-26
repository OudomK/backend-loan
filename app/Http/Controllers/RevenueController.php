<?php

namespace App\Http\Controllers;

use App\Models\Revenue;
use Illuminate\Http\Request;

class RevenueController extends Controller
{
    private function recognizedAdminFeeAmount(object $loan): float
    {
        $feeType = strtolower(trim((string) ($loan->admin_fee_type ?? 'one_time')));
        $adminFeeRate = (float) ($loan->admin_fee ?? 0);

        if ($adminFeeRate <= 0 || $feeType === 'monthly') {
            return 0.0;
        }

        $loanAmount = (float) ($loan->amount ?? 0);
        $disbursedAmount = (float) ($loan->disbursed_amount ?? $loanAmount);

        if (in_array($feeType, ['deducted_upfront', 'capitalized_upfront'], true)) {
            $storedDifference = round(abs($loanAmount - $disbursedAmount), 2);
            if ($storedDifference > 0) {
                return $storedDifference;
            }
        }

        $baseAmount = $loanAmount;
        if ($feeType === 'capitalized_upfront' && $loanAmount > 0) {
            $baseAmount = $loanAmount / (1 + ($adminFeeRate / 100));
        }

        return round($baseAmount * ($adminFeeRate / 100), 2);
    }

    public function index()
    {
        $revenues = Revenue::with('revenue_category')
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Dynamically append Admin Fee from loans
        $adminFeeCategory = \App\Models\RevenueCategory::where('slug', 'admin_fee')->orWhere('name', 'Admin Fee')->first();
        if ($adminFeeCategory) {
            $loans = \App\Models\Loan::where('admin_fee', '>', 0)->get();
            foreach ($loans as $loan) {
                $amount = $this->recognizedAdminFeeAmount($loan);
                if ($amount > 0) {
                    $fakeRevenue = new Revenue([
                        'revenue_category_id' => $adminFeeCategory->id,
                        'amount' => $amount,
                        'currency' => $loan->currency ?? 'USD',
                        'transaction_date' => $loan->start_date ?? now()->toDateString(),
                        'description' => 'Auto Admin Fee from loan ' . ($loan->loan_code ?: ('#' . $loan->id)),
                        'status' => 'completed',
                    ]);
                    
                    // Assign a high virtual ID to avoid collision and make it read-only-ish
                    $fakeRevenue->setAttribute('id', 9000000 + $loan->id);
                    $fakeRevenue->setRelation('revenue_category', $adminFeeCategory);
                    $revenues->push($fakeRevenue);
                }
            }
        }

        // Re-sort the collection including the dynamic items
        return $revenues->sortByDesc('transaction_date')->values();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'revenue_category_id' => 'required|exists:revenue_categories,id',
            'amount' => 'required|numeric',
            'currency' => 'required|string|max:10',
            'transaction_date' => 'required|date',
            'reference_no' => 'nullable|string|unique:revenues,reference_no',
            'payment_method' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        return Revenue::create($validated)->load('revenue_category');
    }

    public function show(Revenue $revenue)
    {
        return $revenue->load('revenue_category');
    }

    public function update(Request $request, Revenue $revenue)
    {
        $validated = $request->validate([
            'revenue_category_id' => 'exists:revenue_categories,id',
            'amount' => 'numeric',
            'currency' => 'string|max:10',
            'transaction_date' => 'date',
            'reference_no' => 'nullable|string|unique:revenues,reference_no,' . $revenue->id,
            'payment_method' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $revenue->update($validated);
        return $revenue->load('revenue_category');
    }

    public function destroy(Revenue $revenue)
    {
        $revenue->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
