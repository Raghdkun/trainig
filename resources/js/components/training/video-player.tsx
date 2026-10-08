import {
    Maximize,
    Minimize,
    Pause,
    PictureInPicture2,
    Play,
    RotateCcw,
    Volume2,
    VolumeX,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

const SPEEDS = [0.5, 1, 1.25, 1.5, 2] as const;

function formatTime(seconds: number): string {
    if (!Number.isFinite(seconds) || seconds < 0) {
        return '0:00';
    }

    const total = Math.floor(seconds);
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const secs = total % 60;

    return hours > 0
        ? `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
        : `${minutes}:${String(secs).padStart(2, '0')}`;
}

/**
 * Training video player: custom controls, buffered progress, speed, volume,
 * picture-in-picture, fullscreen and keyboard shortcuts.
 */
export function VideoPlayer({
    src,
    poster,
    label,
    className,
}: {
    src: string;
    poster?: string;
    label?: string;
    className?: string;
}) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const containerRef = useRef<HTMLDivElement>(null);
    const hideTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const [playing, setPlaying] = useState(false);
    const [duration, setDuration] = useState(0);
    const [current, setCurrent] = useState(0);
    const [buffered, setBuffered] = useState(0);
    const [volume, setVolume] = useState(1);
    const [muted, setMuted] = useState(false);
    const [rate, setRate] = useState<number>(1);
    const [fullscreen, setFullscreen] = useState(false);
    const [waiting, setWaiting] = useState(false);
    const [ended, setEnded] = useState(false);
    const [failed, setFailed] = useState(false);
    const [showControls, setShowControls] = useState(true);

    const togglePlay = useCallback(() => {
        const video = videoRef.current;

        if (!video) {
            return;
        }

        if (video.paused || video.ended) {
            void video.play().catch(() => setFailed(true));
        } else {
            video.pause();
        }
    }, []);

    const seekBy = useCallback((delta: number) => {
        const video = videoRef.current;

        if (!video) {
            return;
        }

        video.currentTime = Math.min(
            Math.max(0, video.currentTime + delta),
            video.duration || 0,
        );
    }, []);

    const changeVolume = useCallback((next: number) => {
        const video = videoRef.current;

        if (!video) {
            return;
        }

        const clamped = Math.min(1, Math.max(0, next));
        video.volume = clamped;
        video.muted = clamped === 0;
    }, []);

    const toggleFullscreen = useCallback(() => {
        if (document.fullscreenElement) {
            void document.exitFullscreen();
        } else {
            void containerRef.current
                ?.requestFullscreen()
                .catch(() => undefined);
        }
    }, []);

    const togglePip = useCallback(() => {
        const video = videoRef.current;

        if (!video) {
            return;
        }

        if (document.pictureInPictureElement) {
            void document.exitPictureInPicture().catch(() => undefined);
        } else {
            void video.requestPictureInPicture?.().catch(() => undefined);
        }
    }, []);

    const clearHideTimer = useCallback(() => {
        if (hideTimer.current) {
            clearTimeout(hideTimer.current);
            hideTimer.current = null;
        }
    }, []);

    const scheduleHide = useCallback(() => {
        clearHideTimer();
        hideTimer.current = setTimeout(() => setShowControls(false), 2500);
    }, [clearHideTimer]);

    // Keep the controls up while paused, and auto-hide shortly after activity.
    const nudgeControls = useCallback(() => {
        setShowControls(true);
        clearHideTimer();

        if (playing) {
            scheduleHide();
        }
    }, [playing, clearHideTimer, scheduleHide]);

    // Cleanup only — the timer is driven by play/pause and pointer events.
    useEffect(() => clearHideTimer, [clearHideTimer]);

    useEffect(() => {
        const onFullscreenChange = () =>
            setFullscreen(document.fullscreenElement === containerRef.current);

        document.addEventListener('fullscreenchange', onFullscreenChange);

        return () =>
            document.removeEventListener(
                'fullscreenchange',
                onFullscreenChange,
            );
    }, []);

    function onKeyDown(event: React.KeyboardEvent<HTMLDivElement>) {
        // Let the range inputs handle their own arrow keys.
        if ((event.target as HTMLElement).tagName === 'INPUT') {
            return;
        }

        const handlers: Record<string, () => void> = {
            ' ': togglePlay,
            k: togglePlay,
            ArrowRight: () => seekBy(5),
            ArrowLeft: () => seekBy(-5),
            ArrowUp: () => changeVolume(volume + 0.1),
            ArrowDown: () => changeVolume(volume - 0.1),
            m: () => {
                const video = videoRef.current;

                if (video) {
                    video.muted = !video.muted;
                }
            },
            f: toggleFullscreen,
        };

        const handler = handlers[event.key];

        if (handler) {
            event.preventDefault();
            handler();
            nudgeControls();
        }
    }

    const progress = duration > 0 ? (current / duration) * 100 : 0;
    const bufferedPercent = duration > 0 ? (buffered / duration) * 100 : 0;

    if (failed) {
        return (
            <div
                className={cn(
                    'flex aspect-video w-full flex-col items-center justify-center gap-2 rounded-lg border bg-muted/40 p-4 text-center',
                    className,
                )}
            >
                <p className="text-sm font-medium">
                    This video can’t be played
                </p>
                <p className="text-xs text-muted-foreground">
                    The file may be missing or in an unsupported format.
                </p>
                <a
                    href={src}
                    target="_blank"
                    rel="noreferrer"
                    className="text-xs text-primary underline underline-offset-2"
                >
                    Open the file directly
                </a>
            </div>
        );
    }

    return (
        <div
            ref={containerRef}
            role="region"
            aria-label={label ? `Video: ${label}` : 'Video player'}
            tabIndex={0}
            onKeyDown={onKeyDown}
            onMouseMove={nudgeControls}
            onMouseLeave={() => playing && setShowControls(false)}
            className={cn(
                'group relative overflow-hidden rounded-lg border bg-black focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                className,
            )}
        >
            <video
                ref={videoRef}
                src={src}
                poster={poster}
                preload="metadata"
                playsInline
                className="aspect-video w-full bg-black"
                onClick={togglePlay}
                onDoubleClick={toggleFullscreen}
                onLoadedMetadata={(e) => setDuration(e.currentTarget.duration)}
                onDurationChange={(e) => setDuration(e.currentTarget.duration)}
                onTimeUpdate={(e) => setCurrent(e.currentTarget.currentTime)}
                onProgress={(e) => {
                    const video = e.currentTarget;

                    if (video.buffered.length > 0) {
                        setBuffered(
                            video.buffered.end(video.buffered.length - 1),
                        );
                    }
                }}
                onPlay={() => {
                    setPlaying(true);
                    setEnded(false);
                    scheduleHide();
                }}
                onPause={() => {
                    setPlaying(false);
                    setShowControls(true);
                    clearHideTimer();
                }}
                onWaiting={() => setWaiting(true)}
                onPlaying={() => setWaiting(false)}
                onEnded={() => {
                    setPlaying(false);
                    setEnded(true);
                    setShowControls(true);
                }}
                onVolumeChange={(e) => {
                    setVolume(e.currentTarget.volume);
                    setMuted(e.currentTarget.muted);
                }}
                onError={() => setFailed(true)}
            />

            {waiting && (
                <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <div className="size-10 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                </div>
            )}

            {/* Big centre affordance while paused. */}
            {!playing && !waiting && (
                <button
                    type="button"
                    onClick={togglePlay}
                    aria-label={ended ? 'Replay video' : 'Play video'}
                    className="absolute inset-0 flex items-center justify-center bg-black/20 transition-colors hover:bg-black/30"
                >
                    <span className="flex size-14 items-center justify-center rounded-full bg-white/90 text-black shadow-lg transition-transform hover:scale-105">
                        {ended ? (
                            <RotateCcw className="size-6" />
                        ) : (
                            <Play
                                className="size-6 translate-x-0.5"
                                fill="currentColor"
                            />
                        )}
                    </span>
                </button>
            )}

            <div
                className={cn(
                    'absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/85 via-black/50 to-transparent px-3 pt-8 pb-2 transition-opacity',
                    showControls || !playing
                        ? 'opacity-100'
                        : 'pointer-events-none opacity-0',
                )}
            >
                {/* Seek bar with buffered range behind it. */}
                <div className="relative flex h-4 items-center">
                    <div className="absolute inset-x-0 h-1 rounded-full bg-white/25" />
                    <div
                        className="absolute h-1 rounded-full bg-white/40"
                        style={{ width: `${bufferedPercent}%` }}
                    />
                    <div
                        className="absolute h-1 rounded-full bg-primary"
                        style={{ width: `${progress}%` }}
                    />
                    <input
                        type="range"
                        min={0}
                        max={duration || 0}
                        step={0.1}
                        value={current}
                        onChange={(e) => {
                            const video = videoRef.current;

                            if (video) {
                                video.currentTime = Number(e.target.value);
                            }
                        }}
                        aria-label="Seek"
                        className="absolute inset-x-0 h-4 w-full cursor-pointer appearance-none bg-transparent [&::-webkit-slider-thumb]:size-3 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-primary"
                    />
                </div>

                <div className="mt-1 flex items-center gap-2 text-white">
                    <button
                        type="button"
                        onClick={togglePlay}
                        aria-label={playing ? 'Pause' : 'Play'}
                        className="rounded p-1 transition-colors hover:bg-white/20"
                    >
                        {playing ? (
                            <Pause className="size-4" />
                        ) : (
                            <Play className="size-4" />
                        )}
                    </button>

                    <div className="group/vol flex items-center gap-1">
                        <button
                            type="button"
                            onClick={() => {
                                const video = videoRef.current;

                                if (video) {
                                    video.muted = !video.muted;
                                }
                            }}
                            aria-label={muted ? 'Unmute' : 'Mute'}
                            className="rounded p-1 transition-colors hover:bg-white/20"
                        >
                            {muted || volume === 0 ? (
                                <VolumeX className="size-4" />
                            ) : (
                                <Volume2 className="size-4" />
                            )}
                        </button>
                        <input
                            type="range"
                            min={0}
                            max={1}
                            step={0.05}
                            value={muted ? 0 : volume}
                            onChange={(e) =>
                                changeVolume(Number(e.target.value))
                            }
                            aria-label="Volume"
                            className="hidden h-1 w-16 cursor-pointer appearance-none rounded-full bg-white/30 sm:block [&::-webkit-slider-thumb]:size-2.5 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-white"
                        />
                    </div>

                    <span className="text-xs tabular-nums">
                        {formatTime(current)} / {formatTime(duration)}
                    </span>

                    <div className="ml-auto flex items-center gap-1">
                        <button
                            type="button"
                            onClick={() => {
                                const next =
                                    SPEEDS[
                                        (SPEEDS.indexOf(
                                            rate as (typeof SPEEDS)[number],
                                        ) +
                                            1) %
                                            SPEEDS.length
                                    ];
                                const video = videoRef.current;

                                if (video) {
                                    video.playbackRate = next;
                                }

                                setRate(next);
                            }}
                            aria-label={`Playback speed ${rate}x`}
                            className="rounded px-1.5 py-1 text-xs font-medium tabular-nums transition-colors hover:bg-white/20"
                        >
                            {rate}x
                        </button>

                        <button
                            type="button"
                            onClick={togglePip}
                            aria-label="Picture in picture"
                            className="hidden rounded p-1 transition-colors hover:bg-white/20 sm:block"
                        >
                            <PictureInPicture2 className="size-4" />
                        </button>

                        <button
                            type="button"
                            onClick={toggleFullscreen}
                            aria-label={
                                fullscreen ? 'Exit fullscreen' : 'Fullscreen'
                            }
                            className="rounded p-1 transition-colors hover:bg-white/20"
                        >
                            {fullscreen ? (
                                <Minimize className="size-4" />
                            ) : (
                                <Maximize className="size-4" />
                            )}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
