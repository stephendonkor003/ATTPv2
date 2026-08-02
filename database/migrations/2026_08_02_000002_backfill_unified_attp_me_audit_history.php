<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('myb_indicators')
            ->whereNotNull('indicator_code')
            ->orderBy('id')
            ->get(['id', 'indicator_code', 'created_by', 'created_at'])
            ->each(function (object $indicator) use ($now): void {
                if (DB::table('me_indicator_code_histories')->where('indicator_id', $indicator->id)->exists()) {
                    return;
                }
                DB::table('me_indicator_code_histories')->insert([
                    'id' => (string) Str::uuid(),
                    'indicator_id' => $indicator->id,
                    'old_code' => null,
                    'new_code' => $indicator->indicator_code,
                    'change_reason' => 'Initial code captured during unified tracker audit-history backfill.',
                    'changed_by' => $indicator->created_by
                        && DB::table('users')->where('id', $indicator->created_by)->exists()
                            ? $indicator->created_by
                            : null,
                    'changed_at' => $indicator->created_at ?: $now,
                ]);
            });

        DB::table('me_knowledge_evidence_items')->orderBy('id')->get()->each(function (object $item) use ($now): void {
            $category = in_array($item->document_type, ['means_of_verification', 'supporting_evidence'], true)
                ? 'evidence'
                : ($item->document_type === 'me_matrix' ? 'matrix' : 'knowledge');
            DB::table('me_knowledge_evidence_items')->where('id', $item->id)->update([
                'repository_category' => $category,
                'version_number' => max(1, (int) ($item->version_number ?? 1)),
                'updated_by' => $item->updated_by ?? $item->created_by,
            ]);
            if (! $item->file_path
                || DB::table('me_repository_document_versions')->where('repository_item_id', $item->id)->exists()) {
                return;
            }
            DB::table('me_repository_document_versions')->insert([
                'id' => (string) Str::uuid(),
                'repository_item_id' => $item->id,
                'version_number' => max(1, (int) ($item->version_number ?? 1)),
                'file_path' => $item->file_path,
                'original_filename' => $item->original_filename ?: basename($item->file_path),
                'mime_type' => $item->mime_type,
                'file_size' => $item->file_size,
                'checksum_sha256' => $item->checksum_sha256,
                'change_notes' => 'Legacy repository file registered as the initial controlled version.',
                'uploaded_by' => $item->created_by,
                'created_at' => $item->created_at ?: $now,
                'updated_at' => $now,
            ]);
        });

        DB::table('me_performance_report_documents')
            ->whereNull('repository_item_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $document) use ($now): void {
                $report = DB::table('me_performance_reports')->where('id', $document->report_id)->first();
                if (! $report) {
                    return;
                }
                $repositoryId = (string) Str::uuid();
                DB::table('me_knowledge_evidence_items')->insert([
                    'id' => $repositoryId,
                    'portfolio_id' => $report->portfolio_id,
                    'title' => $document->document_name,
                    'document_type' => 'supporting_evidence',
                    'repository_category' => 'evidence',
                    'description' => 'Legacy performance-report attachment synchronized during unified tracker backfill.',
                    'file_path' => $document->file_path,
                    'original_filename' => $document->original_filename,
                    'mime_type' => $document->mime_type,
                    'file_size' => $document->file_size,
                    'checksum_sha256' => null,
                    'version_number' => 1,
                    'external_url' => null,
                    'validation_status' => $document->validation_status ?? 'pending',
                    'validated_by' => $document->validated_by ?? null,
                    'validated_at' => $document->validated_at ?? null,
                    'validation_notes' => $document->validation_notes ?? null,
                    'created_by' => $document->uploaded_by,
                    'updated_by' => $document->uploaded_by,
                    'retired_at' => null,
                    'retired_by' => null,
                    'created_at' => $document->created_at ?: $now,
                    'updated_at' => $now,
                ]);
                DB::table('me_repository_document_versions')->insert([
                    'id' => (string) Str::uuid(),
                    'repository_item_id' => $repositoryId,
                    'version_number' => 1,
                    'file_path' => $document->file_path,
                    'original_filename' => $document->original_filename,
                    'mime_type' => $document->mime_type,
                    'file_size' => $document->file_size,
                    'checksum_sha256' => null,
                    'change_notes' => 'Legacy report attachment registered as the initial controlled version.',
                    'uploaded_by' => $document->uploaded_by,
                    'created_at' => $document->created_at ?: $now,
                    'updated_at' => $now,
                ]);
                DB::table('me_repository_document_links')->insert([
                    'id' => (string) Str::uuid(),
                    'repository_item_id' => $repositoryId,
                    'linkable_type' => App\Models\MePerformanceReport::class,
                    'linkable_id' => $document->report_id,
                    'purpose' => 'report_attachment',
                    'linked_by' => $document->uploaded_by,
                    'created_at' => $document->created_at ?: $now,
                    'updated_at' => $now,
                ]);
                DB::table('me_performance_report_documents')->where('id', $document->id)->update([
                    'repository_item_id' => $repositoryId,
                ]);
            });
    }

    public function down(): void
    {
        // Audit-history backfills are intentionally retained on rollback.
    }
};
