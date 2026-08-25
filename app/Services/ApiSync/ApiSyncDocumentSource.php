<?php

namespace App\Services\ApiSync;

use App\Models\MePerformanceReport;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ApiSyncDocumentSource
{
    public const TYPE_PERFORMANCE = 'performance_report_document';

    public const TYPE_MISSION = 'mission_report_document';

    public const TYPE_KNOWLEDGE = 'knowledge_repository_version';

    /**
     * Select only the three approved, project-governance-safe document sets.
     * No applicant, vendor, finance, procurement, identity, grievance, forum,
     * funding agreement, purchase request, or free-form submission table is
     * queried by this adapter.
     *
     * @return array{items:list<array<string,mixed>>,total:int,overflow:int}
     */
    public function candidates(CarbonInterface $capturedAt, int $maximum): array
    {
        $maximum = min(1_000, max(1, $maximum));
        $performanceCountQuery = DB::table('me_performance_report_documents as document')
            ->join('me_performance_reports as report', 'report.id', '=', 'document.report_id')
            ->whereIn('report.status', ['approved', 'archived'])
            ->where('document.validation_status', 'validated')
            ->where('document.created_at', '<=', $capturedAt)
            ->where('document.updated_at', '<=', $capturedAt)
            ->where('report.created_at', '<=', $capturedAt)
            ->where('report.updated_at', '<=', $capturedAt);
        $performanceCount = $this->withoutRepositoryDuplicates($performanceCountQuery, $capturedAt)->count();
        $missionCount = DB::table('me_mission_report_documents as document')
            ->join('me_mission_reports as report', 'report.id', '=', 'document.mission_report_id')
            ->whereIn('report.status', ['reviewed', 'archived'])
            ->where('document.created_at', '<=', $capturedAt)
            ->where('document.updated_at', '<=', $capturedAt)
            ->where('report.created_at', '<=', $capturedAt)
            ->where('report.updated_at', '<=', $capturedAt)
            ->count();
        $knowledgeCount = $this->knowledgeQuery($capturedAt)->count();
        $total = $performanceCount + $missionCount + $knowledgeCount;

        $performanceQuery = DB::table('me_performance_report_documents as document')
            ->join('me_performance_reports as report', 'report.id', '=', 'document.report_id')
            ->whereIn('report.status', ['approved', 'archived'])
            ->where('document.validation_status', 'validated')
            ->where('document.created_at', '<=', $capturedAt)
            ->where('document.updated_at', '<=', $capturedAt)
            ->where('report.created_at', '<=', $capturedAt)
            ->where('report.updated_at', '<=', $capturedAt);
        $performance = $this->withoutRepositoryDuplicates($performanceQuery, $capturedAt)
            ->orderBy('document.id')
            ->limit($maximum + 1)
            ->get([
                'document.id as source_document_id', 'document.repository_item_id', 'document.file_path',
                'document.original_filename', 'document.document_name as title',
                'document.file_size', 'document.updated_at as document_updated_at',
                'document.validation_status', 'report.id as parent_external_id',
                'report.portfolio_id', 'report.project_component_id',
                'report.status as parent_status', 'report.updated_at as parent_updated_at',
            ])
            ->map(fn (object $row): array => $this->performanceCandidate($row))
            ->all();

        $mission = DB::table('me_mission_report_documents as document')
            ->join('me_mission_reports as report', 'report.id', '=', 'document.mission_report_id')
            ->whereIn('report.status', ['reviewed', 'archived'])
            ->where('document.created_at', '<=', $capturedAt)
            ->where('document.updated_at', '<=', $capturedAt)
            ->where('report.created_at', '<=', $capturedAt)
            ->where('report.updated_at', '<=', $capturedAt)
            ->orderBy('document.id')
            ->limit($maximum + 1)
            ->get([
                'document.id as source_document_id', 'document.file_path',
                'document.original_filename', 'document.document_name as title',
                'document.file_size', 'document.updated_at as document_updated_at',
                'report.id as parent_external_id', 'report.portfolio_id',
                'report.project_component_id', 'report.status as parent_status',
                'report.updated_at as parent_updated_at',
            ])
            ->map(fn (object $row): array => $this->missionCandidate($row))
            ->all();

        $knowledgeRows = $this->knowledgeQuery($capturedAt)
            ->orderBy('item.id')
            ->limit($maximum + 1)
            ->get([
                'item.id as source_document_id', 'version.id as source_version_id',
                'version.file_path', 'version.original_filename', 'item.title',
                'item.document_type', 'item.repository_category', 'item.portfolio_id',
                'item.validation_status', 'item.retired_at',
                'item.updated_at as item_updated_at', 'version.updated_at as version_updated_at',
                'version.file_size', 'version.checksum_sha256', 'version.version_number',
            ]);
        $knowledgeProjects = $this->approvedKnowledgeProjects(
            $knowledgeRows->pluck('source_document_id')->map(fn ($id): string => (string) $id)->all(),
            $capturedAt,
        );
        $knowledge = $knowledgeRows
            ->map(fn (object $row): array => $this->knowledgeCandidate(
                $row,
                $knowledgeProjects[(string) $row->source_document_id] ?? [],
            ))
            ->all();
        $items = collect([...$performance, ...$mission, ...$knowledge])
            ->sortBy(fn (array $item): string => $item['source_type'].':'.$item['source_document_id'].':'.($item['source_version_id'] ?? ''))
            ->take($maximum)
            ->values()
            ->all();

        return [
            'items' => $items,
            'total' => $total,
            'overflow' => max(0, $total - count($items)),
        ];
    }

    /** @param array<string,mixed> $candidate */
    public function stillApproved(array $candidate): bool
    {
        $current = match ($candidate['source_type'] ?? null) {
            self::TYPE_PERFORMANCE => $this->currentPerformance((string) $candidate['source_document_id']),
            self::TYPE_MISSION => $this->currentMission((string) $candidate['source_document_id']),
            self::TYPE_KNOWLEDGE => $this->currentKnowledge(
                (string) $candidate['source_document_id'],
                (string) ($candidate['source_version_id'] ?? ''),
            ),
            default => null,
        };

        return is_array($current)
            && hash_equals((string) $candidate['revision'], (string) $current['revision']);
    }

    /**
     * Re-evaluate the exact approval revision while holding every source row
     * that authorizes it. The caller must keep this inside the same database
     * transaction that publishes the immutable snapshot row as ready.
     *
     * @param  array<string,mixed>  $candidate
     */
    public function stillApprovedForUpdate(array $candidate): bool
    {
        $current = match ($candidate['source_type'] ?? null) {
            self::TYPE_PERFORMANCE => $this->currentPerformance((string) $candidate['source_document_id'], true),
            self::TYPE_MISSION => $this->currentMission((string) $candidate['source_document_id'], true),
            self::TYPE_KNOWLEDGE => $this->currentKnowledge(
                (string) $candidate['source_document_id'],
                (string) ($candidate['source_version_id'] ?? ''),
                true,
            ),
            default => null,
        };

        return is_array($current)
            && hash_equals((string) $candidate['revision'], (string) $current['revision']);
    }

    /** @return array<string,mixed>|null */
    public function candidateFor(string $sourceType, string $sourceDocumentId, ?string $sourceVersionId): ?array
    {
        return match ($sourceType) {
            self::TYPE_PERFORMANCE => $this->currentPerformance($sourceDocumentId),
            self::TYPE_MISSION => $this->currentMission($sourceDocumentId),
            self::TYPE_KNOWLEDGE => $this->currentKnowledge($sourceDocumentId, (string) $sourceVersionId),
            default => null,
        };
    }

    private function knowledgeQuery(CarbonInterface $capturedAt): \Illuminate\Database\Query\Builder
    {
        return DB::table('me_repository_document_versions as version')
            ->join('me_knowledge_evidence_items as item', 'item.id', '=', 'version.repository_item_id')
            ->where('item.validation_status', 'validated')
            ->whereNull('item.retired_at')
            ->whereNotNull('version.file_path')
            ->where('item.created_at', '<=', $capturedAt)
            ->where('item.updated_at', '<=', $capturedAt)
            ->where('version.created_at', '<=', $capturedAt)
            ->where('version.updated_at', '<=', $capturedAt)
            ->whereNotExists(function ($newer) use ($capturedAt): void {
                $newer->selectRaw('1')
                    ->from('me_repository_document_versions as newer_version')
                    ->whereColumn('newer_version.repository_item_id', 'version.repository_item_id')
                    ->where('newer_version.created_at', '<=', $capturedAt)
                    ->where('newer_version.updated_at', '<=', $capturedAt)
                    ->whereColumn('newer_version.version_number', '>', 'version.version_number');
            })
            ->where(function ($approvedProjectRelationship) use ($capturedAt): void {
                $approvedProjectRelationship
                    ->whereExists(function ($linkedReport) use ($capturedAt): void {
                        $linkedReport->selectRaw('1')
                            ->from('me_repository_document_links as approved_link')
                            ->join('me_performance_reports as approved_report', 'approved_report.id', '=', 'approved_link.linkable_id')
                            ->whereColumn('approved_link.repository_item_id', 'item.id')
                            ->where('approved_link.linkable_type', MePerformanceReport::class)
                            ->whereIn('approved_report.status', ['approved', 'archived'])
                            ->whereNotNull('approved_report.project_component_id')
                            ->whereColumn('approved_report.portfolio_id', 'item.portfolio_id')
                            ->where('approved_link.created_at', '<=', $capturedAt)
                            ->where('approved_link.updated_at', '<=', $capturedAt)
                            ->where('approved_report.created_at', '<=', $capturedAt)
                            ->where('approved_report.updated_at', '<=', $capturedAt);
                    })
                    ->orWhereExists(function ($attachedReport) use ($capturedAt): void {
                        $attachedReport->selectRaw('1')
                            ->from('me_performance_report_documents as approved_document')
                            ->join('me_performance_reports as approved_report', 'approved_report.id', '=', 'approved_document.report_id')
                            ->whereColumn('approved_document.repository_item_id', 'item.id')
                            ->where('approved_document.validation_status', 'validated')
                            ->whereIn('approved_report.status', ['approved', 'archived'])
                            ->whereNotNull('approved_report.project_component_id')
                            ->whereColumn('approved_report.portfolio_id', 'item.portfolio_id')
                            ->where('approved_document.created_at', '<=', $capturedAt)
                            ->where('approved_document.updated_at', '<=', $capturedAt)
                            ->where('approved_report.created_at', '<=', $capturedAt)
                            ->where('approved_report.updated_at', '<=', $capturedAt);
                    });
            });
    }

    /** @return array<string,mixed>|null */
    private function currentPerformance(string $id, bool $forUpdate = false): ?array
    {
        $query = DB::table('me_performance_report_documents as document')
            ->join('me_performance_reports as report', 'report.id', '=', 'document.report_id')
            ->where('document.id', $id)
            ->whereIn('report.status', ['approved', 'archived'])
            ->where('document.validation_status', 'validated');
        $query = $this->withoutRepositoryDuplicates($query, now()->addSecond());
        if ($forUpdate) {
            $query->lockForUpdate();
        }
        $row = $query->first([
            'document.id as source_document_id', 'document.repository_item_id', 'document.file_path',
            'document.original_filename', 'document.document_name as title',
            'document.file_size', 'document.updated_at as document_updated_at',
            'document.validation_status', 'report.id as parent_external_id',
            'report.portfolio_id', 'report.project_component_id',
            'report.status as parent_status', 'report.updated_at as parent_updated_at',
        ]);

        return $row ? $this->performanceCandidate($row) : null;
    }

    /** @return array<string,mixed>|null */
    private function currentMission(string $id, bool $forUpdate = false): ?array
    {
        $query = DB::table('me_mission_report_documents as document')
            ->join('me_mission_reports as report', 'report.id', '=', 'document.mission_report_id')
            ->where('document.id', $id)
            ->whereIn('report.status', ['reviewed', 'archived']);
        if ($forUpdate) {
            $query->lockForUpdate();
        }
        $row = $query->first([
            'document.id as source_document_id', 'document.file_path',
            'document.original_filename', 'document.document_name as title',
            'document.file_size', 'document.updated_at as document_updated_at',
            'report.id as parent_external_id', 'report.portfolio_id',
            'report.project_component_id', 'report.status as parent_status',
            'report.updated_at as parent_updated_at',
        ]);

        return $row ? $this->missionCandidate($row) : null;
    }

    /** @return array<string,mixed>|null */
    private function currentKnowledge(string $itemId, string $versionId, bool $forUpdate = false): ?array
    {
        if ($versionId === '') {
            return null;
        }
        $query = $this->knowledgeQuery(now()->addSecond())
            ->where('item.id', $itemId)
            ->where('version.id', $versionId);
        if ($forUpdate) {
            $query->lockForUpdate();
        }
        $row = $query->first([
            'item.id as source_document_id', 'version.id as source_version_id',
            'version.file_path', 'version.original_filename', 'item.title',
            'item.document_type', 'item.repository_category', 'item.portfolio_id',
            'item.validation_status', 'item.retired_at',
            'item.updated_at as item_updated_at', 'version.updated_at as version_updated_at',
            'version.file_size', 'version.checksum_sha256', 'version.version_number',
        ]);
        if (! $row) {
            return null;
        }
        $projects = $this->approvedKnowledgeProjects([$itemId], now()->addSecond(), $forUpdate);

        return $this->knowledgeCandidate($row, $projects[$itemId] ?? []);
    }

    /** @return array<string,mixed> */
    private function performanceCandidate(object $row): array
    {
        $candidate = [
            'source_type' => self::TYPE_PERFORMANCE,
            'source_document_id' => (string) $row->source_document_id,
            'source_version_id' => null,
            'dedupe_repository_item_id' => $row->repository_item_id ? (string) $row->repository_item_id : null,
            'category' => 'performance_report_attachment',
            'classification' => 'restricted',
            'title' => (string) $row->title,
            'display_filename' => (string) $row->original_filename,
            'source_path' => (string) $row->file_path,
            'expected_size' => $row->file_size === null ? null : (int) $row->file_size,
            'expected_checksum' => null,
            'portfolio_external_id' => (string) $row->portfolio_id,
            'project_external_ids' => [(string) $row->project_component_id],
            'parent_type' => 'performance_report',
            'parent_external_id' => (string) $row->parent_external_id,
            'source_updated_at' => (string) $row->document_updated_at,
            'approval' => [
                'validation_status' => (string) $row->validation_status,
                'parent_status' => (string) $row->parent_status,
                'parent_updated_at' => (string) $row->parent_updated_at,
            ],
        ];

        return $this->finalize($candidate);
    }

    /** @return array<string,mixed> */
    private function missionCandidate(object $row): array
    {
        $candidate = [
            'source_type' => self::TYPE_MISSION,
            'source_document_id' => (string) $row->source_document_id,
            'source_version_id' => null,
            'category' => 'mission_report_attachment',
            'classification' => 'restricted',
            'title' => (string) $row->title,
            'display_filename' => (string) $row->original_filename,
            'source_path' => (string) $row->file_path,
            'expected_size' => $row->file_size === null ? null : (int) $row->file_size,
            'expected_checksum' => null,
            'portfolio_external_id' => $row->portfolio_id ? (string) $row->portfolio_id : null,
            'project_external_ids' => $row->project_component_id ? [(string) $row->project_component_id] : [],
            'parent_type' => 'mission_report',
            'parent_external_id' => (string) $row->parent_external_id,
            'source_updated_at' => (string) $row->document_updated_at,
            'approval' => [
                'parent_status' => (string) $row->parent_status,
                'parent_updated_at' => (string) $row->parent_updated_at,
            ],
        ];

        return $this->finalize($candidate);
    }

    /** @param list<string> $projectIds @return array<string,mixed> */
    private function knowledgeCandidate(object $row, array $projectIds): array
    {
        sort($projectIds, SORT_STRING);
        $checksum = strtolower(trim((string) $row->checksum_sha256));
        $candidate = [
            'source_type' => self::TYPE_KNOWLEDGE,
            'source_document_id' => (string) $row->source_document_id,
            'source_version_id' => (string) $row->source_version_id,
            'category' => 'knowledge_repository',
            'classification' => 'restricted',
            'title' => (string) $row->title,
            'display_filename' => (string) $row->original_filename,
            'source_path' => (string) $row->file_path,
            'expected_size' => $row->file_size === null ? null : (int) $row->file_size,
            'expected_checksum' => $checksum,
            'portfolio_external_id' => (string) $row->portfolio_id,
            'project_external_ids' => array_values(array_unique($projectIds)),
            'parent_type' => 'knowledge_repository_item',
            'parent_external_id' => (string) $row->source_document_id,
            'source_updated_at' => (string) $row->version_updated_at,
            'approval' => [
                'validation_status' => (string) $row->validation_status,
                'retired_at' => $row->retired_at,
                'item_updated_at' => (string) $row->item_updated_at,
                'version_updated_at' => (string) $row->version_updated_at,
                'version_number' => (int) $row->version_number,
                'repository_category' => (string) $row->repository_category,
                'document_type' => (string) $row->document_type,
            ],
        ];
        if (! preg_match('/^[a-f0-9]{64}$/D', $checksum)) {
            $candidate['pre_hold_code'] = 'source_checksum_unavailable';
            $candidate['pre_hold_message'] = 'The validated repository version does not have a usable SHA-256 checksum.';
        }

        return $this->finalize($candidate);
    }

    /** @param array<string,mixed> $candidate @return array<string,mixed> */
    private function finalize(array $candidate): array
    {
        $candidate['project_external_ids'] = array_values(array_unique(array_filter(
            (array) $candidate['project_external_ids'],
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));
        sort($candidate['project_external_ids'], SORT_STRING);
        $candidate['source_key'] = hash('sha256', implode("\0", [
            (string) $candidate['source_type'],
            (string) $candidate['source_document_id'],
            (string) ($candidate['source_version_id'] ?? ''),
        ]));
        $revisionFields = $candidate;
        unset($revisionFields['revision'], $revisionFields['pre_hold_code'], $revisionFields['pre_hold_message']);
        $candidate['revision'] = hash('sha256', json_encode(
            $this->sortRecursively($revisionFields),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));

        return $candidate;
    }

    /** @param list<string> $itemIds @return array<string,list<string>> */
    private function approvedKnowledgeProjects(array $itemIds, ?CarbonInterface $capturedAt = null, bool $forUpdate = false): array
    {
        if ($itemIds === []) {
            return [];
        }

        $linkedQuery = DB::table('me_repository_document_links as link')
            ->join('me_performance_reports as report', 'report.id', '=', 'link.linkable_id')
            ->join('me_knowledge_evidence_items as approved_item', 'approved_item.id', '=', 'link.repository_item_id')
            ->whereIn('link.repository_item_id', $itemIds)
            ->where('link.linkable_type', MePerformanceReport::class)
            ->whereIn('report.status', ['approved', 'archived'])
            ->whereNotNull('report.project_component_id')
            ->whereColumn('report.portfolio_id', 'approved_item.portfolio_id');
        if ($capturedAt) {
            $linkedQuery->where('link.created_at', '<=', $capturedAt)
                ->where('link.updated_at', '<=', $capturedAt)
                ->where('approved_item.created_at', '<=', $capturedAt)
                ->where('approved_item.updated_at', '<=', $capturedAt)
                ->where('report.created_at', '<=', $capturedAt)
                ->where('report.updated_at', '<=', $capturedAt);
        }
        $linkedQuery->orderBy('report.project_component_id');
        if ($forUpdate) {
            $linkedQuery->lockForUpdate();
        }
        $linked = $linkedQuery
            ->get(['link.repository_item_id', 'report.project_component_id'])
            ->groupBy('repository_item_id')
            ->map(fn ($rows): array => $rows->pluck('project_component_id')
                ->map(fn ($id): string => (string) $id)
                ->unique()
                ->values()
                ->all())
            ->all();
        $attachedQuery = DB::table('me_performance_report_documents as document')
            ->join('me_performance_reports as report', 'report.id', '=', 'document.report_id')
            ->join('me_knowledge_evidence_items as approved_item', 'approved_item.id', '=', 'document.repository_item_id')
            ->whereIn('document.repository_item_id', $itemIds)
            ->where('document.validation_status', 'validated')
            ->whereIn('report.status', ['approved', 'archived'])
            ->whereNotNull('report.project_component_id')
            ->whereColumn('report.portfolio_id', 'approved_item.portfolio_id');
        if ($capturedAt) {
            $attachedQuery->where('document.created_at', '<=', $capturedAt)
                ->where('document.updated_at', '<=', $capturedAt)
                ->where('approved_item.created_at', '<=', $capturedAt)
                ->where('approved_item.updated_at', '<=', $capturedAt)
                ->where('report.created_at', '<=', $capturedAt)
                ->where('report.updated_at', '<=', $capturedAt);
        }
        $attachedQuery->orderBy('report.project_component_id');
        if ($forUpdate) {
            $attachedQuery->lockForUpdate();
        }
        $attached = $attachedQuery
            ->get(['document.repository_item_id', 'report.project_component_id'])
            ->groupBy('repository_item_id')
            ->map(fn ($rows): array => $rows->pluck('project_component_id')
                ->map(fn ($id): string => (string) $id)
                ->unique()
                ->values()
                ->all())
            ->all();

        return collect(array_unique([...array_keys($linked), ...array_keys($attached)]))
            ->mapWithKeys(function (string $itemId) use ($linked, $attached): array {
                $projects = array_values(array_unique([
                    ...($linked[$itemId] ?? []),
                    ...($attached[$itemId] ?? []),
                ]));
                sort($projects, SORT_STRING);

                return [$itemId => $projects];
            })
            ->all();
    }

    private function withoutRepositoryDuplicates(
        \Illuminate\Database\Query\Builder $query,
        CarbonInterface $capturedAt,
    ): \Illuminate\Database\Query\Builder {
        return $query->where(function ($dedupe) use ($capturedAt): void {
            $dedupe->whereNull('document.repository_item_id')
                ->orWhereNotExists(function ($repository) use ($capturedAt): void {
                    $repository->selectRaw('1')
                        ->from('me_knowledge_evidence_items as repository_item')
                        ->join('me_repository_document_versions as repository_version', 'repository_version.repository_item_id', '=', 'repository_item.id')
                        ->whereColumn('repository_item.id', 'document.repository_item_id')
                        ->where('repository_item.validation_status', 'validated')
                        ->whereNull('repository_item.retired_at')
                        ->where('repository_item.created_at', '<=', $capturedAt)
                        ->where('repository_item.updated_at', '<=', $capturedAt)
                        ->whereNotNull('repository_version.file_path')
                        ->where('repository_version.created_at', '<=', $capturedAt)
                        ->where('repository_version.updated_at', '<=', $capturedAt);
                });
        });
    }

    /** @param array<mixed> $value @return array<mixed> */
    private function sortRecursively(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->sortRecursively($item);
            }
        }

        return $value;
    }
}
