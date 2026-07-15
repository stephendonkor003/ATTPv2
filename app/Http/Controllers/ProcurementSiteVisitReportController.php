<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesSiteVisitsToPortfolio;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\{
    Procurement,
    SiteVisit
};
use Illuminate\Support\Str;

class ProcurementSiteVisitReportController extends Controller
{
    use ScopesSiteVisitsToPortfolio;

    public function show(Procurement $procurement)
    {
        // Admin / oversight only
        if (!auth()->user()->can('site_visits.approve')) {
            abort(403, 'Unauthorized');
        }
        $this->assertProcurementInScope($procurement);

        $siteVisits = SiteVisit::with([
            'submission.values',
            'assignment.user',
            'group.leader',
            'group.members.user',
            'observations.media'
        ])
        ->where('procurement_id', $procurement->id)
        ->orderBy('visit_date')
        ->get();

        return view(
            'site-visits.reports.comprehensive', // ✅ UPDATED PATH
            compact('procurement', 'siteVisits')
        );
    }

    public function downloadVisit(Procurement $procurement, SiteVisit $siteVisit)
    {
        return $this->downloadVisitReport($procurement, $siteVisit);
    }

    public function downloadAnonymisedVisit(Procurement $procurement, SiteVisit $siteVisit)
    {
        return $this->downloadVisitReport($procurement, $siteVisit, true);
    }

    private function downloadVisitReport(Procurement $procurement, SiteVisit $siteVisit, bool $anonymised = false)
    {
        if (!auth()->user()->can('site_visits.approve')) {
            abort(403, 'Unauthorized');
        }

        $this->assertProcurementInScope($procurement);
        abort_unless($siteVisit->procurement_id === $procurement->id, 404);
        $this->assertSiteVisitInPortfolioScope($siteVisit);

        $siteVisit->load([
            'procurement',
            'submission.values',
            'assignment.user',
            'group.leader',
            'group.members.user',
            'observations.media',
            'approvals.reviewer',
        ]);

        $name = Str::slug($siteVisit->submission?->display_name ?: 'site-visit-report');
        $prefix = $anonymised ? 'site-visit-anonymised-' : 'site-visit-';
        $filename = $prefix . ($name ?: $siteVisit->id) . '.pdf';

        return Pdf::loadView('site-visits.reports.pdf-single', [
            'procurement' => $procurement,
            'siteVisit' => $siteVisit,
            'anonymised' => $anonymised,
            'platformName' => 'Africa Think Tank Platform',
            'platformUrl' => rtrim(config('app.url') ?: url('/'), '/'),
            'logoDataUri' => $this->logoDataUri(),
        ])
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    private function logoDataUri(): ?string
    {
        $path = public_path('admin/assets/images/logo-full.png');

        if (!is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
    }


    public function index()
{
    if (!auth()->user()->can('site_visits.approve')) {
        abort(403);
    }

    $procurementsQuery = \App\Models\Procurement::orderBy('title');
    $this->applyProcurementScope($procurementsQuery);
    $procurements = $procurementsQuery->get();

    return view(
        'site-visits.reports.index',
        compact('procurements')
    );
}

}
