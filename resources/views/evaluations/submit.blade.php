@extends('layouts.app')

@section('content')
    <div class="nxl-container evaluation-wrapper">

        {{-- ================= HEADER ================= --}}
        <div class="evaluation-header mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h3 class="fw-bold mb-1 text-gradient">
                    {{ $assignment->procurement->title }}
                </h3>
                <p class="text-muted mb-0">
                    Evaluation Type:
                    <span class="badge bg-primary-subtle text-primary fw-semibold">
                        {{ $assignment->evaluation->name }}
                    </span>
                </p>
            </div>

            <div class="eval-guide shadow-sm">
                <i class="feather-info me-1"></i>
                Score objectively. Evidence-based assessment only.
            </div>
        </div>

        <div class="row">

            {{-- ================= MAIN CONTENT ================= --}}
            <div class="col-lg-9">

                {{-- LOCK NOTICE --}}
                <div id="lockedNotice" class="alert alert-warning mb-4">
                    <strong>🔒 Evaluation Locked</strong><br>
                    Start the identity camera on the right to unlock scoring.
                </div>

                {{-- ================= APPLICANT INFO ================= --}}
                <div class="card soft-card mb-5">
                    <div class="card-header soft-card-header">
                        Applicant Submitted Information
                    </div>

                    <div class="card-body">

                        <div class="submission-block mb-4">
                            <div class="row small text-muted mb-3">
                                <div class="col-md-4">
                                    <strong>Submission Code</strong><br>
                                    {{ $applicant->procurement_submission_code }}
                                </div>
                                <div class="col-md-4">
                                    <strong>Applicant</strong><br>
                                    {{ optional($applicant->submitter)->name }}
                                </div>
                                <div class="col-md-4">
                                    <strong>Date</strong><br>
                                    {{ $applicant->created_at->format('d M Y, H:i') }}
                                </div>
                            </div>

                            <table class="table table-sm table-bordered align-middle">
                                <tbody>
                                    @foreach ($applicant->values as $value)
                                        @php
                                            $val = $value->value;
                                            $decoded = is_string($val) ? json_decode($val, true) : null;
                                        @endphp
                                        <tr>
                                            <th width="30%" class="bg-light fw-semibold">
                                                {{ ucwords(str_replace('_', ' ', $value->field_key)) }}
                                            </th>
                                            <td>
                                                @if (is_string($val) && Str::contains($val, 'procurement_submissions'))
                                                    <a href="{{ route('procurement.submissions.values.download', ['submission' => $applicant->id, 'value' => $value->id]) }}" target="_blank"
                                                        class="btn btn-sm btn-outline-primary me-2">View</a>
                                                    <a href="{{ route('procurement.submissions.values.download', ['submission' => $applicant->id, 'value' => $value->id, 'download' => 1]) }}" download
                                                        class="btn btn-sm btn-outline-secondary">Download</a>
                                                @elseif (is_array($decoded))
                                                    @foreach ($decoded as $item)
                                                        <span
                                                            class="badge bg-secondary-subtle text-dark me-1">{{ $item }}</span>
                                                    @endforeach
                                                @else
                                                    {{ $val }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                        </div>
                    </div>
                </div>

                {{-- ================= SCORING FORM ================= --}}
                <form method="POST" action="{{ route('eval.assign.submit', [$assignment->id, $applicant->id]) }}"
                    enctype="multipart/form-data" id="finalForm">

                    @csrf

                    {{-- Critical --}}
                    <input type="hidden" name="form_submission_id" value="{{ $applicant->id }}">

                    <div id="evaluationForm" class="d-none">

                        @php
                            $isGoods = $assignment->evaluation->type === 'goods';
                        @endphp

                        @foreach ($assignment->evaluation->sections as $i => $section)
                            <div class="card soft-card mb-4 evaluation-section">

                                <div class="card-header bg-transparent fw-bold">
                                    {{ $section->name }}
                                </div>

                                <div class="card-body">

                                    {{-- ================= CRITERIA TABLE ================= --}}
                                    <table class="table table-sm table-bordered align-middle soft-table">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Criteria</th>
                                                <th>Description</th>

                                                @if (!$isGoods)
                                                    <th width="80">Max</th>
                                                    <th width="120">Score</th>
                                                @else
                                                    <th width="120">Decision</th>
                                                    <th>Comment</th>
                                                @endif
                                            </tr>
                                        </thead>

                                        <tbody>
                                            @foreach ($section->criteria as $criteria)
                                                @php
                                                    $saved = $submission?->criteriaScores->firstWhere(
                                                        'evaluation_criteria_id',
                                                        $criteria->id,
                                                    );
                                                @endphp

                                                <tr>
                                                    <td class="fw-semibold">{{ $criteria->name }}</td>
                                                    <td class="text-muted">{{ $criteria->description }}</td>

                                                    {{-- ================= SERVICES ================= --}}
                                                    @if (!$isGoods)
                                                        <td class="text-center fw-bold">{{ $criteria->max_score }}</td>
                                                        <td>
                                                            <input type="number" name="criteria[{{ $criteria->id }}]"
                                                                class="form-control form-control-sm score-input"
                                                                min="0" max="{{ $criteria->max_score }}"
                                                                step="0.01" data-max="{{ $criteria->max_score }}"
                                                                value="{{ $saved?->score }}" required>
                                                        </td>

                                                        {{-- ================= GOODS ================= --}}
                                                    @else
                                                        <td>
                                                            <select name="criteria[{{ $criteria->id }}][decision]"
                                                                class="form-select form-select-sm" required>
                                                                <option value="">Select</option>
                                                                <option value="1" @selected($saved?->decision === 1)>YES
                                                                </option>
                                                                <option value="0" @selected($saved?->decision === 0)>NO
                                                                </option>
                                                            </select>
                                                        </td>

                                                        <td>
                                                            <textarea name="criteria[{{ $criteria->id }}][comment]" class="form-control form-control-sm" rows="2"
                                                                placeholder="Evaluator comment…" required>{{ $saved?->comment }}</textarea>
                                                        </td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>

                                    {{-- ================= SECTION NOTES ================= --}}
                                    @php
                                        $sectionScore = $submission?->sectionScores->firstWhere(
                                            'evaluation_section_id',
                                            $section->id,
                                        );
                                    @endphp

                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Section Strengths</label>
                                            <textarea name="sections[{{ $section->id }}][strengths]" class="form-control" rows="3" required>{{ $sectionScore?->strengths }}</textarea>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Section Weaknesses</label>
                                            <textarea name="sections[{{ $section->id }}][weaknesses]" class="form-control" rows="3" required>{{ $sectionScore?->weaknesses }}</textarea>
                                        </div>
                                    </div>

                                    {{-- SERVICES ONLY: HIDDEN SCORE --}}
                                    @if (!$isGoods)
                                        <input type="hidden" name="sections[{{ $section->id }}][score]"
                                            class="section-score-input" value="0">
                                    @endif

                                </div>
                            </div>
                        @endforeach

                        {{-- ================= OVERALL (SERVICES ONLY) ================= --}}
                        @if (!$isGoods)
                            <div class="card soft-card mb-4 border-dark">
                                <div class="card-body text-end fw-bold fs-5">
                                    Overall Score:
                                    <span id="overallScore" class="text-primary">0</span>
                                </div>
                            </div>
                        @endif

                        {{-- VIDEO --}}
                        <input type="file" name="video" id="finalVideo" hidden required>

                        <button class="btn btn-success btn-lg px-5 shadow-sm">
                            Submit Final Evaluation
                        </button>
                    </div>

                </form>
            </div>

            {{-- ================= RIGHT SIDEBAR ================= --}}
            <div class="col-lg-3">

                {{-- MONITOR --}}
                <div class="card soft-card mb-3">
                    <div class="card-header fw-semibold">
                        <i class="feather-activity me-1"></i> Evaluation Monitor
                    </div>
                    <div class="card-body small">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Date</span><strong id="currentDate">—</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Time</span><strong id="currentTime">—</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Status</span><strong id="evalStatus" class="text-danger">Locked</strong>
                        </div>
                    </div>
                </div>

                {{-- CAMERA --}}
                <div class="card soft-card">
                    <div class="card-header fw-semibold">
                        <span id="cameraStatus" class="camera-status idle"></span>
                        Identity Verification
                    </div>

                    <div class="card-body">
                        <video id="preview" autoplay muted playsinline class="w-100 mb-2"></video>

                        <button id="startCamera" class="btn btn-outline-primary btn-sm w-100 mb-2">
                            Start Camera
                        </button>

                        <button id="stopCamera" class="btn btn-outline-danger btn-sm w-100 d-none">
                            Stop Recording (15s)
                        </button>

                        <small class="text-muted d-block text-center mt-2">
                            Video verification required before submission
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* ================= GENERAL CARD ================= */
        .soft-card {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 6px 18px rgba(0, 0, 0, .05);
        }

        /* ================= MONITOR ================= */
        .info-header {
            background: linear-gradient(135deg, #2563eb, #16a34a);
            color: #fff;
            padding: 12px 14px;
            border-radius: 14px 14px 0 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-body {
            padding: 14px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .info-item span {
            color: #475569;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-item strong {
            font-weight: 600;
        }

        .info-item.highlight strong {
            font-size: 18px;
            color: #dc2626;
        }

        .info-note {
            font-size: 12px;
            color: #64748b;
            line-height: 1.4;
            display: flex;
            gap: 6px;
        }

        /* ================= CAMERA ================= */
        .camera-header {
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .camera-status {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .camera-status.idle {
            background: #94a3b8;
        }

        .camera-status.recording {
            background: #dc2626;
            box-shadow: 0 0 0 6px rgba(220, 38, 38, .2);
        }

        .camera-frame {
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            background: #000;
        }

        .camera-frame video {
            width: 100%;
        }

        .camera-actions {
            margin-top: 10px;
        }

        .camera-actions button {
            margin-bottom: 6px;
        }

        .camera-note {
            font-size: 12px;
            color: #64748b;
            text-align: center;
        }

        .text-gradient {
            background: linear-gradient(90deg, #2563eb, #16a34a);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .soft-card {
            border-radius: 14px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 8px 25px rgba(0, 0, 0, .04);
        }

        .soft-card-header {
            background: #f8fafc;
            font-weight: 600;
        }

        .soft-table th,
        .soft-table td {
            vertical-align: middle;
        }

        .eval-guide {
            background: linear-gradient(135deg, #eff6ff, #ecfdf5);
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 14px;
        }

        .info-panel .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .camera-panel video {
            width: 100%;
            border-radius: 10px;
        }
    </style>
    {{-- ================= JS ================= --}}
    <script>
        /*********************************************************
         * CONFIG (SERVER-DRIVEN MODE)
         *********************************************************/
        const EVALUATION_TYPE = "{{ $assignment->evaluation->type }}"; // services | goods
        const IS_SERVICES = EVALUATION_TYPE === 'services';

        /*********************************************************
         * HELPERS
         *********************************************************/
        const byId = id => document.getElementById(id);

        /*********************************************************
         * DOM REFERENCES
         *********************************************************/
        const currentDate = byId('currentDate');
        const currentTime = byId('currentTime');
        const startCamera = byId('startCamera');
        const stopCamera = byId('stopCamera');
        const preview = byId('preview');
        const finalVideo = byId('finalVideo');
        const lockedNotice = byId('lockedNotice');
        const evaluationForm = byId('evaluationForm');
        const overallScore = byId('overallScore');
        const evalStatus = byId('evalStatus');
        const cameraStatus = byId('cameraStatus');
        const finalForm = byId('finalForm');

        /*********************************************************
         * CLOCK
         *********************************************************/
        setInterval(() => {
            const now = new Date();
            if (currentDate) currentDate.textContent = now.toDateString();
            if (currentTime) currentTime.textContent = now.toLocaleTimeString();
        }, 1000);

        /*********************************************************
         * CAMERA RECORDING (IDENTITY LOCK)
         *********************************************************/
        let recorder = null;
        let chunks = [];
        let stream = null;

        startCamera?.addEventListener('click', async () => {
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: true,
                    audio: true
                });

                preview.srcObject = stream;

                recorder = new MediaRecorder(stream);
                recorder.start();
                chunks = [];

                startCamera.classList.add('d-none');
                stopCamera.classList.remove('d-none');

                cameraStatus.className = 'camera-status recording';
                evalStatus.textContent = 'Unlocked';

                recorder.ondataavailable = e => chunks.push(e.data);

                // Auto-stop after 15 seconds
                setTimeout(() => {
                    if (recorder && recorder.state === 'recording') {
                        stopCamera.click();
                    }
                }, 15000);

            } catch (err) {
                alert('Camera access denied.');
                console.error(err);
            }
        });

        stopCamera?.addEventListener('click', () => {
            if (!recorder) return;

            recorder.stop();
            stream?.getTracks().forEach(t => t.stop());
            preview.srcObject = null;

            recorder.onstop = () => {
                const blob = new Blob(chunks, {
                    type: 'video/webm'
                });
                const file = new File([blob], 'identity.webm');

                const dt = new DataTransfer();
                dt.items.add(file);
                finalVideo.files = dt.files;

                lockedNotice?.classList.add('d-none');
                evaluationForm?.classList.remove('d-none');
                stopCamera.classList.add('d-none');
            };
        });

        /*********************************************************
         * SERVICES ONLY: LIVE SCORING
         *********************************************************/
        function recalcScores() {
            if (!IS_SERVICES) return;

            let overall = 0;

            document.querySelectorAll('.evaluation-section').forEach(section => {
                let sectionTotal = 0;

                section.querySelectorAll('.score-input').forEach(input => {
                    let val = parseFloat(input.value) || 0;
                    const max = parseFloat(input.dataset.max || 0);

                    if (val > max) {
                        val = max;
                        input.value = max;
                    }

                    sectionTotal += val;
                });

                // Display section total
                const totalLabel = section.querySelector('.section-total');
                if (totalLabel) {
                    totalLabel.textContent = sectionTotal.toFixed(2);
                }

                // Write into hidden input for backend
                const hidden = section.querySelector('.section-score-input');
                if (hidden) {
                    hidden.value = sectionTotal.toFixed(2);
                }

                overall += sectionTotal;
            });

            if (overallScore) {
                overallScore.textContent = overall.toFixed(2);
            }
        }

        // Live recalculation (SERVICES ONLY)
        if (IS_SERVICES) {
            document.addEventListener('input', e => {
                if (e.target.classList.contains('score-input')) {
                    recalcScores();
                }
            });
        }

        /*********************************************************
         * FINAL SUBMIT GUARANTEE
         *********************************************************/
        finalForm?.addEventListener('submit', function(e) {

            // 🔒 Camera is mandatory for ALL evaluation types
            if (!finalVideo || !finalVideo.files.length) {
                e.preventDefault();
                alert('Please complete identity verification before submitting.');
                return;
            }

            // 🔢 Ensure totals are finalized (SERVICES ONLY)
            if (IS_SERVICES) {
                recalcScores();
            }
        });
    </script>
@endsection
