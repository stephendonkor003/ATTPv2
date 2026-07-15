<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TtlPortalController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();

        $programs = $this->assignedProgramsQuery($user)
            ->with([
                'sector',
                'governanceNode.level',
                'fundings.funder',
                'projects.governanceNode.level',
                'projects.activities.subActivities',
            ])
            ->orderBy('name')
            ->get()
            ->each(fn (Program $program) => $this->decorateProgram($program));

        abort_if($programs->isEmpty(), 403, 'No TTL program assignment is linked to your account.');

        $projects = $programs
            ->flatMap->projects
            ->sortBy('name')
            ->values()
            ->each(fn (Project $project) => $this->decorateProject($project, $project->program));

        $programBudget = (float) $programs->sum(fn (Program $program) => (float) ($program->total_budget ?? 0));
        $projectBudget = (float) $projects->sum(fn (Project $project) => (float) ($project->total_budget ?? 0));

        $stats = [
            'programs' => $programs->count(),
            'projects' => $projects->count(),
            'activities' => $projects->sum(fn (Project $project) => $project->activities->count()),
            'sub_activities' => $projects->sum(fn (Project $project) => $project->activities->sum(fn ($activity) => $activity->subActivities->count())),
            'program_budget' => $programBudget,
            'project_budget' => $projectBudget,
            'progress' => $programBudget > 0 ? min(100, round(($projectBudget / $programBudget) * 100)) : 0,
        ];

        return view('ttl.dashboard', compact('user', 'programs', 'projects', 'stats'));
    }

    public function showProgram(Program $program)
    {
        $this->assertAssignedProgram($program, Auth::user());

        $program->load([
            'sector',
            'governanceNode.level',
            'fundings.funder',
            'projects.governanceNode.level',
            'projects.activities.subActivities',
        ]);

        $this->decorateProgram($program);
        $program->projects->each(fn (Project $project) => $this->decorateProject($project, $program));

        return view('ttl.programs.show', compact('program'));
    }

    public function showProject(Project $project)
    {
        $project->load([
            'program.sector',
            'program.governanceNode.level',
            'program.fundings.funder',
            'governanceNode.level',
            'activities.governanceNode.level',
            'activities.subActivities.governanceNode',
        ]);

        $this->assertAssignedProgram($project->program, Auth::user());
        $this->decorateProgram($project->program);
        $this->decorateProject($project, $project->program);

        return view('ttl.projects.show', compact('project'));
    }

    private function assignedProgramsQuery(User $user): Builder
    {
        $email = Str::lower((string) $user->email);

        return Program::query()
            ->where(function (Builder $query) use ($user, $email) {
                $query->where('ttl_user_id', $user->id);

                if ($email !== '') {
                    $query->orWhereRaw('LOWER(ttl_email) = ?', [$email]);
                }
            });
    }

    private function assertAssignedProgram(?Program $program, ?User $user): void
    {
        abort_if(! $program || ! $user, 404);

        $emailMatches = filled($program->ttl_email)
            && Str::lower((string) $program->ttl_email) === Str::lower((string) $user->email);

        abort_unless((string) $program->ttl_user_id === (string) $user->id || $emailMatches, 403);
    }

    private function decorateProgram(Program $program): void
    {
        $projects = $program->relationLoaded('projects') ? $program->projects : Collection::make();
        $projectBudget = (float) $projects->sum(fn (Project $project) => (float) ($project->total_budget ?? 0));
        $programBudget = (float) ($program->total_budget ?? 0);

        $program->setAttribute('project_budget_value', $projectBudget);
        $program->setAttribute('progress_percent', $programBudget > 0 ? min(100, round(($projectBudget / $programBudget) * 100)) : 0);
        $program->setAttribute('activities_count', $projects->sum(fn (Project $project) => $project->activities->count()));
        $program->setAttribute('sub_activities_count', $projects->sum(fn (Project $project) => $project->activities->sum(fn ($activity) => $activity->subActivities->count())));
    }

    private function decorateProject(Project $project, ?Program $program = null): void
    {
        $activities = $project->relationLoaded('activities') ? $project->activities : Collection::make();
        $programBudget = (float) ($program?->total_budget ?? $project->program?->total_budget ?? 0);
        $projectBudget = (float) ($project->total_budget ?? 0);

        $project->setAttribute('activities_count', $activities->count());
        $project->setAttribute('sub_activities_count', $activities->sum(fn ($activity) => $activity->subActivities->count()));
        $project->setAttribute('budget_share_percent', $programBudget > 0 ? min(100, round(($projectBudget / $programBudget) * 100)) : 0);
    }
}
