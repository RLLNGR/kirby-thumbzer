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

## Regenerate all thumbnails

To regenerate thumbnails for the entire site without going through the panel:

```
GET /thumbzer/regenerate
```

Returns a JSON summary: `{ "generated": 12, "skipped": 48, "errors": [] }`.

## ICC Comparison Tool

The plugin ships a built-in page to visually compare the original file against its generated WebP thumbnail — with live ICC profile metadata loaded asynchronously.

### 1 — Create the content page

Create `content/test-icc/test-icc.txt`:

```
Title: Test ICC

----

Uuid: test-icc-page

----

Status: unlisted
```

The page will be accessible at `yourdomain.com/test-icc` but hidden from the panel navigation and not publicly listed.

### 2 — Create the file blueprint

Create `site/blueprints/files/image.yml` — this is the blueprint used for all uploaded images. It includes the ICC button section:

```yaml
# site/blueprints/files/image.yml
columns:
  - width: 1/2
    sections:
      content:
        type: fields
        fields:
          legend:
            label: Légende
            type: textarea
            size: medium
  - width: 1/2
    sections:
      icc:
        type: thumbzer-icc-button
      meta:
        type: fields
        fields:
          alt:
            label: Alt text
            type: text
```

### 3 — Assign the template in page blueprints

In every page blueprint that has a `type: files` section where images are uploaded, add `uploads: template: image` so Kirby associates uploaded images with the blueprint above:

```yaml
sections:
  files:
    type: files
    uploads:
      template: image
```

### 4 — Migrate existing files (first install only)

If the project already has uploaded images without a template assigned, run this in the project root to update all image metadata files at once:

```bash
find content -name "*.jpg.txt" -o -name "*.jpeg.txt" | grep -v thumbs | while read f; do
  grep -q "^Template:" "$f" || printf "\n----\n\nTemplate: image\n" >> "$f"
done
```

### Comparison interface

- **Left** — original file, **Right** — generated WebP thumbnail
- Drag the handle to reveal either side
- Metadata cards show file size, colorspace, and ICC profile presence (loaded async — no delay on page open)
- Adapts to light and dark mode, matching the Kirby panel appearance
- Opens in the same tab — browser back button returns to the panel

## License

MIT — [Nicolas Rollinger](https://rollinger.design)
