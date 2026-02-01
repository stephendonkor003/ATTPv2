<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\ProcurementProgramPlan;
use Illuminate\Http\Request;

class ProcurementProgramPlanController extends Controller
{
    public function index()
    {
        $plans = ProcurementProgramPlan::with('creator')
            ->withCount('procurements')
            ->orderByDesc('created_at')
            ->get();

        return view('procurement.structure.plans.index', compact('plans'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:myb_procurement_program_plans,name',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $validated['created_by'] = auth()->id();

        ProcurementProgramPlan::create($validated);

        return redirect()->route('procurement.structure.index')
            ->with('success', 'Program plan saved.');
    }
}
