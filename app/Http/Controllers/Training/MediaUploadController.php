<?php

namespace App\Http\Controllers\Training;

use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\InitMediaUploadRequest;
use App\Models\ChecklistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Chunked, resumable media uploads. A large file (video) is split client-side
 * into small parts so no single request approaches PHP's post_max_size or the
 * FastCGI timeout — the failure mode that produced bare Apache 503s in prod.
 *
 * Flow: init (open a session) → chunk × N (append to a temp file on the local
 * disk) → complete (validate the assembled file, move it to the public disk,
 * create the MediaItem). cancel discards a partial upload.
 */
class MediaUploadController extends Controller
{
    /** Chunk size (KB) advertised to the client. */
    private const CHUNK_KB = 5120;

    /** How long an in-progress upload session may sit idle. */
    private const TTL_HOURS = 2;

    public function init(InitMediaUploadRequest $request, ChecklistItem $checklistItem): JsonResponse
    {
        $uploadId = (string) Str::uuid();

        Cache::put($this->cacheKey($uploadId), [
            'user_id' => $request->user()->id,
            'item_id' => $checklistItem->id,
            'type' => $request->validated('type'),
            'label' => $request->validated('label'),
            'filename' => $request->validated('filename'),
            'size' => (int) $request->validated('size'),
            'received' => 0,
        ], now()->addHours(self::TTL_HOURS));

        return response()->json([
            'upload_id' => $uploadId,
            'chunk_size' => self::CHUNK_KB * 1024,
        ]);
    }

    public function chunk(Request $request, string $upload): JsonResponse
    {
        $session = $this->session($request, $upload);

        $request->validate([
            // A little headroom over the advertised chunk size.
            'chunk' => ['required', 'file', 'max:'.(self::CHUNK_KB + 512)],
        ]);

        $chunk = $request->file('chunk');
        $maxBytes = MediaType::from($session['type'])->maxKilobytes() * 1024;
        $projected = $session['received'] + $chunk->getSize();

        // Never let a client stream more than it declared, or past the ceiling.
        if ($projected > $session['size'] || $projected > $maxBytes) {
            $this->discard($upload);

            return response()->json(['message' => __('The upload exceeds the allowed size.')], 422);
        }

        $this->append($this->partPath($upload), $chunk);

        $session['received'] = $projected;
        Cache::put($this->cacheKey($upload), $session, now()->addHours(self::TTL_HOURS));

        return response()->json(['received' => $session['received']]);
    }

    public function complete(Request $request, string $upload): JsonResponse
    {
        $session = $this->session($request, $upload);
        $type = MediaType::from($session['type']);
        $disk = Storage::disk('local');
        $partPath = $this->partPath($upload);

        if ($session['received'] !== $session['size'] || ! $disk->exists($partPath)) {
            $this->discard($upload);

            return response()->json(['message' => __('The upload did not finish. Please try again.')], 422);
        }

        $fullPath = $disk->path($partPath);

        // Validate the reassembled file with the SAME rules as a direct upload
        // (size + real MIME via finfo), so chunking can't smuggle a bad file.
        $uploaded = new UploadedFile($fullPath, $session['filename'], null, null, true);
        $validator = Validator::make(['file' => $uploaded], ['file' => $type->fileValidationRules()]);

        if ($validator->fails()) {
            $this->discard($upload);

            return response()->json(['message' => $validator->errors()->first('file')], 422);
        }

        $item = ChecklistItem::find($session['item_id']);

        if (! $item) {
            $this->discard($upload);

            return response()->json(['message' => __('That checklist item no longer exists.')], 404);
        }

        // Stream to the public disk (never load the whole video into memory).
        $extension = strtolower((string) pathinfo($session['filename'], PATHINFO_EXTENSION))
            ?: (string) $uploaded->guessExtension();
        $destination = "training/media/{$item->id}/".Str::random(40).($extension !== '' ? ".{$extension}" : '');

        $stream = fopen($fullPath, 'rb');
        $stored = $stream !== false && Storage::disk('public')->put($destination, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        if ($stored === false) {
            $this->discard($upload);

            return response()->json(['message' => __('The file could not be saved. Please try again.')], 500);
        }

        $item->media()->create([
            'type' => $type,
            'label' => $session['label'] ?: $session['filename'],
            'path' => $destination,
            'order' => (int) $item->media()->max('order') + 1,
        ]);

        $this->discard($upload);

        return response()->json(['ok' => true]);
    }

    public function cancel(Request $request, string $upload): JsonResponse
    {
        $this->session($request, $upload);
        $this->discard($upload);

        return response()->json(['ok' => true]);
    }

    /**
     * Load the upload session, enforcing existence and ownership.
     *
     * @return array{user_id: int, item_id: int, type: string, label: string|null, filename: string, size: int, received: int}
     */
    private function session(Request $request, string $upload): array
    {
        /** @var array{user_id: int, item_id: int, type: string, label: string|null, filename: string, size: int, received: int}|null $session */
        $session = Cache::get($this->cacheKey($upload));

        abort_if($session === null, 404, __('This upload session has expired. Please start again.'));
        abort_if($session['user_id'] !== $request->user()->id, 403);

        return $session;
    }

    private function append(string $partPath, UploadedFile $chunk): void
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory('media-chunks');

        $out = fopen($disk->path($partPath), 'ab');
        $in = fopen($chunk->getRealPath(), 'rb');

        if ($out !== false && $in !== false) {
            stream_copy_to_stream($in, $out);
        }

        if (is_resource($in)) {
            fclose($in);
        }

        if (is_resource($out)) {
            fclose($out);
        }
    }

    private function discard(string $upload): void
    {
        Storage::disk('local')->delete($this->partPath($upload));
        Cache::forget($this->cacheKey($upload));
    }

    private function cacheKey(string $upload): string
    {
        return "media-upload:{$upload}";
    }

    private function partPath(string $upload): string
    {
        return "media-chunks/{$upload}.part";
    }
}
