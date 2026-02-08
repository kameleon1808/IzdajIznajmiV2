<?php

namespace App\Http\Controllers;

use App\Models\RentalTransaction;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionReportController extends Controller
{
    public function store(Request $request, RentalTransaction $transaction): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        if (! in_array($user->id, [$transaction->seeker_id, $transaction->landlord_id], true)) {
            abort(403, 'Only participants can report');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        Report::firstOrCreate(
            [
                'reporter_id' => $user->id,
                'target_type' => RentalTransaction::class,
                'target_id' => $transaction->id,
            ],
            [
                'reason' => $data['reason'],
                'details' => $data['details'] ?? null,
                'status' => 'open',
            ]
        );

        app(\App\Services\StructuredLogger::class)->info('transaction_reported', [
            'transaction_id' => $transaction->id,
            'user_id' => $user->id,
        ]);

        return response()->json(['status' => 'ok'], 201);
    }
}
