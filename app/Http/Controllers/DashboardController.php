<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Applicant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;


class DashboardController extends Controller
{
    //





    public function index()
    {
        if (Auth::check() && Auth::user()->user_type === 'member_state') {
            return redirect()->route('member-state.dashboard');
        }

        $user = Auth::user();
        $hasDashboardAccess = $user?->can('dashboard.access') ?? false;

        // Keep payload light if the user cannot access dashboard modules.
        if (!$hasDashboardAccess) {
            return view('dashboard', [
                'hasDashboardAccess' => false,
                'totalApplicants' => 0,
                'reviewedApplicants' => 0,
                'countriesCount' => 0,
                'applicationDates' => collect(),
                'applicationCounts' => collect(),
            ]);
        }

        $totalApplicants = Applicant::count();

        // If you have another way to mark reviewed records, replace this with that logic
        $reviewedApplicants = 0;

        $countriesCount = Applicant::distinct('country')->count('country');

        $last7Days = Applicant::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total')
            )
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $applicationDates = $last7Days->pluck('date');
        $applicationCounts = $last7Days->pluck('total');
        $quickStats = [
            'consortia' => Schema::hasTable('attp_consortia') ? DB::table('attp_consortia')->count() : 0,
            'think_tanks' => Schema::hasTable('attp_consortium_think_tanks') ? DB::table('attp_consortium_think_tanks')->count() : 0,
            'published_news' => Schema::hasTable('attp_news_posts') ? DB::table('attp_news_posts')->where('status', 'published')->count() : 0,
            'open_procurements' => Schema::hasTable('procurements') ? DB::table('procurements')->where('status', 'published')->count() : 0,
        ];

        return view('dashboard', compact(
            'hasDashboardAccess',
            'totalApplicants',
            'reviewedApplicants',
            'countriesCount',
            'applicationDates',
            'applicationCounts',
            'quickStats'
        ));
    }


}
