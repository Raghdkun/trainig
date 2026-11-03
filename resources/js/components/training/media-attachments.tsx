import { ExternalLink, FileText, LinkIcon } from 'lucide-react';
import { VideoPlayer } from '@/components/training/video-player';
import type { MediaItem } from '@/types/training';

/**
 * Read-only attachment viewer for managers/admins: images and videos preview
 * inline, files and links open in a new tab.
 */
export function MediaAttachments({ media }: { media: MediaItem[] }) {
    if (media.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-3">
            {media.map((m) => {
                const url = m.display_url ?? '#';

                if (m.type === 'image') {
                    return (
                        <figure key={m.id} className="w-32">
                            <a href={url} target="_blank" rel="noreferrer">
                                <img
                                    src={url}
                                    alt={m.label || 'Attachment'}
                                    loading="lazy"
                                    className="h-24 w-32 rounded-md border object-cover transition-opacity hover:opacity-90"
                                />
                            </a>
                            {m.label && (
                                <figcaption className="mt-1 truncate text-xs text-muted-foreground">
                                    {m.label}
                                </figcaption>
                            )}
                        </figure>
                    );
                }

                if (m.type === 'video') {
                    return (
                        <div
                            key={m.id}
                            className="w-full max-w-md min-w-0 basis-full"
                        >
                            <VideoPlayer
                                src={url}
                                label={m.label ?? undefined}
                            />
                            {m.label && (
                                <p className="mt-1 truncate text-xs text-muted-foreground">
                                    {m.label}
                                </p>
                            )}
                        </div>
                    );
                }

                const Icon = m.type === 'link' ? LinkIcon : FileText;
                const text =
                    m.label ||
                    (m.type === 'link' ? m.url : 'Attachment') ||
                    m.type;

                return (
                    <a
                        key={m.id}
                        href={url}
                        target="_blank"
                        rel="noreferrer"
                        className="flex h-fit items-center gap-2 rounded-md border bg-muted/40 px-3 py-2 text-xs transition-colors hover:bg-muted"
                    >
                        <Icon className="size-4 shrink-0 text-muted-foreground" />
                        <span className="max-w-44 truncate">{text}</span>
                        <ExternalLink className="size-3 shrink-0 text-muted-foreground" />
                    </a>
                );
            })}
        </div>
    );
}
