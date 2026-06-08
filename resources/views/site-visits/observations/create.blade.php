@extends('layouts.app')
@section('title', 'Add Observation')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">

            {{-- ================= HEADER ================= --}}
            <div class="page-header">
                <div class="page-header-left">
                    <h5 class="m-b-10">Add Observation</h5>
                    <p class="text-muted mb-0">
                        Site Visit:
                        <strong>{{ $siteVisit->submission?->display_name ?? '-' }}</strong>
                    </p>
                </div>
            </div>

            <div class="main-content">
                <div class="card">
                    <div class="card-body">

                        {{-- ================= ERRORS ================= --}}
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- ================= AI GUIDE ================= --}}
                        <div class="alert alert-info">
                            <strong>Observation Guidance</strong>
                            <ul class="mb-0">
                                <li>Be specific and factual</li>
                                <li>Reference visible site conditions</li>
                                <li>Avoid assumptions or opinions</li>
                                <li>Use severity honestly — it drives decisions</li>
                            </ul>
                        </div>

                        <form method="POST" action="{{ route('site-visits.observations.store', $siteVisit) }}">
                            @csrf

                            {{-- ================= CATEGORY ================= --}}
                            <div class="mb-3">
                                <label class="form-label">Observation Category</label>
                                <select name="category" id="category" class="form-control" required>
                                    <option value="">-- Select Category --</option>
                                    <option value="Safety">Safety</option>
                                    <option value="Quality">Quality</option>
                                    <option value="Progress">Progress</option>
                                    <option value="Compliance">Compliance</option>
                                    <option value="Documentation">Documentation</option>
                                </select>
                                <small class="text-muted">
                                    Choose the category that best describes the issue
                                </small>
                            </div>

                            {{-- ================= DESCRIPTION ================= --}}
                            <div class="mb-3">
                                <label class="form-label">Observation Description</label>
                                <textarea name="description" id="description" rows="5" class="form-control"
                                    placeholder="Describe what was observed on site..." required>{{ old('description') }}</textarea>

                                <small id="ai_hint" class="text-muted d-block mt-1">
                                    Tip: Mention location, activity, and condition.
                                </small>
                            </div>

                            {{-- ================= SEVERITY ================= --}}
                            <div class="mb-3">
                                <label class="form-label">Severity Level</label>
                                <select name="severity" id="severity" class="form-control" required>
                                    <option value="">-- Select Severity --</option>
                                    <option value="low">Low — Minor issue</option>
                                    <option value="medium">Medium — Needs attention</option>
                                    <option value="high">High — Immediate action required</option>
                                </select>
                            </div>

                            {{-- ================= ACTION REQUIRED ================= --}}
                            <div class="form-check mb-3">
                                <input type="checkbox" name="action_required" value="1" class="form-check-input"
                                    id="action_required">
                                <label class="form-check-label" for="action_required">
                                    Corrective action required
                                </label>
                            </div>

                            {{-- ================= AI AUTOMATION MESSAGE ================= --}}
                            <div id="ai_analysis" class="alert alert-warning" style="display:none;">
                            </div>

                            {{-- ================= BUTTONS ================= --}}
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    Save Observation
                                </button>

                                <a href="{{ route('site-visits.show', $siteVisit) }}"
                                    class="btn btn-outline-secondary ms-2">
                                    Cancel
                                </a>
                            </div>

                        </form>

                    </div>
                </div>
            </div>

        </div>
    </main>

    {{-- ================= JAVASCRIPT (SMART UX) ================= --}}
    <script>
        const category = document.getElementById('category');
        const severity = document.getElementById('severity');
        const description = document.getElementById('description');
        const aiHint = document.getElementById('ai_hint');
        const aiAnalysis = document.getElementById('ai_analysis');

        /* ================= CONTEXTUAL HINTS ================= */
        category.addEventListener('change', () => {
            const hints = {
                Safety: 'Mention hazards, PPE, unsafe behavior, or conditions.',
                Quality: 'Reference workmanship, materials, or standards.',
                Progress: 'Compare observed progress against expected status.',
                Compliance: 'State which rule, drawing, or approval is affected.',
                Documentation: 'Mention missing, incorrect, or outdated records.'
            };

            aiHint.textContent = hints[category.value] || '';
        });

        /* ================= AUTO-SEVERITY SUGGESTION ================= */
        description.addEventListener('input', () => {
            const text = description.value.toLowerCase();

            if (text.includes('unsafe') || text.includes('accident') || text.includes('critical')) {
                severity.value = 'high';
            } else if (text.includes('delay') || text.includes('issue')) {
                severity.value = 'medium';
            }
        });

        /* ================= AI-LIKE ANALYSIS ================= */
        severity.addEventListener('change', () => {
            if (severity.value === 'high') {
                aiAnalysis.style.display = 'block';
                aiAnalysis.innerHTML = `
            <strong>Attention:</strong>
            High severity observations usually require
            immediate corrective action and follow-up.
        `;
            } else {
                aiAnalysis.style.display = 'none';
            }
        });
    </script>
@endsection
