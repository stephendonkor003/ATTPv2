<?php

namespace App\Http\Controllers;

use App\Models\ProgramFunding;
use App\Models\Funder;
use App\Models\AuMemberState;
use App\Models\AuRegionalBlock;
use App\Models\AuAspiration;
use App\Models\AuGoal;
use App\Models\AuFlagshipProject;
use App\Models\Treaty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Barryvdh\DomPDF\Facade\Pdf;

class ImpactMapController extends Controller
{
    /**
     * Display the impact map dashboard.
     */
    public function index()
    {
        // Get all approved fundings with relationships
        $fundings = ProgramFunding::with([
            'funder',
            'memberStates',
            'regionalBlocks',
            'aspirations',
            'goals',
            'flagshipProjects',
        ])
        ->where('status', 'approved')
        ->get();

        // Summary Statistics
        $summary = $this->getSummaryStatistics($fundings);

        // Get all filter options
        $filterOptions = $this->getFilterOptions();

        // Get funding by funder (partner)
        $fundingByPartner = $this->getFundingByPartner($fundings);

        // Get funding by region
        $fundingByRegion = $this->getFundingByRegion($fundings);

        // Get funding by country
        $fundingByCountry = $this->getFundingByCountry($fundings);

        // Get funding by aspiration
        $fundingByAspiration = $this->getFundingByAspiration($fundings);

        // Get funding by goal
        $fundingByGoal = $this->getFundingByGoal($fundings);

        // Get funding by flagship project
        $fundingByFlagship = $this->getFundingByFlagship($fundings);

        // Get year-over-year trend data
        $trendData = $this->getTrendData($fundings);

        // Get country GeoJSON data with funding info
        $countryGeoData = $this->getCountryGeoData($fundings);

        // Shape files for Africa base map
        $shapeFiles = $this->getAfricaShapeFiles();

        // Treaties and agreements data for the additional map tab
        $treatiesData = $this->getTreatiesData();

        return view('impact-map', compact(
            'summary',
            'filterOptions',
            'fundingByPartner',
            'fundingByRegion',
            'fundingByCountry',
            'fundingByAspiration',
            'fundingByGoal',
            'fundingByFlagship',
            'trendData',
            'countryGeoData',
            'shapeFiles',
            'treatiesData'
        ));
    }

    /**
     * API endpoint for filtered impact data.
     */
    public function getFilteredData(Request $request)
    {
        $query = ProgramFunding::with([
            'funder',
            'memberStates',
            'regionalBlocks',
            'aspirations',
            'goals',
            'flagshipProjects',
        ])
        ->where('status', 'approved');

        // Apply filters
        if ($request->has('funders') && !empty($request->funders)) {
            $query->whereIn('funder_id', $request->funders);
        }

        if ($request->has('regions') && !empty($request->regions)) {
            $query->whereHas('regionalBlocks', function ($q) use ($request) {
                $q->whereIn('myb_au_regional_blocks.id', $request->regions);
            });
        }

        if ($request->has('countries') && !empty($request->countries)) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('memberStates', function ($sq) use ($request) {
                    $sq->whereIn('myb_au_member_states.id', $request->countries);
                })
                ->orWhere('is_continental_initiative', true);
            });
        }

        if ($request->has('aspirations') && !empty($request->aspirations)) {
            $query->whereHas('aspirations', function ($q) use ($request) {
                $q->whereIn('myb_au_aspirations.id', $request->aspirations);
            });
        }

        if ($request->has('goals') && !empty($request->goals)) {
            $query->whereHas('goals', function ($q) use ($request) {
                $q->whereIn('myb_au_goals.id', $request->goals);
            });
        }

        if ($request->has('year_from') && $request->year_from) {
            $query->where('start_year', '>=', $request->year_from);
        }

        if ($request->has('year_to') && $request->year_to) {
            $query->where('end_year', '<=', $request->year_to);
        }

        // Filter by scope (continental vs targeted)
        if ($request->has('scope') && !empty($request->scope)) {
            $scopes = is_array($request->scope) ? $request->scope : explode(',', $request->scope);

            if (count($scopes) === 1) {
                if (in_array('continental', $scopes)) {
                    $query->where('is_continental_initiative', true);
                } elseif (in_array('targeted', $scopes)) {
                    $query->where(function ($q) {
                        $q->where('is_continental_initiative', false)
                          ->orWhereNull('is_continental_initiative');
                    });
                }
            }
        }

        $fundings = $query->get();

        return response()->json([
            'summary' => $this->getSummaryStatistics($fundings),
            'fundingByPartner' => $this->getFundingByPartner($fundings),
            'fundingByRegion' => $this->getFundingByRegion($fundings),
            'fundingByCountry' => $this->getFundingByCountry($fundings),
            'fundingByAspiration' => $this->getFundingByAspiration($fundings),
            'fundingByGoal' => $this->getFundingByGoal($fundings),
            'trendData' => $this->getTrendData($fundings),
            'countryGeoData' => $this->getCountryGeoData($fundings),
            'programs' => $this->getProgramsList($fundings),
        ]);
    }

    /**
     * Download impact report as PDF.
     */
    public function downloadPdf(Request $request)
    {
        $fundings = $this->getFilteredFundings($request);

        $data = [
            'title' => 'ATTP Impact Report',
            'generated_at' => now()->format('d M Y, H:i'),
            'summary' => $this->getSummaryStatistics($fundings),
            'fundingByPartner' => $this->getFundingByPartner($fundings),
            'fundingByRegion' => $this->getFundingByRegion($fundings),
            'fundingByCountry' => $this->getFundingByCountry($fundings),
            'fundingByAspiration' => $this->getFundingByAspiration($fundings),
            'programs' => $this->getProgramsList($fundings),
        ];

        $pdf = Pdf::loadView('reports.impact-map-pdf', $data);
        $pdf->setPaper('a4', 'landscape');

        return $pdf->download('attp-impact-report-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Download impact report as Excel/CSV.
     */
    public function downloadExcel(Request $request)
    {
        $fundings = $this->getFilteredFundings($request);

        $csvData = [];
        $csvData[] = ['Program Funding Impact Report - Generated: ' . now()->format('d M Y')];
        $csvData[] = [];

        // Summary row
        $summary = $this->getSummaryStatistics($fundings);
        $csvData[] = ['SUMMARY'];
        $csvData[] = ['Total Funding', 'USD ' . number_format($summary['total_funding'], 2)];
        $csvData[] = ['Total Programs', $summary['total_programs']];
        $csvData[] = ['Funding Partners', $summary['total_partners']];
        $csvData[] = ['Countries Reached', $summary['total_countries']];
        $csvData[] = ['Regional Blocks', $summary['total_regions']];
        $csvData[] = [];

        // Program details
        $csvData[] = ['PROGRAM DETAILS'];
        $csvData[] = ['Program Name', 'Funder', 'Amount (USD)', 'Currency', 'Period', 'Type', 'Countries', 'Regions', 'Aspirations'];

        foreach ($fundings as $funding) {
            $countries = $funding->is_continental_initiative
                ? 'All AU Member States'
                : $funding->memberStates->pluck('name')->implode(', ');

            $csvData[] = [
                $funding->program_name,
                optional($funding->funder)->name ?? 'N/A',
                $funding->approved_amount,
                $funding->currency,
                $funding->start_year . '-' . $funding->end_year,
                ucfirst($funding->funding_type),
                $countries,
                $funding->regionalBlocks->pluck('abbreviation')->implode(', '),
                $funding->aspirations->pluck('number')->map(fn($n) => "Asp. $n")->implode(', '),
            ];
        }

        // Generate CSV
        $filename = 'attp-impact-report-' . now()->format('Y-m-d') . '.csv';
        $handle = fopen('php://temp', 'r+');

        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }

    /**
     * Get summary statistics.
     */
    private function getSummaryStatistics($fundings)
    {
        $totalFunding = $fundings->sum('approved_amount');
        $totalPrograms = $fundings->count();

        // Get unique funders
        $partnerIds = $fundings->pluck('funder_id')->unique()->filter();
        $totalPartners = $partnerIds->count();

        // Get unique countries (including continental initiatives)
        $countriesFromFundings = collect();
        $continentalCount = 0;

        foreach ($fundings as $funding) {
            if ($funding->is_continental_initiative) {
                $continentalCount++;
            } else {
                $countriesFromFundings = $countriesFromFundings->merge($funding->memberStates->pluck('id'));
            }
        }

        $uniqueCountries = $countriesFromFundings->unique()->count();
        // If any continental initiative exists, count all 55 member states
        $totalCountries = $continentalCount > 0 ? 55 : $uniqueCountries;

        // Get unique regions
        $regionIds = collect();
        foreach ($fundings as $funding) {
            $regionIds = $regionIds->merge($funding->regionalBlocks->pluck('id'));
        }
        $totalRegions = $regionIds->unique()->count();

        // Get unique aspirations
        $aspirationIds = collect();
        foreach ($fundings as $funding) {
            $aspirationIds = $aspirationIds->merge($funding->aspirations->pluck('id'));
        }
        $totalAspirations = $aspirationIds->unique()->count();

        // Get unique goals
        $goalIds = collect();
        foreach ($fundings as $funding) {
            $goalIds = $goalIds->merge($funding->goals->pluck('id'));
        }
        $totalGoals = $goalIds->unique()->count();

        // Continental vs targeted
        $continentalPrograms = $fundings->where('is_continental_initiative', true)->count();
        $targetedPrograms = $totalPrograms - $continentalPrograms;

        // By funding type
        $byType = $fundings->groupBy('funding_type')->map->sum('approved_amount');

        return [
            'total_funding' => $totalFunding,
            'total_programs' => $totalPrograms,
            'total_partners' => $totalPartners,
            'total_countries' => $totalCountries,
            'total_regions' => $totalRegions,
            'total_aspirations' => $totalAspirations,
            'total_goals' => $totalGoals,
            'continental_programs' => $continentalPrograms,
            'targeted_programs' => $targetedPrograms,
            'by_funding_type' => $byType,
            'average_funding' => $totalPrograms > 0 ? $totalFunding / $totalPrograms : 0,
        ];
    }

    /**
     * Get all filter options.
     */
    private function getFilterOptions()
    {
        return [
            'funders' => Funder::select('id', 'name')->orderBy('name')->get(),
            'regions' => AuRegionalBlock::select('id', 'name', 'abbreviation')
                ->active()->ordered()->get(),
            'countries' => AuMemberState::select('id', 'name', 'code')
                ->active()->ordered()->get(),
            'aspirations' => AuAspiration::select('id', 'number', 'title')
                ->active()->ordered()->get(),
            'goals' => AuGoal::select('id', 'number', 'title', 'aspiration_id')
                ->with('aspiration:id,number')
                ->active()->ordered()->get(),
            'flagshipProjects' => AuFlagshipProject::select('id', 'number', 'name')
                ->active()->ordered()->get(),
            'years' => $this->getYearRange(),
        ];
    }

    /**
     * Get year range from funding data.
     */
    private function getYearRange()
    {
        $minYear = ProgramFunding::where('status', 'approved')->min('start_year') ?? now()->year;
        $maxYear = ProgramFunding::where('status', 'approved')->max('end_year') ?? now()->year;

        return [
            'min' => $minYear,
            'max' => $maxYear,
            'range' => range($minYear, $maxYear),
        ];
    }

    /**
     * Get funding aggregated by partner/funder.
     */
    private function getFundingByPartner($fundings)
    {
        $byPartner = $fundings->groupBy('funder_id');

        $result = [];
        foreach ($byPartner as $funderId => $partnerFundings) {
            $funder = $partnerFundings->first()->funder;
            if (!$funder) continue;

            $countries = collect();
            $regions = collect();
            $aspirations = collect();
            $continentalCount = 0;

            foreach ($partnerFundings as $f) {
                if ($f->is_continental_initiative) {
                    $continentalCount++;
                } else {
                    $countries = $countries->merge($f->memberStates->pluck('name'));
                }
                $regions = $regions->merge($f->regionalBlocks->pluck('abbreviation'));
                $aspirations = $aspirations->merge($f->aspirations->pluck('number'));
            }

            $result[] = [
                'id' => $funder->id,
                'name' => $funder->name,
                'logo' => $funder->logo ?? null,
                'total_funding' => $partnerFundings->sum('approved_amount'),
                'program_count' => $partnerFundings->count(),
                'countries' => $continentalCount > 0 ? ['All AU States'] : $countries->unique()->values()->toArray(),
                'country_count' => $continentalCount > 0 ? 55 : $countries->unique()->count(),
                'regions' => $regions->unique()->values()->toArray(),
                'aspirations' => $aspirations->unique()->sort()->values()->toArray(),
                'has_continental' => $continentalCount > 0,
            ];
        }

        // Sort by total funding descending
        usort($result, fn($a, $b) => $b['total_funding'] <=> $a['total_funding']);

        return $result;
    }

    /**
     * Get funding aggregated by region.
     */
    private function getFundingByRegion($fundings)
    {
        $allRegions = AuRegionalBlock::active()->ordered()->get();
        $result = [];

        foreach ($allRegions as $region) {
            $regionFundings = $fundings->filter(function ($f) use ($region) {
                return $f->regionalBlocks->contains('id', $region->id);
            });

            if ($regionFundings->isEmpty()) continue;

            $countries = collect();
            $partners = collect();

            foreach ($regionFundings as $f) {
                $countries = $countries->merge($f->memberStates->pluck('name'));
                if ($f->funder) {
                    $partners->push($f->funder->name);
                }
            }

            $result[] = [
                'id' => $region->id,
                'name' => $region->name,
                'abbreviation' => $region->abbreviation,
                'total_funding' => $regionFundings->sum('approved_amount'),
                'program_count' => $regionFundings->count(),
                'countries' => $countries->unique()->values()->toArray(),
                'country_count' => $countries->unique()->count(),
                'partners' => $partners->unique()->values()->toArray(),
                'partner_count' => $partners->unique()->count(),
            ];
        }

        usort($result, fn($a, $b) => $b['total_funding'] <=> $a['total_funding']);

        return $result;
    }

    /**
     * Get funding aggregated by country.
     */
    private function getFundingByCountry($fundings)
    {
        $allCountries = AuMemberState::active()->ordered()->get();
        $result = [];

        // Continental fundings apply to all countries
        $continentalFundings = $fundings->where('is_continental_initiative', true);
        $continentalAmount = $continentalFundings->sum('approved_amount');
        $continentalCount = $continentalFundings->count();

        foreach ($allCountries as $country) {
            $countryFundings = $fundings->filter(function ($f) use ($country) {
                return !$f->is_continental_initiative && $f->memberStates->contains('id', $country->id);
            });

            $directAmount = $countryFundings->sum('approved_amount');
            $directCount = $countryFundings->count();

            // Skip if no funding
            if ($directCount === 0 && $continentalCount === 0) continue;

            $regions = collect();
            $partners = collect();

            foreach ($countryFundings as $f) {
                $regions = $regions->merge($f->regionalBlocks->pluck('abbreviation'));
                if ($f->funder) {
                    $partners->push($f->funder->name);
                }
            }

            // Add continental partners
            foreach ($continentalFundings as $f) {
                if ($f->funder) {
                    $partners->push($f->funder->name);
                }
            }

            $result[] = [
                'id' => $country->id,
                'name' => $country->name,
                'code' => $country->code,
                'direct_funding' => $directAmount,
                'continental_funding' => $continentalAmount,
                'total_funding' => $directAmount + $continentalAmount,
                'direct_programs' => $directCount,
                'continental_programs' => $continentalCount,
                'total_programs' => $directCount + $continentalCount,
                'regions' => $regions->unique()->values()->toArray(),
                'partners' => $partners->unique()->values()->toArray(),
            ];
        }

        usort($result, fn($a, $b) => $b['total_funding'] <=> $a['total_funding']);

        return $result;
    }

    /**
     * Get funding aggregated by aspiration.
     */
    private function getFundingByAspiration($fundings)
    {
        $allAspirations = AuAspiration::active()->ordered()->get();
        $result = [];

        foreach ($allAspirations as $aspiration) {
            $aspirationFundings = $fundings->filter(function ($f) use ($aspiration) {
                return $f->aspirations->contains('id', $aspiration->id);
            });

            if ($aspirationFundings->isEmpty()) continue;

            $goals = collect();
            $partners = collect();

            foreach ($aspirationFundings as $f) {
                $goals = $goals->merge($f->goals->where('aspiration_id', $aspiration->id)->pluck('number'));
                if ($f->funder) {
                    $partners->push($f->funder->name);
                }
            }

            $result[] = [
                'id' => $aspiration->id,
                'number' => $aspiration->number,
                'title' => $aspiration->title,
                'description' => $aspiration->description,
                'total_funding' => $aspirationFundings->sum('approved_amount'),
                'program_count' => $aspirationFundings->count(),
                'goals' => $goals->unique()->sort()->values()->toArray(),
                'goal_count' => $goals->unique()->count(),
                'partners' => $partners->unique()->values()->toArray(),
            ];
        }

        usort($result, fn($a, $b) => $a['number'] <=> $b['number']);

        return $result;
    }

    /**
     * Get funding aggregated by goal.
     */
    private function getFundingByGoal($fundings)
    {
        $allGoals = AuGoal::with('aspiration')->active()->ordered()->get();
        $result = [];

        foreach ($allGoals as $goal) {
            $goalFundings = $fundings->filter(function ($f) use ($goal) {
                return $f->goals->contains('id', $goal->id);
            });

            if ($goalFundings->isEmpty()) continue;

            $result[] = [
                'id' => $goal->id,
                'number' => $goal->number,
                'title' => $goal->title,
                'aspiration_number' => optional($goal->aspiration)->number,
                'total_funding' => $goalFundings->sum('approved_amount'),
                'program_count' => $goalFundings->count(),
            ];
        }

        usort($result, fn($a, $b) => $b['total_funding'] <=> $a['total_funding']);

        return $result;
    }

    /**
     * Get funding aggregated by flagship project.
     */
    private function getFundingByFlagship($fundings)
    {
        $allFlagships = AuFlagshipProject::active()->ordered()->get();
        $result = [];

        foreach ($allFlagships as $flagship) {
            $flagshipFundings = $fundings->filter(function ($f) use ($flagship) {
                return $f->flagshipProjects->contains('id', $flagship->id);
            });

            if ($flagshipFundings->isEmpty()) continue;

            $result[] = [
                'id' => $flagship->id,
                'number' => $flagship->number,
                'name' => $flagship->name,
                'description' => $flagship->description,
                'total_funding' => $flagshipFundings->sum('approved_amount'),
                'program_count' => $flagshipFundings->count(),
            ];
        }

        usort($result, fn($a, $b) => $b['total_funding'] <=> $a['total_funding']);

        return $result;
    }

    /**
     * Get trend data for charts.
     */
    private function getTrendData($fundings)
    {
        $byYear = [];

        foreach ($fundings as $funding) {
            for ($year = $funding->start_year; $year <= $funding->end_year; $year++) {
                if (!isset($byYear[$year])) {
                    $byYear[$year] = [
                        'year' => $year,
                        'funding' => 0,
                        'programs' => 0,
                    ];
                }
                // Distribute funding evenly across years
                $yearSpan = $funding->end_year - $funding->start_year + 1;
                $byYear[$year]['funding'] += $funding->approved_amount / $yearSpan;
                $byYear[$year]['programs']++;
            }
        }

        ksort($byYear);

        return array_values($byYear);
    }

    /**
     * Get country geo data for map visualization.
     */
    private function getCountryGeoData($fundings)
    {
        $countryData = $this->getFundingByCountry($fundings);

        // Create a lookup by country code
        $lookup = [];
        foreach ($countryData as $country) {
            $lookup[$country['code']] = $country;
        }

        return $lookup;
    }

    /**
     * Get programs list for display.
     */
    private function getProgramsList($fundings)
    {
        return $fundings->map(function ($f) {
            return [
                'id' => $f->id,
                'name' => $f->program_name,
                'funder' => optional($f->funder)->name,
                'amount' => $f->approved_amount,
                'currency' => $f->currency,
                'period' => $f->start_year . '-' . $f->end_year,
                'type' => ucfirst($f->funding_type),
                'is_continental' => $f->is_continental_initiative,
                'countries' => $f->is_continental_initiative
                    ? ['Continental Initiative']
                    : $f->memberStates->pluck('name')->toArray(),
                'regions' => $f->regionalBlocks->pluck('abbreviation')->toArray(),
                'aspirations' => $f->aspirations->map(fn($a) => [
                    'number' => $a->number,
                    'title' => $a->title,
                ])->toArray(),
                'goals' => $f->goals->map(fn($g) => [
                    'number' => $g->number,
                    'title' => $g->title,
                ])->toArray(),
            ];
        })->values()->toArray();
    }

    /**
     * Helper to get filtered fundings based on request.
     */
    private function getFilteredFundings(Request $request)
    {
        $query = ProgramFunding::with([
            'funder',
            'memberStates',
            'regionalBlocks',
            'aspirations',
            'goals',
            'flagshipProjects',
        ])
        ->where('status', 'approved');

        if ($request->has('funders') && !empty($request->funders)) {
            $funderIds = is_array($request->funders) ? $request->funders : explode(',', $request->funders);
            $query->whereIn('funder_id', $funderIds);
        }

        if ($request->has('regions') && !empty($request->regions)) {
            $regionIds = is_array($request->regions) ? $request->regions : explode(',', $request->regions);
            $query->whereHas('regionalBlocks', function ($q) use ($regionIds) {
                $q->whereIn('myb_au_regional_blocks.id', $regionIds);
            });
        }

        if ($request->has('countries') && !empty($request->countries)) {
            $countryIds = is_array($request->countries) ? $request->countries : explode(',', $request->countries);
            $query->where(function ($q) use ($countryIds) {
                $q->whereHas('memberStates', function ($sq) use ($countryIds) {
                    $sq->whereIn('myb_au_member_states.id', $countryIds);
                })
                ->orWhere('is_continental_initiative', true);
            });
        }

        // Filter by scope (continental vs targeted)
        if ($request->has('scope') && !empty($request->scope)) {
            $scopes = is_array($request->scope) ? $request->scope : explode(',', $request->scope);

            if (count($scopes) === 1) {
                if (in_array('continental', $scopes)) {
                    // Only continental initiatives
                    $query->where('is_continental_initiative', true);
                } elseif (in_array('targeted', $scopes)) {
                    // Only targeted programs (not continental)
                    $query->where(function ($q) {
                        $q->where('is_continental_initiative', false)
                          ->orWhereNull('is_continental_initiative');
                    });
                }
            }
            // If both scopes are selected, don't filter (show all)
        }

        return $query->get();
    }

    /**
     * Build treaty status data for the impact map treaties tab.
     */
    private function getTreatiesData(): array
    {
        if (!Schema::hasTable('myb_treaties') || !Schema::hasTable('myb_treaty_member_state_statuses')) {
            return [];
        }

        return Treaty::query()
            ->whereIn('status', ['active', 'draft'])
            ->with(['memberStateStatuses.memberState'])
            ->orderByDesc('adoption_date')
            ->orderBy('title')
            ->get()
            ->map(function ($treaty) {
                $statusRows = $treaty->memberStateStatuses
                    ->filter(fn($row) => $row->memberState)
                    ->map(function ($row) {
                        $codeAlpha2 = strtoupper((string) ($row->memberState->code_alpha2 ?? ''));
                        $codeFallback = strtoupper((string) ($row->memberState->code ?? ''));
                        $countryCode = $codeAlpha2 !== '' ? $codeAlpha2 : $codeFallback;

                        return [
                            'country_code' => $countryCode,
                            'country_name' => $row->memberState->name,
                            'is_signed' => (bool) $row->is_signed,
                            'is_ratified' => (bool) $row->is_ratified,
                            'is_original_submitted' => (bool) $row->is_original_submitted,
                            'signed_at' => optional($row->signed_at)->toDateString(),
                            'ratified_at' => optional($row->ratified_at)->toDateString(),
                            'original_submitted_at' => optional($row->original_submitted_at)->toDateString(),
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'id' => $treaty->id,
                    'title' => $treaty->title,
                    'short_title' => $treaty->short_title,
                    'reference_code' => $treaty->reference_code,
                    'status' => $treaty->status,
                    'adoption_date' => optional($treaty->adoption_date)->toDateString(),
                    'entry_into_force_date' => optional($treaty->entry_into_force_date)->toDateString(),
                    'description' => $treaty->description,
                    'overview' => $treaty->overview,
                    'key_provisions' => $treaty->key_provisions,
                    'implementation_framework' => $treaty->implementation_framework,
                    'monitoring_and_reporting' => $treaty->monitoring_and_reporting,
                    'read_more_url' => $treaty->read_more_url,
                    'signed_count' => collect($statusRows)->where('is_signed', true)->count(),
                    'ratified_count' => collect($statusRows)->where('is_ratified', true)->count(),
                    'original_submitted_count' => collect($statusRows)->where('is_original_submitted', true)->count(),
                    'statuses' => $statusRows,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Get available Africa shapefiles from public assets.
     */
    private function getAfricaShapeFiles(): array
    {
        $africaPath = public_path('assets/Africa');

        if (!File::exists($africaPath)) {
            return [];
        }

        $host = app()->bound('request')
            ? rtrim(request()->getSchemeAndHttpHost(), '/')
            : rtrim((string) config('app.url'), '/');
        $baseUrl = app()->bound('request') ? rtrim(request()->getBaseUrl(), '/') : '';
        $assetPathPrefix = $host . ($baseUrl !== '' ? $baseUrl : '') . '/assets/Africa/';

        return collect(File::files($africaPath))
            ->filter(function ($file) {
                return strtolower($file->getExtension()) === 'shp';
            })
            ->sortBy(function ($file) {
                return $file->getFilename();
            })
            ->map(function ($file) use ($assetPathPrefix) {
                return $assetPathPrefix . rawurlencode($file->getFilename());
            })
            ->values()
            ->all();
    }
}
