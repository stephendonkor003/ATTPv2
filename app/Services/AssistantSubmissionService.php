<?php

namespace App\Services;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\AssistantSubmission;
use App\Models\ProcurementAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AssistantSubmissionService
{
    use ScopesAssignedPortfolios;

    public function isReviewer(?User $user): bool
    {
        return $user && ! $user->is_disabled && ! $user->is_blacklisted && ! $user->isAdministrativeAssistant()
            && ($user->isAdmin() || $user->isSuperAdmin());
    }

    public function canReview(User $user, AssistantSubmission $submission): bool
    {
        if (! $this->isReviewer($user) || (string) $user->id === (string) $submission->created_by) return false;
        if ($user->isAdmin() || $user->isSuperAdmin()) return true;
        if ($this->userHasAssignedPortfolioScope($user)) {
            return in_array((string) $submission->governance_node_id, $this->assignedPortfolioNodeIds($user), true);
        }
        return $user->governance_node_id && (string) $user->governance_node_id === (string) $submission->governance_node_id;
    }

    public function reviewers(AssistantSubmission $submission)
    {
        return User::with('role')->where(fn ($q) => $q->whereNull('is_disabled')->orWhere('is_disabled', false))
            ->get()->filter(fn ($user) => $this->canReview($user, $submission))
            ->sortBy(fn ($user) => strtolower($user->email) === strtolower(config('assistant_submissions.coordinator_email')) ? 0 : 1)->values();
    }

    public function capture(Request $request, string $kind, array $validated, ?string $nodeId, array $summary)
    {
        abort_unless($request->user()?->isAdministrativeAssistant(), 403);
        $submission = new AssistantSubmission;
        $submission->id = (string) Str::uuid();
        $documents = [];
        try {
            $payload = $this->storeFiles($validated, '', $submission->id, $documents);
            DB::transaction(function () use ($submission, $kind, $request, $nodeId, $summary, $payload, $documents) {
                $submission->fill([
                    'reference_no' => 'AS-'.now()->format('Y').'-'.Str::upper(Str::random(8)),
                    'kind' => $kind, 'status' => 'pending', 'created_by' => $request->user()->id,
                    'governance_node_id' => $nodeId, 'payload' => $payload, 'summary' => $summary,
                    'documents' => $documents, 'notification_status' => 'queued', 'notification_recipients' => [],
                ])->save();
                $this->audit($submission, 'Assistant submitted for coordinator approval');
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete(array_column($documents, 'path'));
            throw $e;
        }
        app(AssistantSubmissionNotificationService::class)->notify($submission);
        return redirect()->route('administrative-assistant.submissions.show', $submission)
            ->with('success', $submission->reference_no.' submitted for Project Coordinator approval. It has no effect on reports or budgets until approved.');
    }

    private function storeFiles(array $data, string $prefix, string $id, array &$documents): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $field = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if ($value instanceof UploadedFile) {
                $path = $value->store('assistant-submissions/'.$id, 'local');
                if (! $path) throw new \RuntimeException('Unable to save a supporting document.');
                $documents[] = ['field' => $field, 'path' => $path, 'name' => basename(str_replace('\\', '/', $value->getClientOriginalName())), 'mime' => $value->getMimeType(), 'sha256' => hash_file('sha256', Storage::disk('local')->path($path))];
            } else {
                $result[$key] = is_array($value) ? $this->storeFiles($value, $field, $id, $documents) : $value;
            }
        }
        return $result;
    }

    public function files(AssistantSubmission $submission): array
    {
        $files = [];
        foreach ($submission->documents as $document) {
            $path = $this->documentPath($submission, $document);
            abort_unless(hash_equals($document['sha256'], hash_file('sha256', $path)), 409, 'A supporting document has changed.');
            Arr::set($files, $document['field'], new UploadedFile($path, $document['name'], $document['mime'], null, true));
        }
        return $files;
    }

    public function documentPath(AssistantSubmission $submission, array $document): string
    {
        $path = str_replace('\\', '/', $document['path'] ?? '');
        abort_unless(str_starts_with($path, 'assistant-submissions/'.$submission->id.'/') && ! str_contains($path, '..'), 404);
        abort_unless(Storage::disk('local')->exists($path), 404, 'Supporting document is missing.');
        return Storage::disk('local')->path($path);
    }

    public function audit(AssistantSubmission $submission, string $action): void
    {
        ProcurementAuditLog::create(['user_id' => auth()->id(), 'action' => $action,
            'metadata' => ['assistant_submission_id' => $submission->id, 'created_by' => $submission->created_by, 'status' => $submission->status, 'published_ids' => $submission->published_ids], 'created_at' => now()]);
    }
}
