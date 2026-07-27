import { router, useForm, usePage } from '@inertiajs/react';
import {
    ExternalLink,
    FileText,
    ImageIcon,
    LinkIcon,
    Trash2,
    Video,
} from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { VideoPlayer } from '@/components/training/video-player';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { uploadMediaInChunks } from '@/lib/chunked-upload';
import { destroy, store } from '@/routes/training/media';
import type { ChecklistItem, MediaLimits, MediaType } from '@/types/training';

const TYPE_ICON = {
    link: LinkIcon,
    image: ImageIcon,
    video: Video,
    file: FileText,
} as const;

function formatSize(bytes: number): string {
    return bytes >= 1024 * 1024
        ? `${(bytes / (1024 * 1024)).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

export function MediaManager({ item }: { item: ChecklistItem }) {
    const { mediaLimits } = usePage<{ mediaLimits: MediaLimits }>().props;
    const [adding, setAdding] = useState(false);
    const [fileError, setFileError] = useState<string | null>(null);
    const [uploadPercent, setUploadPercent] = useState<number | null>(null);
    const abortRef = useRef<AbortController | null>(null);
    const form = useForm<{
        type: MediaType;
        url: string;
        label: string;
        file: File | null;
    }>({
        type: 'link',
        url: '',
        label: '',
        file: null,
    });

    const limit = mediaLimits?.[form.data.type];
    const uploading = uploadPercent !== null;

    /**
     * Reject an oversized file up front — sending it would tie up (or kill) the
     * server and the user would just get an opaque failure.
     */
    function pickFile(file: File | null) {
        form.setData('file', file);
        form.clearErrors('file');

        if (file && limit && file.size > limit.max_kb * 1024) {
            setFileError(
                `That file is ${formatSize(file.size)}. The limit for ${form.data.type}s is ${formatSize(limit.max_kb * 1024)}.`,
            );

            return;
        }

        setFileError(null);
    }

    function resetForm() {
        form.reset();
        setFileError(null);
        setAdding(false);
    }

    /** Files upload in chunks (see uploadMediaInChunks); links post normally. */
    async function uploadFile(file: File) {
        const controller = new AbortController();
        abortRef.current = controller;
        setUploadPercent(0);

        try {
            await uploadMediaInChunks({
                itemId: item.id,
                type: form.data.type as 'image' | 'video' | 'file',
                label: form.data.label,
                file,
                onProgress: (progress) => setUploadPercent(progress.percentage),
                signal: controller.signal,
            });

            toast.success('Media added.');
            resetForm();
            router.reload({ only: ['section'] });
        } catch (error) {
            if (!controller.signal.aborted) {
                toast.error(
                    error instanceof Error
                        ? error.message
                        : 'The upload failed. Please try again.',
                );
            }
        } finally {
            setUploadPercent(null);
            abortRef.current = null;
        }
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        if (fileError || uploading) {
            return;
        }

        if (form.data.type === 'link') {
            form.post(store(item.id).url, {
                preserveScroll: true,
                onSuccess: resetForm,
            });

            return;
        }

        if (form.data.file) {
            void uploadFile(form.data.file);
        }
    }

    function cancelUpload() {
        abortRef.current?.abort();
        setUploadPercent(null);
    }

    const media = item.media ?? [];

    return (
        <div className="space-y-2">
            {media.length > 0 && (
                <ul className="flex flex-wrap gap-2">
                    {media.map((m) => {
                        const Icon = TYPE_ICON[m.type];
                        const url = m.display_url ?? '#';

                        function removeButton(overlay = false) {
                            return (
                                <button
                                    type="button"
                                    onClick={() =>
                                        router.delete(destroy(m.id).url, {
                                            preserveScroll: true,
                                        })
                                    }
                                    className={
                                        overlay
                                            ? 'absolute -top-1.5 -right-1.5 rounded-full border bg-background p-0.5 text-muted-foreground opacity-0 shadow-sm transition-opacity group-hover:opacity-100 hover:text-destructive'
                                            : 'rounded p-0.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive'
                                    }
                                    aria-label="Remove media"
                                >
                                    <Trash2 className="size-3.5" />
                                </button>
                            );
                        }

                        if (m.type === 'image') {
                            return (
                                <li key={m.id} className="group relative">
                                    <a
                                        href={url}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <img
                                            src={url}
                                            alt={m.label || 'Attachment'}
                                            loading="lazy"
                                            className="h-16 w-20 rounded-md border object-cover"
                                        />
                                    </a>
                                    {removeButton(true)}
                                </li>
                            );
                        }

                        if (m.type === 'video') {
                            return (
                                <li
                                    key={m.id}
                                    className="group relative w-full max-w-xs"
                                >
                                    <VideoPlayer
                                        src={url}
                                        label={m.label ?? undefined}
                                    />
                                    {removeButton(true)}
                                </li>
                            );
                        }

                        return (
                            <li
                                key={m.id}
                                className="flex items-center gap-2 rounded-md border bg-muted/40 py-1 pr-1 pl-2 text-xs"
                            >
                                <Icon className="size-3.5 text-muted-foreground" />
                                <a
                                    href={url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="max-w-44 truncate hover:underline"
                                >
                                    {m.label || m.url || m.type}
                                </a>
                                <ExternalLink className="size-3 text-muted-foreground" />
                                {removeButton()}
                            </li>
                        );
                    })}
                </ul>
            )}

            {adding ? (
                <form
                    onSubmit={submit}
                    className="flex flex-wrap items-start gap-2 rounded-md border p-2"
                >
                    <Select
                        value={form.data.type}
                        onValueChange={(value) => {
                            form.setData('type', value as MediaType);
                            form.setData('file', null);
                            form.setData('url', '');
                            setFileError(null);
                        }}
                    >
                        <SelectTrigger size="sm" className="w-28">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="link">Link</SelectItem>
                            <SelectItem value="image">Image</SelectItem>
                            <SelectItem value="video">Video</SelectItem>
                            <SelectItem value="file">File</SelectItem>
                        </SelectContent>
                    </Select>

                    {form.data.type === 'link' ? (
                        <div className="flex-1">
                            <Input
                                value={form.data.url}
                                onChange={(e) =>
                                    form.setData('url', e.target.value)
                                }
                                placeholder="https://…"
                                className="h-8"
                            />
                            <InputError message={form.errors.url} />
                        </div>
                    ) : (
                        <div className="flex-1">
                            <Input
                                type="file"
                                accept={limit?.accept}
                                disabled={uploading}
                                onChange={(e) =>
                                    pickFile(e.target.files?.[0] ?? null)
                                }
                                className="h-8"
                            />
                            {limit && !fileError && (
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Up to {formatSize(limit.max_kb * 1024)} ·
                                    uploaded in chunks
                                </p>
                            )}
                            <InputError message={fileError ?? undefined} />
                        </div>
                    )}

                    <Input
                        value={form.data.label}
                        onChange={(e) => form.setData('label', e.target.value)}
                        placeholder="Label (optional)"
                        disabled={uploading}
                        className="h-8 w-40"
                    />

                    <Button
                        type="submit"
                        size="sm"
                        disabled={
                            form.processing ||
                            uploading ||
                            fileError !== null
                        }
                    >
                        {uploading ? 'Uploading…' : 'Add'}
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => {
                            if (uploading) {
                                cancelUpload();

                                return;
                            }

                            setFileError(null);
                            setAdding(false);
                        }}
                    >
                        Cancel
                    </Button>

                    {uploading && (
                        <div className="w-full space-y-1">
                            <div
                                className="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                                role="progressbar"
                                aria-valuenow={uploadPercent ?? 0}
                                aria-valuemin={0}
                                aria-valuemax={100}
                                aria-label="Upload progress"
                            >
                                <div
                                    className="h-full rounded-full bg-primary transition-[width]"
                                    style={{ width: `${uploadPercent ?? 0}%` }}
                                />
                            </div>
                            <p className="text-xs text-muted-foreground tabular-nums">
                                Uploading… {uploadPercent ?? 0}%
                            </p>
                        </div>
                    )}
                </form>
            ) : (
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => setAdding(true)}
                >
                    <LinkIcon className="size-3.5" /> Add media
                </Button>
            )}
        </div>
    );
}
