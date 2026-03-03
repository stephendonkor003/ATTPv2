<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Applicant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;


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

        return view('dashboard', compact(
            'hasDashboardAccess',
            'totalApplicants',
            'reviewedApplicants',
            'countriesCount',
            'applicationDates',
            'applicationCounts'
        ));
    }


}
