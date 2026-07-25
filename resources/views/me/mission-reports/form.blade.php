@extends('layouts.app')

@section('title', $missionReport ? 'Mission Report' : 'New Mission Report')
@section('lean_admin_scripts', '1')

@section('content')
@php
    $editing = (bool)$missionReport;
    $editable = !$editing || $missionReport->isEditable();
    $field = fn($name) => old($name, $missionReport?->{$name});
    $completed = $editing ? (12-count($missionReport->completionIssues())) : 0;
@endphp
<div class="container-fluid py-4" style="max-width:1200px">
    <div class="p-4 rounded-4 text-white mb-3" style="background:linear-gradient(120deg,#073f30,#0b6d50)">
        <a href="{{ route('budget.me.mission-reports.index') }}" class="text-white-50 text-decoration-none small"><i class="feather-arrow-left me-1"></i>Mission register</a>
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mt-2">
            <div><div class="small text-uppercase fw-bold opacity-75">Standardized M&amp;E mission report</div><h2 class="text-white fw-bold mb-1">{{ $editing ? $missionReport->title : 'Create Mission Report' }}</h2><p class="mb-0 opacity-75">One consistent structure from draft through archival.</p></div>
            @if($editing)<span class="badge bg-light text-dark fs-6">{{ $missionReport->statusLabel() }}</span>@endif
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($editing && $missionReport->review_notes)<div class="alert alert-warning"><strong>Review notes:</strong> {{ $missionReport->review_notes }}</div>@endif
    @if($editing)<div class="alert {{ $completed===12?'alert-success':'alert-warning' }}"><strong>{{ $completed }}/12 mandatory content fields complete.</strong> All sections must be complete before submission.</div>@endif

    <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('budget.me.mission-reports.update',$missionReport) : route('budget.me.mission-reports.store') }}">
        @csrf @if($editing)@method('PUT')@endif
        <fieldset @disabled(!$editable)>
            <div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white"><h5 class="mb-0 fw-bold">1. Mission identification and team</h5></div><div class="card-body"><div class="row g-3">
                <div class="col-lg-4"><label class="form-label fw-bold">Standard template</label><select name="template_id" class="form-select" required><option value="">Choose template</option>@foreach($templates as $template)<option value="{{ $template->id }}" @selected((string)old('template_id',$missionReport?->template_id)===(string)$template->id)>{{ $template->name }} · v{{ $template->version }}</option>@endforeach</select></div>
                <div class="col-lg-4"><label class="form-label fw-bold">Portfolio</label><select name="portfolio_id" class="form-select" required data-portfolio><option value="">Choose portfolio</option>@foreach($portfolios as $portfolio)<option value="{{ $portfolio->id }}" @selected((string)old('portfolio_id',$missionReport?->portfolio_id)===(string)$portfolio->id)>{{ $portfolio->name }}</option>@endforeach</select></div>
                <div class="col-lg-4"><label class="form-label fw-bold">Project component</label><select name="project_component_id" class="form-select" required><option value="">Choose component</option>@foreach($components as $component)<option value="{{ $component->id }}" data-portfolio-id="{{ $component->program?->sector_id }}" @selected((string)old('project_component_id',$missionReport?->project_component_id)===(string)$component->id)>{{ $component->name }}</option>@endforeach</select></div>
                <div class="col-lg-8"><label class="form-label fw-bold">Mission title</label><input name="title" class="form-control" value="{{ $field('title') }}" required></div>
                <div class="col-lg-4"><label class="form-label fw-bold">Location</label><input name="location" class="form-control" value="{{ $field('location') }}"></div>
                <div class="col-md-4"><label class="form-label fw-bold">Start date</label><input type="date" name="mission_start_date" class="form-control" value="{{ old('mission_start_date',$missionReport?->mission_start_date?->format('Y-m-d')) }}" required></div>
                <div class="col-md-4"><label class="form-label fw-bold">End date</label><input type="date" name="mission_end_date" class="form-control" value="{{ old('mission_end_date',$missionReport?->mission_end_date?->format('Y-m-d')) }}" required></div>
                <div class="col-md-4"><label class="form-label fw-bold">Corrective-action due date</label><input type="date" name="action_due_at" class="form-control" value="{{ old('action_due_at',$missionReport?->action_due_at?->format('Y-m-d')) }}"></div>
                <div class="col-12"><label class="form-label fw-bold">Mission team members</label><textarea name="team_members" rows="3" class="form-control">{{ $field('team_members') }}</textarea></div>
            </div></div></div>

            @foreach([
                ['objectives','2. Objectives and scope','State the purpose, scope and expected mission outputs.'],
                ['methodology','3. Methodology','Describe the approach, locations, interviews, sampling and evidence reviewed.'],
                ['executive_summary','4. Executive summary','Provide a concise management summary of the mission.'],
                ['key_findings','5. Key findings','Record evidence-based findings, including positive results and gaps.'],
                ['recommendations','6. Recommendations','State practical recommendations arising from the findings.'],
                ['corrective_actions','7. Corrective actions','Define each outstanding action and expected result.'],
                ['responsible_parties','8. Responsible parties','Name the owner and accountability point for each action.'],
                ['lessons_learned','9. Lessons learned','Capture lessons and adaptive-management implications.'],
                ['conclusion','10. Conclusion','Give the overall mission conclusion and follow-up decision.'],
            ] as [$name,$label,$help])
                <div class="card border-0 shadow-sm mb-3"><div class="card-body"><label class="form-label fw-bold">{{ $label }}</label><div class="small text-muted mb-2">{{ $help }}</div><textarea name="{{ $name }}" rows="5" class="form-control">{{ $field($name) }}</textarea></div></div>
            @endforeach

            @if($editing && $missionReport->documents->isNotEmpty())
                <div class="card border-0 shadow-sm mb-3"><div class="card-body"><h5 class="fw-bold">Supporting documents</h5>@foreach($missionReport->documents as $document)<div class="d-flex justify-content-between border rounded p-2 mb-2"><span><strong>{{ $document->document_name }}</strong><small class="d-block text-muted">{{ $document->original_filename }}</small></span><a class="btn btn-sm btn-outline-success" href="{{ route('budget.me.mission-reports.documents.download',[$missionReport,$document]) }}"><i class="feather-download"></i></a></div>@endforeach</div></div>
            @endif
            <div class="card border-0 shadow-sm mb-3"><div class="card-body"><div class="d-flex justify-content-between"><h5 class="fw-bold">Attachments</h5><button type="button" class="btn btn-sm btn-outline-success" data-add-document>Add document</button></div><div data-documents><div class="row g-2 mt-1" data-document><div class="col-md-5"><input name="document_names[]" class="form-control" placeholder="Document name"></div><div class="col-md-6"><input type="file" name="documents[]" class="form-control"></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100" data-remove-document>×</button></div></div></div></div></div>
        </fieldset>
        @if($editable)<div class="text-end mb-4"><button class="btn btn-success px-4 fw-bold">{{ $editing ? 'Save Draft' : 'Create Draft' }}</button></div>@endif
    </form>

    @if($editing)
        <div class="card border-0 shadow-sm"><div class="card-body">
            <h5 class="fw-bold">Workflow actions</h5>
            <div class="d-flex flex-wrap gap-2">
                @if($missionReport->isEditable() && auth()->user()->can('me.mission_reports.manage'))
                    <form method="POST" action="{{ route('budget.me.mission-reports.submit',$missionReport) }}">@csrf<button class="btn btn-primary fw-bold"><i class="feather-send me-1"></i>Submit</button></form>
                @endif
                @if($missionReport->isSubmitted() && auth()->user()->can('me.mission_reports.review'))
                    <form method="POST" action="{{ route('budget.me.mission-reports.review',$missionReport) }}" class="d-flex flex-wrap gap-2">@csrf<input name="review_notes" class="form-control" style="min-width:300px" placeholder="Review notes / required corrections"><button name="review_action" value="return" class="btn btn-warning fw-bold">Return Report</button><button name="review_action" value="approve" class="btn btn-success fw-bold">Approve Report</button></form>
                @endif
                @if($missionReport->isReviewed() && auth()->user()->can('me.mission_reports.archive'))
                    <form method="POST" action="{{ route('budget.me.mission-reports.archive',$missionReport) }}">@csrf<button class="btn btn-dark fw-bold"><i class="feather-archive me-1"></i>Archive Report</button></form>
                @endif
            </div>
        </div></div>
    @endif
</div>
<script>
(() => {
    const list=document.querySelector('[data-documents]');
    const bind=button=>button?.addEventListener('click',()=>{const rows=list.querySelectorAll('[data-document]');if(rows.length===1){rows[0].querySelectorAll('input').forEach(i=>i.value='');}else button.closest('[data-document]').remove();});
    list?.querySelectorAll('[data-remove-document]').forEach(bind);
    document.querySelector('[data-add-document]')?.addEventListener('click',()=>{const rows=list.querySelectorAll('[data-document]');if(rows.length>=10)return;const clone=rows[0].cloneNode(true);clone.querySelectorAll('input').forEach(i=>i.value='');bind(clone.querySelector('[data-remove-document]'));list.appendChild(clone);});
})();
</script>
@endsection
