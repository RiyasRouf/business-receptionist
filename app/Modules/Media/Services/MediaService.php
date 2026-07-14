<?php

namespace App\Modules\Media\Services;

use App\Models\Session;
use App\Models\Transcript;
use App\Modules\ConversationEngine\Services\ConversationEngine;
use App\Modules\OutboxRelay\Services\OutboxService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * STT pipeline coordination is voice-specific and not applicable while
 * voice is held (D-013-02) — this handles the channel-agnostic part:
 * turning a session's full turn log into a stored transcript
 * (ADR-064: tenant_id first path segment, Laravel local private
 * storage for MVP, signed URL access only).
 */
class MediaService
{
    public function __construct(
        private readonly ConversationEngine $engine,
        private readonly OutboxService $outbox,
    ) {}

    public function generateTranscript(Session $session): Transcript
    {
        $turns = $this->engine->getFullTurnLog($session);
        $content = implode("\n", array_map(
            fn (array $t) => strtoupper($t['role']).': '.$t['content'],
            $turns
        ));

        $path = "{$session->tenant_id}/transcripts/{$session->session_id}.txt";
        Storage::disk('local')->put($path, $content);

        return DB::transaction(function () use ($session, $content, $path) {
            $transcript = Transcript::create([
                'tenant_id' => $session->tenant_id,
                'session_id' => $session->session_id,
                'content' => $content,
                'storage_path' => $path,
            ]);

            $this->outbox->write(
                tenantId: $session->tenant_id,
                eventType: 'transcript.generated',
                payload: ['transcript_id' => $transcript->transcript_id],
                sessionId: $session->session_id,
            );

            return $transcript;
        });
    }

    /**
     * Signed URL access only (ADR-064) — never a public path.
     */
    public function signedDownloadUrl(Transcript $transcript, int $expiresInMinutes = 15): string
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'transcripts.download',
            now()->addMinutes($expiresInMinutes),
            ['transcript' => $transcript->transcript_id]
        );
    }
}
