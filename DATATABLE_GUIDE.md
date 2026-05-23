# DataTable Implementation Guide

## Overview
This guide explains how to implement beautiful, feature-rich DataTables throughout the ATTP application. All tables now support:
- ✅ Export to Excel, PDF, CSV, Copy, and Print
- ✅ Beautiful gradient color schemes
- ✅ Responsive design
- ✅ Column sorting and filtering
- ✅ Search functionality
- ✅ Pagination
- ✅ Column visibility toggle
- ✅ RTL support for Arabic

---

## Quick Start

### Basic Implementation

The simplest way to create a DataTable:

```blade
<x-data-table id="myTable">
    <thead>
        <tr>
            <th>#</th>
            <th>Name</th>
            <th>Email</th>
            <th class="no-sort no-export">Actions</th>
        </tr>
    </thead>
    <tbody>
        @foreach($users as $index => $user)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $user->name }}</td>
                <td>{{ $user->email }}</td>
                <td class="no-export">
                    <x-table-actions
                        :editRoute="route('users.edit', $user->id)"
                        :deleteRoute="route('users.destroy', $user->id)"
                    />
                </td>
            </tr>
        @endforeach
    </tbody>
</x-data-table>
```

---

## Component Reference

### 1. `<x-data-table>` Component

#### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `id` | string | auto-generated | Unique table identifier |
| `class` | string | '' | Additional CSS classes |
| `striped` | boolean | true | Alternating row colors |
| `hover` | boolean | true | Row hover effect |
| `bordered` | boolean | false | Cell borders |
| `responsive` | boolean | true | Responsive wrapper |
| `config` | array | null | Custom DataTable config |

#### Example with Custom Config

```blade
<x-data-table
    id="customTable"
    :striped="true"
    :hover="true"
    :config="[
        'pageLength' => 50,
        'order' => [[1, 'desc']],
        'language' => [
            'search' => 'Filter records:',
            'lengthMenu' => 'Display _MENU_ records'
        ]
    ]"
>
    <!-- table content -->
</x-data-table>
```

---

### 2. `<x-table-actions>` Component

Pre-built action buttons for Edit, View, and Delete operations.

#### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `editRoute` | string | null | Edit button URL |
| `deleteRoute` | string | null | Delete form action URL |
| `viewRoute` | string | null | View button URL |
| `editText` | string | 'Edit' | Edit button title |
| `deleteText` | string | 'Delete' | Delete button title |
| `viewText` | string | 'View' | View button title |
| `confirmDelete` | boolean | true | Show confirmation dialog |
| `deleteMessage` | string | 'Are you sure...' | Confirmation message |
| `customActions` | slot | null | Additional custom actions |

#### Example with All Actions

```blade
<x-table-actions
    :viewRoute="route('items.show', $item->id)"
    :editRoute="route('items.edit', $item->id)"
    :deleteRoute="route('items.destroy', $item->id)"
    deleteMessage="Delete this item permanently?"
>
    <!-- Custom actions via slot -->
    <a href="{{ route('items.duplicate', $item->id) }}" class="btn btn-sm btn-outline-secondary">
        <i class="feather-copy"></i>
    </a>
</x-table-actions>
```

---

## CSS Classes Reference

### Column Classes

Apply these classes to `<th>` or `<td>` elements:

| Class | Purpose |
|-------|---------|
| `no-sort` | Disable sorting for this column |
| `no-export` | Exclude from exports (e.g., Actions column) |
| `no-toggle` | Prevent column from being hidden via column visibility toggle |
| `text-center` | Center-align content |
| `text-end` | Right-align content |

### Example

```blade
<thead>
    <tr>
        <th class="text-center" style="width: 50px;">#</th>
        <th>Name</th>
        <th>Description</th>
        <th class="text-center no-sort no-export" style="width: 120px;">Actions</th>
    </tr>
</thead>
```

---

## Advanced Features

### 1. Status Badges

Add colorful status indicators:

```blade
<td>
    @if($item->status === 'active')
        <span class="badge bg-success">Active</span>
    @elseif($item->status === 'pending')
        <span class="badge bg-warning">Pending</span>
    @else
        <span class="badge bg-danger">Inactive</span>
    @endif
</td>
```

### 2. Custom Export Configuration

Control what gets exported:

```blade
<td class="no-export">
    <!-- This cell won't appear in exports -->
    <img src="{{ $user->avatar }}" alt="Avatar">
</td>

<td>
    <!-- This will appear in exports -->
    {{ $user->name }}
</td>
```

### 3. Custom JavaScript Initialization

For advanced use cases, you can initialize DataTables manually:

```javascript
$(document).ready(function() {
    $('#myAdvancedTable').DataTable(
        $.extend(true, {}, window.dataTableConfig, {
            // Custom overrides
            pageLength: 100,
            order: [[2, 'desc']],
            columnDefs: [
                {
                    targets: [0],
                    render: function(data, type, row) {
                        return '<strong>' + data + '</strong>';
                    }
                }
            ],
            // Custom export options
            buttons: [
                {
                    extend: 'excelHtml5',
                    title: 'Custom Export Title',
                    exportOptions: {
                        columns: [0, 1, 2, 3]
                    }
                }
            ]
        })
    );
});
```

---

## Color Scheme

The DataTable uses a beautiful gradient color scheme based on your brand colors:

- **Header**: `#532934` to `#6b3545` (gradient)
- **Hover**: Soft rose gradient
- **Striped rows**: Alternating `#f8f9fa` and `#ffffff`
- **Export buttons**: Bootstrap color scheme with gradients

### Customizing Colors

To customize colors, edit:
```
public/admin/assets/css/datatable-custom.css
```

Key CSS variables to modify:
- `.data-table thead th` - Header colors
- `.data-table tbody tr:hover` - Hover effect
- `.data-table tbody tr:nth-child(odd/even)` - Row striping

---

## Export Button Customization

All tables include 6 export buttons by default:

1. **Copy** - Copy to clipboard
2. **CSV** - Export as CSV file
3. **Excel** - Export as .xlsx file
4. **PDF** - Export as PDF (landscape, A4)
5. **Print** - Print-friendly view
6. **Columns** - Toggle column visibility

### Hiding Export Buttons

To hide specific buttons for a table:

```blade
<x-data-table
    :config="[
        'buttons' => [
            'copy',
            'excel',
            'pdf'
        ]
    ]"
>
    <!-- Only Copy, Excel, and PDF buttons will show -->
</x-data-table>
```

---

## Responsive Behavior

All DataTables are responsive by default:

- **Desktop**: Full table with all columns
- **Tablet**: Columns may collapse into child rows
- **Mobile**: Optimized layout with expandable details

To disable responsive behavior:

```blade
<x-data-table :responsive="false">
    <!-- table content -->
</x-data-table>
```

---

## Database Best Practices

For optimal DataTable performance, ensure your database tables follow these guidelines:

### 1. Proper Indexing

```php
// In your migration file
Schema::create('items', function (Blueprint $table) {
    $table->id();
    $table->string('name')->index(); // Index searchable columns
    $table->string('email')->unique();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->enum('status', ['active', 'inactive', 'pending'])->default('pending')->index();
    $table->timestamps();

    // Composite indexes for multi-column searches
    $table->index(['status', 'created_at']);
});
```

### 2. Eager Loading Relationships

Prevent N+1 queries:

```php
// In your controller
$categories = Category::with('creator')->paginate(100);
```

### 3. Use Pagination

For large datasets, use server-side processing:

```php
// Controller
public function index(Request $request)
{
    if ($request->ajax()) {
        return datatables()
            ->eloquent(User::query())
            ->toJson();
    }

    return view('users.index');
}
```

---

## Troubleshooting

### Table Not Initializing?

1. Check browser console for JavaScript errors
2. Ensure jQuery is loaded before DataTables
3. Verify table has a unique `id`
4. Check that table has `<thead>` and `<tbody>` sections

### Export Buttons Not Working?

1. Verify all export libraries are loaded (JSZip, PDFMake)
2. Check browser console for errors
3. Ensure buttons extension is loaded
4. Check internet connection (CDN resources)

### Styling Issues?

1. Verify `datatable-custom.css` is loaded
2. Check for CSS conflicts with other libraries
3. Clear browser cache
4. Inspect element to see which styles are applied

---

## Examples

### Example 1: User Management Table

```blade
<x-data-table id="usersTable">
    <thead>
        <tr>
            <th style="width: 50px;">#</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Created</th>
            <th class="no-sort no-export" style="width: 120px;">Actions</th>
        </tr>
    </thead>
    <tbody>
        @foreach($users as $index => $user)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td><strong>{{ $user->name }}</strong></td>
                <td>{{ $user->email }}</td>
                <td><span class="badge bg-primary">{{ $user->role->name }}</span></td>
                <td>
                    @if($user->status === 'active')
                        <span class="badge bg-success">Active</span>
                    @else
                        <span class="badge bg-danger">Inactive</span>
                    @endif
                </td>
                <td>{{ $user->created_at->format('M d, Y') }}</td>
                <td>
                    <x-table-actions
                        :viewRoute="route('users.show', $user->id)"
                        :editRoute="route('users.edit', $user->id)"
                        :deleteRoute="route('users.destroy', $user->id)"
                    />
                </td>
            </tr>
        @endforeach
    </tbody>
</x-data-table>
```

### Example 2: Financial Report Table

```blade
<x-data-table
    id="financeTable"
    :config="[
        'pageLength' => 50,
        'order' => [[5, 'desc']]
    ]"
>
    <thead>
        <tr>
            <th>#</th>
            <th>Transaction ID</th>
            <th>Description</th>
            <th class="text-end">Amount</th>
            <th>Status</th>
            <th>Date</th>
            <th class="no-sort no-export">Actions</th>
        </tr>
    </thead>
    <tbody>
        @foreach($transactions as $index => $transaction)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td><code>{{ $transaction->transaction_id }}</code></td>
                <td>{{ $transaction->description }}</td>
                <td class="text-end">
                    <strong>USD {{ number_format($transaction->amount, 2) }}</strong>
                </td>
                <td>
                    <span class="badge bg-{{ $transaction->status === 'completed' ? 'success' : 'warning' }}">
                        {{ ucfirst($transaction->status) }}
                    </span>
                </td>
                <td>{{ $transaction->created_at->format('M d, Y h:i A') }}</td>
                <td>
                    <x-table-actions
                        :viewRoute="route('transactions.show', $transaction->id)"
                    />
                </td>
            </tr>
        @endforeach
    </tbody>
</x-data-table>
```

---

## Migration Guide

### Converting Existing Tables

**Before:**
```blade
<table class="table table-hover" id="oldTable">
    <thead>...</thead>
    <tbody>...</tbody>
</table>

<script>
    $('#oldTable').DataTable();
</script>
```

**After:**
```blade
<x-data-table id="newTable">
    <thead>...</thead>
    <tbody>...</tbody>
</x-data-table>
```

That's it! All features are automatically applied.

---

## Support & Resources

- **DataTables Documentation**: https://datatables.net/
- **Bootstrap 5 Documentation**: https://getbootstrap.com/docs/5.0/
- **Local Files**:
  - CSS: `public/admin/assets/css/datatable-custom.css`
  - JS: `public/admin/assets/js/datatable-config.js`
  - Component: `resources/views/components/data-table.blade.php`

---

## Summary

With this DataTable system, every table in your application can have:
- 🎨 Beautiful, consistent styling
- 📊 All export options (Excel, PDF, CSV, Print)
- 🔍 Advanced search and filtering
- 📱 Mobile-responsive design
- ⚡ Fast implementation with Blade components

Simply use `<x-data-table>` and `<x-table-actions>` components, and you're done!
