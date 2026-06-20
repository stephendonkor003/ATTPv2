<?php

namespace App\Services;

use App\Models\ProcurementDisbursement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SignedDisbursementDocumentService
{
    public function response(ProcurementDisbursement $disbursement, int $documentIndex, bool $download = false, bool $asPdf = false)
    {
        $document = $asPdf
            ? $this->ensurePdf($disbursement, $documentIndex)
            : $this->documentAt($disbursement, $documentIndex);

        $path = $asPdf ? ($document['pdf_path'] ?? $document['path'] ?? null) : ($document['path'] ?? null);
        abort_unless(is_string($path) && $path !== '', 404, 'Signed document not found.');

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404, 'Signed document file missing on disk.');

        $fileName = $asPdf
            ? ($document['pdf_name'] ?? $this->pdfNameForDocument($document))
            : (($document['display_name'] ?? null) ?: ($document['name'] ?? basename($path)));

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        return $download
            ? $disk->download($path, $fileName, $headers)
            : $disk->response($path, $fileName, $headers);
    }

    public function documentAt(ProcurementDisbursement $disbursement, int $documentIndex): array
    {
        $documents = $this->documents($disbursement);
        $document = $documents[$documentIndex] ?? null;

        abort_unless(is_array($document) && ! empty($document['path']), 404, 'Signed document not found.');

        return $document;
    }

    public function ensurePdf(ProcurementDisbursement $disbursement, int $documentIndex): array
    {
        $documents = $this->documents($disbursement);
        $document = $documents[$documentIndex] ?? null;

        abort_unless(is_array($document) && ! empty($document['path']), 404, 'Signed document not found.');

        if (empty($document['digital_signature_code'])) {
            $document['digital_signature_code'] = $this->generateDocumentCode($disbursement);
            $documents[$documentIndex] = $document;
            $disbursement->forceFill(['signed_documents' => $documents])->save();
        }

        $disk = Storage::disk('local');
        $existingPdfPath = $document['pdf_path'] ?? null;
        if (is_string($existingPdfPath) && $existingPdfPath !== '' && $disk->exists($existingPdfPath)) {
            return $document;
        }

        $sourcePath = (string) $document['path'];
        abort_unless($disk->exists($sourcePath), 404, 'Signed document file missing on disk.');

        $mimeType = strtolower((string) ($document['mime_type'] ?? ''));
        if ($mimeType === 'application/pdf' || strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) === 'pdf') {
            $document['pdf_path'] = $sourcePath;
            $document['pdf_name'] = $this->pdfNameForDocument($document);
            $documents[$documentIndex] = $document;
            $disbursement->forceFill(['signed_documents' => $documents])->save();

            return $document;
        }

        $disbursement->loadMissing([
            'purchaseOrder',
            'vendor',
            'purchaseRequestItem.resource',
            'purchaseRequestItem.resourceCategory',
            'purchaseRequestItem.deliverable',
            'deliverable',
            'procurement',
        ]);

        $imageDataUri = $this->imageDataUri($sourcePath, $mimeType);
        $pdf = Pdf::loadView('procurement.disbursements.signed-document-pdf', [
            'disbursement' => $disbursement,
            'document' => $document,
            'documentIndex' => $documentIndex,
            'imageDataUri' => $imageDataUri,
        ]);

        $pdfPath = "procurement_disbursements/{$disbursement->id}/signed-documents/pdf/"
            . Str::slug(pathinfo($this->pdfNameForDocument($document), PATHINFO_FILENAME))
            . '-' . Str::random(8) . '.pdf';

        $disk->put($pdfPath, $pdf->output());

        $document['pdf_path'] = $pdfPath;
        $document['pdf_name'] = $this->pdfNameForDocument($document);
        $document['pdf_mime_type'] = 'application/pdf';
        $document['pdf_generated_at'] = now()->toIso8601String();
        $documents[$documentIndex] = $document;
        $disbursement->forceFill(['signed_documents' => $documents])->save();

        return $document;
    }

    private function documents(ProcurementDisbursement $disbursement): array
    {
        return collect($disbursement->signed_documents ?? [])
            ->filter(fn ($document) => is_array($document))
            ->values()
            ->all();
    }

    private function pdfNameForDocument(array $document): string
    {
        $name = ($document['display_name'] ?? null) ?: ($document['name'] ?? 'signed-document');
        $name = pathinfo($name, PATHINFO_FILENAME) ?: 'signed-document';

        return Str::slug($name) . '.pdf';
    }

    private function generateDocumentCode(ProcurementDisbursement $disbursement): string
    {
        $receiptPart = (string) Str::of($disbursement->reference_no ?: 'ATTP')
            ->replaceMatches('/[^A-Za-z0-9]+/', '')
            ->upper()
            ->substr(-8);
        $receiptPart = $receiptPart !== '' ? $receiptPart : 'ATTP';

        return 'ATTP-SD-' . now()->format('Ymd') . '-' . $receiptPart . '-' . Str::upper(Str::random(6));
    }

    private function imageDataUri(string $path, string $mimeType): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $isImage = str_starts_with($mimeType, 'image/')
            || in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);

        if (! $isImage) {
            return null;
        }

        $disk = Storage::disk('local');
        $contents = $disk->get($path);
        $mime = $mimeType !== '' ? $mimeType : match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }
}
