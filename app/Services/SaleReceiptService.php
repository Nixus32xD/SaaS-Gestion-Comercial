<?php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaleReceiptService
{
    public function attachReceipt(Sale $sale, UploadedFile $receipt): Sale
    {
        $disk = $this->disk();
        $directory = sprintf('businesses/%d/sales/receipts/%d', $sale->business_id, $sale->id);
        $extension = strtolower((string) ($receipt->getClientOriginalExtension() ?: $receipt->extension()));
        $storedName = Str::uuid()->toString().($extension !== '' ? '.'.$extension : '');
        $path = $receipt->storeAs($directory, $storedName, ['disk' => $disk]);

        if ($sale->receipt_path !== null && Storage::disk($disk)->exists($sale->receipt_path)) {
            Storage::disk($disk)->delete($sale->receipt_path);
        }

        $sale->forceFill([
            'receipt_path' => $path,
            'receipt_original_name' => $receipt->getClientOriginalName() ?: basename($path),
            'receipt_uploaded_at' => now(),
        ])->save();

        return $sale->refresh();
    }

    public function downloadReceipt(Sale $sale): StreamedResponse
    {
        return Storage::disk($this->disk())->download(
            $sale->receipt_path,
            $sale->receipt_original_name ?: basename((string) $sale->receipt_path)
        );
    }

    private function disk(): string
    {
        return 'local';
    }
}
