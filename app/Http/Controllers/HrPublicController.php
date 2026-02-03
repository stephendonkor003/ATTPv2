<?php

namespace App\Http\Controllers;

use App\Mail\JobApplicationReceived;
use App\Models\HrApplicant;
use App\Models\HrVacancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class HrPublicController extends Controller
{
    /**
     * Public careers page
     */
    // public function index()
    // {
    //     $vacancies = HrVacancy::where('status', 'published')
    //         ->latest()
    //         ->get();

    //     return view('public.careers.index', compact('vacancies'));
    // }

    public function index()
{
    $vacancies = HrVacancy::with('position') // 👈 REQUIRED
        ->where('status', 'published')
        ->where('is_public', true) // optional but recommended
        ->latest()
        ->get();

    return view('public.careers.index', compact('vacancies'));
}


    /**
     * Store public job application
     */
    public function storeApplication(Request $request)
    {
        $validated = $request->validate([
            'vacancy_id'   => 'required|exists:hr_vacancies,id',
            'full_name'    => 'required|string|max:255',
            'email'        => 'required|email|max:255',
            'phone'        => 'required|string|max:50',
            'resume'       => 'required|file|mimes:pdf,doc,docx|max:5120',
            'cover_letter' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
        ]);

        // Fetch vacancy to inherit governance_node_id
        $vacancy = HrVacancy::findOrFail($validated['vacancy_id']);

        // Store applicant documents on the default (private) disk. These files must
        // only be accessible via authorized download endpoints.
        $resumePath = $request->file('resume')
            ->store('hr/applications/cv');

        $coverPath = $request->hasFile('cover_letter')
            ? $request->file('cover_letter')
                ->store('hr/applications/cover_letters')
            : null;

        HrApplicant::create([
            'vacancy_id'         => $validated['vacancy_id'],
            'governance_node_id' => $vacancy->governance_node_id,
            'full_name'          => $validated['full_name'],
            'email'              => $validated['email'],
            'phone'              => $validated['phone'],
            'cv_path'            => $resumePath,
            'cover_letter_path'  => $coverPath,
            'status'             => 'applied',
            'submitted_at'       => now(),
        ]);

        Mail::to($validated['email'])->send(new JobApplicationReceived(
            $validated['full_name'],
            $validated['email']
        ));

        return back()->with('success', 'Application submitted successfully.');
    }
}
