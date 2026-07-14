<?php

namespace App\Modules\Media\Http\Controllers;

use App\Models\Transcript;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TranscriptController
{
    /**
     * Signed URL only (ADR-064) — the 'signed' middleware on this route
     * rejects any request whose signature doesn't validate, so no
     * separate JWT/tenant check is needed here for the download itself.
     * Generating the signed link in the first place IS tenant-gated
     * (MediaService::signedDownloadUrl is only called from authenticated,
     * tenant-scoped code paths).
     */
    public function download(Request $request, string $transcript): StreamedResponse
    {
        $record = Transcript::findOrFail($transcript);

        abort_unless(Storage::disk('local')->exists($record->storage_path), 404);

        return Storage::disk('local')->download($record->storage_path, "transcript-{$record->transcript_id}.txt");
    }
}
