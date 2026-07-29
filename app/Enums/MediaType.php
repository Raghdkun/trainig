<?php

namespace App\Enums;

enum MediaType: string
{
    case Link = 'link';
    case File = 'file';
    case Image = 'image';
    case Video = 'video';

    public function label(): string
    {
        return match ($this) {
            self::Link => 'Link',
            self::File => 'File',
            self::Image => 'Image',
            self::Video => 'Video',
        };
    }

    /**
     * Whether this media type is stored on disk (vs. an external URL).
     */
    public function isUploaded(): bool
    {
        return $this !== self::Link;
    }

    /**
     * Maximum upload size in KILOBYTES (Laravel's `max:` unit).
     *
     * Uploads are chunked (see MediaUploadController), so these ceilings are NOT
     * bounded by the PHP `post_max_size` — each request only ever carries a
     * single ~5 MB chunk. The server just needs its body limit to exceed one
     * chunk (the shipped 64M/68M is ample); the total can be far larger.
     */
    public function maxKilobytes(): int
    {
        return match ($this) {
            self::Image => 5 * 1024,      // 5 MB
            self::Video => 500 * 1024,    // 500 MB (uploaded in chunks)
            self::File => 10 * 1024,      // 10 MB
            self::Link => 0,
        };
    }

    /**
     * The validation rules for an uploaded `file` of this type. Shared by the
     * direct-upload request and the chunked-upload finaliser so both enforce
     * exactly the same constraints.
     *
     * @return array<int, string>
     */
    public function fileValidationRules(): array
    {
        return match ($this) {
            self::Image => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.$this->maxKilobytes()],
            self::Video => ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:'.$this->maxKilobytes()],
            self::File => ['required', 'file', 'mimes:pdf,doc,docx,xlsx,csv,txt', 'max:'.$this->maxKilobytes()],
            self::Link => [],
        };
    }

    /**
     * The `accept` attribute for the file picker, so the OS dialog filters to
     * what the server will actually take.
     */
    public function accept(): string
    {
        return match ($this) {
            self::Image => 'image/jpeg,image/png,image/webp,image/gif',
            self::Video => 'video/mp4,video/quicktime,video/webm',
            self::File => '.pdf,.doc,.docx,.xlsx,.csv,.txt',
            self::Link => '',
        };
    }

    /**
     * Upload constraints for every type, shared with the frontend so the client
     * can reject an oversized file before it is ever sent.
     *
     * @return array<string, array{max_kb: int, accept: string}>
     */
    public static function uploadLimits(): array
    {
        $limits = [];

        foreach (self::cases() as $case) {
            if ($case->isUploaded()) {
                $limits[$case->value] = [
                    'max_kb' => $case->maxKilobytes(),
                    'accept' => $case->accept(),
                ];
            }
        }

        return $limits;
    }
}
