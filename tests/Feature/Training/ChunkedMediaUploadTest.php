<?php

namespace Tests\Feature\Training;

use App\Enums\MediaType;
use App\Models\ChecklistItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChunkedMediaUploadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Drive a full init → chunk × N → complete cycle and return the upload id.
     *
     * @param  array<int, string>  $chunks  raw chunk payloads
     */
    private function uploadInChunks(
        User $admin,
        ChecklistItem $item,
        array $chunks,
        string $filename = 'demo.mp4',
        string $type = 'video',
    ): string {
        $size = array_sum(array_map('strlen', $chunks));

        $upload = $this->actingAs($admin)
            ->postJson(route('training.media.uploads.init', $item), [
                'type' => $type,
                'filename' => $filename,
                'size' => $size,
            ])
            ->assertOk()
            ->json('upload_id');

        foreach ($chunks as $part) {
            $this->actingAs($admin)
                ->post(route('training.media.uploads.chunk', $upload), [
                    'chunk' => UploadedFile::fake()->createWithContent('chunk', $part),
                ])
                ->assertOk();
        }

        return $upload;
    }

    public function test_a_video_uploads_in_chunks_and_becomes_media(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $admin = User::factory()->superAdmin()->create();
        $item = ChecklistItem::factory()->create();

        // A minimal valid MP4 header so the mimetype check passes, padded out.
        $content = "\x00\x00\x00\x18ftypmp42".str_repeat('D', 60_000);
        $upload = $this->uploadInChunks($admin, $item, str_split($content, 20_000));

        $this->actingAs($admin)
            ->postJson(route('training.media.uploads.complete', $upload))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $media = $item->media()->sole();
        $this->assertSame(MediaType::Video, $media->type);
        $this->assertSame('demo.mp4', $media->label);
        Storage::disk('public')->assertExists($media->path);

        // The chunk temp file is cleaned up.
        $this->assertEmpty(Storage::disk('local')->files('media-chunks'));
    }

    public function test_init_rejects_a_file_over_the_type_limit(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $item = ChecklistItem::factory()->create();

        $this->actingAs($admin)
            ->postJson(route('training.media.uploads.init', $item), [
                'type' => 'video',
                'filename' => 'huge.mp4',
                'size' => (MediaType::Video->maxKilobytes() * 1024) + 1,
            ])
            ->assertJsonValidationErrors('size');
    }

    public function test_a_chunk_cannot_exceed_the_declared_size(): void
    {
        Storage::fake('local');
        $admin = User::factory()->superAdmin()->create();
        $item = ChecklistItem::factory()->create();

        $upload = $this->actingAs($admin)
            ->postJson(route('training.media.uploads.init', $item), [
                'type' => 'video',
                'filename' => 'demo.mp4',
                'size' => 100,
            ])
            ->json('upload_id');

        // Send more than declared → refused, session discarded.
        $this->actingAs($admin)
            ->post(route('training.media.uploads.chunk', $upload), [
                'chunk' => UploadedFile::fake()->createWithContent('chunk', str_repeat('x', 500)),
            ])
            ->assertStatus(422);
    }

    public function test_complete_fails_when_bytes_are_missing(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();
        $item = ChecklistItem::factory()->create();

        // Declare 1000 bytes but only send 400.
        $upload = $this->actingAs($admin)
            ->postJson(route('training.media.uploads.init', $item), [
                'type' => 'video',
                'filename' => 'demo.mp4',
                'size' => 1000,
            ])
            ->json('upload_id');

        $this->actingAs($admin)
            ->post(route('training.media.uploads.chunk', $upload), [
                'chunk' => UploadedFile::fake()->createWithContent('chunk', str_repeat('x', 400)),
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->postJson(route('training.media.uploads.complete', $upload))
            ->assertStatus(422);

        $this->assertSame(0, $item->media()->count());
    }

    public function test_complete_rejects_a_non_video_payload(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();
        $item = ChecklistItem::factory()->create();

        // Declared as video, but the bytes are plainly not a video.
        $content = str_repeat('this is definitely not a video file', 100);
        $upload = $this->uploadInChunks($admin, $item, [$content]);

        $this->actingAs($admin)
            ->postJson(route('training.media.uploads.complete', $upload))
            ->assertStatus(422);

        $this->assertSame(0, $item->media()->count());
    }

    public function test_another_user_cannot_touch_someone_elses_upload(): void
    {
        Storage::fake('local');
        $owner = User::factory()->superAdmin()->create();
        $other = User::factory()->superAdmin()->create();
        $item = ChecklistItem::factory()->create();

        $upload = $this->actingAs($owner)
            ->postJson(route('training.media.uploads.init', $item), [
                'type' => 'video',
                'filename' => 'demo.mp4',
                'size' => 100,
            ])
            ->json('upload_id');

        $this->actingAs($other)
            ->post(route('training.media.uploads.chunk', $upload), [
                'chunk' => UploadedFile::fake()->createWithContent('chunk', 'xx'),
            ])
            ->assertForbidden();
    }

    public function test_managers_cannot_use_chunked_upload(): void
    {
        $manager = User::factory()->manager()->create();
        $item = ChecklistItem::factory()->create();

        $this->actingAs($manager)
            ->postJson(route('training.media.uploads.init', $item), [
                'type' => 'video',
                'filename' => 'demo.mp4',
                'size' => 100,
            ])
            ->assertForbidden();
    }
}
