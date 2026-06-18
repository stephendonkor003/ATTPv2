<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News & Updates | ATTP</title>
    <meta name="description" content="Approved ATTP announcements, policy updates, research news, events, and public communications.">
    <link rel="icon" href="{{ asset('assets/images/au.png') }}" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/style.css') }}">
    <style>
        :root {
            --au-green:      #006B3F;
            --au-green-dark: #004d2e;
            --au-green-light:#009A44;
            --gold:          #fbbc05;
            --light:         #f0f5f1;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', Arial, sans-serif; background: var(--light); color: #1a2e22; }

        /* ── NAVBAR ── */
        .navbar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 5%; background: var(--au-green);
            box-shadow: 0 2px 12px rgba(0,0,0,.2);
            position: sticky; top: 0; z-index: 100;
        }
        .nav-links a { margin-left: 20px; font-weight: 500; color: #fff; text-decoration: none; font-size: .9rem; transition: color .2s; }
        .nav-links a.active, .nav-links a:hover { color: var(--gold); }

        /* ── HERO ── */
        .news-hero {
            min-height: 320px;
            background: linear-gradient(135deg, rgba(0,77,46,.94), rgba(0,107,63,.86)),
                        url('{{ asset('assets/images/au3.jpg') }}') center/cover no-repeat;
            display: flex; align-items: center; justify-content: center;
            text-align: center; padding: 60px 24px 80px;
            color: #fff;
        }
        .news-hero h1 { font-size: 2.8rem; margin: 0 0 12px; color: var(--gold); }
        .news-hero p { max-width: 680px; margin: 0 auto; line-height: 1.75; opacity: .92; font-size: 1.05rem; }

        /* ── FILTER BAR ── */
        .filter-wrap {
            max-width: 1160px; margin: -44px auto 0; padding: 0 24px; position: relative; z-index: 10;
        }
        .filter-bar {
            background: #fff; border-radius: 14px;
            box-shadow: 0 12px 32px rgba(0,0,0,.13);
            padding: 20px 24px;
            display: flex; gap: 12px; flex-wrap: wrap; align-items: center;
        }
        .filter-bar input, .filter-bar select {
            flex: 1; min-width: 180px;
            border: 1px solid #ccd8cf; border-radius: 8px;
            padding: 11px 14px; font: inherit; font-size: .95rem;
            background: #f7faf8; outline: none; transition: border-color .2s;
        }
        .filter-bar input:focus, .filter-bar select:focus { border-color: var(--au-green); background: #fff; }
        .filter-bar button {
            padding: 11px 26px; border-radius: 30px; border: none;
            background: var(--au-green); color: #fff; font-weight: 700; font-size: .95rem;
            cursor: pointer; white-space: nowrap; transition: background .2s;
        }
        .filter-bar button:hover { background: var(--au-green-dark); }

        /* ── MAIN CONTENT ── */
        .content-wrap { max-width: 1160px; margin: 40px auto 60px; padding: 0 24px; }

        /* ── CATEGORY TABS ── */
        .category-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 28px; }
        .cat-tab {
            padding: 6px 16px; border-radius: 99px; font-size: .85rem; font-weight: 600;
            border: 1.5px solid #c8d8ce; background: #fff; color: #4a6355;
            text-decoration: none; transition: all .2s; white-space: nowrap;
        }
        .cat-tab:hover, .cat-tab.active {
            background: var(--au-green); color: #fff; border-color: var(--au-green);
        }

        /* ── CARD GRID ── */
        .news-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px;
        }

        .news-card {
            background: #fff; border-radius: 14px;
            overflow: hidden; display: flex; flex-direction: column;
            box-shadow: 0 2px 12px rgba(0,0,0,.07);
            border: 1px solid #e0ebe5;
            transition: transform .25s, box-shadow .25s;
        }
        .news-card:hover { transform: translateY(-4px); box-shadow: 0 10px 32px rgba(0,107,63,.14); }

        .card-cover { height: 200px; overflow: hidden; background: #d4e6da; flex-shrink: 0; }
        .card-cover img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s ease; }
        .news-card:hover .card-cover img { transform: scale(1.04); }

        .card-body { padding: 20px; display: flex; flex-direction: column; gap: 10px; flex: 1; }

        .card-badge {
            display: inline-block; background: #e8f5ee; color: var(--au-green);
            padding: 4px 10px; border-radius: 99px; font-size: .75rem; font-weight: 700;
            text-transform: capitalize; width: fit-content;
        }

        .card-title {
            font-size: 1.05rem; font-weight: 700; line-height: 1.4;
            color: #102018; margin: 0; text-decoration: none; display: block;
        }
        .card-title:hover { color: var(--au-green); }

        .card-meta { font-size: .8rem; color: #7a9384; }

        .card-excerpt { color: #4a6355; line-height: 1.6; font-size: .9rem; margin: 0; flex: 1; }

        .card-link {
            display: inline-block; margin-top: 4px; padding: 9px 18px; border-radius: 8px;
            background: var(--au-green); color: #fff; font-weight: 700; font-size: .85rem;
            text-decoration: none; width: fit-content; transition: background .2s;
        }
        .card-link:hover { background: var(--au-green-dark); }

        /* ── EMPTY STATE ── */
        .news-empty {
            grid-column: 1 / -1; text-align: center;
            background: #fff; border: 1px solid #e0ebe5; border-radius: 14px;
            padding: 60px 40px;
        }
        .news-empty h2 { color: var(--au-green); margin-bottom: 10px; }
        .news-empty p { color: #6b8676; }

        /* ── PAGINATION ── */
        .pagination-wrap { margin-top: 36px; display: flex; justify-content: center; }
        .pagination-wrap nav { display: flex; }

        /* ── SUBSCRIBE BANNER ── */
        .subscribe-banner {
            margin-top: 48px; background: var(--au-green-dark);
            border-radius: 16px; padding: 36px 40px;
            display: grid; grid-template-columns: 1fr auto;
            gap: 24px; align-items: center;
        }
        .subscribe-banner h2 { margin: 0 0 8px; color: var(--gold); font-size: 1.5rem; }
        .subscribe-banner p { margin: 0; color: #c2ddd0; line-height: 1.6; }
        .sub-form { display: flex; gap: 10px; }
        .sub-form input {
            min-width: 260px; border: none; border-radius: 8px;
            padding: 12px 16px; font: inherit; font-size: .95rem; outline: none;
        }
        .sub-form button {
            padding: 12px 24px; border: none; border-radius: 8px;
            background: var(--gold); color: #1a2e22; font-weight: 700; font-size: .95rem;
            cursor: pointer; white-space: nowrap; transition: opacity .2s;
        }
        .sub-form button:hover { opacity: .88; }

        /* ── ALERT ── */
        .alert-success {
            background: #dcfce7; color: #166534;
            border: 1px solid #86efac; border-radius: 10px;
            padding: 14px 18px; margin-bottom: 24px; font-weight: 500;
        }

        /* ── FOOTER ── */
        footer.footer { margin-top: 0; }

        /* ── RESPONSIVE ── */
        @media (max-width: 960px) {
            .news-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .news-hero h1 { font-size: 2rem; }
            .news-grid { grid-template-columns: 1fr; }
            .filter-bar { flex-direction: column; }
            .filter-wrap { margin-top: -30px; }
            .subscribe-banner { grid-template-columns: 1fr; }
            .sub-form { flex-direction: column; }
            .sub-form input { min-width: 0; }
        }
    </style>
</head>
<body>

<!-- ── NAVBAR ── -->
<header class="navbar">
    <div class="logo">
        <img src="{{ asset('assets/images/au.png') }}" alt="ATTP" class="logo logo-sm">
    </div>
    <nav class="nav-links">
        <a href="{{ route('landing.index') }}">Home</a>

        <div class="has-dropdown">
            <a href="#">Programs</a>
            <ul class="nav-dropdown">
                <li><a href="{{ route('events') }}">Events / Webinars</a></li>
                <li><a href="{{ route('careers.index') }}">Careers</a></li>
            </ul>
        </div>

        <div class="has-dropdown">
            <a href="#">Analytics</a>
            <ul class="nav-dropdown">
                <li><a href="{{ route('impact.map') }}">Impact Map</a></li>
                <li><a href="{{ route('world.indicators.performance') }}">World Indicators / Performance</a></li>
            </ul>
        </div>

        <a href="{{ route('news.index') }}" class="active">News &amp; Updates</a>
        <a href="#contact">Contact</a>
    </nav>
    <div class="nav-actions">
        <a href="{{ route('public.procurement.index') }}" class="btn btn-primary">Policy Programs &amp; Research</a>
        <a href="{{ route('login') }}" class="btn btn-login">Login</a>
        <x-language-selector style="news" />
    </div>
</header>

<!-- ── HERO ── -->
<section class="news-hero">
    <div>
        <h1>News &amp; Updates</h1>
        <p>Approved ATTP announcements, policy updates, research findings, events, and public communications from across the continent.</p>
    </div>
</section>

<!-- ── FILTER BAR ── -->
<div class="filter-wrap">
    <form class="filter-bar" method="GET" action="{{ route('news.index') }}">
        <input name="q" value="{{ request('q') }}" placeholder="Search news and updates…" aria-label="Search">
        <select name="category" aria-label="Category">
            <option value="">All categories</option>
            @foreach($categories as $cat)
                <option value="{{ $cat }}" @selected(request('category') === $cat)>
                    {{ ucfirst(str_replace('_', ' ', $cat)) }}
                </option>
            @endforeach
        </select>
        <button type="submit">Search</button>
        @if(request('q') || request('category'))
            <a href="{{ route('news.index') }}" style="padding:11px 16px;border-radius:8px;border:1.5px solid #ccd8cf;color:#4a6355;text-decoration:none;font-weight:600;font-size:.9rem;white-space:nowrap;">Clear</a>
        @endif
    </form>
</div>

<!-- ── CONTENT ── -->
<main class="content-wrap">
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif

    @if(request('q') || request('category'))
        <p style="color:#6b8676;margin-bottom:20px;">
            Showing results
            @if(request('q')) for "<strong>{{ request('q') }}</strong>"@endif
            @if(request('category')) in category "<strong>{{ ucfirst(str_replace('_', ' ', request('category'))) }}</strong>"@endif
            — {{ $posts->total() }} {{ Str::plural('result', $posts->total()) }}
        </p>
    @endif

    <section class="news-grid">
        @forelse($posts as $post)
            <article class="news-card">
                <div class="card-cover">
                    <img src="{{ $post->cover_image_url ?: $newsCoverFallbackUrl }}"
                         alt="{{ $post->title }}" loading="lazy"
                         data-fallback-src="{{ $newsCoverFallbackUrl }}"
                         onerror="this.onerror=null;this.src=this.dataset.fallbackSrc;">
                </div>
                <div class="card-body">
                    <span class="card-badge">{{ str_replace('_', ' ', $post->category) }}</span>
                    <a class="card-title" href="{{ route('news.show', $post) }}">{{ $post->title }}</a>
                    <div class="card-meta">
                        {{ optional($post->published_at)->format('d M Y') }}
                    </div>
                    <p class="card-excerpt">{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body), 140) }}</p>
                    <a class="card-link" href="{{ route('news.show', $post) }}">Read more &rarr;</a>
                </div>
            </article>
        @empty
            <div class="news-empty">
                <h2>No approved news yet</h2>
                <p>Approved ATTP news and communications will appear here once published.</p>
            </div>
        @endforelse
    </section>

    @if($posts->hasPages())
        <div class="pagination-wrap">
            {{ $posts->links() }}
        </div>
    @endif

    <!-- ── SUBSCRIBE BANNER ── -->
    <div class="subscribe-banner">
        <div>
            <h2>Subscribe to ATTP News</h2>
            <p>Get an email when new approved ATTP news or communications are published.</p>
        </div>
        <form class="sub-form" method="POST" action="{{ route('news.subscribe') }}">
            @csrf
            <input type="email" name="email" placeholder="your@email.com" required aria-label="Email address">
            <button type="submit">Subscribe</button>
        </form>
    </div>
</main>

@include('partials.gallery-strip')

<!-- ── FOOTER ── -->
<footer id="contact" class="footer">
    <div class="footer-content">
        <div class="footer-logo">
            <h3>ATTP<span> · Administration</span></h3>
            <p>African Think Tank Platform Administration — supporting African Union institutions through centralized governance, policy coordination, and strategic oversight of programs and funded initiatives.</p>
        </div>
        <div class="footer-links">
            <h4>Quick Links</h4>
            <a href="{{ route('landing.index') }}">Home</a>
            <a href="{{ route('news.index') }}">News &amp; Updates</a>
            <a href="{{ route('careers.index') }}">Careers</a>
            <a href="{{ route('events') }}">Events</a>
        </div>
        <div class="footer-contact">
            <h4>Contact</h4>
            <p>Email: attpinfo@africanunion.org</p>
            <p>&copy; 2026 African Think Tank Platform Administration (ATTP)</p>
        </div>
    </div>
    <div class="footer-bottom">
        <p>Supporting African Union policy coordination, governance reform, and evidence-based decision-making across the continent.</p>
    </div>
</footer>

<script>
    document.addEventListener('click', function(e) {
        document.querySelectorAll('.lang-switcher.open').forEach(function(el) {
            if (!el.contains(e.target)) el.classList.remove('open');
        });
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.lang-switcher.open').forEach(function(el) {
                el.classList.remove('open');
            });
        }
    });
</script>
</body>
</html>
