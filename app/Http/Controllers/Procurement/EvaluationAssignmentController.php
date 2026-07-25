<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\EvaluationAssignment;
use App\Support\ProcurementReviewAssignees;
use Illuminate\Http\Request;

class EvaluationAssignmentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'procurement_id' => 'required',
            'form_id' => 'required',
            'user_id' => [
                'required',
                'uuid',
                ProcurementReviewAssignees::existsRule(),
            ],
            'stage' => 'required',
        ], [
            'user_id.exists' => ProcurementReviewAssignees::INELIGIBLE_MESSAGE,
        ]);

        EvaluationAssignment::create(
            $validated + [
                'assigned_by' => auth()->id(),
                'assigned_at' => now(),
            ]
        );

        return back()->with('success', 'Evaluator assigned');
    }
}
