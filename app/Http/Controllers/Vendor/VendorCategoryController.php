<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VendorCategory;
use Illuminate\Http\Request;

class VendorCategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner', 'permission:vendor.manage']);
    }

    public function index()
    {
        $categories = VendorCategory::orderBy('name')->get();

        return view('vendor.admin.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('vendor.admin.categories.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:vendor_categories,name',
            'description' => 'nullable|string',
            'is_active' => 'nullable',
        ]);

        VendorCategory::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->has('is_active'),
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('vendors.categories.index')
            ->with('success', 'Vendor category created successfully.');
    }

    public function edit(VendorCategory $category)
    {
        return view('vendor.admin.categories.edit', compact('category'));
    }

    public function update(Request $request, VendorCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:vendor_categories,name,' . $category->id,
            'description' => 'nullable|string',
            'is_active' => 'nullable',
        ]);

        $category->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()
            ->route('vendors.categories.index')
            ->with('success', 'Vendor category updated successfully.');
    }

    public function destroy(VendorCategory $category)
    {
        $inUse = User::where('user_type', 'vendor')
            ->where('vendor_category', $category->name)
            ->exists();

        if ($inUse) {
            return back()->with('error', 'Cannot delete category that is assigned to vendors.');
        }

        $category->delete();

        return redirect()
            ->route('vendors.categories.index')
            ->with('success', 'Vendor category deleted successfully.');
    }
}
