<?php

namespace App\Http\Controllers;

use App\Models\ProgramFunding;
use App\Models\ProgramFundingDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProgramFundingDocumentController extends Controller
{
    public function store(Request $request, ProgramFunding $programFunding)
    {
        $data = $request->validate([
            'document_type' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf,doc,docx',
            'issued_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
        ]);

        $path = $request->file('file')->store('program-funding-documents');

        ProgramFundingDocument::create([
            'program_funding_id' => $programFunding->id,
            'document_type' => $data['document_type'],
            'file_name' => $request->file('file')->getClientOriginalName(),
            'file_path' => $path,
            'issued_date' => $data['issued_date'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'uploaded_by' => auth()->id(),
        ]);

        return back()->with('success', 'Document uploaded successfully.');
    }

    public function download(Request $request, ProgramFunding $programFunding, ProgramFundingDocument $document)
    {
        abort_unless($document->program_funding_id === $programFunding->id, 404);

        $path = (string) ($document->file_path ?? '');
        abort_if($path === '', 404, 'Document not found.');

        $privateDisk = Storage::disk('local');

        if (! $privateDisk->exists($path) && Storage::disk('public')->exists($path)) {
            // Best-effort migration from public -> private.
            $stream = Storage::disk('public')->readStream($path);
            if ($stream !== false) {
                $privateDisk->writeStream($path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                Storage::disk('public')->delete($path);
            }
        }

        abort_unless($privateDisk->exists($path), 404, 'Document file missing on disk.');

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($request->boolean('download')) {
            return $privateDisk->download($path, $document->file_name ?? basename($path), $headers);
        }

        return $privateDisk->response($path, null, $headers);
    }
}
