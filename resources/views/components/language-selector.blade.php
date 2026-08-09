@props(['style' => 'default'])

@php
    $locales = [
        'en' => ['short' => 'EN', 'name' => 'English'],
        'fr' => ['short' => 'FR', 'name' => 'Français'],
        'ar' => ['short' => 'AR', 'name' => 'العربية'],
        'pt' => ['short' => 'PT', 'name' => 'Português'],
        'es' => ['short' => 'ES', 'name' => 'Español'],
        'sw' => ['short' => 'SW', 'name' => 'Kiswahili'],
    ];
    $currentLocale = app()->getLocale();
    $current = $locales[$currentLocale] ?? ['short' => strtoupper($currentLocale), 'name' => $currentLocale];
    static $languageSelectorInstance = 0;
    $languageSelectorInstance++;
    $styleSlug = preg_replace('/[^a-z0-9_-]/i', '-', $style);
    $uid = 'ls-' . $styleSlug . '-' . $languageSelectorInstance;
@endphp

@if ($style !== 'think-tank')
@once
<style>
.lang-switcher {
    position: relative;
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
    font-family: 'Inter', sans-serif;
}

.lang-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 42px;
    padding: 6px 10px 6px 7px;
    background: rgba(255,255,255,.16);
    border: 1px solid rgba(255,255,255,.48);
    border-radius: 11px;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.14), 0 4px 12px rgba(0,0,0,.1);
    color: #fff;
    cursor: pointer;
    white-space: nowrap;
    font: inherit;
    transition: background .18s ease, border-color .18s ease, box-shadow .18s ease, transform .18s ease;
}

.lang-btn:hover,
.lang-switcher.open .lang-btn {
    background: rgba(255,255,255,.25);
    border-color: rgba(255,255,255,.78);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.2), 0 7px 18px rgba(0,0,0,.16);
}

.lang-btn:active {
    transform: translateY(1px);
}

.lang-btn:focus-visible {
    outline: 3px solid #fbbc05;
    outline-offset: 3px;
}

.lang-globe {
    display: inline-grid;
    width: 28px;
    height: 28px;
    place-items: center;
    flex: 0 0 28px;
    border-radius: 8px;
    background: rgba(255,255,255,.16);
}

.lang-globe svg {
    width: 17px;
    height: 17px;
    stroke: currentColor;
}

.lang-current {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    line-height: 1;
}

.lang-current-code {
    color: #fbbc05;
    font-size: .7rem;
    font-weight: 800;
    letter-spacing: .06em;
}

.lang-current-name {
    font-size: .84rem;
    font-weight: 700;
    letter-spacing: .01em;
}

.lang-caret {
    display: inline-flex;
    margin-inline-start: 1px;
    opacity: .9;
    transition: transform .18s ease;
}

.lang-caret svg {
    width: 13px;
    height: 13px;
    stroke: currentColor;
}

.lang-switcher.open .lang-caret { transform: rotate(180deg); }

.lang-switcher .lang-menu {
    position: absolute;
    top: calc(100% + 11px);
    right: auto;
    left: auto;
    inset-inline-end: 0;
    width: max-content;
    min-width: 248px;
    background: #fff;
    border: 1px solid #cfded5;
    border-radius: 15px;
    box-shadow: 0 22px 55px rgba(0,35,20,.25), 0 3px 10px rgba(0,0,0,.1);
    list-style: none;
    margin: 0;
    padding: 8px;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transform: translateY(-7px) scale(.985);
    transform-origin: top right;
    transition: opacity .16s ease, transform .16s ease, visibility .16s ease;
}

.lang-switcher .lang-menu::before {
    content: '';
    position: absolute;
    top: -6px;
    inset-inline-end: 22px;
    width: 11px;
    height: 11px;
    background: #fff;
    border-top: 1px solid #cfded5;
    border-left: 1px solid #cfded5;
    transform: rotate(45deg);
}

.lang-switcher.open .lang-menu {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
    transform: translateY(0);
}

.lang-switcher .lang-menu li {
    margin: 0;
}

.lang-menu-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 7px 10px 10px;
    color: #50665a;
    font-size: .7rem;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.lang-menu-heading::after {
    content: '';
    width: 28px;
    height: 2px;
    border-radius: 99px;
    background: #fbbc05;
}

.lang-switcher .lang-menu li a {
    display: flex;
    align-items: center;
    gap: 11px;
    min-height: 47px;
    padding: 9px 11px !important;
    border: 0 !important;
    border-radius: 10px;
    text-decoration: none;
    color: #172b20 !important;
    font-size: .9rem;
    line-height: 1.2;
    transition: background .14s ease, color .14s ease, transform .14s ease;
}

.lang-switcher .lang-menu li + li {
    margin-top: 2px;
}

.lang-switcher .lang-menu a:hover,
.lang-switcher .lang-menu a:focus-visible {
    padding: 9px 11px !important;
    background: #eef7f2;
    color: #004d2e !important;
    outline: none;
    transform: translateX(2px);
}

[dir="rtl"] .lang-switcher .lang-menu a:hover,
[dir="rtl"] .lang-switcher .lang-menu a:focus-visible {
    transform: translateX(-2px);
}

.lang-switcher .lang-menu a.lang-active {
    background: #e2f3e9;
    color: #004d2e !important;
    box-shadow: inset 3px 0 0 #006b3f;
}

[dir="rtl"] .lang-switcher .lang-menu a.lang-active {
    box-shadow: inset -3px 0 0 #006b3f;
}

.lang-switcher .lang-code {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 28px;
    border: 1px solid #cfe2d6;
    border-radius: 8px;
    background: #e8f5ee;
    color: #006B3F;
    font-size: .71rem;
    font-weight: 800;
    letter-spacing: .04em;
    flex-shrink: 0;
}

.lang-name {
    flex: 1;
    font-weight: 700;
    opacity: 1;
}

.lang-check {
    display: inline-grid;
    width: 22px;
    height: 22px;
    place-items: center;
    margin-inline-start: auto;
    border-radius: 50%;
    background: #006b3f;
    color: #fff;
    font-size: .72rem;
    font-weight: 900;
    opacity: 0;
    transform: scale(.7);
}

.lang-active .lang-check {
    opacity: 1;
    transform: scale(1);
}

.mobile-nav-actions .lang-switcher {
    display: block;
    width: 100%;
}

.mobile-nav-actions .lang-btn {
    width: 100%;
    justify-content: flex-start;
    background: #fff;
    border-color: #d6e4dc;
    color: #004d2e;
    box-shadow: 0 5px 16px rgba(0,0,0,.16);
}

.mobile-nav-actions .lang-btn:hover,
.mobile-nav-actions .lang-switcher.open .lang-btn {
    background: #f4faf7;
    border-color: #9fc4af;
}

.mobile-nav-actions .lang-globe {
    background: #e5f3eb;
}

.mobile-nav-actions .lang-caret {
    margin-inline-start: auto;
}

.mobile-nav-actions .lang-switcher .lang-menu {
    top: auto;
    right: 0;
    left: 0;
    bottom: calc(100% + 11px);
    width: 100%;
    min-width: 0;
    transform: translateY(7px) scale(.985);
    transform-origin: bottom center;
}

.mobile-nav-actions .lang-switcher .lang-menu::before {
    top: auto;
    bottom: -6px;
    border: 0;
    border-right: 1px solid #cfded5;
    border-bottom: 1px solid #cfded5;
}

.mobile-nav-actions .lang-switcher.open .lang-menu {
    transform: translateY(0);
}
</style>
@endonce
@endif

<div class="lang-switcher lang-switcher--{{ $styleSlug }}" id="{{ $uid }}">
    <button type="button" class="lang-btn"
            onclick="(function(button){var el=button.closest('.lang-switcher');var open=!el.classList.contains('open');document.querySelectorAll('.lang-switcher.open').forEach(function(item){item.classList.remove('open');var other=item.querySelector('.lang-btn');if(other){other.setAttribute('aria-expanded','false');}});el.classList.toggle('open',open);button.setAttribute('aria-expanded',open?'true':'false');})(this)"
            aria-haspopup="listbox"
            aria-expanded="false"
            aria-controls="{{ $uid }}-menu"
            aria-label="{{ __('navigation.select_language') }}">
        <span class="lang-globe" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="9"></circle>
                <path d="M3 12h18M12 3c2.2 2.5 3.3 5.5 3.3 9S14.2 18.5 12 21M12 3C9.8 5.5 8.7 8.5 8.7 12s1.1 6.5 3.3 9"></path>
            </svg>
        </span>
        <span class="lang-current">
            <span class="lang-current-code">{{ $current['short'] }}</span>
            <span class="lang-current-name">{{ $current['name'] }}</span>
        </span>
        <span class="lang-caret" aria-hidden="true">
            <svg viewBox="0 0 20 20" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m5 7.5 5 5 5-5"></path>
            </svg>
        </span>
    </button>
    <ul class="lang-menu" id="{{ $uid }}-menu" role="listbox" aria-label="{{ __('navigation.select_language') }}">
        <li class="lang-menu-heading" role="presentation">{{ __('navigation.select_language') }}</li>
        @foreach ($locales as $locale => $info)
            <li role="option" aria-selected="{{ $currentLocale === $locale ? 'true' : 'false' }}">
                <a href="{{ route('language.select', ['locale' => $locale]) }}"
                   lang="{{ $locale }}"
                   hreflang="{{ $locale }}"
                   class="{{ $currentLocale === $locale ? 'lang-active' : '' }}">
                    <span class="lang-code">{{ $info['short'] }}</span>
                    <span class="lang-name">{{ $info['name'] }}</span>
                    <span class="lang-check" aria-hidden="true">✓</span>
                </a>
            </li>
        @endforeach
    </ul>
</div>

@if ($style !== 'think-tank')
@once
<script>
document.addEventListener('click', function (event) {
    document.querySelectorAll('.lang-switcher.open').forEach(function (selector) {
        if (!selector.contains(event.target)) {
            selector.classList.remove('open');
            var button = selector.querySelector('.lang-btn');
            if (button) button.setAttribute('aria-expanded', 'false');
        }
    });
});

document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;

    document.querySelectorAll('.lang-switcher.open').forEach(function (selector) {
        selector.classList.remove('open');
        var button = selector.querySelector('.lang-btn');
        if (button) button.setAttribute('aria-expanded', 'false');
    });
});
</script>
@endonce
@endif
