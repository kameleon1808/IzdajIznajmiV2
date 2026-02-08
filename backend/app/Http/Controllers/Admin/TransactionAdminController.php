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
        return response()->json(['message' => 'Admin actions are read-only for transactions'], 403);
    }

    public function cancel(Request $request, RentalTransaction $transaction): JsonResponse
    {
        return response()->json(['message' => 'Admin actions are read-only for transactions'], 403);
    }

    public function payout(Request $request, RentalTransaction $transaction): JsonResponse
    {
        return response()->json(['message' => 'Admin actions are read-only for transactions'], 403);
    }
}
