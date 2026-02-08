<?php

namespace App\Http\Controllers;

use App\Models\GovernanceLevel;
use App\Models\GovernanceNode;
use App\Models\GovernanceReportingLine;
use App\Models\GovernanceNodeAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class GovernanceStructureController extends Controller
{
    public function index()
    {
        $levels = GovernanceLevel::orderBy('sort_order')->get();
        $nodes = GovernanceNode::with('level')->orderBy('name')->get();
        $lines = GovernanceReportingLine::with(['child.level', 'parent.level'])->orderBy('id')->get();
        $assignments = GovernanceNodeAssignment::with(['node.level', 'user'])->orderBy('id')->get();
        $users = User::orderBy('name')->get();

        $orgNodes = $nodes->map(function ($n) {
            return [
                'id' => $n->id,
                'name' => $n->name,
                'level' => $n->level->name ?? '',
            ];
        })->values();

        $orgLines = $lines->map(function ($l) {
            return [
                'child' => $l->child_node_id,
                'parent' => $l->parent_node_id,
                'type' => $l->line_type,
            ];
        })->values();

        return view('finance.governance.index', compact(
            'levels',
            'nodes',
            'lines',
            'assignments',
            'users',
            'orgNodes',
            'orgLines'
        ));
    }

    public function editLevel(GovernanceLevel $level)
    {
        return view('finance.governance.levels.edit', compact('level'));
    }

    public function editNode(GovernanceNode $node)
    {
        $levels = GovernanceLevel::orderBy('sort_order')->get();

        return view('finance.governance.nodes.edit', compact('node', 'levels'));
    }

    public function editLine(GovernanceReportingLine $line)
    {
        $nodes = GovernanceNode::with('level')->orderBy('name')->get();

        return view('finance.governance.lines.edit', compact('line', 'nodes'));
    }

    public function editAssignment(GovernanceNodeAssignment $assignment)
    {
        $nodes = GovernanceNode::with('level')->orderBy('name')->get();
        $users = User::orderBy('name')->get();

        return view('finance.governance.assignments.edit', compact('assignment', 'nodes', 'users'));
    }

    public function storeLevel(Request $request)
    {
        $validated = $request->validate([
            'key'         => 'required|string|max:50|unique:myb_governance_levels,key',
            'name'        => 'required|string|max:100',
            'sort_order'  => 'nullable|integer|min:0',
            'description' => 'nullable|string',
        ]);

        $nextSort = (GovernanceLevel::max('sort_order') ?? -1) + 1;
        $validated['sort_order'] = $nextSort;

        GovernanceLevel::create($validated);

        return back()->with('success', 'Governance level created successfully.');
    }

    public function updateLevel(Request $request, GovernanceLevel $level)
    {
        $validated = $request->validate([
            'key'         => 'required|string|max:50|unique:myb_governance_levels,key,' . $level->id,
            'name'        => 'required|string|max:100',
            'sort_order'  => 'nullable|integer|min:0',
            'description' => 'nullable|string',
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? $level->sort_order;

        $level->update($validated);

        return back()->with('success', 'Governance level updated successfully.');
    }

    public function destroyLevel(GovernanceLevel $level)
    {
        if ($level->nodes()->exists()) {
            return back()->with('error', 'Cannot delete a level that is already assigned to nodes. Please move or delete those nodes first.');
        }

        $level->delete();

        return back()->with('success', 'Governance level deleted successfully.');
    }

    public function storeNode(Request $request)
    {
        $validated = $request->validate([
            'level_id' => 'required|exists:myb_governance_levels,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'effective_start' => 'nullable|date',
        ]);

        $validated['created_by'] = Auth::id();

        GovernanceNode::create($validated);

        return back()->with('success', 'Governance node created successfully.');
    }

    public function updateNode(Request $request, GovernanceNode $node)
    {
        $validated = $request->validate([
            'level_id' => 'required|exists:myb_governance_levels,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'effective_start' => 'nullable|date',
        ]);

        $node->update($validated);

        return back()->with('success', 'Governance node updated successfully.');
    }

    public function destroyNode(GovernanceNode $node)
    {
        $node->delete();

        return back()->with('success', 'Governance node deleted successfully.');
    }

    public function storeLine(Request $request)
    {
        $validated = $request->validate([
            'child_node_id' => 'required|exists:myb_governance_nodes,id',
            'parent_node_id' => 'required|exists:myb_governance_nodes,id',
            'line_type' => 'required|in:primary,dotted,advisory',
            'effective_start' => 'nullable|date',
            'effective_end' => 'nullable|date',
        ]);

        // Trim stray whitespace
        $validated['child_node_id'] = trim((string) $validated['child_node_id']);
        $validated['parent_node_id'] = trim((string) $validated['parent_node_id']);

        // Re-verify the nodes exist after normalization (defensive against malformed inputs)
        if (!GovernanceNode::whereKey($validated['child_node_id'])->exists()) {
            throw ValidationException::withMessages([
                'child_node_id' => 'Selected child node no longer exists.',
            ]);
        }
        if (!GovernanceNode::whereKey($validated['parent_node_id'])->exists()) {
            throw ValidationException::withMessages([
                'parent_node_id' => 'Selected parent node no longer exists.',
            ]);
        }

        // Only enforce ordering when both dates are supplied
        if ($validated['effective_start'] && $validated['effective_end']) {
            if (Carbon::parse($validated['effective_end'])->lt(Carbon::parse($validated['effective_start']))) {
                throw ValidationException::withMessages([
                    'effective_end' => 'End date must be on or after the start date.',
                ]);
            }
        }

        if ($validated['line_type'] === 'primary') {
            $this->assertPrimaryLineAvailable(
                $validated['child_node_id'],
                $validated['effective_start'] ?? null,
                $validated['effective_end'] ?? null
            );
        }

        $validated['created_by'] = Auth::id();

        GovernanceReportingLine::create($validated);

        return back()->with('success', 'Reporting line saved successfully.');
    }

    public function updateLine(Request $request, GovernanceReportingLine $line)
    {
        $validated = $request->validate([
            'child_node_id' => 'required|exists:myb_governance_nodes,id',
            'parent_node_id' => 'required|exists:myb_governance_nodes,id',
            'line_type' => 'required|in:primary,dotted,advisory',
            'effective_start' => 'nullable|date',
            'effective_end' => 'nullable|date',
        ]);

        $validated['child_node_id'] = trim((string) $validated['child_node_id']);
        $validated['parent_node_id'] = trim((string) $validated['parent_node_id']);

        if (!GovernanceNode::whereKey($validated['child_node_id'])->exists()) {
            throw ValidationException::withMessages([
                'child_node_id' => 'Selected child node no longer exists.',
            ]);
        }
        if (!GovernanceNode::whereKey($validated['parent_node_id'])->exists()) {
            throw ValidationException::withMessages([
                'parent_node_id' => 'Selected parent node no longer exists.',
            ]);
        }

        if ($validated['effective_start'] && $validated['effective_end']) {
            if (Carbon::parse($validated['effective_end'])->lt(Carbon::parse($validated['effective_start']))) {
                throw ValidationException::withMessages([
                    'effective_end' => 'End date must be on or after the start date.',
                ]);
            }
        }

        if ($validated['line_type'] === 'primary') {
            $this->assertPrimaryLineAvailable(
                $validated['child_node_id'],
                $validated['effective_start'] ?? null,
                $validated['effective_end'] ?? null,
                $line->id
            );
        }

        $line->update($validated);

        return back()->with('success', 'Reporting line updated successfully.');
    }

    public function destroyLine(GovernanceReportingLine $line)
    {
        $line->delete();

        return back()->with('success', 'Reporting line deleted successfully.');
    }

    public function storeAssignment(Request $request)
    {
        $validated = $request->validate([
            'node_id' => 'required|exists:myb_governance_nodes,id',
            'user_id' => 'required|exists:users,id',
            'role_title' => 'nullable|string|max:255',
            'is_primary' => 'nullable|boolean',
            'notify_user' => 'nullable|boolean',
            'effective_start' => 'nullable|date',
            'effective_end' => 'nullable|date|after_or_equal:effective_start',
        ]);

        $validated['node_id'] = trim((string) $validated['node_id']);
        $validated['user_id'] = trim((string) $validated['user_id']);

        $validated['is_primary'] = (bool) ($validated['is_primary'] ?? false);
        $notifyUser = (bool) ($validated['notify_user'] ?? false);
        unset($validated['notify_user']);


        if ($validated['is_primary']) {
            $this->assertPrimaryAssignmentAvailable(
                $validated['node_id'],
                $validated['effective_start'] ?? null,
                $validated['effective_end'] ?? null
            );
        }

        $validated['created_by'] = Auth::id();

        $assignment = GovernanceNodeAssignment::create($validated);

        if ($notifyUser) {
            $sent = $this->notifyAssignmentUser($assignment, 'assigned');
            if (!$sent) {
                return back()->with('error', 'Assignment saved, but the email notification failed.');
            }
        }

        return back()->with('success', 'Assignment saved successfully.');
    }

    public function updateAssignment(Request $request, GovernanceNodeAssignment $assignment)
    {
        $validated = $request->validate([
            'node_id' => 'required|exists:myb_governance_nodes,id',
            'user_id' => 'required|exists:users,id',
            'role_title' => 'nullable|string|max:255',
            'is_primary' => 'nullable|boolean',
            'notify_user' => 'nullable|boolean',
            'effective_start' => 'nullable|date',
            'effective_end' => 'nullable|date|after_or_equal:effective_start',
        ]);

        $validated['node_id'] = trim((string) $validated['node_id']);
        $validated['user_id'] = trim((string) $validated['user_id']);

        $validated['is_primary'] = (bool) ($validated['is_primary'] ?? false);
        $notifyUser = (bool) ($validated['notify_user'] ?? false);
        unset($validated['notify_user']);


        if ($validated['is_primary']) {
            $this->assertPrimaryAssignmentAvailable(
                $validated['node_id'],
                $validated['effective_start'] ?? null,
                $validated['effective_end'] ?? null,
                $assignment->id
            );
        }

        $assignment->update($validated);

        if ($notifyUser) {
            $assignment->refresh();
            $sent = $this->notifyAssignmentUser($assignment, 'updated');
            if (!$sent) {
                return back()->with('error', 'Assignment updated, but the email notification failed.');
            }
        }

        return back()->with('success', 'Assignment updated successfully.');
    }

    public function destroyAssignment(GovernanceNodeAssignment $assignment)
    {
        $assignment->delete();

        return back()->with('success', 'Assignment deleted successfully.');
    }

    private function assertPrimaryLineAvailable(string $childNodeId, ?string $start, ?string $end, ?string $ignoreId = null): void
    {
        $startDate = $start ?? '0001-01-01';
        $endDate = $end ?? '9999-12-31';

        $query = GovernanceReportingLine::where('child_node_id', $childNodeId)
            ->where('line_type', 'primary')
            ->whereRaw("COALESCE(effective_end, '9999-12-31') >= ?", [$startDate])
            ->whereRaw("COALESCE(effective_start, '0001-01-01') <= ?", [$endDate]);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'line_type' => 'Primary reporting line overlaps an existing primary line for this node.',
            ]);
        }
    }

    private function assertPrimaryAssignmentAvailable(string $nodeId, ?string $start, ?string $end, ?string $ignoreId = null): void
    {
        $startDate = $start ?? '0001-01-01';
        $endDate = $end ?? '9999-12-31';

        $query = GovernanceNodeAssignment::where('node_id', $nodeId)
            ->where('is_primary', true)
            ->whereRaw("COALESCE(effective_end, '9999-12-31') >= ?", [$startDate])
            ->whereRaw("COALESCE(effective_start, '0001-01-01') <= ?", [$endDate]);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'is_primary' => 'Primary assignment overlaps an existing primary assignment for this node.',
            ]);
        }
    }

    private function notifyAssignmentUser(GovernanceNodeAssignment $assignment, string $action): bool
    {
        $user = $assignment->user;
        $node = $assignment->node;

        if (!$user || !$node || !$user->email) {
            return false;
        }

        $subject = 'Governance Assignment ' . ucfirst($action);
        $start = $assignment->effective_start?->format('d M Y') ?? 'Not set';
        $end = $assignment->effective_end?->format('d M Y') ?? 'Open';
        $role = $assignment->role_title ?: 'Unspecified role';

        $body = "Hello {$user->name},\n\n"
            . "Your governance assignment has been {$action}.\n\n"
            . "Node: {$node->name}\n"
            . "Role: {$role}\n"
            . "Effective: {$start} to {$end}\n\n"
            . "If you have questions, please contact the administrator.";

        try {
            Mail::raw($body, function ($message) use ($user, $subject) {
                $message->to($user->email)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('Governance assignment email failed', [
                'assignment_id' => $assignment->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        return true;
    }
}
