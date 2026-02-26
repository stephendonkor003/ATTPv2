<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Available locales for the application.
     *
     * @var array
     */
    protected $availableLocales = ['en', 'fr', 'ar', 'pt', 'es', 'sw'];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->determineLocale($request);

        // Set the application locale
        App::setLocale($locale);

        // Prepare response
        $response = $next($request);

        // Sync session/cookie with the final locale in case it was changed downstream
        $finalLocale = App::getLocale();
        $request->session()->put('locale', $finalLocale);

        // Set cookie for 30 days with explicit security attributes.
        $this->queuePreferredLocaleCookie($finalLocale);

        return $response;
    }

    /**
     * Determine the locale to use based on priority:
     * 1. URL parameter
     * 2. Session
     * 3. Cookie
     * 4. Browser Accept-Language header
     * 5. Default from config
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function determineLocale(Request $request): string
    {
        // 1. Check URL parameter (?lang=fr)
        if ($request->has('lang') && $this->isValidLocale($request->input('lang'))) {
            return $request->input('lang');
        }

        // 2. Check session
        if ($request->session()->has('locale') && $this->isValidLocale($request->session()->get('locale'))) {
            return $request->session()->get('locale');
        }

        // 3. Check cookie
        if ($request->hasCookie('preferred_locale') && $this->isValidLocale($request->cookie('preferred_locale'))) {
            return $request->cookie('preferred_locale');
        }

        // 4. Check browser Accept-Language header
        $browserLocale = $this->getBrowserLocale($request);
        if ($browserLocale) {
            return $browserLocale;
        }

        // 5. Fallback to default config
        return config('app.locale', 'en');
    }

    /**
     * Check if the given locale is valid/available.
     *
     * @param  string|null  $locale
     * @return bool
     */
    protected function isValidLocale(?string $locale): bool
    {
        return $locale && in_array($locale, $this->availableLocales);
    }

    /**
     * Get browser preferred language from Accept-Language header.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function getBrowserLocale(Request $request): ?string
    {
        $acceptLanguage = $request->header('Accept-Language');

        if (!$acceptLanguage) {
            return null;
        }

        // Parse Accept-Language header (e.g., "en-US,en;q=0.9,fr;q=0.8")
        $languages = explode(',', $acceptLanguage);

        foreach ($languages as $language) {
            // Extract language code (first 2 characters before '-' or ';')
            $locale = strtolower(substr(trim($language), 0, 2));

            if ($this->isValidLocale($locale)) {
                return $locale;
            }
        }

        return null;
    }

    protected function queuePreferredLocaleCookie(string $locale): void
    {
        Cookie::queue(cookie()->make(
            'preferred_locale',
            $locale,
            43200,
            config('session.path', '/'),
            config('session.domain'),
            (bool) config('session.secure', false),
            true,
            false,
            config('session.same_site', 'lax')
        ));
    }
}
