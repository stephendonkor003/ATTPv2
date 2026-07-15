@php
    $portfolioOptions = $portfolioOptions ?? collect();
    $selectedPortfolioId = old('portfolio_id', $selectedPortfolioId ?? null);
    $portfolioFieldLocked = $portfolioFieldLocked ?? false;
    $selectedPortfolio = $portfolioOptions->firstWhere('id', $selectedPortfolioId);
@endphp

<div class="col-md-6">
    <label class="form-label">Portfolio <span class="text-danger">*</span></label>

    @if ($portfolioFieldLocked && $selectedPortfolio)
        <input type="hidden" name="portfolio_id" value="{{ $selectedPortfolio->id }}">
        <div class="form-control bg-light fw-semibold">{{ $selectedPortfolio->name }}</div>
        <small class="text-muted">This M&E record is tied to your assigned portfolio.</small>
    @else
        <select name="portfolio_id" class="form-select @error('portfolio_id') is-invalid @enderror" required>
            <option value="">Select portfolio</option>
            @foreach ($portfolioOptions as $portfolio)
                <option value="{{ $portfolio->id }}" @selected((string) $selectedPortfolioId === (string) $portfolio->id)>
                    {{ $portfolio->name }}
                </option>
            @endforeach
        </select>
        <small class="text-muted">M&E configuration is portfolio-specific; no shared global records are used.</small>
    @endif

    @error('portfolio_id')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
