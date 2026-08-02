@php
    $achievementEditable = $editable ?? false;
@endphp

<style>
    .achievement-workspace{margin-top:1rem;border:1px solid #d9e5df;border-radius:14px;background:#fff;overflow:hidden}
    .achievement-workspace__head{padding:1rem 1.1rem;color:#fff;background:linear-gradient(120deg,#0b5c45,#16805d)}
    .achievement-workspace__head h5{margin:0;color:#fff;font-weight:800}.achievement-workspace__head p{margin:.25rem 0 0;color:rgba(255,255,255,.76);font-size:.8rem}
    .achievement-indicator{margin:1rem;border:1px solid #dbe6e0;border-radius:12px;overflow:hidden}
    .achievement-indicator__head{display:flex;justify-content:space-between;gap:1rem;padding:.85rem 1rem;background:#f5faf7}
    .achievement-indicator__head h6{margin:.15rem 0 0;font-weight:800}.achievement-code{color:#0b6b4d;font-size:.68rem;font-weight:850;letter-spacing:.04em}
    .achievement-form{padding:1rem;border-top:1px solid #dbe6e0;background:#fbfdfc}.achievement-theme-grid{display:flex;flex-wrap:wrap;gap:.45rem}
    .achievement-theme-option{display:inline-flex;align-items:center;gap:.35rem;padding:.42rem .55rem;border:1px solid #d6e3dc;border-radius:8px;background:#fff;font-size:.72rem}
    .achievement-card{margin:0 1rem 1rem;border:1px solid #dce7e1;border-radius:11px;overflow:hidden}.achievement-card__head{display:flex;justify-content:space-between;gap:.75rem;padding:.8rem;background:#fbfdfc}
    .achievement-card__head h6{margin:.15rem 0;font-weight:800}.achievement-meta{display:flex;flex-wrap:wrap;gap:.35rem;color:#64736b;font-size:.7rem}
    .achievement-chip{display:inline-flex;align-items:center;padding:.22rem .42rem;border-radius:999px;color:#0d6248;background:#e8f5ef;font-size:.64rem;font-weight:750}
    .achievement-total{min-width:90px;text-align:right}.achievement-total strong{display:block;color:#0b6b4d;font-size:1.15rem}.achievement-total small{color:#6a796f;font-size:.58rem;text-transform:uppercase}
    .achievement-card__body{padding:.85rem}.achievement-subsection{margin-top:.8rem;padding-top:.8rem;border-top:1px dashed #d8e3dd}
    .achievement-subsection h6{font-size:.76rem;font-weight:800}.achievement-breakdown-form{display:grid;grid-template-columns:repeat(5,minmax(120px,1fr));gap:.5rem;align-items:end}
    .achievement-breakdown-form .span-2{grid-column:span 2}.achievement-evidence-form{display:grid;grid-template-columns:1fr 1.3fr auto;gap:.5rem;align-items:end}
    .achievement-doc{display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding:.5rem;border:1px solid #dfe8e3;border-radius:8px;background:#fbfdfc}
    @media(max-width:900px){.achievement-breakdown-form{grid-template-columns:repeat(2,minmax(0,1fr))}.achievement-evidence-form{grid-template-columns:1fr}.achievement-breakdown-form .span-2{grid-column:span 1}}
    @media(max-width:520px){.achievement-breakdown-form{grid-template-columns:1fr}.achievement-indicator__head,.achievement-card__head{flex-direction:column}.achievement-total{text-align:left}}
</style>

<section class="achievement-workspace" id="achievement-register">
    <div class="achievement-workspace__head">
        <h5><i class="feather-award me-2" aria-hidden="true"></i>Unified achievement and beneficiary tracker</h5>
        <p>Record concrete achievements separately from indicator totals, apply combined ATTP disaggregation, and synchronize evidence with the repository.</p>
    </div>

    <datalist id="attp-country-options">@foreach($achievementTaxonomy['countries'] as $country)<option value="{{ $country }}"></option>@endforeach</datalist>

    @foreach ($report->indicatorResults as $result)
        @php
            $indicator = $result->indicator;
            $requirements = $indicator?->disaggregationRequirements ?? collect();
            $requiredCodes = $requirements->where('is_required', true)->pluck('dimension.code');
        @endphp
        <article class="achievement-indicator">
            <div class="achievement-indicator__head">
                <div>
                    <span class="achievement-code">{{ $indicator?->indicator_code }}</span>
                    <h6>{{ $indicator?->name }}</h6>
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        @forelse ($requirements as $requirement)
                            <span class="achievement-chip">{{ $requirement->dimension?->name }}{{ $requirement->is_required ? ' · required' : '' }}</span>
                        @empty
                            <span class="achievement-chip">Standard ATTP fields available</span>
                        @endforelse
                    </div>
                </div>
                <div class="achievement-total"><strong>{{ number_format($result->achievements->count()) }}</strong><small>achievement records</small></div>
            </div>

            @if ($achievementEditable)
                <form method="POST" action="{{ route('budget.me.performance-reports.achievements.store', [$report, $result]) }}" class="achievement-form">
                    @csrf
                    <div class="row g-2">
                        <div class="col-md-7"><label class="form-label">Achievement title</label><input name="title" class="form-control" maxlength="255" placeholder="e.g. Ghana trade-policy evidence brief completed" required></div>
                        <div class="col-md-5"><label class="form-label">Date achieved</label><input type="date" name="achieved_on" class="form-control" min="{{ $report->reportingPeriod?->period_start?->format('Y-m-d') }}" max="{{ $report->reportingPeriod?->period_end?->format('Y-m-d') }}" required></div>
                        <div class="col-12"><label class="form-label">Achievement description</label><textarea name="description" rows="3" class="form-control" maxlength="10000" placeholder="Describe the output, outcome, users reached and why it matters." required></textarea></div>
                        <div class="col-md-3"><label class="form-label">Geographic scope</label><select name="geographic_scope" class="form-select" required>@foreach($achievementTaxonomy['geographic_scopes'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label">Country</label><input name="country" class="form-control" list="attp-country-options" value="{{ $report->thinkTank?->country }}" maxlength="120"></div>
                        <div class="col-md-3"><label class="form-label">REC</label><select name="rec" class="form-select"><option value="">Not applicable</option>@foreach($achievementTaxonomy['recs'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label">Location</label><input name="location" class="form-control" maxlength="255" placeholder="City, province or multi-country area"></div>
                        <div class="col-md-4"><label class="form-label">Lead think tank</label><select name="lead_think_tank_member_id" class="form-select"><option value="">Use report owner</option>@foreach($activeThinkTanks as $thinkTank)<option value="{{ $thinkTank->id }}" @selected((string)$report->think_tank_member_id === (string)$thinkTank->id)>{{ $thinkTank->name }}</option>@endforeach</select></div>
                        <div class="col-md-8"><label class="form-label">Collaborating institutions</label><input name="collaborating_institutions" class="form-control" maxlength="5000" placeholder="Separate partner institutions with commas"></div>
                        <div class="col-12">
                            <label class="form-label">ATTP priority thematic area(s)</label>
                            <div class="achievement-theme-grid">@foreach($achievementTaxonomy['priority_themes'] as $value=>$label)<label class="achievement-theme-option"><input type="checkbox" name="priority_themes[]" value="{{ $value }}"> {{ $label }}</label>@endforeach</div>
                        </div>
                        <div class="col-12 text-end"><button class="btn btn-success" type="submit"><i class="feather-plus me-1"></i>Add achievement</button></div>
                    </div>
                </form>
            @endif

            @forelse ($result->achievements as $achievement)
                <article class="achievement-card">
                    <div class="achievement-card__head">
                        <div>
                            <span class="achievement-code">{{ $achievement->achievement_code }} · {{ $achievement->achieved_on?->format('d M Y') }}</span>
                            <h6>{{ $achievement->title }}</h6>
                            <div class="achievement-meta">
                                <span>{{ \App\Models\MeIndicatorAchievement::GEOGRAPHIC_SCOPES[$achievement->geographic_scope] ?? $achievement->geographic_scope }}</span>
                                @if($achievement->country)<span>· {{ $achievement->country }}</span>@endif
                                @if($achievement->rec)<span>· {{ \App\Models\MeIndicatorAchievement::RECS[$achievement->rec] ?? $achievement->rec }}</span>@endif
                                @if($achievement->leadThinkTank)<span>· {{ $achievement->leadThinkTank->name }}</span>@endif
                            </div>
                            <div class="d-flex flex-wrap gap-1 mt-2">@foreach($achievement->priority_themes ?? [] as $theme)<span class="achievement-chip">{{ \App\Models\MeIndicatorAchievement::PRIORITY_THEMES[$theme] ?? $theme }}</span>@endforeach</div>
                        </div>
                        <div class="achievement-total"><strong>{{ number_format($achievement->total_beneficiaries) }}</strong><small>calculated beneficiaries</small></div>
                    </div>
                    <div class="achievement-card__body">
                        <p class="mb-2">{{ $achievement->description }}</p>
                        @if(!empty($achievement->collaborating_institutions))<div class="small text-muted"><strong>Collaborators:</strong> {{ collect($achievement->collaborating_institutions)->join(', ') }}</div>@endif

                        @if($achievementEditable)
                            <details class="achievement-subsection">
                                <summary class="fw-semibold small">Edit achievement details</summary>
                                <form method="POST" action="{{ route('budget.me.performance-reports.achievements.update', [$report, $achievement]) }}" class="row g-2 mt-1">
                                    @csrf @method('PUT')
                                    <div class="col-md-8"><label class="form-label">Title</label><input name="title" class="form-control" value="{{ $achievement->title }}" required></div>
                                    <div class="col-md-4"><label class="form-label">Date</label><input type="date" name="achieved_on" class="form-control" value="{{ $achievement->achieved_on?->format('Y-m-d') }}" required></div>
                                    <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3" required>{{ $achievement->description }}</textarea></div>
                                    <div class="col-md-3"><label class="form-label">Scope</label><select name="geographic_scope" class="form-select">@foreach($achievementTaxonomy['geographic_scopes'] as $value=>$label)<option value="{{ $value }}" @selected($achievement->geographic_scope===$value)>{{ $label }}</option>@endforeach</select></div>
                                    <div class="col-md-3"><label class="form-label">Country</label><input name="country" class="form-control" list="attp-country-options" value="{{ $achievement->country }}"></div>
                                    <div class="col-md-3"><label class="form-label">REC</label><select name="rec" class="form-select"><option value="">Not applicable</option>@foreach($achievementTaxonomy['recs'] as $value=>$label)<option value="{{ $value }}" @selected($achievement->rec===$value)>{{ $label }}</option>@endforeach</select></div>
                                    <div class="col-md-3"><label class="form-label">Location</label><input name="location" class="form-control" value="{{ $achievement->location }}"></div>
                                    <div class="col-md-4"><label class="form-label">Lead think tank</label><select name="lead_think_tank_member_id" class="form-select">@foreach($activeThinkTanks as $thinkTank)<option value="{{ $thinkTank->id }}" @selected((string)$achievement->lead_think_tank_member_id===(string)$thinkTank->id)>{{ $thinkTank->name }}</option>@endforeach</select></div>
                                    <div class="col-md-8"><label class="form-label">Collaborators</label><input name="collaborating_institutions" class="form-control" value="{{ collect($achievement->collaborating_institutions)->join(', ') }}"></div>
                                    <div class="col-12"><div class="achievement-theme-grid">@foreach($achievementTaxonomy['priority_themes'] as $value=>$label)<label class="achievement-theme-option"><input type="checkbox" name="priority_themes[]" value="{{ $value }}" @checked(in_array($value,$achievement->priority_themes??[],true))> {{ $label }}</label>@endforeach</div></div>
                                    <div class="col-12 text-end"><button class="btn btn-sm btn-primary">Save achievement</button></div>
                                </form>
                            </details>
                        @endif

                        <div class="achievement-subsection">
                            <h6>Combined beneficiary disaggregation</h6>
                            @if($achievement->breakdowns->isNotEmpty())
                                <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Geography</th><th>Institution</th><th>Theme</th><th>Gender</th><th>Age</th><th>Stakeholder</th><th class="text-end">Count</th>@if($achievementEditable)<th></th>@endif</tr></thead><tbody>
                                    @foreach($achievement->breakdowns as $breakdown)<tr>
                                        <td>{{ $breakdown->country ?: ($achievementTaxonomy['recs'][$breakdown->rec] ?? '—') }}</td>
                                        <td>{{ $breakdown->implementing_institution ?: '—' }}</td>
                                        <td>{{ $achievementTaxonomy['priority_themes'][$breakdown->priority_theme] ?? '—' }}</td>
                                        <td>{{ $achievementTaxonomy['genders'][$breakdown->gender] ?? '—' }}</td>
                                        <td>{{ $achievementTaxonomy['age_groups'][$breakdown->age_group] ?? '—' }}</td>
                                        <td>{{ $achievementTaxonomy['stakeholder_categories'][$breakdown->stakeholder_category] ?? '—' }}</td>
                                        <td class="text-end fw-bold">{{ number_format($breakdown->beneficiary_count) }}</td>
                                        @if($achievementEditable)<td><form method="POST" action="{{ route('budget.me.performance-reports.achievements.breakdowns.destroy',[$report,$achievement,$breakdown]) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this beneficiary combination?')"><i class="feather-trash-2"></i></button></form></td>@endif
                                    </tr>@endforeach
                                </tbody></table></div>
                            @else
                                <p class="small text-muted">No beneficiary combinations recorded. Use one row for each unique intersection, for example Ghana + Female + Youth + Government.</p>
                            @endif

                            @if($achievementEditable)
                                <form method="POST" action="{{ route('budget.me.performance-reports.achievements.breakdowns.store',[$report,$achievement]) }}" class="achievement-breakdown-form">
                                    @csrf
                                    <div><label class="form-label">Scope{{ $requiredCodes->contains('geographic_scope')?' *':'' }}</label><select name="geographic_scope" class="form-select"><option value="">Not reported</option>@foreach($achievementTaxonomy['geographic_scopes'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                                    <div><label class="form-label">Country{{ $requiredCodes->contains('country')?' *':'' }}</label><input name="country" class="form-control" list="attp-country-options" value="{{ $achievement->country }}"></div>
                                    <div><label class="form-label">REC{{ $requiredCodes->contains('rec')?' *':'' }}</label><select name="rec" class="form-select"><option value="">Not applicable</option>@foreach($achievementTaxonomy['recs'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                                    <div><label class="form-label">Institution type</label><select name="implementing_institution_type" class="form-select"><option value="">Not reported</option>@foreach($achievementTaxonomy['institution_types'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                                    <div><label class="form-label">Institution</label><input name="implementing_institution" class="form-control" value="{{ $achievement->leadThinkTank?->name }}"></div>
                                    <div><label class="form-label">Theme{{ $requiredCodes->contains('priority_theme')?' *':'' }}</label><select name="priority_theme" class="form-select"><option value="">Not reported</option>@foreach($achievementTaxonomy['priority_themes'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                                    <div><label class="form-label">Gender{{ $requiredCodes->contains('gender')?' *':'' }}</label><select name="gender" class="form-select"><option value="">Not reported</option>@foreach($achievementTaxonomy['genders'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                                    <div><label class="form-label">Age{{ $requiredCodes->contains('age_group')?' *':'' }}</label><select name="age_group" class="form-select"><option value="">Not reported</option>@foreach($achievementTaxonomy['age_groups'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                                    <div><label class="form-label">Stakeholder category{{ $requiredCodes->contains('stakeholder_category')?' *':'' }}</label><select name="stakeholder_category" class="form-select"><option value="">Not reported</option>@foreach($achievementTaxonomy['stakeholder_categories'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                                    <div><label class="form-label">Beneficiaries</label><input type="number" min="0" name="beneficiary_count" class="form-control" value="0" required></div>
                                    <div><button class="btn btn-outline-success w-100"><i class="feather-plus me-1"></i>Add combination</button></div>
                                </form>
                            @endif
                        </div>

                        <div class="achievement-subsection">
                            <h6>Evidence Repository links</h6>
                            <div class="d-grid gap-2">
                                @forelse($achievement->documentLinks as $link)
                                    @if($link->repositoryItem)<div class="achievement-doc"><div><strong>{{ $link->repositoryItem->title }}</strong><div class="small text-muted">{{ $link->repositoryItem->original_filename }} · {{ ucfirst($link->repositoryItem->validation_status) }}</div></div><div class="d-flex gap-1"><a class="btn btn-sm btn-light border" href="{{ route('budget.me.knowledge-evidence.download',$link->repositoryItem) }}"><i class="feather-download"></i></a>@if($achievementEditable)<form method="POST" action="{{ route('budget.me.performance-reports.achievements.documents.unlink',[$report,$achievement,$link]) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" title="Unlink"><i class="feather-link-2"></i></button></form>@endif</div></div>@endif
                                @empty<p class="small text-muted mb-0">No evidence linked to this achievement.</p>@endforelse
                            </div>
                            @if($achievementEditable)
                                <form method="POST" action="{{ route('budget.me.performance-reports.achievements.documents.store',[$report,$achievement]) }}" enctype="multipart/form-data" class="achievement-evidence-form mt-2">
                                    @csrf
                                    <div><label class="form-label">Document title</label><input name="document_title" class="form-control" required></div>
                                    <div><label class="form-label">Evidence file</label><input type="file" name="evidence_file" class="form-control" required></div>
                                    <button class="btn btn-outline-primary"><i class="feather-upload-cloud me-1"></i>Upload and link</button>
                                </form>
                            @endif
                        </div>

                        @if($achievementEditable)
                            <div class="text-end mt-3"><form method="POST" action="{{ route('budget.me.performance-reports.achievements.destroy',[$report,$achievement]) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this achievement and its beneficiary breakdowns? Repository files will be retained.')"><i class="feather-trash-2 me-1"></i>Delete achievement</button></form></div>
                        @endif
                    </div>
                </article>
            @empty
                <div class="p-3 text-muted small">No detailed achievements have been recorded for this indicator.</div>
            @endforelse
        </article>
    @endforeach
</section>
