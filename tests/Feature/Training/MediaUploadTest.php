<?php

namespace Tests\Feature\Training;

use App\Enums\MediaType;
use App\Models\ChecklistItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_upload_an_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();
        $item = ChecklistItem::factory()->create();

        $this->actingAs($admin)->post(route('training.media.store', $item), [
            'type' => MediaType::Image->value,
            'file' => UploadedFile::fake()->image('day-dot.jpg'),
            'label' => 'Day Dot placement',
        ])->assertSessionHasNoErrors();

        $media = $item->media()->sole();
        $this->assertSame(MediaType::Image, $media->type);
        $this->assertNotNull($media->path);
        $this->assertNull($media->url);
        Storage::disk('public')->assertExists($media->path);

        // Managers/admins open the file via an origin-relative public URL.
        $this->assertStringStartsWith('/storage/', (string) $media->display_url);
        $this->assertStringContainsString($media->path, (string) $media->display_url);
    }

    public function test_super_admin_can_upload_a_video(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();
        $item = ChecklistItem::factory()->create();

        $this->actingAs($admin)->post(route('training.media.store', $item), [
            'type' => MediaType::Video->value,
            'file' => UploadedFile::fake()->create('demo.mp4', 2048, 'video/mp4'),
        ])->assertSessionHasNoErrors();

        $media = $item->media()->sole();
        $this->assertSame(MediaType::Video, $media->type);
        Storage::disk('public')->assertExists($media->path);

        // With no label given, the original filename is kept so the attachment
        // doesn't render as a generic "Attachment".
        $this->assertSame('demo.mp4', $media->label);
    }

    public function test_oversized_video_is_rejected(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();
        $item = ChecklistItem::factory()->create();

        $tooBig = MediaType::Video->maxKilobytes() + 1024;

        $this->actingAs($admin)->post(route('training.media.store', $item), [
            'type' => MediaType::Video->value,
            'file' => UploadedFile::fake()->create('huge.mp4', $tooBig, 'video/mp4'),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, $item->media()->count());
    }

    public function test_non_video_mime_is_rejected_for_video_type(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();
        $item = ChecklistItem::factory()->create();

        $this->actingAs($admin)->post(route('training.media.store', $item), [
            'type' => MediaType::Video->value,
            'file' => UploadedFile::fake()->create('not-a-video.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('file');
    }

    public function test_upload_limits_are_shared_with_the_frontend(): void
    {
        $limits = MediaType::uploadLimits();

        // Links aren't uploads; every uploadable type needs a size + accept hint.
        $this->assertArrayNotHasKey('link', $limits);

        foreach (['image', 'video', 'file'] as $type) {
            $this->assertArrayHasKey($type, $limits);
            $this->assertGreaterThan(0, $limits[$type]['max_kb']);
            $this->assertNotSame('', $limits[$type]['accept']);
        }

        // Video is chunk-uploaded, so its ceiling is far above the PHP body
        // limit — pin it so a regression that quietly shrinks it is caught.
        $this->assertSame(500 * 1024, $limits['video']['max_kb']);
    }

    public function test_super_admin_can_delete_media_and_its_file(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();
        $item = ChecklistItem::factory()->create();

        $this->actingAs($admin)->post(route('training.media.store', $item), [
            'type' => MediaType::Image->value,
            'file' => UploadedFile::fake()->image('temp.jpg'),
        ])->assertSessionHasNoErrors();

        $media = $item->media()->sole();
        $path = $media->path;

        $this->actingAs($admin)
            ->delete(route('training.media.destroy', $media))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('media_items', ['id' => $media->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_super_admin_can_add_a_link(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $item = ChecklistItem::factory()->create();

        $this->actingAs($admin)->post(route('training.media.store', $item), [
            'type' => MediaType::Link->value,
            'url' => 'https://littlecaesars.read.inkling.com/example',
            'label' => 'Reference',
        ])->assertSessionHasNoErrors();

        $media = $item->media()->sole();
        $this->assertSame(MediaType::Link, $media->type);
        $this->assertSame('https://littlecaesars.read.inkling.com/example', $media->url);
        $this->assertNull($media->path);
        // A link opens at its external URL unchanged.
        $this->assertSame($media->url, $media->display_url);
    }

    public function test_oversized_image_is_rejected(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();
        $item = ChecklistItem::factory()->create();

        $this->actingAs($admin)->post(route('training.media.store', $item), [
            'type' => MediaType::Image->value,
            'file' => UploadedFile::fake()->image('huge.jpg')->size(6000),
        ])->assertSessionHasErrors('file');
    }

    public function test_wrong_mime_for_image_is_rejected(): void
    {
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();
        $item = ChecklistItem::factory()->create();

        $this->actingAs($admin)->post(route('training.media.store', $item), [
            'type' => MediaType::Image->value,
            'file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('file');
    }

    public function test_link_without_url_is_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $item = ChecklistItem::factory()->create();

        $this->actingAs($admin)->post(route('training.media.store', $item), [
            'type' => MediaType::Link->value,
        ])->assertSessionHasErrors('url');
    }

    public function test_managers_cannot_add_media(): void
    {
        $manager = User::factory()->manager()->create();
        $item = ChecklistItem::factory()->create();

        $this->actingAs($manager)->post(route('training.media.store', $item), [
            'type' => MediaType::Link->value,
            'url' => 'https://example.com',
        ])->assertForbidden();
    }
}
