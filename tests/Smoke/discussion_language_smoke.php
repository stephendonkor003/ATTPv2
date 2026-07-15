<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
$kernel = $app->make(HttpKernel::class);

$pages = [
    '/discussion/thematic-areas' => 'thematic',
    '/discussion/current' => 'current',
    '/discussion/join' => 'join',
];
$locales = ['en', 'fr', 'ar', 'pt', 'es', 'sw'];

foreach ($locales as $locale) {
    $translations = require base_path("lang/{$locale}/discussion.php");

    foreach ($pages as $path => $pageKey) {
        $request = Request::create("{$path}?lang={$locale}", 'GET', [], [], [], [
            'HTTP_HOST' => '127.0.0.1:8000',
            'SERVER_PORT' => 8000,
        ]);
        $response = $kernel->handle($request);
        $content = (string) $response->getContent();

        if ($response->getStatusCode() !== 200) {
            fwrite(STDERR, "Expected 200 from {$path} in {$locale}, got {$response->getStatusCode()}.\n");
            exit(1);
        }

        if (! str_contains($content, e($translations[$pageKey]['title']))) {
            fwrite(STDERR, "{$path} did not render its {$locale} title.\n");
            exit(1);
        }

        $direction = $locale === 'ar' ? 'rtl' : 'ltr';
        if (! str_contains($content, "<html lang=\"{$locale}\" dir=\"{$direction}\">")) {
            fwrite(STDERR, "{$path} did not render the expected {$locale} document direction.\n");
            exit(1);
        }
    }
}

$joinRequest = Request::create('/discussion/join?lang=en', 'GET', [], [], [], [
    'HTTP_HOST' => '127.0.0.1:8000',
    'SERVER_PORT' => 8000,
]);
$joinResponse = $kernel->handle($joinRequest);
$joinContent = (string) $joinResponse->getContent();

$countrySelectorMarkers = [
    'admin/assets/vendors/css/select2.min.css',
    'admin/assets/vendors/js/jquery.min.js',
    'admin/assets/vendors/js/select2.min.js',
    'class="forum-country-select"',
    'ref="countrySelect"',
    ':data-flag-url="country.flag_url"',
];

foreach ($countrySelectorMarkers as $marker) {
    if (! str_contains($joinContent, $marker)) {
        fwrite(STDERR, "The participant country selector is missing its advanced flag-picker integration: {$marker}.\n");
        exit(1);
    }
}

$publicDiscussionMarkers = [
    'class="forum-topic-presence-toast"',
    'Live discussion community',
    'activeTopicJoiner.flag_url',
    "Number(topicParticipation.countries_count) === 1 ? ' country' : ' countries'",
    "Number(topicParticipation.participants_count) === 1 ? ' participant' : ' participants'",
    'Forgot your password?',
    'Send password reset link',
    'Reset password',
    'Your contribution appears immediately',
    'Live &middot; post-moderated',
];

foreach ($publicDiscussionMarkers as $marker) {
    if (! str_contains($joinContent, $marker)) {
        fwrite(STDERR, "The public discussion experience is missing {$marker}.\n");
        exit(1);
    }
}

foreach (['Submit for review', 'reviewed before publication', 'awaiting moderation'] as $approvalCopy) {
    if (str_contains($joinContent, $approvalCopy)) {
        fwrite(STDERR, "The public discussion page still advertises prior approval: {$approvalCopy}.\n");
        exit(1);
    }
}

$countrySelectorScript = (string) file_get_contents(public_path('assets/js/discussion-forum.js'));
foreach (['initialiseCountrySelector', 'templateResult', 'templateSelection', 'renderCountryOption'] as $marker) {
    if (! str_contains($countrySelectorScript, $marker)) {
        fwrite(STDERR, "The participant country selector script is missing {$marker}.\n");
        exit(1);
    }
}

foreach (['joinTopicPresence', 'loadTopicActivity', 'topicActivityTimer', 'requestPasswordReset', 'resetParticipantPassword'] as $marker) {
    if (! str_contains($countrySelectorScript, $marker)) {
        fwrite(STDERR, "The live discussion script is missing {$marker}.\n");
        exit(1);
    }
}

if (! str_contains($countrySelectorScript, 'showForumStartupFailure(error)')) {
    fwrite(STDERR, "The discussion app is missing its visible startup-failure fallback.\n");
    exit(1);
}

$authenticationMethodStart = strpos($countrySelectorScript, 'afterAuthentication: async function ()');
$authenticationMethodEnd = strpos($countrySelectorScript, 'completeAuthentication: function', $authenticationMethodStart ?: 0);
$authenticationMethod = $authenticationMethodStart !== false && $authenticationMethodEnd !== false
    ? substr($countrySelectorScript, $authenticationMethodStart, $authenticationMethodEnd - $authenticationMethodStart)
    : '';

if (! str_contains($authenticationMethod, 'await this.continueAfterAccount();')
    || str_contains($authenticationMethod, 'if (this.returnToTopic)')) {
    fwrite(STDERR, "Successful participant authentication does not always enter the active discussion experience.\n");
    exit(1);
}

$registrationMethodStart = strpos($countrySelectorScript, 'register: async function ()');
$registrationMethodEnd = strpos($countrySelectorScript, 'login: async function ()', $registrationMethodStart ?: 0);
$registrationMethod = $registrationMethodStart !== false && $registrationMethodEnd !== false
    ? substr($countrySelectorScript, $registrationMethodStart, $registrationMethodEnd - $registrationMethodStart)
    : '';

if (str_contains($registrationMethod, 'await this.loadCountries(true)')
    || ! str_contains($registrationMethod, 'await this.afterAuthentication();')) {
    fwrite(STDERR, "Participant registration still blocks discussion entry on a secondary country refresh.\n");
    exit(1);
}

$homeRequest = Request::create('/?lang=fr', 'GET', [], [], [], [
    'HTTP_HOST' => '127.0.0.1:8000',
    'SERVER_PORT' => 8000,
]);
$homeResponse = $kernel->handle($homeRequest);
$home = (string) $homeResponse->getContent();
$frenchLanding = require base_path('lang/fr/landing.php');

if ($homeResponse->getStatusCode() !== 200 || ! str_contains($home, e($frenchLanding['hero_title']))) {
    fwrite(STDERR, "The landing page did not render the localized French hero.\n");
    exit(1);
}

foreach ($locales as $locale) {
    $languageUrl = "http://127.0.0.1:8000/language/{$locale}";
    if (! str_contains($home, $languageUrl)) {
        fwrite(STDERR, "The landing language selector is missing {$locale}.\n");
        exit(1);
    }
}

preg_match_all('/id="(ls-[^"]+)"/', $home, $selectorIdMatches);
$selectorIds = $selectorIdMatches[1] ?? [];
if (count($selectorIds) !== count(array_unique($selectorIds))) {
    fwrite(STDERR, "The landing page rendered duplicate language selector IDs.\n");
    exit(1);
}

$switchRequest = Request::create('/language/es', 'GET', [], [], [], [
    'HTTP_HOST' => '127.0.0.1:8000',
    'HTTP_REFERER' => 'http://127.0.0.1:8000/',
    'SERVER_PORT' => 8000,
]);
$switchResponse = $kernel->handle($switchRequest);

if (! $switchResponse->isRedirect() || app()->getLocale() !== 'es') {
    fwrite(STDERR, "The GET language switch route did not activate Spanish and redirect back.\n");
    exit(1);
}

$preferredLocaleCookie = collect($switchResponse->headers->getCookies())
    ->first(fn ($cookie) => $cookie->getName() === 'preferred_locale');

if (! $preferredLocaleCookie) {
    fwrite(STDERR, "The selected language was not persisted in the preferred_locale cookie.\n");
    exit(1);
}

$persistedRequest = Request::create('/discussion/current', 'GET', [], [
    'preferred_locale' => $preferredLocaleCookie->getValue(),
], [], [
    'HTTP_HOST' => '127.0.0.1:8000',
    'SERVER_PORT' => 8000,
]);
$persistedResponse = $kernel->handle($persistedRequest);
$spanishDiscussion = require base_path('lang/es/discussion.php');

if (! str_contains((string) $persistedResponse->getContent(), e($spanishDiscussion['current']['title']))) {
    fwrite(STDERR, "The preferred_locale cookie did not restore Spanish on the next request.\n");
    exit(1);
}

$contactRequest = Request::create('/contact', 'GET', [], [], [], [
    'HTTP_HOST' => '127.0.0.1:8000',
    'SERVER_PORT' => 8000,
]);
$contactResponse = $kernel->handle($contactRequest);

if (! $contactResponse->isRedirect('http://127.0.0.1:8000#contact')) {
    fwrite(STDERR, "The public contact route did not redirect to the landing-page contact section.\n");
    exit(1);
}

echo "DISCUSSION_LANGUAGE_OK\n";
