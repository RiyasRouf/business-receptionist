<?php

namespace App\Modules\CorePlatform\Http\Controllers;

use App\Models\AiProvider;
use App\Models\AiProviderModel;
use App\Models\AiTurnLineage;
use App\Models\Tenant;
use App\Modules\CorePlatform\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    public function update(Request $request, string $providerId): JsonResponse
    {
        $provider = AiProvider::find($providerId);

        if ($provider === null) {
            return $this->error('provider_not_found', 'Provider not found.', 404);
        }

        $validated = $request->validate([
            'api_key' => ['sometimes', 'nullable', 'string'],
            'base_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        $provider->update($validated);

        return $this->success($provider);
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

    /**
     * "All models available for the API key" — a live models-list call
     * against the provider's real API when the provider's name and
     * stored key allow it (OpenAI, Gemini, Anthropic each expose one).
     * Falls back to a curated static list on any failure (no key set,
     * invalid key, network error, or an unrecognised/custom provider
     * name) so the picker is never just empty — providers don't return
     * per-token pricing via these endpoints either way, so cost stays
     * admin-entered regardless of source.
     */
    public function fetchModels(Request $request, string $providerId): JsonResponse
    {
        $provider = AiProvider::find($providerId);

        if ($provider === null) {
            return $this->error('provider_not_found', 'Provider not found.', 404);
        }

        $names = strtolower($provider->name);
        $live = null;

        try {
            if ($provider->api_key && str_contains($names, 'openai')) {
                $resp = Http::withToken($provider->api_key)->timeout(8)->get('https://api.openai.com/v1/models');
                if ($resp->successful()) {
                    $live = collect($resp->json('data'))->pluck('id')->filter(fn ($id) => str_starts_with($id, 'gpt-'))->values()->all();
                }
            } elseif ($provider->api_key && (str_contains($names, 'gemini') || str_contains($names, 'google'))) {
                $resp = Http::timeout(8)->get('https://generativelanguage.googleapis.com/v1beta/models', ['key' => $provider->api_key]);
                if ($resp->successful()) {
                    $live = collect($resp->json('models'))->pluck('name')->map(fn ($n) => str_replace('models/', '', $n))->values()->all();
                }
            } elseif ($provider->api_key && (str_contains($names, 'anthropic') || str_contains($names, 'claude'))) {
                $resp = Http::withHeaders(['x-api-key' => $provider->api_key, 'anthropic-version' => '2023-06-01'])
                    ->timeout(8)->get('https://api.anthropic.com/v1/models');
                if ($resp->successful()) {
                    $live = collect($resp->json('data'))->pluck('id')->values()->all();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ai_provider.fetch_models_failed', ['provider_id' => $providerId, 'error' => $e->getMessage()]);
        }

        if ($live !== null && $live !== []) {
            return $this->success(['source' => 'live', 'models' => $live]);
        }

        return $this->success(['source' => 'fallback', 'models' => $this->fallbackModels($names)]);
    }

    /** @return string[] */
    private function fallbackModels(string $providerNameLower): array
    {
        return match (true) {
            str_contains($providerNameLower, 'openai') => ['gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo', 'gpt-3.5-turbo'],
            str_contains($providerNameLower, 'anthropic'), str_contains($providerNameLower, 'claude') => ['claude-sonnet-4-6', 'claude-haiku-4-5', 'claude-opus-4-8'],
            str_contains($providerNameLower, 'gemini'), str_contains($providerNameLower, 'google') => ['gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-1.5-flash'],
            default => [],
        };
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

        $tenants = Tenant::with('aiModel.provider')->get()->keyBy('tenant_id');

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
