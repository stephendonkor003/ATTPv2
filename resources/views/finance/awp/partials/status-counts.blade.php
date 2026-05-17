<span class="status-count text-success">Approved: {{ number_format($counts['approved'] ?? 0) }}</span>
<span class="status-count text-warning">Not approved by World Bank: {{ number_format($counts['pending'] ?? 0) }}</span>
<span class="status-count text-primary">Needs revision: {{ number_format($counts['needs_revision'] ?? 0) }}</span>
<span class="status-count text-danger">Rejected: {{ number_format($counts['rejected'] ?? 0) }}</span>
