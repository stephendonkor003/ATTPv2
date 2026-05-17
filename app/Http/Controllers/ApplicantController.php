<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Mail\ApplicantSubmissionReceived;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Models\ThinkDataset;

class ApplicantController extends Controller
{
    // public function index()
    // {


    //     $applicants = Applicant::latest()->paginate(10);
    //     return view('applicants.index', compact('applicants'));
    // }


    public function index()
    {
        $user = Auth::user();

        $query = Applicant::query()
            ->select('*')
            // Compute covered countries count in SQL for ordering, without loading the entire dataset.
            ->selectRaw("CASE WHEN covered_countries IS NULL OR covered_countries = '' THEN 0 ELSE jsonb_array_length(covered_countries::jsonb) END as covered_count")
            ->orderByDesc('covered_count')
            ->orderByDesc('created_at');

        if ($user->user_type === 'admin') {
            // no-op (see all)
        } elseif ($user->user_type === 'applicant') {
            $query->where('code', $user->name);
        } else {
            abort(403, 'Unauthorized access.');
        }

        $applicants = $query->paginate(20);

        // Add a readable list for tooltips/views.
        $applicants->getCollection()->transform(function ($applicant) {
            $countries = $applicant->covered_countries ? json_decode($applicant->covered_countries, true) : [];
            $applicant->covered_list = is_array($countries) ? implode(', ', $countries) : '';
            return $applicant;
        });

        return view('applicants.index', [
            'applicants' => $applicants,
        ]);
    }



    // public function show(Applicant $applicant)
    // {
    //     return view('applicants.show', compact('applicant'));
    // }

    public function show(Applicant $applicant)
    {
        $this->authorizeApplicantAccess($applicant);

        $countries = $applicant->covered_countries ? json_decode($applicant->covered_countries, true) : [];
        $applicant->covered_count = is_array($countries) ? count($countries) : 0;
        $applicant->covered_list  = is_array($countries) ? implode(', ', $countries) : '';

        return view('applicants.show', compact('applicant'));
    }

    public function edit(Applicant $applicant)
    {
        $this->authorizeApplicantAccess($applicant);
        return view('applicants.edit', compact('applicant'));
    }

    public function update(Request $request, Applicant $applicant)
    {
        $this->authorizeApplicantAccess($applicant);

        $validated = $request->validate([
            'think_tank_name' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'sub_region' => 'nullable|array',
            'focus_areas' => 'nullable|array',
            'email' => 'nullable|email|max:255',

            'consortium_name' => 'nullable|string|max:255',
            'members_names' => 'nullable|string|max:6000',
            'lead_think_tank_name' => 'nullable|string|max:255',
            'lead_think_tank_country' => 'nullable|string|max:255',
            'consortium_region' => 'nullable|string|max:255',
            'covered_countries' => 'nullable|array',

            'application_form' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'legal_registration' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'trustees_formation' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'audited_reports' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'commitment_letter' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'work_plan_budget' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'cv_coordinator' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'cv_deputy' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'cv_team_members' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'past_research' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ]);

        $data = $validated;

        // Handle file uploads
        foreach ([
            'application_form', 'legal_registration', 'trustees_formation', 'audited_reports',
            'commitment_letter', 'work_plan_budget', 'cv_coordinator', 'cv_deputy',
            'cv_team_members', 'past_research'
        ] as $field) {
            if ($request->hasFile($field)) {
                // Store on the default (private) disk. Documents must be served via authorized routes.
                $data[$field] = $request->file($field)->store('documents');
            }
        }

        // JSON encode array input
        if ($request->has('sub_region')) {
            $data['sub_region'] = json_encode($request->input('sub_region'));
        }

        if ($request->has('focus_areas')) {
            $data['focus_areas'] = json_encode($request->input('focus_areas'));
        }

        if ($request->has('covered_countries')) {
            $data['covered_countries'] = json_encode($request->input('covered_countries'));
        }

        $applicant->update($data);

        return redirect()->route('applicants.show', $applicant->id)->with('success', 'Submission updated successfully.');
    }


    public function create()
    {
        $thinkTanks = ThinkDataset::distinct()
            ->whereNotNull('tt_name_en')
            ->orderBy('tt_name_en')
            ->pluck('tt_name_en');

        return view('applicants.create', compact('thinkTanks'));

    }

    public function faq()
    {
        return view('applicants.faq');

    }
    public function events()
    {
        return view('events');

    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'think_tank_name' => 'required|string|max:255',
            'custom_think_tank' => 'nullable|string|max:255',
            'country' => 'required|string|max:255',
            'sub_region' => 'nullable|array',
            'focus_areas' => 'nullable|array',
            'email' => 'required|email|max:255',

            'consortium_name' => 'nullable|string|max:255',
            'members_names' => 'nullable|string|max:6000',
            'lead_think_tank_name' => 'nullable|string|max:255',
            'lead_think_tank_country' => 'nullable|string|max:255',
            'consortium_region' => 'nullable|string|max:255',
            'covered_countries' => 'nullable|array',

            'application_form' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'legal_registration' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'trustees_formation' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'audited_reports' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'commitment_letter' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'work_plan_budget' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'cv_coordinator' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'cv_deputy' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'cv_team_members' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'past_research' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ]);

        $data = $validated;

        // Handle think_tank_name logic
        $selectedTank = $request->input('think_tank_name');
        $customTank = $request->input('custom_think_tank');
        $data['think_tank_name'] = $selectedTank === 'Other' && $customTank ? $customTank : $selectedTank;

        // Encode multi-select arrays
        $data['sub_region'] = $request->has('sub_region') ? json_encode($request->input('sub_region')) : null;
        $data['covered_countries'] = $request->has('covered_countries') ? json_encode($request->input('covered_countries')) : null;
        $data['focus_areas'] = $request->has('focus_areas') ? json_encode($request->input('focus_areas')) : null;

        // Handle file uploads
        foreach ([
            'application_form', 'legal_registration', 'trustees_formation',
            'audited_reports', 'commitment_letter', 'work_plan_budget',
            'cv_coordinator', 'cv_deputy', 'cv_team_members', 'past_research'
        ] as $fileField) {
            if ($request->hasFile($fileField)) {
                // Store on the default (private) disk. Documents must be served via authorized routes.
                $data[$fileField] = $request->file($fileField)->store('applicants');
            }
        }

        try {
            // Generate unique applicant code
            do {
                $uniqueCode = 'AUC-TK-2-' . random_int(100000, 999999);
            } while (Applicant::where('code', $uniqueCode)->exists());
            $data['code'] = $uniqueCode;

            // Save applicant
            $applicant = Applicant::create($data);

            // Create user account
            $defaultPassword = Str::random(8);

            try {
                $user = User::create([
                    'name' => $uniqueCode,
                    'email' => $applicant->email,
                    'password' => Hash::make($defaultPassword),
                    'user_type' => 'applicant',
                    'must_change_password' => true,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Check if it's a duplicate email error
                if (($e->errorInfo[0] ?? null) === '23505') {
                    $applicant->delete(); // Rollback applicant record
                    return redirect()->back()->withErrors(['error' => 'A Think Tank with this email already exists. Please try again later.']);
                }

                return redirect()->back()->withErrors(['error' => 'An unexpected database error occurred.']);
            }

            // Send confirmation email
            Mail::to($applicant->email)->queue(new ApplicantSubmissionReceived($applicant, $uniqueCode, $defaultPassword));

            return redirect()->back()->with('success', 'Application submitted successfully. Login credentials have been sent to your email.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to save application. Please try again later.']);
        }
    }


    public function destroy(Applicant $applicant)
    {
        $this->authorizeApplicantAccess($applicant);
        $applicant->delete();
        return redirect()->route('applicants.index')->with('success', 'Applicant deleted.');
    }

    /**
     * Serve an applicant document securely from the private disk.
     *
     * Note: The project historically stored files on the public disk. To avoid breaking
     * old records (and to remove public exposure), we migrate the file to the private
     * disk on first access when possible.
     */
    public function downloadDocument(Request $request, Applicant $applicant, string $field)
    {
        $this->authorizeApplicantDocumentAccess($applicant);

        $allowedFields = [
            'application_form',
            'legal_registration',
            'trustees_formation',
            'audited_reports',
            'commitment_letter',
            'work_plan_budget',
            'cv_coordinator',
            'cv_deputy',
            'cv_team_members',
            'past_research',
        ];

        abort_unless(in_array($field, $allowedFields, true), 404);

        $path = (string) ($applicant->getAttribute($field) ?? '');
        abort_if($path === '', 404, 'Document not found.');

        $privateDisk = Storage::disk('local');

        if (! $privateDisk->exists($path) && Storage::disk('public')->exists($path)) {
            // Best-effort migration from public -> private.
            $stream = Storage::disk('public')->readStream($path);
            if ($stream !== false) {
                $privateDisk->writeStream($path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                Storage::disk('public')->delete($path);
            }
        }

        abort_unless($privateDisk->exists($path), 404, 'Document file missing on disk.');

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($request->boolean('download')) {
            return $privateDisk->download($path, basename($path), $headers);
        }

        return $privateDisk->response($path, null, $headers);
    }

    protected function authorizeApplicantAccess(Applicant $applicant): void
    {
        $user = Auth::user();

        if (!$user) {
            abort(403, 'Unauthenticated.');
        }

        if ($user->user_type === 'admin') {
            return;
        }

        if ($user->user_type === 'applicant' && $applicant->code === $user->name) {
            return;
        }

        abort(403, 'Unauthorized access.');
    }

    protected function authorizeApplicantDocumentAccess(Applicant $applicant): void
    {
        $user = Auth::user();

        if (!$user) {
            abort(403, 'Unauthenticated.');
        }

        // Admins can always access.
        if ($user->user_type === 'admin') {
            return;
        }

        // Applicants can access their own submission documents.
        if ($user->user_type === 'applicant' && $applicant->code === $user->name) {
            return;
        }

        // Internal staff access: allow only for specific modules that legitimately
        // need to read applicant documents (e.g., prescreening and finance reviews).
        $staffPermissions = [
            'finance.access',
            'prescreening.access',
            'prescreening.evaluate',
            'prescreening.view_all',
            'prescreening.reports.view_all',
            'evaluations.manage',
            'evaluations.view_all',
        ];

        foreach ($staffPermissions as $perm) {
            if ($user->can($perm)) {
                return;
            }
        }

        abort(403, 'Unauthorized access.');
    }
}
