<?php

namespace App\Services;

use App\Models\BiAnnualSiteVisitProfile;
use App\Models\ConsortiumThinkTank;
use App\Models\Sector;
use Illuminate\Support\Facades\File;

class BiAnnualSiteVisitBrandingService
{
    public const FALLBACK_PORTFOLIO_NAME = 'ATTP Portfolio';

    public function portfolioForThinkTank(ConsortiumThinkTank $thinkTank): ?Sector
    {
        $thinkTank->loadMissing([
            'consortium.programFunding.program.sector',
            'consortium.funder',
        ]);

        $portfolio = $thinkTank->consortium?->programFunding?->program?->sector;
        if ($portfolio instanceof Sector) {
            return $portfolio;
        }

        return null;
    }

    /**
     * @return array{id: string, name: string}|null
     */
    public function portfolioSnapshot(ConsortiumThinkTank $thinkTank): ?array
    {
        $portfolio = $this->portfolioForThinkTank($thinkTank);

        if (! $portfolio) {
            return null;
        }

        return [
            'id' => (string) $portfolio->id,
            'name' => (string) $portfolio->name,
        ];
    }

    public function portfolioNameForVisit(BiAnnualSiteVisitProfile $visit): string
    {
        $snapshotName = trim((string) data_get($visit->settings, 'portfolio.name'));
        if ($snapshotName !== '') {
            return $snapshotName;
        }

        $visit->loadMissing('thinkTank.consortium.programFunding.program.sector');
        $portfolio = $visit->thinkTank
            ? $this->portfolioForThinkTank($visit->thinkTank)
            : null;

        return trim((string) $portfolio?->name)
            ?: self::FALLBACK_PORTFOLIO_NAME;
    }

    public function portfolioIdForVisit(BiAnnualSiteVisitProfile $visit): ?string
    {
        $snapshotId = trim((string) data_get($visit->settings, 'portfolio.id'));
        if ($snapshotId !== '') {
            return $snapshotId;
        }

        $visit->loadMissing('thinkTank.consortium.programFunding.program.sector');
        $portfolio = $visit->thinkTank
            ? $this->portfolioForThinkTank($visit->thinkTank)
            : null;

        return $portfolio ? (string) $portfolio->id : null;
    }

    public function logoDataUri(): ?string
    {
        $path = public_path('assets/images/attp-logo.jpeg');
        if (! File::isFile($path)) {
            return null;
        }

        $contents = File::get($path);

        return 'data:image/jpeg;base64,'.base64_encode($contents);
    }
}
