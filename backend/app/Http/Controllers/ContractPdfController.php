<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractPdfController extends Controller
{
    public function show(Request $request, Contract $contract): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated');

        $contract->loadMissing('transaction');
        $transaction = $contract->transaction;
        abort_unless($transaction, 404, 'Transaction not found');

        if (! $this->isParticipant($user, $transaction)) {
            abort(403, 'Forbidden');
        }

        $path = $contract->pdf_path;
        if (! $path || ! Storage::disk('private')->exists($path)) {
            abort(404, 'Contract PDF not found');
        }

        $mime = Storage::disk('private')->mimeType($path) ?? 'application/pdf';
        $safeName = $this->sanitizeFilename("contract_v{$contract->version}.pdf");
        $stream = Storage::disk('private')->readStream($path);

        return response()->stream(function () use ($stream) {
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $safeName . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function isParticipant($user, $transaction): bool
    {
        return $transaction->landlord_id === $user->id
            || $transaction->seeker_id === $user->id
            || $this->userHasRole($user, 'admin');
    }

    private function sanitizeFilename(string $name): string
    {
        $name = basename($name);
        $name = Str::of($name)->replace(['"', "'"], '')->toString();

        return $name ?: 'contract.pdf';
    }

    private function userHasRole($user, array|string $roles): bool
    {
        $roles = (array) $roles;

        return ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles))
            || ($user && isset($user->role) && in_array($user->role, $roles, true));
    }
}
