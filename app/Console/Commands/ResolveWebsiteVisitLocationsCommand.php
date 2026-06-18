<?php

namespace App\Console\Commands;

use App\Models\WebsiteVisit;
use App\Services\WebsiteVisitLocationResolver;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class ResolveWebsiteVisitLocationsCommand extends Command
{
    protected $signature = 'website-visits:resolve-locations
                            {--limit=100 : Maximum unresolved visits to retry}
                            {--force : Ignore retry delay and max attempts}';

    protected $description = 'Retry geolocation lookup for website visits with unknown countries';

    public function handle(WebsiteVisitLocationResolver $locationResolver): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $force = (bool) $this->option('force');
        $maxAttempts = max(1, (int) config('services.ipgeo.max_attempts', 8));
        $retryMinutes = max(1, (int) config('services.ipgeo.retry_minutes', 15));

        $visits = WebsiteVisit::query()
            ->whereNotNull('ip_address')
            ->where(function (Builder $query) {
                $query->whereNull('country_iso2')
                    ->orWhere('country_iso2', '')
                    ->orWhereNull('country_name')
                    ->orWhere('country_name', '')
                    ->orWhere('country_name', 'Unknown');
            })
            ->when(!$force, function (Builder $query) use ($maxAttempts, $retryMinutes) {
                $query->where('location_lookup_attempts', '<', $maxAttempts)
                    ->where(function (Builder $builder) use ($retryMinutes) {
                        $builder->whereNull('location_lookup_last_attempt_at')
                            ->orWhere('location_lookup_last_attempt_at', '<=', now()->subMinutes($retryMinutes));
                    });
            })
            ->oldest('last_seen_at')
            ->limit($limit)
            ->get();

        $resolved = 0;
        $unresolved = 0;

        foreach ($visits as $visit) {
            $visit->location_lookup_attempts = min(255, ((int) $visit->location_lookup_attempts) + 1);
            $visit->location_lookup_last_attempt_at = now();

            $geo = $locationResolver->resolve($visit->ip_address, forceRefresh: true);
            $changed = $locationResolver->apply($visit, $geo, overwrite: true);

            if ($changed && !$locationResolver->needsLocation($visit)) {
                $visit->location_lookup_failed_at = null;
                $resolved++;
            } else {
                if ($visit->location_lookup_attempts >= $maxAttempts) {
                    $visit->location_lookup_failed_at = now();
                }
                $unresolved++;
            }

            $visit->save();
        }

        $this->info("Location retry complete. Checked: {$visits->count()}, resolved: {$resolved}, still unknown: {$unresolved}.");

        return self::SUCCESS;
    }
}
