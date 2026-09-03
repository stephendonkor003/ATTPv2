<?php

namespace App\Http\Controllers\Api\V1\ThinkTank;

use App\Data\ThinkTank\CreateThinkTankUserData;
use App\Data\ThinkTank\UpdateThinkTankUserData;
use App\Http\Resources\ThinkTankUserResource;
use App\Models\ConsortiumThinkTank;
use App\Models\User;
use App\Services\ThinkTank\ThinkTankUserManagementService;
use App\Support\ThinkTankApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends ThinkTankApiController
{
    public function __construct(private readonly ThinkTankUserManagementService $users) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $this->validateOnly($request, [
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'access_level' => ['sometimes', 'nullable', Rule::in(array_keys(User::THINK_TANK_ACCESS_LEVELS))],
            'account_status' => ['sometimes', 'nullable', Rule::in(['active', 'disabled', 'blacklisted'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->users->paginate($this->tenant($request), $filters);

        return ThinkTankApiResponse::success(
            ThinkTankUserResource::collection(collect($paginator->items()))->resolve($request),
            extra: ['meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ]],
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateOnly($request, [
            'name' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'access_level' => ['required', Rule::in(array_keys(User::THINK_TANK_ACCESS_LEVELS))],
        ]);
        $result = $this->users->create(
            $request,
            $request->user(),
            $this->tenant($request),
            CreateThinkTankUserData::from($data),
        );

        return ThinkTankApiResponse::success(
            (new ThinkTankUserResource($result['user']))->resolve($request),
            201,
            $result['invitation_sent']
                ? 'User created and invitation sent.'
                : 'User created, but the invitation could not be delivered.',
            ['invitation_sent' => $result['invitation_sent']],
        );
    }

    public function show(Request $request, string $user): JsonResponse
    {
        $this->validateOnly($request, []);
        $target = $this->users->findForTenant($this->tenant($request), $user);

        return ThinkTankApiResponse::success((new ThinkTankUserResource($target))->resolve($request));
    }

    public function update(Request $request, string $user): JsonResponse
    {
        $tenant = $this->tenant($request);
        // Scope before body validation so an out-of-tenant identifier is always 404.
        $target = $this->users->findForTenant($tenant, $user);
        $data = $this->validateOnly($request, [
            'name' => ['sometimes', 'required', 'string', 'max:255', 'not_regex:/^\s*$/'],
            'email' => ['sometimes', 'required', 'string', 'email:rfc', 'max:255'],
            'access_level' => ['sometimes', 'required', Rule::in(array_keys(User::THINK_TANK_ACCESS_LEVELS))],
            'is_disabled' => ['sometimes', 'required', 'boolean'],
        ]);

        if ($data === []) {
            throw ValidationException::withMessages([
                'request' => ['At least one editable field is required.'],
            ]);
        }

        $result = $this->users->update(
            $request,
            $request->user(),
            $tenant,
            $target,
            UpdateThinkTankUserData::from($data),
        );

        return ThinkTankApiResponse::success(
            (new ThinkTankUserResource($result['user']))->resolve($request),
            200,
            match ($result['invitation_sent']) {
                true => 'User updated and a new invitation was sent.',
                false => 'User updated, but the invitation could not be delivered. Resend it before the user signs in.',
                null => 'User updated successfully.',
            },
            $result['invitation_sent'] === null
                ? []
                : ['invitation_sent' => $result['invitation_sent']],
        );
    }

    public function invitation(Request $request, string $user): JsonResponse
    {
        $this->validateOnly($request, []);
        $tenant = $this->tenant($request);
        $target = $this->users->findForTenant($tenant, $user);
        $sent = $this->users->resendInvitation($request, $request->user(), $tenant, $target);

        return ThinkTankApiResponse::success(
            ['invitation_sent' => $sent],
            202,
            $sent ? 'Invitation sent.' : 'The invitation could not be delivered.',
        );
    }

    private function tenant(Request $request): ConsortiumThinkTank
    {
        /** @var ConsortiumThinkTank $tenant */
        $tenant = $request->attributes->get('think_tank.membership');

        return $tenant;
    }
}
