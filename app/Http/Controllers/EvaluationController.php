<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use Illuminate\Http\Request;

class EvaluationController extends Controller
{
    /**
     * Display a listing of evaluations
     */
    public function index()
    {
        $evaluations = Evaluation::withCount('sections')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('evaluations.index', compact('evaluations'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        return view('evaluations.create');
    }

    /**
     * Store evaluation
     */
    public function store(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'type' => 'required|in:services,goods',
    ]);

    Evaluation::create([
        'name'        => $request->name,
        'description' => $request->description,
        'type'        => $request->type, // ✅ IMPORTANT
        'status'      => 'draft',
        'created_by'  => auth()->id(),
    ]);

    return redirect()
        ->route('evals.cfg.index')
        ->with('success', 'Evaluation created successfully.');
}


    /**
     * Show evaluation builder
     */
    public function show(Evaluation $evaluation)
    {
        $evaluation->load('sections.criteria');

        return view('evaluations.show', compact('evaluation'));
    }

    /**
     * Edit evaluation
     */
    public function edit(Evaluation $evaluation)
    {
        return view('evaluations.edit', compact('evaluation'));
    }

    /**
     * Update evaluation
     */
    public function update(Request $request, Evaluation $evaluation)
{
    // ===============================
    // STATUS UPDATE (FROM INDEX)
    // ===============================
    if ($request->has('status')) {

        $allowedStatuses = ['draft', 'active', 'close'];

        if (!in_array($request->status, $allowedStatuses)) {
            return back()->with('error', 'Invalid evaluation status.');
        }

        // Prevent reopening a closed evaluation
        if ($evaluation->status === 'close') {
            return back()->with('error', 'Closed evaluations cannot be modified.');
        }

        $evaluation->update([
            'status' => $request->status
        ]);

        return back()->with('success', 'Evaluation status updated successfully.');
    }

    // ===============================
    // FULL UPDATE (EDIT PAGE)
    // ===============================
    $request->validate([
        'name'        => 'required|string|max:255',
        'description' => 'nullable|string',
    ]);

    if ($evaluation->status !== 'draft') {
        return back()->with('error', 'Only draft evaluations can be edited.');
    }

    $evaluation->update(
        $request->only('name', 'description')
    );

    return redirect()
        ->route('evals.cfg.index')
        ->with('success', 'Evaluation updated successfully.');
}



    /**
     * Delete evaluation
     */
    public function destroy(Evaluation $evaluation)
    {
        if ($evaluation->status !== 'draft') {
            return back()->with('error', 'Only draft evaluations can be deleted.');
        }

        $evaluation->delete();

        return redirect()
            ->route('evals.cfg.index')
            ->with('success', 'Evaluation deleted successfully.');
    }
}
