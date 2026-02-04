<?php

namespace App\Http\Controllers\Vendor;

use App\Exports\VendorTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\VendorImport;
use App\Models\User;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;

class VendorManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner', 'permission:vendor.manage']);
    }

    public function index()
    {
        $vendors = User::where('user_type', 'vendor')
            ->orderByDesc('created_at')
            ->get();

        return view('vendor.admin.index', compact('vendors'));
    }

    public function template()
    {
        return Excel::download(new VendorTemplateExport(), 'vendor_upload_template.xlsx');
    }

    public function edit(User $vendor)
    {
        $this->assertVendor($vendor);

        $categories = \App\Models\VendorCategory::where('is_active', true)
            ->orderBy('name')
            ->pluck('name');

        return view('vendor.admin.edit', compact('vendor', 'categories'));
    }

    public function update(Request $request, User $vendor)
    {
        $this->assertVendor($vendor);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $vendor->id,
            'vendor_category' => 'nullable|string|max:255',
        ]);

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

        return redirect()
            ->route('vendors.index')
            ->with('success', 'Vendor updated successfully.');
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
}
