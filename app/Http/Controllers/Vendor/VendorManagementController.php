<?php

namespace App\Http\Controllers\Vendor;

use App\Exports\VendorTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\VendorImport;
use App\Mail\VendorAccountCreated;
use App\Models\Program;
use App\Models\SubActivity;
use App\Models\User;
use App\Models\VendorCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;
use Throwable;

class VendorManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner', 'permission:vendor.manage']);
    }

    public function index()
    {
        $vendors = User::where('user_type', 'vendor')
            ->withCount('vendorSubActivityAssignments')
            ->orderByDesc('created_at')
            ->get();

        return view('vendor.admin.index', compact('vendors'));
    }

    public function create()
    {
        return view('system.users.create', [
            'roles' => collect(),
            'nodes' => collect(),
            'memberStates' => collect(),
            'vendorCategories' => VendorCategory::where('is_active', true)
                ->orderBy('name')
                ->pluck('name'),
            'defaultUserType' => 'vendor',
            'vendorCreateOnly' => true,
            'formAction' => route('vendors.store'),
            'cancelRoute' => route('vendors.index'),
            'pageTitle' => 'Create Vendor',
            'pageSubtitle' => 'Create a vendor portal account without assigning a system role.',
            'backButtonText' => 'Back to Vendors',
            'submitButtonText' => 'Create Vendor',
            'vendorFundingPrograms' => $this->vendorFundingPrograms(),
            'vendorFundingAssignments' => collect(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'vendor_category' => [
                'nullable',
                'string',
                'max:255',
                Rule::exists('vendor_categories', 'name')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'convert_existing_vendor' => ['nullable', 'boolean'],
            ...$this->vendorAssignmentValidationRules(),
        ]);
        $assignmentRows = $this->vendorAssignmentRows($request);

        $existingUser = $this->findUserByEmail($validated['email']);

        if ($existingUser) {
            if ($existingUser->user_type === 'vendor') {
                return back()
                    ->withErrors(['email' => 'This email address already belongs to a vendor account.'])
                    ->withInput();
            }

            if ((string) $existingUser->id === (string) $request->user()?->id) {
                return back()
                    ->withErrors(['email' => 'You cannot convert your own back-office account into a vendor account.'])
                    ->withInput();
            }

            if ($existingUser->role && $existingUser->role->name === 'Super Admin') {
                return back()
                    ->withErrors(['email' => 'Super Admin accounts cannot be converted into vendor accounts.'])
                    ->withInput();
            }

            if (! $request->boolean('convert_existing_vendor')) {
                return back()
                    ->withInput()
                    ->with('vendor_conversion_prompt', $this->vendorConversionPromptData($existingUser));
            }

            $existingUser->update([
                'name' => $validated['name'],
                'user_type' => 'vendor',
                'role_id' => null,
                'governance_node_id' => null,
                'member_state_id' => null,
                'vendor_category' => $validated['vendor_category'] ?? null,
            ]);
            $this->syncVendorAssignments($existingUser, $assignmentRows);

            return redirect()
                ->route('vendors.index')
                ->with('success', 'Existing back-office user converted to a vendor account successfully. The user can sign in with their existing password.');
        }

        $plainPassword = str()->random(12);

        $vendor = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($plainPassword),
            'user_type' => 'vendor',
            'role_id' => null,
            'governance_node_id' => null,
            'member_state_id' => null,
            'vendor_category' => $validated['vendor_category'] ?? null,
            'must_change_password' => true,
        ]);
        $this->syncVendorAssignments($vendor, $assignmentRows);

        $mailSent = $this->sendVendorMailSafely($vendor, $plainPassword);

        return redirect()
            ->route('vendors.index')
            ->with('success', $mailSent
                ? 'Vendor account created successfully.'
                : "Vendor account created successfully, but email delivery failed. Temporary password: {$plainPassword}");
    }

    public function template()
    {
        return Excel::download(new VendorTemplateExport(), 'vendor_upload_template.xlsx');
    }

    public function edit(User $vendor)
    {
        $this->assertVendor($vendor);
        $vendor->load('vendorSubActivityAssignments.subActivity.activity.project.program');

        $categories = \App\Models\VendorCategory::where('is_active', true)
            ->orderBy('name')
            ->pluck('name');
        $vendorFundingPrograms = $this->vendorFundingPrograms();
        $vendorFundingAssignments = $this->vendorFundingAssignmentsForForm($vendor);

        return view('vendor.admin.edit', compact(
            'vendor',
            'categories',
            'vendorFundingPrograms',
            'vendorFundingAssignments'
        ));
    }

    public function update(Request $request, User $vendor)
    {
        $this->assertVendor($vendor);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $vendor->id,
            'vendor_category' => 'nullable|string|max:255',
            ...$this->vendorAssignmentValidationRules(),
        ]);
        $assignmentRows = $this->vendorAssignmentRows($request);

        if (!empty($validated['vendor_category'])) {
            $exists = \App\Models\VendorCategory::where('name', $validated['vendor_category'])->exists();
            if (!$exists) {
                return back()->withErrors([
                    'vendor_category' => 'Selected vendor category does not exist.',
                ])->withInput();
            }
        }

        $vendor->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'vendor_category' => !empty($validated['vendor_category']) ? $validated['vendor_category'] : null,
        ]);
        $this->syncVendorAssignments($vendor, $assignmentRows);

        return redirect()
            ->route('vendors.index')
            ->with('success', 'Vendor updated successfully.');
    }

    public function show(User $vendor)
    {
        $this->assertVendor($vendor);
        $vendor->load([
            'vendorSubActivityAssignments.program',
            'vendorSubActivityAssignments.project',
            'vendorSubActivityAssignments.activity',
            'vendorSubActivityAssignments.subActivity.activity.project.program',
        ]);

        return view('vendor.admin.show', compact('vendor'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $import = new VendorImport();

        try {
            Excel::import($import, $request->file('file'));
        } catch (ExcelValidationException $exception) {
            $errors = collect($exception->failures())->flatMap(function ($failure) {
                return collect($failure->errors())->map(fn ($error) => sprintf('Row %s: %s', $failure->row(), $error));
            })->all();

            return back()
                ->with('import_errors', $errors)
                ->with('error', 'Some rows failed validation. See the list below for details.');
        }

        $summary = $import->summary();

        return back()
            ->with('success', "Vendor upload completed. {$summary['created']} vendor accounts created.")
            ->with('import_duplicates', $summary['duplicates'])
            ->with('import_mail_failures', $summary['mail_failures']);
    }

    public function disable(Request $request, User $vendor)
    {
        $this->assertVendor($vendor);

        $vendor->update([
            'is_disabled' => true,
            'disabled_at' => now(),
            'disabled_reason' => $request->input('reason'),
        ]);

        return back()->with('success', 'Vendor access disabled.');
    }

    public function enable(User $vendor)
    {
        $this->assertVendor($vendor);

        $vendor->update([
            'is_disabled' => false,
            'disabled_at' => null,
            'disabled_reason' => null,
        ]);

        return back()->with('success', 'Vendor access restored.');
    }

    public function blacklist(Request $request, User $vendor)
    {
        $this->assertVendor($vendor);

        $vendor->update([
            'is_blacklisted' => true,
            'blacklisted_at' => now(),
            'blacklisted_reason' => $request->input('reason'),
        ]);

        return back()->with('success', 'Vendor has been blacklisted.');
    }

    public function unblacklist(User $vendor)
    {
        $this->assertVendor($vendor);

        $vendor->update([
            'is_blacklisted' => false,
            'blacklisted_at' => null,
            'blacklisted_reason' => null,
        ]);

        return back()->with('success', 'Vendor removed from blacklist.');
    }

    private function assertVendor(User $vendor): void
    {
        if ($vendor->user_type !== 'vendor') {
            abort(404);
        }
    }

    private function vendorAssignmentValidationRules(): array
    {
        return [
            'assignments' => ['nullable', 'array'],
            'assignments.*.program_id' => ['nullable', 'uuid', Rule::exists('myb_programs', 'id')],
            'assignments.*.project_id' => ['nullable', 'uuid', Rule::exists('myb_projects', 'id')],
            'assignments.*.activity_id' => ['nullable', 'uuid', Rule::exists('myb_activities', 'id')],
            'assignments.*.sub_activity_id' => ['nullable', 'uuid', Rule::exists('myb_sub_activities', 'id')],
        ];
    }

    private function vendorAssignmentRows(Request $request): array
    {
        $rows = collect($request->input('assignments', []))
            ->filter(fn ($row) => filled($row['sub_activity_id'] ?? null))
            ->values();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'assignments' => 'Select at least one funding source for this vendor.',
            ]);
        }

        $subActivities = SubActivity::with('activity.project')
            ->whereIn('id', $rows->pluck('sub_activity_id')->unique()->all())
            ->get()
            ->keyBy('id');
        $normalized = [];

        foreach ($rows as $index => $row) {
            $subActivity = $subActivities->get($row['sub_activity_id']);
            $activity = $subActivity?->activity;
            $project = $activity?->project;
            $programId = $project?->program_id;

            if (! $subActivity || ! $activity || ! $project || ! $programId) {
                throw ValidationException::withMessages([
                    "assignments.{$index}.sub_activity_id" => 'The selected funding source is incomplete. Please choose another source.',
                ]);
            }

            if (filled($row['activity_id'] ?? null) && (string) $row['activity_id'] !== (string) $activity->id) {
                throw ValidationException::withMessages([
                    "assignments.{$index}.activity_id" => 'The selected activity does not match the funding source.',
                ]);
            }

            if (filled($row['project_id'] ?? null) && (string) $row['project_id'] !== (string) $project->id) {
                throw ValidationException::withMessages([
                    "assignments.{$index}.project_id" => 'The selected project does not match the funding source.',
                ]);
            }

            if (filled($row['program_id'] ?? null) && (string) $row['program_id'] !== (string) $programId) {
                throw ValidationException::withMessages([
                    "assignments.{$index}.program_id" => 'The selected program does not match the funding source.',
                ]);
            }

            $normalized[$subActivity->id] = [
                'program_id' => $programId,
                'project_id' => $project->id,
                'activity_id' => $activity->id,
                'sub_activity_id' => $subActivity->id,
            ];
        }

        return array_values($normalized);
    }

    private function syncVendorAssignments(User $vendor, array $assignmentRows): void
    {
        $vendor->vendorSubActivityAssignments()->delete();
        $vendor->vendorSubActivityAssignments()->createMany($assignmentRows);
    }

    private function vendorFundingPrograms()
    {
        return Program::with([
            'projects' => fn ($query) => $query->orderBy('name'),
            'projects.activities' => fn ($query) => $query->orderBy('name'),
            'projects.activities.subActivities' => fn ($query) => $query->orderBy('name'),
        ])
            ->whereHas('projects.activities.subActivities')
            ->orderBy('name')
            ->get();
    }

    private function vendorFundingAssignmentsForForm(User $vendor)
    {
        return $vendor->vendorSubActivityAssignments
            ->map(function ($assignment) {
                $subActivity = $assignment->subActivity;
                $activity = $assignment->activity ?: $subActivity?->activity;
                $project = $assignment->project ?: $activity?->project;
                $program = $assignment->program ?: $project?->program;

                return [
                    'program_id' => $program?->id,
                    'project_id' => $project?->id,
                    'activity_id' => $activity?->id,
                    'sub_activity_id' => $subActivity?->id,
                ];
            })
            ->filter(fn ($assignment) => filled($assignment['sub_activity_id'] ?? null))
            ->values();
    }

    private function findUserByEmail(string $email): ?User
    {
        return User::with('role')
            ->whereRaw('LOWER(email) = ?', [Str::lower($email)])
            ->first();
    }

    private function vendorConversionPromptData(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'user_type' => ucfirst(str_replace('_', ' ', (string) $user->user_type)),
            'role' => $user->role?->name,
        ];
    }

    private function sendVendorMailSafely(User $vendor, string $plainPassword): bool
    {
        try {
            Mail::to($vendor->email)->send(new VendorAccountCreated($vendor, $plainPassword));

            return true;
        } catch (Throwable $exception) {
            Log::warning('Vendor account created email could not be sent.', [
                'vendor_id' => $vendor->id,
                'email' => $vendor->email,
                'mailer' => config('mail.default'),
                'error' => $exception->getMessage(),
            ]);

            if (app()->environment(['local', 'testing'])) {
                Log::info('Local development temporary vendor password fallback.', [
                    'vendor_id' => $vendor->id,
                    'email' => $vendor->email,
                    'temporary_password' => $plainPassword,
                ]);
            }

            return false;
        }
    }
}
