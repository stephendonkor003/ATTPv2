<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;

class LanguageController extends Controller
{
    /**
     * Available locales for the application.
     *
     * @var array
     */
    protected $availableLocales = ['en', 'fr', 'ar', 'pt', 'es', 'sw'];

    /**
     * Switch the application language.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $locale
     * @return \Illuminate\Http\Response
     */
    public function switch(Request $request, string $locale)
    {
        // Validate locale
        if (!in_array($locale, $this->availableLocales)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid language code.',
                ], 400);
            }

            return redirect()->back()->with('error', 'Invalid language code.');
        }

        // Set the application locale
        App::setLocale($locale);

        // Store in session
        $request->session()->put('locale', $locale);

        // Set cookie for 30 days with explicit security attributes.
        $this->queuePreferredLocaleCookie($locale);

        // Return appropriate response
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'locale' => $locale,
                'message' => 'Language changed successfully.',
            ]);
        }

        return redirect()->back()->with('success', 'Language changed to ' . $this->getLanguageName($locale) . '.');
    }

    /**
     * Get the full language name from locale code.
     *
     * @param  string  $locale
     * @return string
     */
    protected function getLanguageName(string $locale): string
    {
        $languages = [
            'en' => 'English',
            'fr' => 'Français',
            'ar' => 'العربية',
            'pt' => 'Português',
            'es' => 'Español',
            'sw' => 'Kiswahili',
        ];

        return $languages[$locale] ?? $locale;
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

    /**
     * Get the current locale.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function current()
    {
        return response()->json([
            'locale' => App::getLocale(),
            'name' => $this->getLanguageName(App::getLocale()),
        ]);
    }

    /**
     * Get all available locales.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function available()
    {
        $locales = [];

        foreach ($this->availableLocales as $locale) {
            $locales[] = [
                'code' => $locale,
                'name' => $this->getLanguageName($locale),
            ];
        }

        return response()->json([
            'locales' => $locales,
        ]);
    }
}
