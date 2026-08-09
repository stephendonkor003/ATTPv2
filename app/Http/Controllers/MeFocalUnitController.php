<?php

namespace App\Http\Controllers;

use App\Models\ConsortiumThinkTank;
use App\Models\MeFocalUnitContact;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MeFocalUnitController extends Controller
{
    private const READINESS = [
        'ready' => ['label' => 'Ready to report', 'tone' => 'success', 'color' => '#187459'],
        'link_required' => ['label' => 'Formal link required', 'tone' => 'warning', 'color' => '#b8791f'],
        'no_account' => ['label' => 'No matching account', 'tone' => 'danger', 'color' => '#ae4d49'],
        'disabled' => ['label' => 'Login disabled', 'tone' => 'danger', 'color' => '#d07a34'],
        'blacklisted' => ['label' => 'Account blacklisted', 'tone' => 'danger', 'color' => '#7c3d5b'],
        'assignment_mismatch' => ['label' => 'Account assignment mismatch', 'tone' => 'warning', 'color' => '#6b63a8'],
        'unmapped' => ['label' => 'Organization not mapped', 'tone' => 'warning', 'color' => '#3f8aa0'],
        'archived' => ['label' => 'Archived contact', 'tone' => 'neutral', 'color' => '#73838a'],
    ];

    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner']);
        $this->middleware('permission:me.configuration.view|me.configuration.manage|world.indicators.manage')->only(['index', 'pdf']);
        $this->middleware('permission:me.configuration.manage')->except(['index', 'pdf']);
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        [$contacts, $thinkTanks] = $this->contactData();
        $this->assertAuthorizedThinkTank($filters['think_tank_id'], $thinkTanks);
        $filteredContacts = $this->applyFilters($contacts, $filters);
        $sortedContacts = $this->sortContacts($filteredContacts, $filters['sort']);

        $page = max(1, (int) $request->query('page', 1));
        $contactsPage = new LengthAwarePaginator(
            $sortedContacts->forPage($page, $filters['per_page'])->values(),
            $sortedContacts->count(),
            $filters['per_page'],
            $page,
            [
                'path' => route('budget.me.focal-units.index'),
                'query' => $request->query(),
            ]
        );

        $selectedContact = null;
        if ($filters['contact_id']) {
            $selectedContact = $filteredContacts->firstWhere('id', $filters['contact_id']);
            abort_unless($selectedContact, 404, 'The selected focal contact is outside the active scope.');
        } elseif ($contactsPage->isNotEmpty()) {
            $selectedContact = $contactsPage->first();
        }
        $activeThinkTankCount = $thinkTanks->count();

        return view('me.focal-units.index', [
            'contacts' => $contactsPage,
            'thinkTanks' => $thinkTanks,
            'consortia' => $contacts->pluck('consortium_name')->filter()->unique()->sort()->values(),
            'readinessOptions' => self::READINESS,
            'filters' => $filters,
            'metrics' => $this->metrics($filteredContacts, $activeThinkTankCount),
            'charts' => $this->charts($filteredContacts),
            'selectedContact' => $selectedContact,
            'generatedAt' => now(),
            'canManage' => $request->user()->hasPermission('me.configuration.manage'),
            'canManageUsers' => $request->user()->hasPermission('users.manage'),
            'exportQuery' => collect($filters)
                ->except(['contact_id', 'per_page'])
                ->reject(fn ($value, $key): bool => $value === null || $value === ''
                    || ($key === 'activity' && $value === 'active')
                    || ($key === 'sort' && $value === 'organization'))
                ->all(),
        ]);
    }

    public function pdf(Request $request)
    {
        $filters = $this->filters($request);
        [$contacts, $thinkTanks] = $this->contactData();
        $this->assertAuthorizedThinkTank($filters['think_tank_id'], $thinkTanks);
        $filteredContacts = $this->sortContacts($this->applyFilters($contacts, $filters), $filters['sort']);
        if ($filters['contact_id']) {
            $filteredContacts = $filteredContacts->where('id', $filters['contact_id'])->values();
            abort_if($filteredContacts->isEmpty(), 404, 'The selected focal contact is outside the active scope.');
        }

        $filename = $filters['contact_id'] && $filteredContacts->first()
            ? Str::slug($filteredContacts->first()->think_tank_label.'-'.$filteredContacts->first()->focal_person_name).'-focal-control-sheet.pdf'
            : 'attp-me-focal-unit-register-'.now()->format('Ymd-His').'.pdf';

        return Pdf::loadView('me.focal-units.report-pdf', [
            'contacts' => $filteredContacts,
            'metrics' => $this->metrics($filteredContacts, $thinkTanks->count()),
            'charts' => $this->charts($filteredContacts),
            'scopeLabel' => $this->scopeLabel($filters, $thinkTanks, $filteredContacts),
            'generatedAt' => now(),
            'generatedBy' => $request->user(),
            'isIndividual' => filled($filters['contact_id']),
        ])->setPaper('a4', 'landscape')->download($filename);
    }

    public function store(Request $request)
    {
        $this->normalizeInput($request);
        $validated = $this->rules($request);
        $contact = DB::transaction(function () use ($request, $validated): MeFocalUnitContact {
            $this->demoteOtherPrimaryContacts($validated, $request->boolean('is_primary'));

            return MeFocalUnitContact::query()->create($validated + [
                'email' => strtolower($validated['email']),
                'is_primary' => $request->boolean('is_primary'),
                'source' => 'Platform maintained',
                'is_active' => true,
            ]);
        });

        return redirect()->route('budget.me.focal-units.index', ['contact_id' => $contact->id])
            ->with('success', 'M&E focal contact added to the controlled register.');
    }

    public function update(Request $request, MeFocalUnitContact $contact)
    {
        $this->normalizeInput($request);
        $validated = $this->rules($request, $contact);
        if ($contact->user_id
            && (strtolower((string) $validated['email']) !== strtolower((string) $contact->email)
                || (string) ($validated['think_tank_member_id'] ?? '') !== (string) ($contact->think_tank_member_id ?? ''))) {
            throw ValidationException::withMessages([
                'contact' => 'Unlink the platform account before changing this contact email or mapped organization.',
            ]);
        }

        DB::transaction(function () use ($request, $validated, $contact): void {
            $this->demoteOtherPrimaryContacts($validated, $request->boolean('is_primary'), $contact);
            $contact->update($validated + [
                'email' => strtolower($validated['email']),
                'is_primary' => $request->boolean('is_primary'),
            ]);
        });

        return redirect()->route('budget.me.focal-units.index', [
            'activity' => $contact->is_active ? 'active' : 'archived',
            'contact_id' => $contact->id,
        ])->with('success', 'M&E focal contact details updated.');
    }

    public function linkAccount(Request $request, MeFocalUnitContact $contact)
    {
        $validated = $request->validate(['user_id' => 'required|uuid|exists:users,id']);
        if (! $contact->is_active) {
            throw ValidationException::withMessages(['contact' => 'Restore this focal contact before linking an account.']);
        }
        if (! $contact->think_tank_member_id) {
            throw ValidationException::withMessages(['contact' => 'Map the focal contact to an active think tank before linking an account.']);
        }
        $user = User::query()->with('role:id,name')->findOrFail($validated['user_id']);
        if (strtolower((string) $user->email) !== strtolower((string) $contact->email)) {
            throw ValidationException::withMessages(['user_id' => 'The selected account email must match the focal register email.']);
        }
        if ($user->is_blacklisted) {
            throw ValidationException::withMessages(['user_id' => 'A blacklisted account cannot be linked as an M&E focal officer.']);
        }
        if ($user->isAdmin() || $user->isSuperAdmin()
            || (filled($user->user_type) && $user->user_type !== 'think_tank')) {
            throw ValidationException::withMessages(['user_id' => 'Internal, vendor and funding-partner accounts cannot be converted into think tank focal accounts.']);
        }
        if ($user->user_type === 'think_tank'
            && $user->think_tank_member_id
            && (string) $user->think_tank_member_id !== (string) $contact->think_tank_member_id) {
            throw ValidationException::withMessages(['user_id' => 'This account is assigned to another think tank. Reassign it through Think Tank Users before linking it here.']);
        }
        if (MeFocalUnitContact::query()->where('user_id', $user->id)->whereKeyNot($contact->id)->exists()) {
            throw ValidationException::withMessages(['user_id' => 'This account is already linked to another focal contact.']);
        }

        DB::transaction(function () use ($user, $contact): void {
            $user->update([
                'user_type' => 'think_tank',
                'think_tank_member_id' => $contact->think_tank_member_id,
                'think_tank_access_level' => User::THINK_TANK_ACCESS_ME,
            ]);
            $contact->update(['user_id' => $user->id]);
        });

        return redirect()->route('budget.me.focal-units.index', ['contact_id' => $contact->id])
            ->with('success', $contact->focal_person_name." is linked as the organization's M&E Officer.");
    }

    public function unlinkAccount(MeFocalUnitContact $contact)
    {
        $contact->update(['user_id' => null]);

        return redirect()->route('budget.me.focal-units.index', ['contact_id' => $contact->id])
            ->with('success', 'The formal focal-register link was removed. The user account and its access were not deleted.');
    }

    public function restore(MeFocalUnitContact $contact)
    {
        $contact->update(['is_active' => true]);

        return redirect()->route('budget.me.focal-units.index', ['contact_id' => $contact->id])
            ->with('success', 'The focal contact was restored to the active register.');
    }

    public function destroy(MeFocalUnitContact $contact)
    {
        if ($contact->user_id) {
            throw ValidationException::withMessages(['contact' => 'Unlink the platform account before archiving this focal contact.']);
        }
        $contact->update(['is_active' => false, 'is_primary' => false]);

        return redirect()->route('budget.me.focal-units.index', [
            'activity' => 'archived',
            'contact_id' => $contact->id,
        ])->with('success', 'Focal contact archived and retained for register history.');
    }

    private function contactData(): array
    {
        $contacts = MeFocalUnitContact::query()
            ->with([
                'thinkTank:id,name,country,status',
                'user:id,name,email,user_type,role_id,think_tank_member_id,think_tank_access_level,is_disabled,is_blacklisted',
                'user.role:id,name',
            ])
            ->get();
        $emails = $contacts->pluck('email')->filter()->map(fn ($email): string => strtolower((string) $email))->unique()->values();
        $matchingUsers = $emails->isEmpty()
            ? collect()
            : User::query()
                ->with('role:id,name')
                ->whereIn(DB::raw('LOWER(email)'), $emails->all())
                ->get(['id', 'name', 'email', 'user_type', 'role_id', 'think_tank_member_id', 'think_tank_access_level', 'is_disabled', 'is_blacklisted'])
                ->keyBy(fn (User $user): string => strtolower((string) $user->email));
        $linkedUsers = $contacts->pluck('user_id')->filter()->countBy();

        $contacts->each(function (MeFocalUnitContact $contact) use ($matchingUsers, $linkedUsers): void {
            $account = $contact->user ?: $matchingUsers->get(strtolower((string) $contact->email));
            $readiness = $this->readiness($contact, $account);
            $contact->setRelation('resolvedAccount', $account);
            $contact->setAttribute('readiness_key', $readiness['key']);
            $contact->setAttribute('readiness_label', $readiness['label']);
            $contact->setAttribute('readiness_tone', $readiness['tone']);
            $contact->setAttribute('can_link_account', $this->canLinkAccount(
                $contact,
                $account,
                (int) ($account ? $linkedUsers->get($account->id, 0) : 0)
            ));
        });

        $thinkTanks = ConsortiumThinkTank::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'country']);

        return [$contacts, $thinkTanks];
    }

    private function readiness(MeFocalUnitContact $contact, ?User $account): array
    {
        $key = match (true) {
            ! $contact->is_active => 'archived',
            ! $contact->think_tank_member_id => 'unmapped',
            ! $account => 'no_account',
            (bool) $account->is_blacklisted => 'blacklisted',
            (bool) $account->is_disabled => 'disabled',
            strtolower((string) $account->email) !== strtolower((string) $contact->email),
            $account->user_type !== 'think_tank',
            (string) $account->think_tank_member_id !== (string) $contact->think_tank_member_id,
            ! in_array($account->think_tank_access_level, [User::THINK_TANK_ACCESS_ADMIN, User::THINK_TANK_ACCESS_ME], true) => 'assignment_mismatch',
            (string) $contact->user_id !== (string) $account->id => 'link_required',
            default => 'ready',
        };

        return ['key' => $key] + self::READINESS[$key];
    }

    private function canLinkAccount(MeFocalUnitContact $contact, ?User $account, int $linkedContactCount): bool
    {
        return (bool) ($contact->is_active
            && $contact->think_tank_member_id
            && $account
            && ! $account->is_blacklisted
            && ! $account->isAdmin()
            && ! $account->isSuperAdmin()
            && (! filled($account->user_type) || $account->user_type === 'think_tank')
            && (! $account->think_tank_member_id || (string) $account->think_tank_member_id === (string) $contact->think_tank_member_id)
            && strtolower((string) $account->email) === strtolower((string) $contact->email)
            && ($linkedContactCount === 0 || (string) $contact->user_id === (string) $account->id));
    }

    private function filters(Request $request): array
    {
        $readiness = trim((string) $request->query('readiness'));
        $activity = trim((string) $request->query('activity', 'active'));
        $primary = trim((string) $request->query('primary'));
        $sort = trim((string) $request->query('sort', 'organization'));
        $perPage = (int) $request->query('per_page', 25);

        return [
            'q' => Str::limit(trim((string) $request->query('q')), 120, ''),
            'consortium' => Str::limit(trim((string) $request->query('consortium')), 120, ''),
            'think_tank_id' => $this->uuidOrNull($request->query('think_tank_id')),
            'contact_id' => $this->uuidOrNull($request->query('contact_id')),
            'readiness' => array_key_exists($readiness, self::READINESS) ? $readiness : null,
            'activity' => in_array($activity, ['active', 'archived', 'all'], true) ? $activity : 'active',
            'primary' => in_array($primary, ['primary', 'secondary'], true) ? $primary : null,
            'sort' => in_array($sort, ['organization', 'consortium', 'contact', 'readiness', 'newest', 'updated'], true)
                ? $sort
                : 'organization',
            'per_page' => in_array($perPage, [15, 25, 50, 100], true) ? $perPage : 25,
        ];
    }

    private function applyFilters(Collection $contacts, array $filters): Collection
    {
        return $contacts
            ->when($filters['activity'] === 'active', fn (Collection $rows): Collection => $rows->where('is_active', true))
            ->when($filters['activity'] === 'archived', fn (Collection $rows): Collection => $rows->where('is_active', false))
            ->when($filters['consortium'] !== '', fn (Collection $rows): Collection => $rows->filter(
                fn (MeFocalUnitContact $contact): bool => strcasecmp((string) $contact->consortium_name, $filters['consortium']) === 0
            ))
            ->when($filters['think_tank_id'], fn (Collection $rows): Collection => $rows->where('think_tank_member_id', $filters['think_tank_id']))
            ->when($filters['readiness'], fn (Collection $rows): Collection => $rows->where('readiness_key', $filters['readiness']))
            ->when($filters['primary'] === 'primary', fn (Collection $rows): Collection => $rows->where('is_primary', true))
            ->when($filters['primary'] === 'secondary', fn (Collection $rows): Collection => $rows->where('is_primary', false))
            ->when($filters['q'] !== '', function (Collection $rows) use ($filters): Collection {
                $query = mb_strtolower($filters['q']);

                return $rows->filter(function (MeFocalUnitContact $contact) use ($query): bool {
                    $account = $contact->resolvedAccount;
                    $haystack = mb_strtolower(implode(' ', array_filter([
                        $contact->consortium_name,
                        $contact->think_tank_label,
                        $contact->thinkTank?->name,
                        $contact->thinkTank?->country,
                        $contact->focal_person_name,
                        $contact->email,
                        $contact->notes,
                        $account?->name,
                        $account?->email,
                    ])));

                    return str_contains($haystack, $query);
                });
            })->values();
    }

    private function sortContacts(Collection $contacts, string $sort): Collection
    {
        $readinessOrder = array_flip(array_keys(self::READINESS));

        return match ($sort) {
            'consortium' => $contacts->sortBy(fn (MeFocalUnitContact $contact): string => mb_strtolower($contact->consortium_name.' '.$contact->think_tank_label.' '.$contact->focal_person_name), SORT_NATURAL)->values(),
            'contact' => $contacts->sortBy(fn (MeFocalUnitContact $contact): string => mb_strtolower($contact->focal_person_name), SORT_NATURAL)->values(),
            'readiness' => $contacts->sortBy(fn (MeFocalUnitContact $contact): string => str_pad((string) ($readinessOrder[$contact->readiness_key] ?? 99), 2, '0', STR_PAD_LEFT).mb_strtolower($contact->think_tank_label))->values(),
            'newest' => $contacts->sortByDesc('created_at')->values(),
            'updated' => $contacts->sortByDesc('updated_at')->values(),
            default => $contacts->sortBy(fn (MeFocalUnitContact $contact): string => mb_strtolower(($contact->thinkTank?->name ?: $contact->think_tank_label).' '.$contact->focal_person_name), SORT_NATURAL)->values(),
        };
    }

    private function metrics(Collection $contacts, int $activeThinkTankCount): array
    {
        $activeContacts = $contacts->where('is_active', true);
        $mappedOrganizations = $activeContacts->pluck('think_tank_member_id')->filter()->unique();
        $readyOrganizations = $activeContacts->where('readiness_key', 'ready')->pluck('think_tank_member_id')->filter()->unique();
        $accountMatches = $activeContacts->filter(fn (MeFocalUnitContact $contact): bool => (bool) $contact->resolvedAccount)->count();

        return [
            'contacts' => $contacts->count(),
            'active_contacts' => $activeContacts->count(),
            'archived_contacts' => $contacts->where('is_active', false)->count(),
            'consortia' => $activeContacts->pluck('consortium_name')->filter()->unique()->count(),
            'mapped_organizations' => $mappedOrganizations->count(),
            'ready_organizations' => $readyOrganizations->count(),
            'organization_target' => $activeThinkTankCount,
            'readiness_rate' => $activeThinkTankCount > 0 ? round(($readyOrganizations->count() / $activeThinkTankCount) * 100, 1) : 0.0,
            'account_matches' => $accountMatches,
            'account_coverage' => $activeContacts->isNotEmpty() ? round(($accountMatches / $activeContacts->count()) * 100, 1) : 0.0,
            'link_required' => $activeContacts->where('readiness_key', 'link_required')->count(),
            'disabled' => $activeContacts->where('readiness_key', 'disabled')->count(),
            'blacklisted' => $activeContacts->where('readiness_key', 'blacklisted')->count(),
            'primary_contacts' => $activeContacts->where('is_primary', true)->count(),
        ];
    }

    private function charts(Collection $contacts): array
    {
        $readiness = collect(self::READINESS)->map(function (array $definition, string $key) use ($contacts): array {
            return ['key' => $key, 'label' => $definition['label'], 'color' => $definition['color'], 'count' => $contacts->where('readiness_key', $key)->count()];
        })->filter(fn (array $row): bool => $row['count'] > 0)->values();

        $consortia = $contacts->where('is_active', true)->groupBy('consortium_name')->map(function (Collection $rows, string $name): array {
            return [
                'key' => $name,
                'label' => $name,
                'contacts' => $rows->count(),
                'mapped' => $rows->pluck('think_tank_member_id')->filter()->unique()->count(),
                'ready' => $rows->where('readiness_key', 'ready')->pluck('think_tank_member_id')->filter()->unique()->count(),
            ];
        })->sortByDesc('contacts')->values();

        $countries = $contacts->where('is_active', true)
            ->filter(fn (MeFocalUnitContact $contact): bool => filled($contact->thinkTank?->country))
            ->groupBy(fn (MeFocalUnitContact $contact): string => (string) $contact->thinkTank->country)
            ->map(fn (Collection $rows, string $country): array => [
                'key' => $country,
                'label' => $country,
                'organizations' => $rows->pluck('think_tank_member_id')->filter()->unique()->count(),
                'contacts' => $rows->count(),
            ])->sortByDesc('organizations')->take(10)->values();

        return compact('readiness', 'consortia', 'countries');
    }

    private function demoteOtherPrimaryContacts(array $validated, bool $isPrimary, ?MeFocalUnitContact $except = null): void
    {
        if (! $isPrimary) {
            return;
        }
        $query = MeFocalUnitContact::query()->where('is_active', true);
        if (filled($validated['think_tank_member_id'] ?? null)) {
            $query->where('think_tank_member_id', $validated['think_tank_member_id']);
        } else {
            $query->whereNull('think_tank_member_id')
                ->where('consortium_name', $validated['consortium_name'])
                ->where('think_tank_label', $validated['think_tank_label']);
        }
        if ($except) {
            $query->whereKeyNot($except->id);
        }
        $query->update(['is_primary' => false, 'updated_at' => now()]);
    }

    private function normalizeInput(Request $request): void
    {
        $request->merge([
            'consortium_name' => trim((string) $request->input('consortium_name')),
            'think_tank_label' => trim((string) $request->input('think_tank_label')),
            'focal_person_name' => trim((string) $request->input('focal_person_name')),
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);
    }

    private function rules(Request $request, ?MeFocalUnitContact $contact = null): array
    {
        return $request->validate([
            'consortium_name' => 'required|string|max:120',
            'think_tank_member_id' => [
                'nullable',
                'uuid',
                Rule::exists('attp_consortium_think_tanks', 'id')->where('status', 'active'),
            ],
            'think_tank_label' => 'required|string|max:160',
            'focal_person_name' => 'required|string|max:180',
            'email' => ['required', 'email', 'max:255', Rule::unique('me_focal_unit_contacts', 'email')->ignore($contact?->id)],
            'is_primary' => 'nullable|boolean',
            'notes' => 'nullable|string|max:2000',
        ]);
    }

    private function scopeLabel(array $filters, Collection $thinkTanks, Collection $contacts): string
    {
        if ($filters['contact_id'] && $contacts->first()) {
            return $contacts->first()->think_tank_label.' · '.$contacts->first()->focal_person_name;
        }
        $parts = [];
        if ($filters['consortium'] !== '') {
            $parts[] = $filters['consortium'];
        }
        if ($filters['think_tank_id']) {
            $parts[] = $thinkTanks->firstWhere('id', $filters['think_tank_id'])?->name ?: 'Selected think tank';
        }
        if ($filters['readiness']) {
            $parts[] = self::READINESS[$filters['readiness']]['label'];
        }
        if ($filters['activity'] !== 'active') {
            $parts[] = Str::headline($filters['activity']).' records';
        }
        if ($filters['q'] !== '') {
            $parts[] = 'Search: '.$filters['q'];
        }

        return $parts === [] ? 'All active focal contacts' : implode(' · ', $parts);
    }

    private function assertAuthorizedThinkTank(?string $thinkTankId, Collection $thinkTanks): void
    {
        if ($thinkTankId && ! $thinkTanks->contains(fn ($thinkTank): bool => (string) $thinkTank->id === $thinkTankId)) {
            abort(403, 'The selected think tank is not active or available.');
        }
    }

    private function uuidOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return Str::isUuid($value) ? $value : null;
    }
}
