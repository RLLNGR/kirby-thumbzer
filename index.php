<?php

declare(strict_types=1);

use Kirby\Cms\App as Kirby;
use Kirby\Http\Response;
use Rllngr\Thumbzer\ThumbGenerator;

@include_once __DIR__ . '/vendor/autoload.php';

Kirby::plugin('rllngr/kirby-thumbzer', [

    // ── Options ───────────────────────────────────────────────────────────────
    //
    // Set these in your project's config.php:
    //
    //   'thumbnails.prefix'      => 'my-project-',
    //   'thumbnails.sizes'       => ['small' => 600, 'medium' => 1200, 'large' => 1800, 'extra-large' => 2400],
    //   'thumbnails.format'      => 'webp',
    //   'thumbnails.quality'     => 90,
    //   'thumbnails.preserveICC' => false,   // true for photography projects (Adobe RGB / ProPhoto)
    //
    // The plugin reads legacy define('THUMB_ROOT', '...') constants as a fallback for prefix.

    // ── Snippets ──────────────────────────────────────────────────────────────

    'snippets' => [
        'rllngr/kirby-thumbzer' => __DIR__ . '/snippets/srcset-img.php',
    ],

    // ── Templates ─────────────────────────────────────────────────────────────

    'templates' => [
        'test-icc' => __DIR__ . '/templates/test-icc.php',
    ],

    // ── Blueprints ────────────────────────────────────────────────────────────

    'blueprints' => [
        'pages/test-icc'          => __DIR__ . '/blueprints/pages/test-icc.yml',
        'tabs/thumbzer-thumbnails' => __DIR__ . '/blueprints/tabs/thumbzer-thumbnails.yml',
    ],

    // ── Panel section: ICC test button ────────────────────────────────────────
    //
    // Add to any file blueprint:
    //
    //   sections:
    //     icc:
    //       type: thumbzer-icc-button

    'sections' => [
        'thumbzer-icc-button' => [
            'computed' => [
                'link' => function () {
                    return kirby()->url() . '/test-icc?file=' . $this->model()->id();
                }
            ]
        ]
    ],

    // ── Routes: serve thumb files from /content/ ──────────────────────────────
    //
    // Kirby's .htaccess blocks direct access to /content/*.
    // This route serves thumb files through Kirby with proper cache headers.

    'routes' => [
        [
            // ICC profile info for a single file path (called async by the test-icc template)
            // GET /thumbzer/icc-info?path=/absolute/path/to/file.jpg
            'pattern' => 'thumbzer/icc-info',
            'action'  => function () {
                $path     = get('path');
                $identify = str_replace('convert', 'identify', kirby()->option('thumbs.bin', 'convert'));

                if (!$path || !file_exists($path)) {
                    return \Kirby\Http\Response::json(['colorspace' => '—', 'icc' => 'absent']);
                }

                $colorspace = trim(shell_exec("{$identify} -format '%[colorspace]' " . escapeshellarg($path) . " 2>/dev/null") ?? '—');
                $icc        = trim(shell_exec("{$identify} -verbose " . escapeshellarg($path) . " 2>/dev/null | grep -i 'Profile-icc' | awk '{print $2}'") ?? '');

                return \Kirby\Http\Response::json([
                    'colorspace' => $colorspace ?: '—',
                    'icc'        => $icc ? $icc . 'B' : 'absent',
                ]);
            }
        ],
        [
            // Regenerate all thumbs for all listed pages
            // GET /thumbzer/regenerate
            'pattern' => 'thumbzer/regenerate',
            'action'  => function () {
                set_time_limit(0);

                // Stream output line by line to keep the FastCGI connection alive
                // (avoids idle timeout when processing hundreds of images)
                header('Content-Type: text/plain; charset=utf-8');
                header('X-Accel-Buffering: no');
                header('Cache-Control: no-cache');
                while (ob_get_level() > 0) ob_end_flush();

                $generated = 0;
                $skipped   = 0;
                $errors    = [];

                $pages = kirby()->site()->index()->filterBy('slug', '!=', 'thumbs');

                foreach ($pages as $page) {
                    foreach ($page->files()->filterBy('type', 'image') as $file) {
                        if (in_array($file->extension(), ['gif', 'svg'])) continue;

                        echo '→ ' . $file->id() . PHP_EOL;
                        flush();

                        try {
                            $before = ThumbGenerator::countExisting($file);
                            ThumbGenerator::generate($file);
                            $after  = ThumbGenerator::countExisting($file);
                            $new    = $after - $before;
                            $generated += $new;
                            $skipped   += $before;

                            echo ($new > 0 ? '✓' : '–') . ' ' . $file->id() . PHP_EOL;
                        } catch (\Exception $e) {
                            $errors[] = $file->id() . ': ' . $e->getMessage();
                            echo '✗ ' . $file->id() . ': ' . $e->getMessage() . PHP_EOL;
                        }

                        flush();
                    }
                }

                echo PHP_EOL . json_encode([
                    'generated' => $generated,
                    'skipped'   => $skipped,
                    'errors'    => $errors,
                ], JSON_PRETTY_PRINT) . PHP_EOL;
                exit;
            }
        ],
        [
            'pattern' => 'content/(:all)/thumbs/(:any)',
            'action'  => function (string $path, string $filename) {
                $root = kirby()->root('content') . '/' . $path . '/thumbs/' . $filename;

                if (!file_exists($root)) {
                    return false;
                }

                $ext   = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $mimes = [
                    'webp' => 'image/webp',
                    'jpg'  => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png'  => 'image/png',
                    'gif'  => 'image/gif',
                    'avif' => 'image/avif',
                ];

                header('Content-Type: '    . ($mimes[$ext] ?? 'application/octet-stream'));
                header('Cache-Control: public, max-age=31536000, immutable');
                header('Content-Length: '  . filesize($root));
                readfile($root);
                exit;
            }
        ]
    ],

    // ── Hooks: auto-generate thumbs on file lifecycle events ─────────────────

    'hooks' => [

        'file.create:after' => function ($file) {
            if ($file->page()->slug() === 'thumbs') return;
            ThumbGenerator::generate($file);
        },

        'file.replace:after' => function ($newFile, $oldFile) {
            if ($newFile->page()->slug() === 'thumbs') return;
            ThumbGenerator::cleanup($oldFile);
            ThumbGenerator::generate($newFile);
        },

        'file.changeName:after' => function ($newFile, $oldFile) {
            if ($newFile->page()->slug() === 'thumbs') return;
            ThumbGenerator::rename($newFile, $oldFile);
        },

        'file.delete:after' => function ($bool, $file) {
            if ($file->page()->slug() === 'thumbs') return;
            ThumbGenerator::cleanup($file);
        },

    ],

]);
