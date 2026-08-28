@if (!empty($proposalTarget) && $proposalTarget['proposal_submission'] && $proposalTarget['candidate'])
    @php
        $proposalRound = $proposalTarget['round'];
        $proposalCandidate = $proposalTarget['candidate'];
        $proposalSubmission = $proposalTarget['proposal_submission'];
        $proposalDocuments = $proposalSubmission->documents ?? collect();
    @endphp

    <section class="proposal-dossier mb-4" aria-labelledby="proposal-dossier-title">
        <header class="proposal-dossier-heading">
            <span><i class="feather-folder" aria-hidden="true"></i></span>
            <div>
                <small>Evidence being evaluated</small>
                <h2 id="proposal-dossier-title">Technical proposal dossier</h2>
                <p>{{ $proposalRound?->title ?: 'Technical proposal round' }} · Revision {{ $proposalSubmission->revision_number }}</p>
            </div>
            <b><i class="feather-check-circle" aria-hidden="true"></i> Compliance qualified</b>
        </header>

        <div class="proposal-dossier-facts">
            <div><span>Proposal round</span><strong>Round {{ $proposalRound?->round_number ?? '—' }}</strong></div>
            <div><span>Received through</span><strong>{{ \Illuminate\Support\Str::headline($proposalSubmission->received_via) }}</strong></div>
            <div><span>Received</span><strong>{{ $proposalSubmission->received_at?->format('d M Y, H:i') ?? 'Date unavailable' }}</strong></div>
            <div><span>Documents</span><strong>{{ number_format($proposalDocuments->count()) }}</strong></div>
        </div>

        @if (filled($proposalSubmission->cover_note))
            <div class="proposal-cover-note">
                <strong>Applicant cover note</strong>
                <p>{{ $proposalSubmission->cover_note }}</p>
            </div>
        @endif

        <div class="proposal-document-grid">
            @forelse ($proposalDocuments as $document)
                <article class="proposal-document">
                    <span class="proposal-document-icon"><i class="feather-file" aria-hidden="true"></i></span>
                    <div>
                        <strong>{{ $document->document_label ?: $document->original_filename }}</strong>
                        <small>{{ $document->original_filename }} · {{ strtoupper($document->extension ?: 'FILE') }} · {{ number_format(((int) $document->file_size) / 1024, 1) }} KB</small>
                    </div>
                    <a href="{{ route('my.eval.proposal-document', [$assignment, $proposalCandidate, $proposalSubmission, $document]) }}"
                        target="_blank" rel="noopener">
                        <i class="feather-download" aria-hidden="true"></i> Open
                    </a>
                </article>
            @empty
                <div class="proposal-document-empty">
                    <i class="feather-alert-circle" aria-hidden="true"></i>
                    The accepted proposal revision has no accessible document.
                </div>
            @endforelse
        </div>
    </section>

    @once
        @push('styles')
            <style>
                .proposal-dossier { overflow:hidden; border:1px solid #b8d6ca; border-radius:15px; background:#fff; box-shadow:0 8px 24px rgba(15,95,67,.07); }
                .proposal-dossier-heading { display:flex; align-items:center; gap:.75rem; padding:1rem 1.1rem; color:#123e31; border-bottom:1px solid #d8ebe3; background:linear-gradient(135deg,#effaf5,#f8fcfa); }
                .proposal-dossier-heading>span { display:grid; width:42px; height:42px; flex:0 0 42px; place-items:center; color:#08754d; border-radius:11px; background:#dff5ea; }
                .proposal-dossier-heading>div { min-width:0; flex:1; }
                .proposal-dossier-heading small { display:block; color:#56806f; font-size:.67rem; font-weight:760; letter-spacing:.08em; text-transform:uppercase; }
                .proposal-dossier-heading h2 { margin:.08rem 0; color:#123e31; font-size:1rem; font-weight:780; }
                .proposal-dossier-heading p { margin:0; color:#668679; font-size:.72rem; }
                .proposal-dossier-heading>b { display:inline-flex; flex:0 0 auto; align-items:center; gap:.32rem; padding:.42rem .55rem; color:#067647; border:1px solid #a8ddc4; border-radius:8px; background:#fff; font-size:.66rem; }
                .proposal-dossier-facts { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.65rem; padding:.9rem 1rem; }
                .proposal-dossier-facts>div { padding:.65rem .72rem; border:1px solid #e2ebe7; border-radius:9px; background:#fbfdfc; }
                .proposal-dossier-facts span,.proposal-dossier-facts strong { display:block; }
                .proposal-dossier-facts span { margin-bottom:.18rem; color:#718079; font-size:.62rem; font-weight:700; text-transform:uppercase; }
                .proposal-dossier-facts strong { color:#263f36; font-size:.75rem; overflow-wrap:anywhere; }
                .proposal-cover-note { margin:0 1rem .85rem; padding:.72rem .78rem; border-left:3px solid #47aa80; border-radius:0 8px 8px 0; background:#f5faf8; }
                .proposal-cover-note strong { color:#285443; font-size:.7rem; }
                .proposal-cover-note p { margin:.2rem 0 0; color:#65776f; font-size:.7rem; line-height:1.5; white-space:pre-line; }
                .proposal-document-grid { display:grid; gap:.55rem; padding:0 1rem 1rem; }
                .proposal-document { display:flex; min-width:0; align-items:center; gap:.65rem; padding:.7rem .75rem; border:1px solid #e1e8e5; border-radius:10px; background:#fff; }
                .proposal-document-icon { display:grid; width:34px; height:34px; flex:0 0 34px; place-items:center; color:#08754d; border-radius:8px; background:#eaf8f1; }
                .proposal-document>div { min-width:0; flex:1; }
                .proposal-document strong,.proposal-document small { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
                .proposal-document strong { color:#34453e; font-size:.73rem; }
                .proposal-document small { margin-top:.13rem; color:#7b8983; font-size:.62rem; }
                .proposal-document>a { display:inline-flex; flex:0 0 auto; align-items:center; gap:.3rem; padding:.42rem .55rem; color:#067647; border:1px solid #a8d9c2; border-radius:7px; background:#f4fbf7; font-size:.65rem; font-weight:730; text-decoration:none; }
                .proposal-document>a:hover { color:#045f39; background:#e7f7ef; }
                .proposal-document-empty { padding:.8rem; color:#9a5b18; border:1px solid #efd4aa; border-radius:9px; background:#fff9ee; font-size:.7rem; }
                @media (max-width:767.98px) { .proposal-dossier-heading { align-items:flex-start; flex-wrap:wrap; } .proposal-dossier-heading>b { margin-left:3.55rem; } .proposal-dossier-facts { grid-template-columns:repeat(2,minmax(0,1fr)); } }
                @media (max-width:575.98px) { .proposal-dossier-facts { grid-template-columns:1fr; } .proposal-document { align-items:flex-start; flex-wrap:wrap; } .proposal-document>a { width:100%; justify-content:center; } }
            </style>
        @endpush
    @endonce
@endif
