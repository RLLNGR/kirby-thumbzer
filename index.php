<?php

declare(strict_types=1);

use Kirby\Cms\App as Kirby;
use Kirby\Http\Response;
use Rllngr\Thumbzer\Access;
use Rllngr\Thumbzer\ThumbGenerator;

@include_once __DIR__ . '/vendor/autoload.php';

// Composer autoloads these; this covers manual installs
load([
    'Rllngr\\Thumbzer\\Access'         => 'classes/Access.php',
    'Rllngr\\Thumbzer\\ThumbGenerator' => 'classes/ThumbGenerator.php',
], __DIR__);

$runThumbTaskSafely = static function (callable $task, string $label): void {
    try {
        $task();
    } catch (\Throwable $exception) {
        error_log('[kirby-thumbzer] ' . $label . ': ' . $exception->getMessage());
    }
};

// Thumbs live in the page's folder: site and user files, and the thumbs themselves, are skipped
$skipFile = static fn ($file): bool => $file->page() === null || $file->page()->slug() === 'thumbs';

// Kirby 6 has no custom sections: the ICC button is a field there, and the old section type is mapped onto it
$isKirby6 = version_compare(Kirby::version(), '6.0.0', '>=');
if ($isKirby6) {
    require_once __DIR__ . '/classes/IccButtonField.php';
    \Kirby\Blueprint\Normalizer::$sectionFields['thumbzer-icc-button'] = 'thumbzer-icc-button';
}

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

    // ── Permissions ───────────────────────────────────────────────────────────
    //
    // Admins always have them. Other Panel users get these defaults, which
    // their user blueprint can override (site/blueprints/users/editor.yml):
    //
    //   permissions:
    //     rllngr.kirby-thumbzer:
    //       regenerate: true
    //       icc: false

    'permissions' => [
        'icc'        => true,   // ICC tool: test-icc page, thumbzer/icc-info, Panel button
        'regenerate' => false,  // thumbzer/regenerate
    ],

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

    // Kirby 4 and 5
    'sections' => $isKirby6 ? [] : [
        'thumbzer-icc-button' => [
            'computed' => [
                'link' => function () {
                    // no button for roles without the icc permission
                    if (Access::can('icc') === false) {
                        return null;
                    }

                    return kirby()->url() . '/test-icc?file=' . rawurlencode($this->model()->id());
                }
            ]
        ]
    ],

    // Kirby 6
    'fields' => $isKirby6 ? ['thumbzer-icc-button' => \Rllngr\Thumbzer\IccButtonField::class] : [],

    // ── Routes ────────────────────────────────────────────────────────────────
    //
    // The two thumbzer/* maintenance routes answer 403 unless the visitor is
    // logged in to the Panel with the matching permission (see above).
    //
    // Kirby's .htaccess blocks direct access to /content/*: the last route
    // serves thumb files through Kirby with proper cache headers (the thumbs
    // of draft pages only with a signed URL or to users logged in to the Panel).

    'routes' => [
        [
            // ICC profile info of an image or of one of its thumbs (called async by the test-icc template)
            // GET /thumbzer/icc-info?file=projects/my-project/photo.jpg[&thumb=1200]
            // `file` is a Kirby file id or UUID, `thumb` one of the thumbnails.sizes widths
            'pattern' => 'thumbzer/icc-info',
            'action'  => function () {
                $error = fn (int $code, string $message) => Response::json(
                    ['error' => $message],
                    $code,
                    headers: ['Cache-Control' => 'no-store']
                );

                if (Access::can('icc') === false) {
                    return $error(403, 'Forbidden');
                }

                // only files the user can open in the Panel
                $file = Access::file(get('file'));

                if ($file === null) {
                    return $error(404, 'File not found');
                }

                $path = $file->root();

                if (($thumb = get('thumb')) !== null) {
                    $width = filter_var($thumb, FILTER_VALIDATE_INT);
                    $sizes = array_map('intval', ThumbGenerator::sizes());

                    if (in_array($width, $sizes, true) === false) {
                        return $error(400, 'Unknown thumb size');
                    }

                    $path = ThumbGenerator::thumbPath($file, $width, ThumbGenerator::format());
                }

                // identify only ever runs on an existing file inside /content
                if (($path = Access::contentPath($path)) === null) {
                    return $error(404, 'File not found');
                }

                $identify   = str_replace('convert', 'identify', kirby()->option('thumbs.bin', 'convert'));
                $colorspace = trim(shell_exec("{$identify} -format '%[colorspace]' " . escapeshellarg($path) . " 2>/dev/null") ?? '—');
                $icc        = trim(shell_exec("{$identify} -verbose " . escapeshellarg($path) . " 2>/dev/null | grep -i 'Profile-icc' | awk '{print $2}'") ?? '');

                return Response::json(
                    [
                        'colorspace' => $colorspace ?: '—',
                        'icc'        => $icc ? $icc . 'B' : 'absent',
                    ],
                    200,
                    headers: ['Cache-Control' => 'no-store']
                );
            }
        ],
        [
            // Regenerate the missing thumbs of every listed and unlisted page (drafts are skipped)
            // GET /thumbzer/regenerate
            'pattern' => 'thumbzer/regenerate',
            'action'  => function () {
                if (Access::can('regenerate') === false) {
                    return new Response('Forbidden', 'text/plain', 403, [
                        'Cache-Control' => 'no-store',
                    ]);
                }

                set_time_limit(0);

                // Stream output line by line to keep the FastCGI connection alive
                // (avoids idle timeout when processing hundreds of images)
                header('Content-Type: text/plain; charset=utf-8');
                header('X-Accel-Buffering: no');
                header('Cache-Control: no-store');
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
                // keep the response contained in /content, since $path
                // is attacker-controlled and may contain ../
                $root = Access::contentPath(kirby()->root('content') . '/' . $path . '/thumbs/' . $filename);

                // thumbs of drafts (generated on upload) are only served with
                // the signature thumbUrl() adds (`?t=`), or to users logged in
                // to the Panel, and never cached
                $relative = $root !== null ? Access::relativePath($root) : '';
                $draft    = $root !== null && Access::isDraftPath($relative);
                $allowed  = $draft === false || Access::hasValidThumbToken($relative, get('t')) || Access::isPanelUser();

                // a missing thumb must answer with a cheap 404, never with
                // `return false`: Kirby turns an empty route result into a full
                // render of the error page, so a gallery whose thumbs have not
                // been generated yet would render the whole site once per
                // <img srcset> entry (4 sizes x N images) instead of 404ing.
                // a draft thumb gets the same 404 for anyone else, so that
                // draft URLs cannot be probed
                if ($root === null || $allowed === false) {
                    return new Response('', 'text/plain', 404, [
                        'Cache-Control' => 'no-store',
                    ]);
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
                header('Cache-Control: '   . ($draft === true ? 'private, no-store' : 'public, max-age=31536000, immutable'));
                header('Content-Length: '  . filesize($root));
                readfile($root);
                exit;
            }
        ]
    ],

    // ── Hooks: auto-generate thumbs on file lifecycle events ─────────────────

    'hooks' => [

        'file.create:after' => function ($file) use ($runThumbTaskSafely, $skipFile) {
            if ($skipFile($file)) return;
            $runThumbTaskSafely(
                fn () => ThumbGenerator::generate($file),
                'Failed to generate thumbnails for ' . $file->id()
            );
        },

        'file.replace:after' => function ($newFile, $oldFile) use ($runThumbTaskSafely, $skipFile) {
            if ($skipFile($newFile)) return;
            $runThumbTaskSafely(
                function () use ($newFile, $oldFile): void {
                    ThumbGenerator::cleanup($oldFile);
                    ThumbGenerator::generate($newFile);
                },
                'Failed to replace thumbnails for ' . $newFile->id()
            );
        },

        'file.changeName:after' => function ($newFile, $oldFile) use ($runThumbTaskSafely, $skipFile) {
            if ($skipFile($newFile)) return;
            $runThumbTaskSafely(
                fn () => ThumbGenerator::rename($newFile, $oldFile),
                'Failed to rename thumbnails for ' . $newFile->id()
            );
        },

        'file.delete:after' => function ($bool, $file) use ($runThumbTaskSafely, $skipFile) {
            if ($skipFile($file)) return;
            $runThumbTaskSafely(
                fn () => ThumbGenerator::cleanup($file),
                'Failed to cleanup thumbnails for ' . $file->id()
            );
        },

    ],

]);
