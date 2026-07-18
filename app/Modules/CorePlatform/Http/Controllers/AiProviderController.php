<?php

namespace App\Modules\CorePlatform\Http\Controllers;

use App\Models\AiProvider;
use App\Models\AiProviderModel;
use App\Models\AiTurnLineage;
use App\Models\Tenant;
use App\Modules\CorePlatform\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Platform-wide AI provider + model management, and per-tenant cost
 * reporting. Cost is computed from ai_turn_lineage (tokens_prompt/
 * tokens_completion, populated by ConversationEngine::recordLineage
 * on every AI-answered turn) x the assigned model's per-1M-token
 * rates — real numbers, not estimates, for whatever traffic has
 * actually run. Turns using a model with no configured cost row (e.g.
 * "mock") correctly cost $0.
 */
class AiProviderController
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $providers = AiProvider::with('models')->orderBy('name')->get();

        return $this->success($providers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'api_key' => ['sometimes', 'nullable', 'string'],
            'base_url' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $provider = AiProvider::create($validated);

        return $this->success($provider, status: 201);
    }

    public function storeModel(Request $request, string $providerId): JsonResponse
    {
        $provider = AiProvider::find($providerId);

        if ($provider === null) {
            return $this->error('provider_not_found', 'Provider not found.', 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'input_cost_per_1m' => ['required', 'numeric', 'min:0'],
            'output_cost_per_1m' => ['required', 'numeric', 'min:0'],
        ]);

        $model = AiProviderModel::create([
            'provider_id' => $provider->provider_id,
            ...$validated,
        ]);

        return $this->success($model, status: 201);
    }

    public function assignTenant(Request $request, string $tenantId): JsonResponse
    {
        $tenant = Tenant::find($tenantId);

        if ($tenant === null) {
            return $this->error('tenant_not_found', 'Tenant not found.', 404);
        }

        $validated = $request->validate([
            'ai_provider_model_id' => ['required', 'uuid', 'exists:ai_provider_models,model_id'],
        ]);

        $tenant->update($validated);

        return $this->success($tenant->fresh('aiModel'));
    }

    public function costs(Request $request): JsonResponse
    {
        $monthStart = now()->startOfMonth();

        $models = AiProviderModel::with('provider')->get()->keyBy('model_id');
        $tenants = Tenant::with('aiModel')->get()->keyBy('tenant_id');

        $usage = AiTurnLineage::where('created_at', '>=', $monthStart)
            ->selectRaw('tenant_id, model_id, sum(tokens_prompt) as prompt_tokens, sum(tokens_completion) as completion_tokens, count(*) as turns')
            ->groupBy('tenant_id', 'model_id')
            ->get();

        $byTenant = [];

        foreach ($usage as $row) {
            $tenant = $tenants->get($row->tenant_id);
            $rateModel = $tenant?->aiModel;
            $cost = $rateModel
                ? ($row->prompt_tokens / 1_000_000 * $rateModel->input_cost_per_1m)
                    + ($row->completion_tokens / 1_000_000 * $rateModel->output_cost_per_1m)
                : 0.0;

            $byTenant[$row->tenant_id] ??= [
                'tenant_id' => $row->tenant_id,
                'tenant_name' => $tenant?->name ?? $tenant?->slug ?? 'Unknown',
                'model' => $rateModel?->name,
                'provider' => $rateModel?->provider?->name,
                'tokens' => 0,
                'turns' => 0,
                'cost_usd' => 0.0,
            ];

            $byTenant[$row->tenant_id]['tokens'] += $row->prompt_tokens + $row->completion_tokens;
            $byTenant[$row->tenant_id]['turns'] += $row->turns;
            $byTenant[$row->tenant_id]['cost_usd'] += round($cost, 4);
        }

        return $this->success(array_values($byTenant));
    }
}
