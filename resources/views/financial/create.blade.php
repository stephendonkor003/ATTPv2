@extends('layouts.app')
@section('title', 'Financial Evaluation')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4>Financial Evaluation</h4>
                <h6>{{ $applicant->think_tank_name }} ({{ $applicant->country }})</h6>
            </div>
            <a href="{{ route('financial.index') }}" class="btn btn-secondary">← Back</a>
        </div>

        {{-- Applicant Financial Documents --}}
        <div class="card mb-4">
            <div class="card-header bg-light fw-bold">Supporting Financial Documents</div>
            <div class="card-body">
                <ul class="list-group list-group-flush">

                    {{-- Work Plan & Budget --}}
                    @if ($applicant->work_plan_budget)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="feather-file-text text-primary me-2"></i> Work Plan & Budget</span>
                            <span>
                                <a href="{{ route('applicants.documents.download', ['applicant' => $applicant->id, 'field' => 'work_plan_budget']) }}" target="_blank"
                                    class="btn btn-sm btn-outline-primary me-2">
                                    <i class="feather-eye"></i> View
                                </a>
                                <a href="{{ route('applicants.documents.download', ['applicant' => $applicant->id, 'field' => 'work_plan_budget', 'download' => 1]) }}" download
                                    class="btn btn-sm btn-outline-success">
                                    <i class="feather-download"></i> Download
                                </a>
                            </span>
                        </li>
                    @endif

                    {{-- Audited Reports --}}
                    @if ($applicant->audited_reports)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="feather-file-text text-primary me-2"></i> Audited Reports</span>
                            <span>
                                <a href="{{ route('applicants.documents.download', ['applicant' => $applicant->id, 'field' => 'audited_reports']) }}" target="_blank"
                                    class="btn btn-sm btn-outline-primary me-2">
                                    <i class="feather-eye"></i> View
                                </a>
                                <a href="{{ route('applicants.documents.download', ['applicant' => $applicant->id, 'field' => 'audited_reports', 'download' => 1]) }}" download
                                    class="btn btn-sm btn-outline-success">
                                    <i class="feather-download"></i> Download
                                </a>
                            </span>
                        </li>
                    @endif

                    {{-- If no documents --}}
                    @if (!$applicant->work_plan_budget && !$applicant->audited_reports)
                        <li class="list-group-item text-muted">
                            <i class="feather-info me-1"></i> No supporting documents uploaded.
                        </li>
                    @endif

                </ul>
            </div>
        </div>


        <div class="card watermark">
            <div class="card-body">
                <form action="{{ route('financial.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="applicant_id" value="{{ $applicant->id }}">

                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:5%">Sn</th>
                                <th style="width:30%">Criteria</th>
                                <th>Evaluation</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Criterion 1 --}}
                            <tr>
                                <td>1</td>
                                <td>Financial Health and Stability</td>
                                <td>
                                    <label class="fw-bold">Strength</label>
                                    <textarea name="strength_financial_health" class="editor mb-2"></textarea>
                                    <label class="fw-bold">Gap</label>
                                    <textarea name="gap_financial_health" class="editor"></textarea>
                                </td>
                            </tr>

                            {{-- Criterion 2 --}}
                            <tr>
                                <td>2</td>
                                <td>Accuracy and Completeness</td>
                                <td>
                                    <label class="fw-bold">Strength</label>
                                    <textarea name="strength_accuracy" class="editor mb-2"></textarea>
                                    <label class="fw-bold">Gap</label>
                                    <textarea name="gap_accuracy" class="editor"></textarea>
                                </td>
                            </tr>

                            {{-- Criterion 3 --}}
                            <tr>
                                <td>3</td>
                                <td>Revenue Sources and Diversification</td>
                                <td>
                                    <label class="fw-bold">Strength</label>
                                    <textarea name="strength_revenue" class="editor mb-2"></textarea>
                                    <label class="fw-bold">Gap</label>
                                    <textarea name="gap_revenue" class="editor"></textarea>
                                </td>
                            </tr>

                            {{-- Criterion 4 --}}
                            <tr>
                                <td>4</td>
                                <td>Use of Funds and Compliance</td>
                                <td>
                                    <label class="fw-bold">Strength</label>
                                    <textarea name="strength_fund_use" class="editor mb-2"></textarea>
                                    <label class="fw-bold">Gap</label>
                                    <textarea name="gap_fund_use" class="editor"></textarea>
                                </td>
                            </tr>

                            {{-- Criterion 5 --}}
                            <tr>
                                <td>5</td>
                                <td>Liabilities and Risks</td>
                                <td>
                                    <label class="fw-bold">Strength</label>
                                    <textarea name="strength_liabilities" class="editor mb-2"></textarea>
                                    <label class="fw-bold">Gap</label>
                                    <textarea name="gap_liabilities" class="editor"></textarea>
                                </td>
                            </tr>

                            {{-- Criterion 6 --}}
                            <tr>
                                <td>6</td>
                                <td>Compliance with Funding Limitations</td>
                                <td>
                                    <label class="fw-bold">Strength</label>
                                    <textarea name="strength_compliance" class="editor mb-2"></textarea>
                                    <label class="fw-bold">Gap</label>
                                    <textarea name="gap_compliance" class="editor"></textarea>
                                </td>
                            </tr>

                            {{-- Criterion 7 --}}
                            <tr>
                                <td>7</td>
                                <td><strong>Overall Assessment</strong></td>
                                <td>
                                    <textarea name="overall_financial_assessment" class="editor"></textarea>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    {{-- Status --}}
                    <div class="mt-3">
                        <label class="fw-bold">Status</label>
                        <select name="status" class="form-control" required>
                            <option value="">-- Select --</option>
                            {{-- <option value="draft">Save as Draft</option> --}}
                            <option value="submitted">Submit Evaluation</option>
                        </select>
                    </div>

                    {{-- Actions --}}
                    <div class="mt-4">
                        <button type="submit" class="btn btn-success">Save Financial Evaluation</button>
                        <a href="{{ route('financial.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- CKEditor --}}
    <script src="https://cdn.ckeditor.com/ckeditor5/41.2.0/classic/ckeditor.js"></script>
    <script>
        let editors = {};
        document.querySelectorAll('.editor').forEach(el => {
            ClassicEditor.create(el).then(editor => {
                editors[el.name] = editor;
                el.removeAttribute('required');
                el.dataset.required = "true";
            }).catch(err => console.error(err));
        });

        // Sync editors before submit
        document.querySelector('form').addEventListener('submit', function() {
            for (let name in editors) {
                const editor = editors[name];
                const textarea = document.querySelector(`[name="${name}"]`);
                if (textarea) textarea.value = editor.getData().trim();
            }
        });
    </script>
@endsection
