<?php

namespace App\Services\Transactions;

use App\Models\Contract;
use App\Models\RentalTransaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ContractService
{
    public function generate(RentalTransaction $transaction, array $payloadOverrides = []): Contract
    {
        $transaction->loadMissing(['listing', 'landlord', 'seeker', 'contracts.signatures']);

        $latest = $transaction->contracts->sortByDesc('version')->first();
        $templateKey = config('transactions.contract.template', 'standard_v1');
        $version = $latest ? (int) $latest->version : 1;

        $payload = $this->buildPayload($transaction, $payloadOverrides, $version);
        $html = view("contracts.{$templateKey}", $payload)->render();
        $hash = hash('sha256', $html);

        if ($latest && $latest->contract_hash === $hash) {
            return $latest;
        }

        $hasSignatures = $latest ? $latest->signatures->isNotEmpty() : false;

        if ($latest && ! $hasSignatures) {
            $pdfPath = $this->storePdf($transaction->id, $latest->version, $html);
            $latest->fill([
                'template_key' => $templateKey,
                'contract_hash' => $hash,
                'pdf_path' => $pdfPath,
                'rendered_payload' => $payload,
                'status' => Contract::STATUS_DRAFT,
            ]);
            $latest->save();

            return $latest->fresh(['signatures']);
        }

        if ($latest) {
            $version = $latest->version + 1;
        }

        $pdfPath = $this->storePdf($transaction->id, $version, $html);

        return Contract::create([
            'transaction_id' => $transaction->id,
            'version' => $version,
            'template_key' => $templateKey,
            'contract_hash' => $hash,
            'pdf_path' => $pdfPath,
            'rendered_payload' => $payload,
            'status' => Contract::STATUS_DRAFT,
        ]);
    }

    private function buildPayload(RentalTransaction $transaction, array $overrides, int $version): array
    {
        $listing = $transaction->listing;
        $landlord = $transaction->landlord;
        $seeker = $transaction->seeker;
        $currency = $transaction->currency ?: config('transactions.default_currency', 'EUR');

        $address = trim(implode(', ', array_filter([
            $listing?->address,
            $listing?->city,
            $listing?->country,
        ])));

        return array_merge([
            'version' => $version,
            'generated_at' => now()->toDateTimeString(),
            'landlord_name' => $landlord?->full_name ?? $landlord?->name ?? 'Landlord',
            'landlord_email' => $landlord?->email ?? '',
            'seeker_name' => $seeker?->full_name ?? $seeker?->name ?? 'Seeker',
            'seeker_email' => $seeker?->email ?? '',
            'listing_title' => $listing?->title ?? 'Listing',
            'listing_address' => $address ?: 'Address on file',
            'rent_amount' => $transaction->rent_amount ?? 0,
            'deposit_amount' => $transaction->deposit_amount ?? 0,
            'currency' => $currency,
            'start_date' => $overrides['start_date'] ?? now()->toDateString(),
            'terms' => $overrides['terms'] ?? config('transactions.contract.terms'),
        ], $overrides);
    }

    private function storePdf(int $transactionId, int $version, string $html): string
    {
        $path = "contracts/{$transactionId}/contract_v{$version}.pdf";
        $pdf = Pdf::loadHTML($html);
        Storage::disk('private')->put($path, $pdf->output());

        return $path;
    }
}
