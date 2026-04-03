# kirby-Thumbzer

A Kirby CMS plugin that pre-generates thumbnails on file upload and renders responsive `<picture>` elements with srcset — including WebP conversion with optional ICC colour profile preservation. Part of the **rllngr** plugin set.

## Features

- Auto-generates resized thumbnails on `file.create`, `file.replace`, `file.changeName`, `file.delete`
- Renders responsive `<picture>` + `<source>` + `<img>` with named breakpoints
- WebP output with fallback to original format
- **ICC profile preservation** — uses ImageMagick directly (no `-strip`) for photography projects working in Adobe RGB or ProPhoto RGB
- Built-in Kirby route to serve thumbnail files from `/content/` (bypasses `.htaccess` block)
- Panel button on every image to open the **ICC comparison tool**
- Drop-in migration from per-project `define(THUMB_ROOT, ...)` + duplicated hooks

## Requirements

- Kirby 4+
- PHP 8.1+
- ImageMagick (`convert`) — required for ICC preservation, optional otherwise (falls back to Kirby's GD driver)

## Installation

**Via Composer** (recommended — installs automatically into `site/plugins/`):

```bash
composer require rllngr/kirby-thumbzer
```

**Manually** — clone or copy into `site/plugins/kirby-thumbzer`.

## Configuration

In `site/config/config.php`:

```php
return [
    // Project prefix — prepended to every generated thumbnail filename
    // e.g. 'my-project-600-photo.webp'
    'thumbnails.prefix' => 'my-project-',

    // Named breakpoints used in the srcset and for the $breakpoint param
    'thumbnails.sizes' => [
        'small'       => 600,
        'medium'      => 1200,
        'large'       => 1800,
        'extra-large' => 2400,
    ],

    // Output format for generated thumbnails
    'thumbnails.format'  => 'webp',

    // Compression quality (0–100)
    'thumbnails.quality' => 90,

    // ── ICC profile preservation ──────────────────────────────────────────────
    // false (default) — Kirby's thumb driver, smallest file size, ICC stripped
    // true            — ImageMagick called directly without -strip, ICC embedded
    //                   Required for Adobe RGB / ProPhoto RGB photography projects
    'thumbnails.preserveICC' => false,

    // Path to the ImageMagick convert binary (only needed when preserveICC is true
    // or when using the 'im' driver)
    'thumbs' => [
        'driver' => 'im',
        'bin'    => '/usr/local/bin/convert',
    ],
];
```

### Migrating from `define(THUMB_ROOT, ...)`

Replace the constant definition with the config option — the plugin reads both as a fallback:

```php
// Before (remove this)
define('THUMB_ROOT', 'my-project-');

// After
'thumbnails.prefix' => 'my-project-',
```

Then **remove** the `file.create:after`, `file.replace:after`, `file.changeName:after`, and `file.delete:after` hooks from `config.php` — the plugin registers them automatically.

## Usage

### Snippet

```php
<?php snippet('rllngr/kirby-thumbzer', [
    'image'      => $image,         // Kirby File object — required
    'sizes'      => '100vw',        // CSS sizes attribute
    'breakpoint' => 'large',        // Max size key to include (default: extra-large)
    'alt'        => 'My caption',   // Alt text prefix (appended with the file's alt field)
    'lazy'       => true,           // Native lazy loading (default: true)
]) ?>
```

> **Migrating from `snippet('includes/srcset-img', ...)`**: replace with `snippet('rllngr/kirby-thumbzer', ...)` — params are identical.

The snippet renders a `<picture>` element with a `<source type="image/webp">` and an `<img>` fallback. For drafts, GIFs, and SVGs it falls back to a plain `<img>`. For video files it renders a `<video>` with autoplay.

### Thumbnails directory

Generated thumbnails are stored in a `thumbs/` subdirectory alongside the original files:

```
content/
  projects/
    my-project/
      photo.jpg
      thumbs/
        my-project-600-photo.webp
        my-project-1200-photo.webp
        my-project-1800-photo.webp
        my-project-2400-photo.webp
```

They are served through a Kirby route (`content/*/thumbs/*`) — no `.htaccess` modifications needed.

## ICC Comparison Tool

The plugin ships a built-in test page to visually compare the original file against WebP with and without ICC profile preservation.

### Setup

Create the page in `content/test-icc/test-icc.txt`:

```
Title: Test ICC

----

Uuid: test-icc-page
```

Then open `yourdomain.com/test-icc` — you can drop images directly into the `content/test-icc/` folder to test them.

### Panel button

Add `thumbzer-icc-button` to any file blueprint to get a one-click link to the comparison tool:

```yaml
# site/blueprints/files/default.yml
sections:
  icc:
    type: thumbzer-icc-button
```

Clicking the button in the panel opens the comparison tool pre-loaded with that specific image.

### Comparison interface

- **Left side** — original JPG (resized to the same dimensions)
- **Right side** — WebP, switchable between `-strip` (ICC removed) and `+ICC` (profile preserved)
- Drag the handle to reveal either side
- Metadata below shows file size, colorspace, and whether the ICC profile is present

## License

MIT — [Nicolas Rollinger](https://rollinger.design)
