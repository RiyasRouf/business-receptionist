<?php

namespace App\Modules\KnowledgeBase\Http\Controllers;

use App\Models\KbDocument;
use App\Modules\KnowledgeBase\Services\KnowledgeBaseService;
use App\Modules\KnowledgeBase\Services\RetrievalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KnowledgeBaseController
{
    public function __construct(
        private readonly KnowledgeBaseService $kb,
        private readonly RetrievalService $retrieval,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('auth_tenant_id');

        $documents = KbDocument::where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->get(['document_id', 'title', 'status', 'created_at']);

        return response()->json(['data' => $documents]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            // MVP: plain-text KB sources. PDF/DOCX extraction needs a
            // parser library choice not yet made — tracked as follow-up.
            'file' => ['required', 'file', 'mimes:txt,md', 'max:10240'],
        ]);

        $tenantId = $request->attributes->get('auth_tenant_id');

        $path = $request->file('file')->store("kb/{$tenantId}", 'local');
        $rawText = Storage::disk('local')->get($path);

        $document = $this->kb->ingest($tenantId, $validated['title'], $path, $rawText);

        return response()->json(['data' => $document], 201);
    }

    public function destroy(Request $request, string $documentId): JsonResponse
    {
        $tenantId = $request->attributes->get('auth_tenant_id');

        $document = KbDocument::where('tenant_id', $tenantId)
            ->where('document_id', $documentId)
            ->firstOrFail();

        $this->kb->delete($document);

        return response()->json(['message' => 'Deleted']);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string'],
            'top_k' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $tenantId = $request->attributes->get('auth_tenant_id');

        $results = $this->retrieval->retrieve(
            $tenantId,
            $validated['query'],
            $validated['top_k'] ?? null
        );

        return response()->json(['data' => $results]);
    }
}
