<?php

namespace App\Http\Controllers\Api\V1\ThinkTank;

use App\Models\ConsortiumThinkTank;
use App\Models\ThinkTankBudgetLine;
use App\Models\User;
use App\Services\ThinkTankFinanceApiService;
use App\Services\ThinkTankFundingReceiptService;
use App\Support\ThinkTankApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinanceController extends ThinkTankApiController
{
    public function __construct(
        private readonly ThinkTankFinanceApiService $finance,
        private readonly ThinkTankFundingReceiptService $receipts,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $this->validateOnly($request, []);

        return ThinkTankApiResponse::success(
            $this->finance->overview($this->member($request), $this->actor($request))
        );
    }

    public function funds(Request $request): JsonResponse
    {
        $filters = $this->validateOnly($request, [
            'q' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/'],
            'currency' => ['nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
        ]);

        return ThinkTankApiResponse::success(
            $this->finance->funds($this->member($request), $this->actor($request), $filters)
        );
    }

    public function fundingRequest(Request $request): JsonResponse
    {
        $data = $this->validateOnly($request, [
            'fund_allocation_id' => ['nullable', 'uuid'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999999.99'],
            'currency' => ['required', 'string', Rule::in(['USD'])],
            'purpose' => ['required', 'string', 'min:10', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ]);
        $member = $this->member($request);
        $result = $this->finance->createFundingRequest($request, $member, $this->actor($request), $data);

        return ThinkTankApiResponse::success(
            [
                'fundingRequest' => $this->finance->fundingRequestResource($result['request']),
                'idempotent' => $result['idempotent'],
            ],
            $result['idempotent'] ? 200 : 201,
            $result['idempotent'] ? 'Funding request replayed safely.' : 'Funding request submitted.',
            ['meta' => ['idempotent' => $result['idempotent']]],
        );
    }

    public function confirmTransfer(Request $request, string $disbursement): JsonResponse
    {
        $data = $this->validateOnly($request, [
            'lock_token' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/i'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $member = $this->member($request);
        $actor = $this->actor($request);
        $result = $this->receipts->confirm(
            $request,
            $member,
            $actor,
            $disbursement,
            $data['lock_token'],
            $data['notes'] ?? null,
        );

        return ThinkTankApiResponse::success([
            'transfer' => $this->finance->transferResource($result['transfer'], $actor),
            'idempotent' => $result['idempotent'],
        ], 200, $result['idempotent'] ? 'Receipt was already confirmed.' : 'Receipt confirmed.');
    }

    public function budgetLines(Request $request): JsonResponse
    {
        $filters = $this->validateOnly($request, [
            'q' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'string', Rule::in(ThinkTankBudgetLine::STATUSES)],
            'fiscal_year' => ['nullable', 'string', 'max:7', 'regex:/^20\d{2}(?:\/\d{2})?$/'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return ThinkTankApiResponse::success(
            $this->finance->budgetLines($this->member($request), $this->actor($request), $filters)
        );
    }

    public function storeBudgetLine(Request $request): JsonResponse
    {
        $member = $this->member($request);
        $line = $this->finance->createBudgetLine(
            $request,
            $member,
            $this->actor($request),
            $this->validateOnly($request, $this->budgetLineRules(false)),
        );

        return ThinkTankApiResponse::success(
            $this->finance->budgetLine($member, $line),
            201,
            'Budget line created.',
        );
    }

    public function updateBudgetLine(Request $request, string $line): JsonResponse
    {
        $data = $this->validateOnly($request, $this->budgetLineRules(true));
        if (array_diff(array_keys($data), ['lock_token']) === []) {
            throw ValidationException::withMessages([
                'budget_line' => ['Provide at least one budget-line field to update.'],
            ]);
        }

        $member = $this->member($request);
        $updated = $this->finance->updateBudgetLine(
            $request,
            $member,
            $this->actor($request),
            $line,
            $data,
        );

        return ThinkTankApiResponse::success(
            $this->finance->budgetLine($member, $updated),
            200,
            'Budget line updated.',
        );
    }

    public function execution(Request $request): JsonResponse
    {
        $filters = $this->validateOnly($request, [
            'q' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/'],
            'fiscal_year' => ['nullable', 'string', 'max:7', 'regex:/^20\d{2}(?:\/\d{2})?$/'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return ThinkTankApiResponse::success(
            $this->finance->execution($this->member($request), $this->actor($request), $filters)
        );
    }

    public function reports(Request $request): JsonResponse
    {
        $filters = $this->validateOnly($request, [
            'period' => ['nullable', 'integer', 'digits:4', 'min:2000', 'max:'.now()->year],
            'currency' => ['nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
        ]);

        return ThinkTankApiResponse::success(
            $this->finance->reports($this->member($request), $this->actor($request), $filters)
        );
    }

    /** @return array<string, mixed> */
    private function budgetLineRules(bool $update): array
    {
        $presence = $update ? 'sometimes' : 'required';

        return [
            'parent_id' => ['sometimes', 'nullable', 'uuid'],
            'fund_allocation_id' => ['sometimes', 'nullable', 'uuid'],
            'procurement_item_id' => ['sometimes', 'nullable', 'uuid'],
            'code' => [$presence, 'string', 'max:80', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/'],
            'name' => [$presence, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'fiscal_year' => [$presence, 'string', 'max:7', 'regex:/^20\d{2}(?:\/\d{2})?$/'],
            'currency' => ['sometimes', 'string', Rule::in(['USD'])],
            'amount' => [$presence, 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999999.99'],
            'status' => ['sometimes', 'string', Rule::in(ThinkTankBudgetLine::STATUSES)],
            'lock_token' => $update
                ? ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/i']
                : ['prohibited'],
        ];
    }

    private function member(Request $request): ConsortiumThinkTank
    {
        $member = $request->attributes->get('think_tank.membership');
        abort_unless($member instanceof ConsortiumThinkTank, 403);

        return $member;
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
