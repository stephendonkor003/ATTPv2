<?php

namespace App\Http\Controllers;

use App\Models\WebsiteVisit;
use App\Models\WebsiteVisitActivity;
use App\Models\WorldBankCountry;
use App\Support\IpGeo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WebsiteVisitTrackerController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        if ($this->shouldIgnore($request)) {
            return response()->json(['ignored' => true]);
        }

        $data = $this->validatedPayload($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $visitorUuid = $this->visitorUuid($data['visitor_uuid'] ?? null);
        $visit = $this->findReusableVisit($visitorUuid, $data['visit_id'] ?? null);
        $now = now();
        $clientIp = $this->clientIp($request);
        $geo = $this->resolveGeo($request, $clientIp);

        if (! $visit) {
            $visit = WebsiteVisit::create([
                'visitor_uuid' => $visitorUuid,
                'session_id' => $request->session()?->getId(),
                'ip_address' => $clientIp,
                'ip_hash' => $this->ipHash($clientIp),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                'referrer' => $data['referrer'] ?? null,
                'landing_url' => $data['url'] ?? null,
                'current_url' => $data['url'] ?? null,
                'current_path' => $data['path'] ?? null,
                'country_name' => $geo['country_name'] ?? null,
                'country_iso2' => $geo['country_iso2'] ?? null,
                'continent' => $geo['continent'] ?? null,
                'latitude' => $geo['latitude'] ?? null,
                'longitude' => $geo['longitude'] ?? null,
                'page_views' => 0,
                'duration_seconds' => 0,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'is_active' => true,
            ]);
        }

        $visit->fill([
            'current_url' => $data['url'] ?? $visit->current_url,
            'current_path' => $data['path'] ?? $visit->current_path,
            'last_seen_at' => $now,
            'is_active' => true,
        ]);
        if ($clientIp) {
            $visit->ip_address = $clientIp;
            $visit->ip_hash = $this->ipHash($clientIp);
        }

        foreach (['country_name', 'country_iso2', 'continent', 'latitude', 'longitude'] as $field) {
            if (blank($visit->{$field}) && filled($geo[$field] ?? null)) {
                $visit->{$field} = $geo[$field];
            }
        }

        $visit->page_views = ((int) $visit->page_views) + 1;
        $visit->duration_seconds = max((int) $visit->duration_seconds, $this->duration($data));
        $visit->save();

        $this->recordActivity($visit, 'page_view', $data);

        return response()->json([
            'visit_id' => $visit->id,
            'visitor_uuid' => $visitorUuid,
        ]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        if ($this->shouldIgnore($request)) {
            return response()->json(['ignored' => true]);
        }

        $data = $this->validatedPayload($request, requireVisit: true);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $visitorUuid = $this->visitorUuid($data['visitor_uuid'] ?? null);
        $visit = $this->findReusableVisit($visitorUuid, $data['visit_id'] ?? null, hours: 24);

        if (! $visit) {
            return response()->json(['message' => 'Visit not found.'], 404);
        }

        $isEnding = (bool) ($data['ended'] ?? false);
        $clientIp = $this->clientIp($request);

        $visit->fill([
            'current_url' => $data['url'] ?? $visit->current_url,
            'current_path' => $data['path'] ?? $visit->current_path,
            'last_seen_at' => now(),
            'is_active' => ! $isEnding,
        ]);
        if ($clientIp) {
            $visit->ip_address = $clientIp;
            $visit->ip_hash = $this->ipHash($clientIp);
        }
        $visit->duration_seconds = max((int) $visit->duration_seconds, $this->duration($data));
        $visit->save();

        if ($isEnding) {
            $this->recordActivity($visit, 'exit', $data);
        }

        return response()->json([
            'visit_id' => $visit->id,
            'duration_seconds' => $visit->duration_seconds,
        ]);
    }

    private function validatedPayload(Request $request, bool $requireVisit = false): array|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'visitor_uuid' => ['nullable', 'string', 'max:80'],
            'visit_id' => [$requireVisit ? 'required' : 'nullable', 'string', 'max:80'],
            'url' => ['nullable', 'string', 'max:2000'],
            'path' => ['nullable', 'string', 'max:1000'],
            'title' => ['nullable', 'string', 'max:255'],
            'referrer' => ['nullable', 'string', 'max:2000'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'ended' => ['nullable', 'boolean'],
            'timezone' => ['nullable', 'string', 'max:120'],
            'screen' => ['nullable', 'string', 'max:80'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid visit tracking payload.',
                'errors' => $validator->errors(),
            ], 422);
        }

        return $validator->validated();
    }

    private function visitorUuid(?string $visitorUuid): string
    {
        $visitorUuid = trim((string) $visitorUuid);

        return $visitorUuid !== '' ? Str::limit($visitorUuid, 80, '') : (string) Str::uuid();
    }

    private function findReusableVisit(string $visitorUuid, ?string $visitId, int $hours = 12): ?WebsiteVisit
    {
        if (blank($visitId)) {
            return null;
        }

        return WebsiteVisit::query()
            ->whereKey($visitId)
            ->where('visitor_uuid', $visitorUuid)
            ->where('last_seen_at', '>=', now()->subHours($hours))
            ->first();
    }

    private function recordActivity(WebsiteVisit $visit, string $type, array $data): void
    {
        WebsiteVisitActivity::create([
            'website_visit_id' => $visit->id,
            'activity_type' => $type,
            'url' => $data['url'] ?? null,
            'path' => $data['path'] ?? null,
            'title' => $data['title'] ?? null,
            'referrer' => $data['referrer'] ?? null,
            'duration_seconds' => $this->duration($data),
            'metadata' => [
                'timezone' => $data['timezone'] ?? null,
                'screen' => $data['screen'] ?? null,
            ],
            'occurred_at' => now(),
        ]);
    }

    private function duration(array $data): int
    {
        return max(0, min(86400, (int) ($data['duration_seconds'] ?? 0)));
    }

    private function shouldIgnore(Request $request): bool
    {
        $agent = Str::lower((string) $request->userAgent());

        return $agent === ''
            || Str::contains($agent, ['bot', 'crawl', 'spider', 'slurp', 'preview', 'uptime', 'monitor']);
    }

    private function resolveGeo(Request $request, ?string $clientIp): array
    {
        $headerCountry = $this->countryCodeFromHeaders($request);
        if ($headerCountry) {
            return $this->countryFromIso2($headerCountry);
        }

        $lookup = IpGeo::lookup($clientIp);
        $countryCode = strtoupper((string) ($lookup['country_code'] ?? ''));
        if ($countryCode !== '') {
            $country = $this->countryFromIso2($countryCode);

            return array_filter([
                ...$country,
                'latitude' => $country['latitude'] ?? $lookup['latitude'] ?? null,
                'longitude' => $country['longitude'] ?? $lookup['longitude'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');
        }

        $countryName = trim((string) ($lookup['country_name'] ?? ''));
        if ($countryName !== '') {
            $country = WorldBankCountry::query()
                ->where('is_aggregate', false)
                ->where(function ($query) use ($countryName) {
                    $query->where('name', $countryName)
                        ->orWhere('name', 'like', '%' . $countryName . '%');
                })
                ->first();

            if ($country) {
                return $this->countryPayload($country);
            }

            return ['country_name' => $countryName];
        }

        return [];
    }

    private function countryCodeFromHeaders(Request $request): ?string
    {
        foreach (['CF-IPCountry', 'X-Vercel-IP-Country', 'X-Appengine-Country'] as $header) {
            $value = strtoupper(trim((string) $request->headers->get($header)));
            if (preg_match('/^[A-Z]{2}$/', $value) && ! in_array($value, ['XX', 'ZZ'], true)) {
                return $value;
            }
        }

        return null;
    }

    private function countryFromIso2(string $iso2): array
    {
        $country = WorldBankCountry::query()
            ->where('is_aggregate', false)
            ->where('iso2_code', strtoupper($iso2))
            ->first();

        if (! $country) {
            return ['country_iso2' => strtoupper($iso2)];
        }

        return $this->countryPayload($country);
    }

    private function countryPayload(WorldBankCountry $country): array
    {
        return [
            'country_name' => $country->name,
            'country_iso2' => strtoupper((string) $country->iso2_code),
            'continent' => $country->continent,
            'latitude' => is_numeric($country->latitude) ? (float) $country->latitude : null,
            'longitude' => is_numeric($country->longitude) ? (float) $country->longitude : null,
        ];
    }

    private function ipHash(?string $ip): ?string
    {
        if (blank($ip)) {
            return null;
        }

        return hash_hmac('sha256', (string) $ip, (string) config('app.key'));
    }

    private function clientIp(Request $request): ?string
    {
        foreach (['CF-Connecting-IP', 'X-Real-IP', 'X-Forwarded-For'] as $header) {
            $value = trim((string) $request->headers->get($header));
            if ($value === '') {
                continue;
            }

            $candidate = trim(explode(',', $value)[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        $ip = $request->ip();

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }
}
