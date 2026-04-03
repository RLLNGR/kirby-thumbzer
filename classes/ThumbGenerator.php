<?php

declare(strict_types=1);

namespace Rllngr\Thumbzer;

use Kirby\Cms\File;

class ThumbGenerator
{
    // ── Config helpers ──────────────────────────────────────────────────────

    public static function prefix(): string
    {
        // Supports both new option and legacy THUMB_ROOT constant
        return kirby()->option('thumbnails.prefix',
            defined('THUMB_ROOT') ? THUMB_ROOT : ''
        );
    }

    public static function sizes(): array
    {
        return kirby()->option('thumbnails.sizes', [
            'small'       => 600,
            'medium'      => 1200,
            'large'       => 1800,
            'extra-large' => 2400,
        ]);
    }

    public static function format(): string
    {
        return kirby()->option('thumbnails.format', 'webp');
    }

    public static function quality(): int
    {
        return (int) kirby()->option('thumbnails.quality', 90);
    }

    /**
     * When true, calls ImageMagick directly without -strip
     * so the ICC colour profile is embedded in the output file.
     * Required for photography projects working in Adobe RGB / ProPhoto.
     */
    public static function preserveICC(): bool
    {
        return (bool) kirby()->option('thumbnails.preserveICC', false);
    }

    public static function bin(): string
    {
        return kirby()->option('thumbs.bin', 'convert');
    }

    // ── Path helpers ─────────────────────────────────────────────────────────

    public static function thumbsDir(File $file): string
    {
        return $file->page()->root() . '/thumbs';
    }

    public static function thumbFilename(File $file, int $width, string $format): string
    {
        return static::prefix() . $width . '-' . $file->name() . '.' . $format;
    }

    public static function thumbPath(File $file, int $width, string $format): string
    {
        return static::thumbsDir($file) . '/' . static::thumbFilename($file, $width, $format);
    }

    public static function thumbUrl(File $file, int $width, string $format): string
    {
        return kirby()->url() . '/content/' . $file->page()->diruri() . '/thumbs/' . static::thumbFilename($file, $width, $format);
    }

    // ── Generation ───────────────────────────────────────────────────────────

    public static function generate(File $file): void
    {
        if (!$file->isResizable()) return;

        $dir = static::thumbsDir($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $format      = static::format();
        $quality     = static::quality();
        $preserveICC = static::preserveICC();
        $bin         = static::bin();

        foreach (static::sizes() as $width) {
            $dst = static::thumbPath($file, $width, $format);
            if (file_exists($dst)) continue;

            if ($preserveICC) {
                // Direct ImageMagick call — no -strip, ICC profile is preserved
                exec(
                    $bin . ' ' . escapeshellarg($file->root()) .
                    ' -resize ' . (int) $width . 'x' .
                    ' -quality ' . $quality .
                    ' ' . escapeshellarg($dst) . ' 2>&1'
                );
            } else {
                try {
                    kirby()->thumb($file->root(), $dst, [
                        'width'   => $width,
                        'quality' => $quality,
                        'format'  => $format,
                    ]);
                } catch (\Exception $e) {
                    // silently skip — thumb will be missing, original serves as fallback
                }
            }
        }
    }

    // ── Cleanup ──────────────────────────────────────────────────────────────

    public static function cleanup(File $file): void
    {
        foreach (static::sizes() as $width) {
            $path = static::thumbPath($file, $width, static::format());
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    // ── Rename ───────────────────────────────────────────────────────────────

    public static function rename(File $newFile, File $oldFile): void
    {
        $format = static::format();
        foreach (static::sizes() as $width) {
            $old = static::thumbsDir($oldFile) . '/' . static::prefix() . $width . '-' . $oldFile->name() . '.' . $format;
            $new = static::thumbsDir($newFile) . '/' . static::prefix() . $width . '-' . $newFile->name() . '.' . $format;
            if (file_exists($old)) {
                rename($old, $new);
            }
        }
    }
}
