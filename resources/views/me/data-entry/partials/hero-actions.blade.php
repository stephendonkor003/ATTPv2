@if ($canManage && $tab === 'submissions')
    <div class="me-hero-actions">
        <a href="{{ route('budget.me.submission-reviews.index') }}" class="me-primary-action">
            <i class="feather-check-square" aria-hidden="true"></i> Open review queue
        </a>
    </div>
@elseif ($canManage && $createTarget && ! $showFormBuilder && ! $showPeriodForm && ! $showCollectionForm)
    <div class="me-hero-actions">
        <a href="{{ $createHref }}" class="me-primary-action">
            <i class="feather-plus" aria-hidden="true"></i> {{ $createTarget['label'] }}
        </a>
    </div>
@endif
