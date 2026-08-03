<?php

namespace App\Services;

use App\Models\MePerformanceReport;
use App\Models\MeRepositoryFolder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MeRepositoryFolderService
{
    /** @param Collection<int, string>|array<int, string> $indicatorIds */
    public function resolve(
        string $portfolioId,
        string $name,
        ?string $userId,
        Collection|array $indicatorIds = [],
        ?string $description = null
    ): MeRepositoryFolder {
        $folder = MeRepositoryFolder::query()->firstOrCreate(
            ['portfolio_id' => $portfolioId, 'name' => Str::limit(trim($name), 180, '')],
            [
                'description' => $description,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );

        $ids = collect($indicatorIds)->filter()->map(fn ($id) => (string) $id)->unique();
        if ($ids->isNotEmpty()) {
            $folder->indicators()->syncWithoutDetaching(
                $ids->mapWithKeys(fn (string $id): array => [$id => ['linked_by' => $userId]])->all()
            );
        }

        return $folder;
    }

    public function forReport(MePerformanceReport $report, ?string $userId): MeRepositoryFolder
    {
        $report->loadMissing(['thinkTank:id,name', 'form:id,code,title', 'indicatorResults:id,report_id,indicator_id']);
        $owner = $report->thinkTank?->name ?: 'ATTP Secretariat';
        $form = $report->form?->code ?: 'Performance Report';

        return $this->resolve(
            (string) $report->portfolio_id,
            $owner.' - '.$report->periodLabel().' - '.$form,
            $userId,
            $report->indicatorResults->pluck('indicator_id'),
            'Supporting evidence and retained versions for '.$owner.' '.$report->periodLabel().'.'
        );
    }

    public function general(string $portfolioId, ?string $userId): MeRepositoryFolder
    {
        return $this->resolve(
            $portfolioId,
            'General Knowledge and Evidence',
            $userId,
            [],
            'General repository documents awaiting classification or indicator linkage.'
        );
    }
}
