<?php

namespace App\Services;

use App\Models\BusinessDocumentSequence;
use Illuminate\Database\QueryException;

class DocumentNumberService
{
    public function nextSaleNumber(int $businessId): string
    {
        $number = $this->nextNumber($businessId, 'sale');

        return 'S-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }

    public function nextPurchaseNumber(int $businessId): string
    {
        $number = $this->nextNumber($businessId, 'purchase');

        return 'P-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }

    private function nextNumber(int $businessId, string $type): int
    {
        $this->ensureSequenceExists($businessId, $type);

        $sequence = BusinessDocumentSequence::query()
            ->where('business_id', $businessId)
            ->where('type', $type)
            ->lockForUpdate()
            ->firstOrFail();

        $sequence->increment('current_number');

        return (int) $sequence->fresh()->current_number;
    }

    private function ensureSequenceExists(int $businessId, string $type): void
    {
        try {
            BusinessDocumentSequence::query()->create([
                'business_id' => $businessId,
                'type' => $type,
                'current_number' => 0,
            ]);
        } catch (QueryException $exception) {
            $sqlState = $exception->errorInfo[0] ?? null;

            if ($sqlState !== '23000') {
                throw $exception;
            }
        }
    }
}
