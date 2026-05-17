<x-think-tank.partials.shell :member="$member" title="Applications">
    <div class="top">
        <div>
            <h1>Applications</h1>
            <p class="sub">{{ $procurement->title }}. Evaluate and select inside the Think Tank portal.</p>
        </div>
        <a class="btn light" href="{{ route('think-tank.procurement') }}">Back</a>
    </div>

    <section class="card">
        <table>
            <thead><tr><th>Applicant</th><th>Status</th><th>Scores</th><th>Submission Values</th><th>Evaluate</th><th>Select</th></tr></thead>
            <tbody>
            @forelse($procurement->submissions as $submission)
                <tr>
                    <td>
                        {{ $submission->submitter?->name ?? 'Applicant' }}<br>
                        <span class="label">{{ $submission->submitter?->email }}</span>
                    </td>
                    <td><span class="badge">{{ $submission->status }}</span></td>
                    <td>
                        @if($submission->thinkTankReview)
                            Technical: {{ $submission->thinkTankReview->technical_score }}<br>
                            Financial: {{ $submission->thinkTankReview->financial_score }}<br>
                            Total: {{ $submission->thinkTankReview->total_score }}
                        @else
                            Not evaluated
                        @endif
                    </td>
                    <td>
                        @foreach($submission->values as $value)
                            <strong>{{ str_replace('_', ' ', $value->field_key) }}:</strong>
                            {{ \Illuminate\Support\Str::limit($value->value, 45) }}<br>
                        @endforeach
                    </td>
                    <td>
                        <form class="stack" method="POST" action="{{ route('think-tank.procurement.submissions.review', [$procurement, $submission]) }}">
                            @csrf
                            <input type="number" min="0" max="100" step="0.01" name="technical_score" value="{{ $submission->thinkTankReview?->technical_score ?? 0 }}" placeholder="Technical">
                            <input type="number" min="0" max="100" step="0.01" name="financial_score" value="{{ $submission->thinkTankReview?->financial_score ?? 0 }}" placeholder="Financial">
                            <select name="recommendation">
                                @foreach(['pending', 'shortlisted', 'recommended', 'rejected'] as $state)
                                    <option value="{{ $state }}" @selected($submission->thinkTankReview?->recommendation === $state)>{{ ucfirst($state) }}</option>
                                @endforeach
                            </select>
                            <textarea name="comments" placeholder="Evaluation comments">{{ $submission->thinkTankReview?->comments }}</textarea>
                            <button class="btn" type="submit">Save</button>
                        </form>
                    </td>
                    <td>
                        @if($procurement->awarded_submission_id === $submission->id)
                            <span class="badge good">Selected</span>
                        @else
                            <form method="POST" action="{{ route('think-tank.procurement.submissions.select', [$procurement, $submission]) }}">
                                @csrf
                                <button class="btn secondary" type="submit">Select</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">No applications received yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
</x-think-tank.partials.shell>
