<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\RentalTransactionResource;
use App\Models\RentalTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->input('status');

        $transactions = RentalTransaction::with(['listing.images', 'latestContract.signatures', 'payments'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->get();

        return response()->json(RentalTransactionResource::collection($transactions));
    }

    public function show(Request $request, RentalTransaction $transaction): JsonResponse
    {
        $transaction->load(['listing.images', 'latestContract.signatures', 'payments']);

        return response()->json(new RentalTransactionResource($transaction));
    }

    public function markDisputed(Request $request, RentalTransaction $transaction): JsonResponse
    {
        if ($transaction->status === RentalTransaction::STATUS_COMPLETED) {
            return response()->json(['message' => 'Completed transactions cannot be disputed'], 422);
        }

        $transaction->update(['status' => RentalTransaction::STATUS_DISPUTED]);

        return response()->json(new RentalTransactionResource($transaction->load(['listing.images', 'latestContract.signatures', 'payments'])));
    }

    public function cancel(Request $request, RentalTransaction $transaction): JsonResponse
    {
        if ($transaction->status === RentalTransaction::STATUS_COMPLETED) {
            return response()->json(['message' => 'Completed transactions cannot be cancelled'], 422);
        }

        $transaction->update(['status' => RentalTransaction::STATUS_CANCELLED]);

        return response()->json(new RentalTransactionResource($transaction->load(['listing.images', 'latestContract.signatures', 'payments'])));
    }

    public function payout(Request $request, RentalTransaction $transaction): JsonResponse
    {
        if ($transaction->status !== RentalTransaction::STATUS_MOVE_IN_CONFIRMED) {
            return response()->json(['message' => 'Payout only allowed after move-in confirmation'], 422);
        }

        $transaction->update([
            'status' => RentalTransaction::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        return response()->json(new RentalTransactionResource($transaction->load(['listing.images', 'latestContract.signatures', 'payments'])));
    }
}
