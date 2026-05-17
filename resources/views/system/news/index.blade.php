@extends('layouts.app')

@section('title', 'News Administration')

@section('content')
    <div class="nxl-container">
        <div class="card mb-4 border-0"
            style="background: linear-gradient(120deg, #0f172a, #0f766e 55%, #0ea5e9); color: #fff; border-radius: 14px;">
            <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <div class="text-uppercase small text-white-50 fw-semibold">Back-Office Communications</div>
                    <h4 class="fw-bold mb-1 text-white">News Administration</h4>
                    <p class="mb-0 text-white-50">Draft, submit, approve, and publish ATTP news on the public news page.</p>
                </div>
                @can('news.manage')
                    <a href="{{ route('system.news.create') }}" class="btn btn-light">
                        <i class="feather-plus-circle me-1"></i> New Post
                    </a>
                @endcan
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>
        @endif

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('system.news.index') }}" class="row g-2 align-items-end">
                    <div class="col-md-4 col-lg-3">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All statuses</option>
                            @foreach (['draft', 'submitted', 'approved', 'published', 'rejected'] as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>
                                    {{ ucfirst($status) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary">
                            <i class="feather-filter me-1"></i> Filter
                        </button>
                    </div>
                    <div class="col-auto">
                        <a class="btn btn-light border" href="{{ route('system.news.index') }}">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">
                    <i class="feather-send me-1"></i> News Posting Register
                </h5>
                <span class="badge bg-light text-dark">{{ $posts->total() }} posts</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Title</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th class="text-center">Attachments</th>
                                <th>Published</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($posts as $post)
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-semibold text-dark">{{ $post->title }}</div>
                                        <small class="text-muted">{{ $post->slug }}</small>
                                    </td>
                                    <td>{{ ucfirst($post->category) }}</td>
                                    <td>
                                        <span class="badge text-capitalize bg-{{ $post->status === 'published' ? 'success' : ($post->status === 'rejected' ? 'danger' : ($post->status === 'approved' ? 'primary' : 'secondary')) }}">
                                            {{ $post->status }}
                                        </span>
                                    </td>
                                    <td class="text-center">{{ $post->attachments_count }}</td>
                                    <td>{{ optional($post->published_at)->format('d M Y H:i') ?? 'Not published' }}</td>
                                    <td class="text-end pe-4">
                                        <a class="btn btn-sm btn-light border" href="{{ route('system.news.edit', $post) }}">
                                            <i class="feather-edit-2 me-1"></i> Edit / Review
                                        </a>
                                        @if ($post->isPublished())
                                            <a class="btn btn-sm btn-primary" href="{{ route('news.show', $post) }}" target="_blank">
                                                <i class="feather-external-link me-1"></i> Public
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">No news posts yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($posts->hasPages())
                <div class="card-footer bg-white">
                    {{ $posts->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
