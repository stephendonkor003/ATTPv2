<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Indicator;
use App\Models\IndicatorDataSourceSyncLog;
use App\Models\IndicatorSurveyLink;
use App\Models\IndicatorSurveyResponse;
use App\Models\IndicatorResult;
use App\Models\Program;
use App\Models\Project;
use App\Models\SubActivity;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use PDF;

class MeDataSourceController extends Controller
{
    protected const SUPPORTED_SYNC_FILE_EXTENSIONS = ['csv', 'xlsx', 'xls'];

    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner']);
        $this->middleware('permission:me.configuration.view')->only([
            'index',
            'downloadGenericTemplate',
            'downloadTemplate',
            'exportSurveys',
            'showSurvey',
            'exportSurvey',
            'rawData',
        ]);
        $this->middleware('permission:me.configuration.manage')->only([
            'previewColumns',
            'manualSync',
            'manualSyncAll',
        ]);
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $indicators = Indicator::query()
            ->with(['indicatorable', 'latestDataSourceSyncLog'])
            ->whereNotNull('primary_source')
            ->where('primary_source', '!=', '')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%' . $search . '%')
                        ->orWhere('primary_source', 'like', '%' . $search . '%')
                        ->orWhere('methodology', 'like', '%' . $search . '%');
                });
            })
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        $rows = $indicators->getCollection()
            ->map(fn (Indicator $indicator) => $this->buildDataSourceRow($indicator))
            ->values();

        $summary = [
            'total' => $rows->count(),
            'file_sources' => $rows->where('source_type_key', 'file_location')->count(),
            'link_sources' => $rows->filter(fn (array $row) => in_array($row['source_type_key'], ['link', 'external_system_connector'], true))->count(),
            'accessible_directories' => $rows->where('directory_accessible', true)->count(),
            'last_success' => $rows->where('last_status', 'success')->count(),
            'needs_attention' => $rows->filter(function (array $row) {
                return in_array($row['last_status'], ['failed', 'never', 'partial'], true);
            })->count(),
        ];

        $surveyLinks = IndicatorSurveyLink::with(['indicator:id,name', 'methodology:id,name'])
            ->withCount('responses')
            ->latest()
            ->get();

        $surveyStats = [
            'surveys' => $surveyLinks->count(),
            'responses' => IndicatorSurveyResponse::count(),
            'last_response' => optional(IndicatorSurveyResponse::latest('submitted_at')->first())->submitted_at,
        ];

        return view('me.data-sources.index', [
            'indicators' => $indicators,
            'rows' => $rows,
            'search' => $search,
            'summary' => $summary,
            'surveyLinks' => $surveyLinks,
            'surveyStats' => $surveyStats,
        ]);
    }

    public function downloadGenericTemplate(Request $request): StreamedResponse
    {
        $sourceType = trim((string) $request->query('source_type', 'file_location'));
        $sourceValue = trim((string) $request->query('source_value', ''));

        return $this->streamTemplateCsv(
            null,
            $sourceType !== '' ? $sourceType : 'file_location',
            $sourceValue
        );
    }

    public function downloadTemplate(Request $request, Indicator $indicator): StreamedResponse
    {
        [$storedType, $storedValue] = $this->unpackPrimarySource($indicator->primary_source);

        $sourceType = trim((string) $request->query('source_type', $storedType ?? 'file_location'));
        $sourceValue = trim((string) $request->query('source_value', $storedValue ?? ''));

        return $this->streamTemplateCsv(
            $indicator,
            $sourceType !== '' ? $sourceType : 'file_location',
            $sourceValue
        );
    }

    public function previewColumns(Request $request, Indicator $indicator): JsonResponse
    {
        $request->validate([
            'upload_file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:20480'],
        ]);

        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $request->file('upload_file');
        $extension = strtolower((string) $uploadedFile->getClientOriginalExtension());
        $columns = $this->extractColumnsFromFile((string) $uploadedFile->getRealPath(), $extension);

        return response()->json([
            'ok' => true,
            'indicator_id' => $indicator->id,
            'columns' => $columns,
        ]);
    }

    public function exportSurveys(Request $request)
    {
        $format = strtolower((string) $request->query('format', 'csv'));

        $responses = IndicatorSurveyResponse::with(['indicator:id,name', 'methodology:id,name', 'surveyLink:id,public_token'])
            ->orderByDesc('submitted_at')
            ->get();

        return $this->streamSurveyExport($responses, 'me-survey-responses', $format);
    }

    public function showSurvey(IndicatorSurveyLink $surveyLink)
    {
        $surveyLink->load(['indicator', 'methodology']);
        $responses = IndicatorSurveyResponse::with('indicator', 'methodology')
            ->where('survey_link_id', $surveyLink->id)
            ->latest('submitted_at')
            ->paginate(20)
            ->withQueryString();

        return view('me.data-sources.survey-responses', [
            'surveyLink' => $surveyLink,
            'responses' => $responses,
        ]);
    }

    public function exportSurvey(Request $request, IndicatorSurveyLink $surveyLink)
    {
        $format = strtolower((string) $request->query('format', 'csv'));

        $responses = IndicatorSurveyResponse::with(['indicator:id,name', 'methodology:id,name'])
            ->where('survey_link_id', $surveyLink->id)
            ->orderByDesc('submitted_at')
            ->get();

        return $this->streamSurveyExport($responses, 'survey-' . ($surveyLink->public_token ?? 'export'), $format);
    }

    private function streamSurveyExport($responses, string $baseFilename, string $format = 'csv')
    {
        $format = in_array($format, ['csv', 'pdf'], true) ? $format : 'csv';
        $filename = $baseFilename . '-' . now()->format('Ymd_His') . '.' . $format;

        if ($format === 'pdf') {
            $pdf = PDF::loadView('me.data-sources.survey-export-pdf', [
                'responses' => $responses,
                'title' => 'Survey Responses',
            ])->setPaper('a4', 'portrait');

            return $pdf->download($filename);
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($responses) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Indicator',
                'Methodology',
                'Survey Token',
                'Respondent Name',
                'Respondent Email',
                'Organization',
                'Submitted At',
                'Answers (JSON)',
                'IP Address',
            ]);

            foreach ($responses as $resp) {
                fputcsv($out, [
                    $resp->indicator->name ?? '',
                    $resp->methodology->name ?? '',
                    optional($resp->surveyLink)->public_token ?? '',
                    $resp->respondent_name,
                    $resp->respondent_email,
                    $resp->respondent_organization,
                    optional($resp->submitted_at)->toDateTimeString(),
                    json_encode($resp->answers),
                    $resp->ip_address,
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function manualSync(Request $request, Indicator $indicator)
    {
        $searchQuery = trim((string) $request->input('q', $request->query('q', '')));
        [$sourceType, $sourceValue] = $this->unpackPrimarySource($indicator->primary_source);
        if (!$sourceType || !$sourceValue) {
            return redirect()
                ->route('budget.me.data-sources.index', ['q' => $searchQuery])
                ->withErrors(['data_source_sync' => 'Indicator has no valid primary source configured.']);
        }

        $request->validate([
            'source_file' => ['nullable', 'string', 'max:255'],
            'upload_file' => ['nullable', 'file', 'mimes:csv,xlsx,xls', 'max:20480'],
            'column_map' => ['nullable', 'array'],
            'column_map.*' => ['nullable', 'string', 'max:120'],
            'row_mode' => ['nullable', 'string', 'in:all_rows,latest_row_only'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $columnMap = $this->sanitizeColumnMap((array) $request->input('column_map', []));
        $rowMode = $this->sanitizeRowMode((string) $request->input('row_mode', 'all_rows'));
        $startedAt = now();
        $result = [
            'status' => 'failed',
            'message' => 'Sync failed unexpectedly.',
            'rows' => 0,
            'meta' => [],
        ];

        $effectiveSourceValue = $sourceValue;
        $syncOptions = [
            'column_map' => $columnMap,
            'row_mode' => $rowMode,
        ];

        try {
            if ($sourceType === 'file_location') {
                $uploadedFile = $request->file('upload_file');
                if ($uploadedFile instanceof UploadedFile) {
                    $extension = strtolower((string) $uploadedFile->getClientOriginalExtension());
                    if (!$this->isSupportedSyncExtension($extension)) {
                        throw new \RuntimeException('Uploaded file must be CSV, XLSX, or XLS.');
                    }

                    $syncOptions['source_file_path'] = (string) $uploadedFile->getRealPath();
                    $syncOptions['source_file_extension'] = $extension;
                    $syncOptions['source_label'] = 'upload:' . $uploadedFile->getClientOriginalName();
                    $effectiveSourceValue = (string) $syncOptions['source_label'];
                } else {
                    $selectedFile = trim((string) $request->input('source_file', ''));
                    [$resolvedPath, $resolvedLabel] = $this->resolvePreferredSourceFile(
                        $sourceValue,
                        $selectedFile !== '' ? $selectedFile : null
                    );

                    $syncOptions['source_file_path'] = $resolvedPath;
                    $syncOptions['source_file_extension'] = strtolower((string) pathinfo($resolvedPath, PATHINFO_EXTENSION));
                    $syncOptions['source_label'] = 'file:' . $resolvedLabel;
                    $syncOptions['selected_file'] = $resolvedLabel;
                    $effectiveSourceValue = (string) $syncOptions['source_label'];
                }
            }

            $result = $this->syncIndicatorData($indicator, $sourceType, $sourceValue, $syncOptions);
        } catch (Throwable $throwable) {
            $result = [
                'status' => 'failed',
                'message' => $this->humanizeSyncException($throwable, $sourceType),
                'rows' => 0,
                'meta' => [],
            ];
        }

        $result = $this->normalizeSyncResultMessage($result, $sourceType);

        $resultMeta = (array) ($result['meta'] ?? []);
        if (!empty($columnMap)) {
            $resultMeta['column_map'] = $columnMap;
        }
        $resultMeta['row_mode'] = $rowMode;
        if (!empty($syncOptions['selected_file'])) {
            $resultMeta['selected_source_file'] = (string) $syncOptions['selected_file'];
        }
        if ($effectiveSourceValue !== $sourceValue) {
            $resultMeta['effective_source_value'] = $effectiveSourceValue;
        }

        IndicatorDataSourceSyncLog::create([
            'indicator_id' => $indicator->id,
            'source_type' => $sourceType,
            'source_value' => $sourceValue,
            'status' => $result['status'],
            'message' => $result['message'],
            'synced_rows' => (int) ($result['rows'] ?? 0),
            'started_at' => $startedAt,
            'synced_at' => now(),
            'synced_by' => auth()->id(),
            'meta' => $resultMeta,
        ]);

        if (($result['status'] ?? 'failed') === 'failed') {
            return redirect()
                ->route('budget.me.data-sources.index', ['q' => $searchQuery])
                ->withErrors(['data_source_sync' => $result['message']]);
        }

        return redirect()
            ->route('budget.me.data-sources.index', ['q' => $searchQuery])
            ->with('success', $result['message']);
    }

    public function manualSyncAll(Request $request)
    {
        $searchQuery = trim((string) $request->input('q', $request->query('q', '')));
        $indicators = Indicator::query()
            ->whereNotNull('primary_source')
            ->where('primary_source', '!=', '')
            ->get();

        $success = 0;
        $failed = 0;
        $partial = 0;

        foreach ($indicators as $indicator) {
            [$sourceType, $sourceValue] = $this->unpackPrimarySource($indicator->primary_source);
            if (!$sourceType || !$sourceValue) {
                continue;
            }

            $latestLog = $indicator->latestDataSourceSyncLog()->first();
            $savedMeta = is_array($latestLog?->meta) ? $latestLog->meta : [];
            $savedColumnMap = $this->sanitizeColumnMap((array) ($savedMeta['column_map'] ?? []));
            $savedRowMode = $this->sanitizeRowMode((string) ($savedMeta['row_mode'] ?? 'all_rows'));
            $savedSelectedFile = trim((string) ($savedMeta['selected_source_file'] ?? ''));

            $startedAt = now();
            $result = [
                'status' => 'failed',
                'message' => 'Sync failed unexpectedly.',
                'rows' => 0,
                'meta' => [],
            ];

            try {
                $syncOptions = [
                    'column_map' => $savedColumnMap,
                    'row_mode' => $savedRowMode,
                ];
                if ($sourceType === 'file_location' && $savedSelectedFile !== '') {
                    $syncOptions['selected_file'] = $savedSelectedFile;
                }

                $result = $this->syncIndicatorData($indicator, $sourceType, $sourceValue, $syncOptions);
            } catch (Throwable $throwable) {
                $result = [
                    'status' => 'failed',
                    'message' => $this->humanizeSyncException($throwable, $sourceType),
                    'rows' => 0,
                    'meta' => [],
                ];
            }

            $result = $this->normalizeSyncResultMessage($result, $sourceType);

            $resultMeta = (array) ($result['meta'] ?? []);
            if (!empty($savedColumnMap)) {
                $resultMeta['column_map'] = $savedColumnMap;
            }
            $resultMeta['row_mode'] = $savedRowMode;
            if ($savedSelectedFile !== '') {
                $resultMeta['selected_source_file'] = $savedSelectedFile;
            }

            IndicatorDataSourceSyncLog::create([
                'indicator_id' => $indicator->id,
                'source_type' => $sourceType,
                'source_value' => $sourceValue,
                'status' => $result['status'],
                'message' => $result['message'],
                'synced_rows' => (int) ($result['rows'] ?? 0),
                'started_at' => $startedAt,
                'synced_at' => now(),
                'synced_by' => auth()->id(),
                'meta' => $resultMeta,
            ]);

            if (($result['status'] ?? '') === 'success') {
                $success++;
            } elseif (($result['status'] ?? '') === 'partial') {
                $partial++;
            } else {
                $failed++;
            }
        }

        return redirect()
            ->route('budget.me.data-sources.index', ['q' => $searchQuery])
            ->with('success', "Manual sync complete. Success: {$success}, Partial: {$partial}, Failed: {$failed}.");
    }

    public function rawData(Request $request, Indicator $indicator)
    {
        $search = trim((string) $request->query('q', ''));
        [$sourceType, $sourceValue] = $this->unpackPrimarySource($indicator->primary_source);

        $results = IndicatorResult::query()
            ->with([
                'unit:id,name,symbol',
                'collectedByUser:id,name,email',
            ])
            ->where('indicator_id', $indicator->id)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('period_label', 'like', '%' . $search . '%')
                        ->orWhere('period_type', 'like', '%' . $search . '%')
                        ->orWhere('method', 'like', '%' . $search . '%')
                        ->orWhere('notes', 'like', '%' . $search . '%')
                        ->orWhere('data_source', 'like', '%' . $search . '%')
                        ->orWhere('actual_value', 'like', '%' . $search . '%');
                });
            })
            ->orderByDesc('collected_at')
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $latestResult = IndicatorResult::query()
            ->where('indicator_id', $indicator->id)
            ->orderByDesc('collected_at')
            ->orderByDesc('created_at')
            ->first();

        $syncLogs = IndicatorDataSourceSyncLog::query()
            ->where('indicator_id', $indicator->id)
            ->latest('synced_at')
            ->limit(20)
            ->get();

        $summary = [
            'total_rows' => IndicatorResult::query()->where('indicator_id', $indicator->id)->count(),
            'latest_value' => $latestResult?->actual_value,
            'latest_collected_at' => $latestResult?->collected_at,
            'latest_collected_by' => $latestResult?->collectedByUser?->name,
            'source_type' => $sourceType ? ucwords(str_replace('_', ' ', $sourceType)) : 'Unknown',
            'source_value' => $sourceValue ?: '—',
            'owner' => $this->ownerLabel($indicator),
        ];

        return view('me.data-sources.raw-data', [
            'indicator' => $indicator,
            'results' => $results,
            'syncLogs' => $syncLogs,
            'summary' => $summary,
            'search' => $search,
        ]);
    }

    protected function streamTemplateCsv(?Indicator $indicator, string $sourceType, string $sourceValue): StreamedResponse
    {
        $owner = $indicator ? $this->ownerLabel($indicator) : 'Unlinked';
        $indicatorId = $indicator?->id ?: 'new';

        $filename = 'indicator-data-bridge-'
            . $indicatorId
            . '-'
            . now()->format('Ymd_His')
            . '-'
            . Str::upper(Str::random(6))
            . '.csv';

        $rows = [
            ['source_type', 'source_value', 'owner', 'period_type', 'period_label', 'period_start', 'period_end', 'actual_value', 'method', 'notes'],
            [$sourceType, $sourceValue, $owner, 'month', now()->format('Y-m'), now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString(), '0', 'External Feed', 'Replace with real synchronized value'],
        ];

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function buildDataSourceRow(Indicator $indicator): array
    {
        [$sourceType, $sourceValue] = $this->unpackPrimarySource($indicator->primary_source);
        $latest = $indicator->latestDataSourceSyncLog;
        $latestMeta = is_array($latest?->meta) ? $latest->meta : [];
        $savedColumnMap = $this->sanitizeColumnMap((array) ($latestMeta['column_map'] ?? []));
        $savedRowMode = $this->sanitizeRowMode((string) ($latestMeta['row_mode'] ?? 'all_rows'));
        $savedSelectedFile = trim((string) ($latestMeta['selected_source_file'] ?? ''));
        $sourceUrl = filter_var((string) $sourceValue, FILTER_VALIDATE_URL) ? (string) $sourceValue : null;
        $directoryInventory = $this->buildDirectoryInventory($sourceType, $sourceValue);

        return [
            'indicator' => $indicator,
            'owner' => $this->ownerLabel($indicator),
            'source_type_key' => $sourceType,
            'source_type' => $sourceType ? ucwords(str_replace('_', ' ', $sourceType)) : 'Unknown',
            'source_value' => $sourceValue ?: '—',
            'source_url' => $sourceUrl,
            'last_sync_at' => $latest?->synced_at,
            'last_status' => $latest?->status ?: 'never',
            'last_message' => $latest?->message ?: 'No sync history yet.',
            'synced_rows' => (int) ($latest?->synced_rows ?? 0),
            'directory_path' => $directoryInventory['directory_path'],
            'directory_accessible' => $directoryInventory['directory_accessible'],
            'directory_files' => $directoryInventory['files'],
            'supported_files' => $directoryInventory['supported_files'],
            'default_source_file' => $directoryInventory['default_source_file'],
            'saved_column_map' => $savedColumnMap,
            'saved_row_mode' => $savedRowMode,
            'saved_selected_file' => $savedSelectedFile,
        ];
    }

    protected function syncIndicatorData(
        Indicator $indicator,
        string $sourceType,
        string $sourceValue,
        array $options = []
    ): array {
        $columnMap = $this->sanitizeColumnMap((array) ($options['column_map'] ?? []));
        $rowMode = $this->sanitizeRowMode((string) ($options['row_mode'] ?? 'all_rows'));
        $records = $this->readSourceRecords($sourceType, $sourceValue, $options);
        $records = $this->applyRowMode($records, $rowMode, $columnMap);
        if (empty($records)) {
            return [
                'status' => 'failed',
                'message' => 'The source was reachable, but no usable data rows were found. Check file headers, row data, and your column mapping.',
                'rows' => 0,
                'meta' => ['skipped' => 0, 'errors' => []],
            ];
        }

        $processed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($records as $index => $rawRecord) {
            if (!is_array($rawRecord)) {
                $skipped++;
                $errors[] = 'Row ' . ($index + 1) . ': Invalid row format.';
                continue;
            }

            $mappedRecord = $this->applyColumnMapping($rawRecord, $columnMap);
            $record = $this->normalizeIncomingRecord($mappedRecord);
            $actualValueParse = $this->normalizeActualValueForStorage($record['actual_value']);
            if (!$actualValueParse['valid']) {
                $skipped++;
                $errors[] = 'Row ' . ($index + 1) . ': ' . ($actualValueParse['reason'] ?? 'Actual Value is invalid.');
                continue;
            }

            $periodType = in_array($record['period_type'], ['year', 'quarter', 'month', 'custom'], true)
                ? $record['period_type']
                : 'custom';
            $periodStart = $this->normalizeDate($record['period_start']);
            $periodEnd = $this->normalizeDate($record['period_end']);

            $periodLabel = trim((string) $record['period_label']);
            if ($periodLabel === '') {
                if ($rowMode === 'latest_row_only') {
                    $periodLabel = 'LATEST_VALUE';
                } else {
                    $periodLabel = $periodStart ?: ('SYNC-' . now()->format('YmdHis') . '-' . ($index + 1));
                }
            }

            $result = IndicatorResult::firstOrNew([
                'indicator_id' => $indicator->id,
                'period_type' => $periodType,
                'period_label' => $periodLabel,
                'period_start' => $periodStart,
            ]);

            if (!$result->exists) {
                $result->created_by = auth()->id();
            }

            $result->period_end = $periodEnd;
            $result->actual_value = (float) $actualValueParse['numeric'];
            $result->unit_id = $indicator->unit_id;
            $result->data_source = $sourceType . ':' . $sourceValue;
            $result->method = $record['method'] ?: 'Data Source Controller Manual Sync';

            $notes = trim((string) $record['notes']);
            $conversionNote = trim((string) ($actualValueParse['conversion_note'] ?? ''));
            if ($conversionNote !== '') {
                $notes = $notes !== '' ? ($notes . ' | ' . $conversionNote) : $conversionNote;
            }
            $result->notes = $notes;
            $result->collected_by = auth()->id();
            $result->collected_at = now();
            $result->updated_by = auth()->id();
            $result->save();

            $processed++;
        }

        $status = 'failed';
        $message = 'No records were synchronized.';
        $sampleIssue = $errors[0] ?? null;
        $sampleIssueText = $sampleIssue
            ? ' Example issue: ' . $this->humanizeRowError($sampleIssue)
            : '';

        if ($processed > 0 && $skipped === 0) {
            $status = 'success';
            $message = "Synchronization successful. {$processed} record(s) processed.";
        } elseif ($processed > 0 && $skipped > 0) {
            $status = 'partial';
            $message = "Synchronization completed with warnings. Saved {$processed} row(s), skipped {$skipped} row(s)." . $sampleIssueText;
        } elseif ($processed === 0 && $skipped > 0) {
            $status = 'failed';
            $message = "Synchronization failed because all {$skipped} row(s) were invalid." . $sampleIssueText
                . ' Please verify mapping and ensure "Actual Value" is in a supported format (number, Yes/No, True/False, %, or number with unit).';
        }

        return [
            'status' => $status,
            'message' => $message,
            'rows' => $processed,
            'meta' => [
                'skipped' => $skipped,
                'errors' => array_slice($errors, 0, 25),
                'column_map' => $columnMap,
                'row_mode' => $rowMode,
            ],
        ];
    }

    protected function readSourceRecords(string $sourceType, string $sourceValue, array $options = []): array
    {
        if ($sourceType === 'file_location') {
            $path = $options['source_file_path'] ?? null;
            $forcedExtension = isset($options['source_file_extension'])
                ? strtolower((string) $options['source_file_extension'])
                : null;

            if (!$path) {
                [$resolvedPath] = $this->resolvePreferredSourceFile(
                    $sourceValue,
                    isset($options['selected_file']) ? trim((string) $options['selected_file']) : null
                );
                $path = $resolvedPath;
                $forcedExtension = strtolower((string) pathinfo($resolvedPath, PATHINFO_EXTENSION));
            }

            if (!is_file($path) || !is_readable($path)) {
                throw new \RuntimeException('Source file path is invalid or unreadable.');
            }

            return $this->readRecordsFromFile($path, $forcedExtension);
        }

        if (in_array($sourceType, ['link', 'external_system_connector'], true)) {
            if (!filter_var($sourceValue, FILTER_VALIDATE_URL)) {
                throw new \RuntimeException('Source endpoint must be a valid URL for link/connector types.');
            }

            $response = Http::timeout(25)->get($sourceValue);
            if (!$response->ok()) {
                throw new \RuntimeException('Source endpoint responded with status ' . $response->status() . '.');
            }

            $contentType = strtolower((string) $response->header('Content-Type', ''));
            if (str_contains($contentType, 'csv')) {
                return $this->parseCsvString($response->body());
            }

            $json = $response->json();
            if (!is_array($json)) {
                throw new \RuntimeException('Source endpoint must return JSON array/object with results.');
            }

            return $this->extractRecordsFromPayload($json);
        }

        throw new \RuntimeException('Unsupported source type for synchronization.');
    }

    protected function readRecordsFromFile(string $path, ?string $forcedExtension = null): array
    {
        $extension = $forcedExtension ?: strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            $content = file_get_contents($path);
            return $this->parseCsvString((string) $content);
        }

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->parseSpreadsheetFile($path);
        }

        $content = file_get_contents($path);
        $json = json_decode((string) $content, true);
        if (!is_array($json)) {
            throw new \RuntimeException('Source file must be a valid CSV, XLSX, XLS, or JSON file.');
        }

        return $this->extractRecordsFromPayload($json);
    }

    protected function parseSpreadsheetFile(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        $header = null;
        $records = [];

        foreach ($rows as $row) {
            $hasData = count(array_filter($row, fn ($value) => trim((string) $value) !== '')) > 0;
            if (!$hasData) {
                continue;
            }

            if ($header === null) {
                $header = array_map(function ($value) {
                    return $this->normalizeColumnName((string) $value);
                }, $row);
                continue;
            }

            $records[] = collect($header)
                ->mapWithKeys(function ($key, $index) use ($row) {
                    return [$key => $row[$index] ?? null];
                })
                ->all();
        }

        return $records;
    }

    protected function parseCsvString(string $content): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $header = fgetcsv($handle);
        if (!$header || !is_array($header)) {
            fclose($handle);
            return [];
        }

        $normalizedHeader = array_map(function ($value) {
            return $this->normalizeColumnName((string) $value);
        }, $header);

        $records = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $records[] = collect($normalizedHeader)
                ->mapWithKeys(function ($key, $index) use ($row) {
                    return [$key => $row[$index] ?? null];
                })
                ->all();
        }
        fclose($handle);

        return $records;
    }

    protected function extractRecordsFromPayload(array $payload): array
    {
        if (isset($payload['results']) && is_array($payload['results'])) {
            return $payload['results'];
        }

        if (array_is_list($payload)) {
            return $payload;
        }

        return [];
    }

    protected function sanitizeColumnMap(array $columnMap): array
    {
        $allowedFields = ['period_type', 'period_label', 'period_start', 'period_end', 'actual_value', 'method', 'notes'];
        $sanitized = [];

        foreach ($allowedFields as $field) {
            $sourceColumn = $this->normalizeColumnName((string) ($columnMap[$field] ?? ''));
            if ($sourceColumn === '') {
                continue;
            }

            $sanitized[$field] = $sourceColumn;
        }

        return $sanitized;
    }

    protected function applyColumnMapping(array $record, array $columnMap): array
    {
        if (empty($columnMap)) {
            return $record;
        }

        $normalizedRecord = [];
        foreach ($record as $key => $value) {
            $normalizedRecord[$this->normalizeColumnName((string) $key)] = $value;
        }

        foreach ($columnMap as $targetField => $sourceColumn) {
            $sourceKey = $this->normalizeColumnName((string) $sourceColumn);
            if ($sourceKey !== '' && array_key_exists($sourceKey, $normalizedRecord)) {
                $normalizedRecord[$targetField] = $normalizedRecord[$sourceKey];
            }
        }

        return $normalizedRecord;
    }

    protected function applyRowMode(array $records, string $rowMode, array $columnMap = []): array
    {
        if ($rowMode !== 'latest_row_only') {
            return $records;
        }

        if (empty($records)) {
            return [];
        }

        $latest = null;
        for ($index = count($records) - 1; $index >= 0; $index--) {
            $row = $records[$index] ?? null;
            if (!is_array($row)) {
                continue;
            }

            $mapped = $this->applyColumnMapping($row, $columnMap);
            $normalized = $this->normalizeIncomingRecord($mapped);
            $actualValueParse = $this->normalizeActualValueForStorage($normalized['actual_value']);
            if (!$actualValueParse['valid']) {
                continue;
            }

            $latest = $row;
            break;
        }

        if ($latest === null) {
            return [];
        }

        return [$latest];
    }

    protected function normalizeActualValueForStorage(mixed $value): array
    {
        if ($value === null) {
            return [
                'valid' => false,
                'numeric' => null,
                'reason' => 'Actual Value is missing.',
                'conversion_note' => null,
            ];
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return [
                'valid' => false,
                'numeric' => null,
                'reason' => 'Actual Value is empty.',
                'conversion_note' => null,
            ];
        }

        $noSpace = str_replace(' ', '', $raw);
        $normalized = str_replace(',', '', $noSpace);
        if (preg_match('/^[-+]?\d+,\d+$/', $noSpace) && !str_contains($noSpace, '.')) {
            $normalized = str_replace(',', '.', $noSpace);
        }

        if (is_numeric($normalized)) {
            return [
                'valid' => true,
                'numeric' => (float) $normalized,
                'reason' => null,
                'conversion_note' => null,
            ];
        }

        $lower = strtolower($raw);
        $binaryTrue = ['yes', 'y', 'true', 't', 'on', 'pass', 'passed', 'done', 'completed'];
        $binaryFalse = ['no', 'n', 'false', 'f', 'off', 'fail', 'failed', 'not_done', 'not done'];

        if (in_array($lower, $binaryTrue, true)) {
            return [
                'valid' => true,
                'numeric' => 1.0,
                'reason' => null,
                'conversion_note' => "Actual Value converted from '{$raw}' to 1.",
            ];
        }

        if (in_array($lower, $binaryFalse, true)) {
            return [
                'valid' => true,
                'numeric' => 0.0,
                'reason' => null,
                'conversion_note' => "Actual Value converted from '{$raw}' to 0.",
            ];
        }

        if (preg_match('/^[-+]?\d+(?:\.\d+)?\s*%$/', $raw)) {
            $numericPart = rtrim($raw, " \t\n\r\0\x0B%");
            if (is_numeric($numericPart)) {
                return [
                    'valid' => true,
                    'numeric' => (float) $numericPart,
                    'reason' => null,
                    'conversion_note' => null,
                ];
            }
        }

        if (preg_match('/[-+]?\d+(?:\.\d+)?/', $raw, $matches)) {
            $number = (string) $matches[0];
            if (is_numeric($number)) {
                return [
                    'valid' => true,
                    'numeric' => (float) $number,
                    'reason' => null,
                    'conversion_note' => "Actual Value extracted from '{$raw}' as {$number}.",
                ];
            }
        }

        return [
            'valid' => false,
            'numeric' => null,
            'reason' => "Actual Value '{$raw}' is not numeric or convertible. Use a number, Yes/No, True/False, percentage (e.g., 75%), or numeric value with unit (e.g., 6kg).",
            'conversion_note' => null,
        ];
    }

    protected function normalizeIncomingRecord(array $record): array
    {
        return [
            'period_type' => strtolower(trim((string) ($record['period_type'] ?? $record['type'] ?? 'custom'))),
            'period_label' => trim((string) ($record['period_label'] ?? $record['period'] ?? '')),
            'period_start' => $record['period_start'] ?? $record['start_date'] ?? $record['date'] ?? null,
            'period_end' => $record['period_end'] ?? $record['end_date'] ?? null,
            'actual_value' => $record['actual_value'] ?? $record['actual'] ?? $record['value'] ?? null,
            'method' => trim((string) ($record['method'] ?? '')),
            'notes' => trim((string) ($record['notes'] ?? '')),
        ];
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    protected function normalizeSyncResultMessage(array $result, ?string $sourceType = null): array
    {
        $status = strtolower((string) ($result['status'] ?? 'failed'));
        $message = trim((string) ($result['message'] ?? ''));
        $meta = (array) ($result['meta'] ?? []);
        $errors = array_values(array_filter((array) ($meta['errors'] ?? []), function ($error) {
            return is_string($error) && trim($error) !== '';
        }));

        if ($message === '') {
            $message = 'Synchronization failed due to an unknown issue. Please try again.';
        }

        if ($status !== 'success' && !empty($errors) && !str_contains($message, 'Example issue:')) {
            $message .= ' Example issue: ' . $this->humanizeRowError((string) $errors[0]);
        }

        if ($status === 'failed') {
            $message = $this->humanizeFailureText($message, $sourceType);
        }

        $result['message'] = $message;
        return $result;
    }

    protected function humanizeSyncException(Throwable $throwable, ?string $sourceType = null): string
    {
        if ($throwable instanceof ConnectionException) {
            return 'Could not connect to the remote source. Check internet/network access, URL, and firewall settings.';
        }

        $message = trim($throwable->getMessage());
        if ($message === '') {
            return 'Synchronization failed because of an unexpected system error. Please try again.';
        }

        return $this->humanizeFailureText($message, $sourceType);
    }

    protected function humanizeFailureText(string $message, ?string $sourceType = null): string
    {
        $normalized = strtolower(trim($message));

        if (str_contains($normalized, 'responded with status')) {
            preg_match('/status\s+(\d{3})/i', $message, $matches);
            $statusCode = isset($matches[1]) ? (int) $matches[1] : 0;

            if ($statusCode === 401 || $statusCode === 403) {
                return 'The remote source denied access (authorization error). Check credentials, tokens, or permissions.';
            }
            if ($statusCode === 404) {
                return 'The source URL was reached, but the file/endpoint was not found (404). Verify the URL/path.';
            }
            if (in_array($statusCode, [500, 502, 503, 504], true)) {
                return 'The remote server is currently unavailable or returned an internal error. Please try again later.';
            }

            return 'The remote source returned an unexpected response status'
                . ($statusCode > 0 ? " ({$statusCode})" : '')
                . '. Please verify the endpoint and access settings.';
        }

        if (str_contains($normalized, 'must be a valid url')) {
            return 'The source link is not a valid URL. Please enter a complete URL starting with http:// or https://.';
        }

        if (
            str_contains($normalized, 'invalid or unreadable')
            || str_contains($normalized, 'does not exist')
            || str_contains($normalized, 'not readable')
            || str_contains($normalized, 'selected file path is invalid')
        ) {
            return 'The configured file or directory could not be read by the server. Confirm the path exists and server permissions are correct.';
        }

        if (str_contains($normalized, 'must be csv, xlsx, or xls')) {
            return 'The selected file format is not supported. Please use CSV, XLSX, or XLS.';
        }

        if (str_contains($normalized, 'no csv/xlsx/xls files')) {
            return 'No supported files were found in the configured directory. Add at least one CSV/XLSX/XLS file and retry.';
        }

        if (str_contains($normalized, 'must return json')) {
            return 'The remote source returned data in an unexpected format. It should return JSON rows or CSV content.';
        }

        if (str_contains($normalized, 'unsupported source type')) {
            return 'The selected primary source type is not supported for synchronization.';
        }

        if (str_contains($normalized, 'no usable data rows were found')) {
            return $message;
        }

        if (str_starts_with($normalized, 'synchronization failed')) {
            return $message;
        }

        if ($sourceType === 'file_location' && str_contains($normalized, 'source file')) {
            return 'The source file could not be read. Confirm the selected file exists, is not locked, and is a valid CSV/XLSX/XLS.';
        }

        return $message;
    }

    protected function humanizeRowError(string $error): string
    {
        $friendly = str_replace(
            ['actual_value', 'period_label', 'period_start', 'period_end', 'period_type'],
            ['Actual Value', 'Period Label', 'Period Start', 'Period End', 'Period Type'],
            $error
        );

        $friendly = str_replace(
            ['is required and must be numeric', 'Invalid row format', 'not numeric or convertible'],
            ['is required and must be a number', 'Row structure is invalid', 'not in a supported value format'],
            $friendly
        );

        return trim($friendly);
    }

    protected function sanitizeRowMode(string $rowMode): string
    {
        return in_array($rowMode, ['all_rows', 'latest_row_only'], true) ? $rowMode : 'all_rows';
    }

    protected function buildDirectoryInventory(?string $sourceType, ?string $sourceValue): array
    {
        if ($sourceType !== 'file_location' || !$sourceValue) {
            return [
                'directory_path' => null,
                'directory_accessible' => false,
                'files' => [],
                'supported_files' => [],
                'default_source_file' => null,
            ];
        }

        $node = $this->resolveLocalSourceNode((string) $sourceValue);
        if (!$node) {
            return [
                'directory_path' => null,
                'directory_accessible' => false,
                'files' => [],
                'supported_files' => [],
                'default_source_file' => null,
            ];
        }

        $directoryPath = $node['type'] === 'directory'
            ? $node['path']
            : dirname((string) $node['path']);

        if (!is_dir($directoryPath) || !is_readable($directoryPath)) {
            return [
                'directory_path' => $directoryPath,
                'directory_accessible' => false,
                'files' => [],
                'supported_files' => [],
                'default_source_file' => null,
            ];
        }

        $files = $this->listDirectoryFiles($directoryPath);
        $supportedFiles = array_values(array_filter($files, fn ($file) => $file['is_supported']));

        $defaultSourceFile = null;
        if ($node['type'] === 'file') {
            $configuredFile = basename((string) $node['path']);
            foreach ($supportedFiles as $file) {
                if (strcasecmp((string) $file['name'], $configuredFile) === 0) {
                    $defaultSourceFile = $file['name'];
                    break;
                }
            }
        }

        if ($defaultSourceFile === null && !empty($supportedFiles)) {
            usort($supportedFiles, function ($a, $b) {
                return ($b['modified_at_timestamp'] ?? 0) <=> ($a['modified_at_timestamp'] ?? 0);
            });
            $defaultSourceFile = $supportedFiles[0]['name'];
        }

        return [
            'directory_path' => $directoryPath,
            'directory_accessible' => true,
            'files' => $files,
            'supported_files' => array_values(array_filter($files, fn ($file) => $file['is_supported'])),
            'default_source_file' => $defaultSourceFile,
        ];
    }

    protected function listDirectoryFiles(string $directory): array
    {
        $entries = scandir($directory);
        if (!is_array($entries)) {
            return [];
        }

        $files = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }

            $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            $isSupported = $this->isSupportedSyncExtension($extension);
            $modifiedTimestamp = (int) (filemtime($path) ?: 0);
            $size = (int) (filesize($path) ?: 0);

            $files[] = [
                'name' => $entry,
                'extension' => $extension !== '' ? $extension : '-',
                'is_supported' => $isSupported,
                'columns' => $isSupported ? $this->extractColumnsFromFile($path, $extension) : [],
                'size' => $size,
                'size_human' => $this->formatBytes($size),
                'modified_at' => $modifiedTimestamp > 0 ? Carbon::createFromTimestamp($modifiedTimestamp) : null,
                'modified_at_timestamp' => $modifiedTimestamp,
            ];
        }

        usort($files, function ($a, $b) {
            return strnatcasecmp((string) $a['name'], (string) $b['name']);
        });

        return $files;
    }

    protected function extractColumnsFromFile(string $path, ?string $forcedExtension = null): array
    {
        $extension = $forcedExtension ?: strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if (!$this->isSupportedSyncExtension($extension)) {
            return [];
        }

        try {
            $header = $extension === 'csv'
                ? $this->readCsvHeader($path)
                : $this->readSpreadsheetHeader($path);
        } catch (Throwable) {
            return [];
        }

        $columns = [];
        $seen = [];

        foreach ($header as $value) {
            $label = trim((string) $value);
            if ($label === '') {
                continue;
            }

            $normalized = $this->normalizeColumnName($label);
            if ($normalized === '' || isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $columns[] = [
                'label' => $label,
                'value' => $normalized,
            ];
        }

        return $columns;
    }

    protected function readCsvHeader(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $header = fgetcsv($handle);
        fclose($handle);

        return is_array($header) ? $header : [];
    }

    protected function readSpreadsheetHeader(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        foreach ($rows as $row) {
            $hasData = count(array_filter($row, fn ($value) => trim((string) $value) !== '')) > 0;
            if ($hasData) {
                return $row;
            }
        }

        return [];
    }

    protected function resolvePreferredSourceFile(string $sourceValue, ?string $selectedFile = null): array
    {
        $node = $this->resolveLocalSourceNode($sourceValue);
        if (!$node) {
            throw new \RuntimeException('Configured source location does not exist or is not readable.');
        }

        $directoryPath = $node['type'] === 'directory'
            ? $node['path']
            : dirname((string) $node['path']);
        $directoryRealPath = realpath($directoryPath);

        if (!$directoryRealPath || !is_readable($directoryRealPath)) {
            throw new \RuntimeException('Configured source directory is not readable.');
        }

        if ($selectedFile !== null && trim($selectedFile) !== '') {
            $normalizedSelected = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($selectedFile));
            if (str_contains($normalizedSelected, '..')) {
                throw new \RuntimeException('Selected file path is invalid.');
            }

            $resolvedFile = realpath($directoryRealPath . DIRECTORY_SEPARATOR . ltrim($normalizedSelected, DIRECTORY_SEPARATOR));
            if (
                !$resolvedFile
                || !is_file($resolvedFile)
                || !is_readable($resolvedFile)
                || !$this->pathWithinDirectory($resolvedFile, $directoryRealPath)
            ) {
                throw new \RuntimeException('Selected file does not exist in the configured directory.');
            }

            $extension = strtolower((string) pathinfo($resolvedFile, PATHINFO_EXTENSION));
            if (!$this->isSupportedSyncExtension($extension)) {
                throw new \RuntimeException('Selected file must be CSV, XLSX, or XLS.');
            }

            return [$resolvedFile, basename($resolvedFile)];
        }

        if ($node['type'] === 'file') {
            $path = (string) $node['path'];
            $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            if (!$this->isSupportedSyncExtension($extension)) {
                throw new \RuntimeException('Configured source file must be CSV, XLSX, or XLS.');
            }

            return [$path, basename($path)];
        }

        $files = array_values(array_filter(
            $this->listDirectoryFiles($directoryRealPath),
            fn ($file) => $file['is_supported'] === true
        ));

        if (empty($files)) {
            throw new \RuntimeException('No CSV/XLSX/XLS files were found in the configured directory.');
        }

        usort($files, function ($a, $b) {
            return ($b['modified_at_timestamp'] ?? 0) <=> ($a['modified_at_timestamp'] ?? 0);
        });

        $defaultFile = $files[0]['name'];
        $resolvedFile = realpath($directoryRealPath . DIRECTORY_SEPARATOR . $defaultFile);
        if (!$resolvedFile || !is_file($resolvedFile) || !is_readable($resolvedFile)) {
            throw new \RuntimeException('Automatic source file selection failed.');
        }

        return [$resolvedFile, basename($resolvedFile)];
    }

    protected function pathWithinDirectory(string $filePath, string $directoryPath): bool
    {
        $fileRealPath = realpath($filePath);
        $directoryRealPath = realpath($directoryPath);
        if (!$fileRealPath || !$directoryRealPath) {
            return false;
        }

        $prefix = rtrim($directoryRealPath, '/\\') . DIRECTORY_SEPARATOR;
        return str_starts_with(strtolower($fileRealPath), strtolower($prefix));
    }

    protected function resolveLocalSourcePath(string $sourceValue): ?string
    {
        $node = $this->resolveLocalSourceNode($sourceValue);
        if (!$node || $node['type'] !== 'file') {
            return null;
        }

        return $node['path'];
    }

    protected function resolveLocalSourceNode(string $sourceValue): ?array
    {
        $value = trim($sourceValue);
        if ($value === '') {
            return null;
        }

        if (str_starts_with(strtolower($value), 'file://')) {
            $value = substr($value, 7);
        }

        $candidates = collect([
            $value,
            storage_path('app/' . ltrim($value, '/\\')),
            base_path(ltrim($value, '/\\')),
        ])->unique()->values();

        foreach ($candidates as $candidate) {
            if (is_dir($candidate) && is_readable($candidate)) {
                return [
                    'type' => 'directory',
                    'path' => (string) realpath($candidate),
                ];
            }

            if (is_file($candidate) && is_readable($candidate)) {
                return [
                    'type' => 'file',
                    'path' => (string) realpath($candidate),
                ];
            }
        }

        return null;
    }

    protected function isSupportedSyncExtension(?string $extension): bool
    {
        return in_array(strtolower((string) $extension), self::SUPPORTED_SYNC_FILE_EXTENSIONS, true);
    }

    protected function normalizeColumnName(string $value): string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
        return trim($normalized, '_');
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return number_format($value, $power === 0 ? 0 : 2) . ' ' . $units[$power];
    }

    protected function unpackPrimarySource(?string $source): array
    {
        if (!$source) {
            return [null, null];
        }

        if (!str_contains($source, ':')) {
            return [null, trim((string) $source)];
        }

        [$type, $value] = explode(':', $source, 2);
        $type = trim((string) $type);
        $value = trim((string) $value);

        if (!in_array($type, ['file_location', 'link', 'external_system_connector'], true)) {
            return [null, trim((string) $source)];
        }

        return [$type, $value];
    }

    protected function ownerLabel(Indicator $indicator): string
    {
        if ($indicator->indicatorable_type === Program::class) {
            return 'Program: ' . ($indicator->indicatorable?->name ?: 'Unknown');
        }

        if ($indicator->indicatorable_type === Project::class) {
            return 'Project: ' . ($indicator->indicatorable?->name ?: 'Unknown');
        }

        if ($indicator->indicatorable_type === Activity::class) {
            return 'Activity: ' . ($indicator->indicatorable?->name ?: 'Unknown');
        }

        if ($indicator->indicatorable_type === SubActivity::class) {
            return 'Sub-Activity: ' . ($indicator->indicatorable?->name ?: 'Unknown');
        }

        return 'Unlinked';
    }

}
