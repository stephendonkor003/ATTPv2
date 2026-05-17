@props(['style' => 'default'])

@php
    $locales = [
        'en' => ['short' => 'EN', 'name' => 'English'],
        'fr' => ['short' => 'FR', 'name' => 'Français'],
        'ar' => ['short' => 'AR', 'name' => 'العربية'],
        'pt' => ['short' => 'PT', 'name' => 'Português'],
    ];
    $currentLocale = app()->getLocale();
    $current = $locales[$currentLocale] ?? ['short' => strtoupper($currentLocale), 'name' => $currentLocale];
    $uid = 'ls-' . $style;
@endphp

@once
<style>
.lang-switcher {
    position: relative;
    display: inline-flex;
    align-items: center;
}
.lang-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: rgba(255,255,255,0.12);
    border: 1.5px solid rgba(255,255,255,0.28);
    border-radius: 8px;
    color: #fff;
    font-size: .82rem;
    font-weight: 700;
    padding: 6px 11px;
    cursor: pointer;
    white-space: nowrap;
    letter-spacing: .02em;
    transition: background .2s, border-color .2s;
    line-height: 1;
}
.lang-btn:hover,
.lang-switcher.open .lang-btn {
    background: rgba(255,255,255,0.22);
    border-color: rgba(255,255,255,0.5);
}
.lang-globe { font-size: .9rem; }
.lang-caret {
    font-size: .6rem;
    opacity: .8;
    transition: transform .2s;
}
.lang-switcher.open .lang-caret { transform: rotate(180deg); }

.lang-menu {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    min-width: 160px;
    background: #fff;
    border: 1px solid #e0ebe5;
    border-radius: 10px;
    box-shadow: 0 12px 32px rgba(0,0,0,.16);
    list-style: none;
    margin: 0;
    padding: 6px;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-6px);
    transition: opacity .18s, transform .18s, visibility .18s;
}
.lang-switcher.open .lang-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}
.lang-menu li { margin: 0; }
.lang-menu a {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 9px 10px;
    border-radius: 7px;
    text-decoration: none;
    color: #1a2e22;
    font-size: .85rem;
    transition: background .15s;
}
.lang-menu a:hover { background: #f0f5f1; }
.lang-menu a.lang-active { background: #006B3F; color: #fff; }
.lang-menu .lang-code {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 22px;
    border-radius: 5px;
    background: #e8f5ee;
    color: #006B3F;
    font-size: .72rem;
    font-weight: 800;
    flex-shrink: 0;
}
.lang-menu a.lang-active .lang-code {
    background: rgba(255,255,255,.2);
    color: #fff;
}
</style>
@endonce

<div class="lang-switcher" id="{{ $uid }}">
    <button type="button" class="lang-btn"
            onclick="(function(el){el.classList.toggle('open');})(document.getElementById('{{ $uid }}'))"
            aria-haspopup="listbox"
            aria-label="Select language">
        <span class="lang-globe">🌐</span>
        <span>{{ $current['short'] }}</span>
        <span class="lang-caret">▾</span>
    </button>
    <ul class="lang-menu" role="listbox">
        @foreach ($locales as $locale => $info)
            <li role="option">
                <a href="{{ url('/language/' . $locale) }}"
                   class="{{ $currentLocale === $locale ? 'lang-active' : '' }}">
                    <span class="lang-code">{{ $info['short'] }}</span>
                    <span>{{ $info['name'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</div>
