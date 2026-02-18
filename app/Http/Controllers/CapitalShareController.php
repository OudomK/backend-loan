<?php

namespace App\Http\Controllers;

use App\Models\CapitalShare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CapitalShareController extends Controller
{
    public function index()
    {
        return response()->json(CapitalShare::with('borrower')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'borrower_id' => 'required|exists:borrowers,id',
            'holder_id' => 'required|unique:capital_shares',
            'certificate_no' => 'required|unique:capital_shares',
            'share_qty' => 'required|integer|min:1',
            'par_value' => 'required|numeric',
            'total_capital' => 'required|numeric',
            'currency' => 'required|string|max:20',
            'purchase_date' => 'required|date',
            'status' => 'required|in:Active,Withdrawn',
        ]);

        if (abs(($validated['share_qty'] * $validated['par_value']) - $validated['total_capital']) > 0.01) {
            return response()->json(['message' => 'Total capital must equal Share Qty * Par Value'], 422);
        }

        $share = CapitalShare::create($validated);
        return response()->json($share->load('borrower'), 201);
    }

    public function update(Request $request, CapitalShare $share)
    {
        $validated = $request->validate([
            'share_qty' => 'sometimes|integer|min:1',
            'total_capital' => 'sometimes|numeric',
            'status' => 'sometimes|in:Active,Withdrawn',
        ]);

        // Integrity check if updating quantities
        if (isset($validated['share_qty']) || isset($validated['total_capital'])) {
            $qty = $validated['share_qty'] ?? $share->share_qty;
            $par = $share->par_value; // Assuming par value doesn't change on update
            $total = $validated['total_capital'] ?? $share->total_capital;

            if (abs(($qty * $par) - $total) > 0.01) {
                return response()->json(['message' => 'Total capital must equal Share Qty * Par Value'], 422);
            }
        }

        $share->update($validated);
        return response()->json($share);
    }

    public function sellShare(Request $request, CapitalShare $share)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'remarks' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($share, $validated) {
            $share->lockForUpdate(); // Prevent race conditions

            // Reload share to get latest quantity after lock
            $freshShare = $share->fresh();

            if ($freshShare->share_qty < $validated['quantity']) {
                throw new \Exception('Insufficient shares'); // Will rollback transaction
            }

            // Calculate amount to reduce
            $reduceAmount = $freshShare->par_value * $validated['quantity'];

            // Update share quantity and total capital
            $freshShare->decrement('share_qty', $validated['quantity']);
            $freshShare->decrement('total_capital', $reduceAmount);

            // If all shares sold, update status
            if ($freshShare->share_qty == 0) {
                $freshShare->update(['status' => 'Withdrawn']);
            }

            return response()->json([
                'message' => 'Shares sold successfully',
                'remaining_shares' => $freshShare->share_qty,
                'remaining_capital' => $freshShare->total_capital,
            ]);
        });
    }
}
