# kirby-Thumbzer

A Kirby CMS plugin that pre-generates thumbnails on file upload and renders responsive `<picture>` elements with srcset — including WebP conversion with optional ICC colour profile preservation. Part of the **rllngr** plugin set.

## Features

- Auto-generates resized thumbnails on `file.create`, `file.replace`, `file.changeName`, `file.delete`
- Renders responsive `<picture>` + `<source>` + `<img>` with named breakpoints
- WebP output with fallback to original format
- **ICC profile preservation** — uses ImageMagick directly (no `-strip`) for photography projects working in Adobe RGB or ProPhoto RGB
- Built-in Kirby route to serve thumbnail files from `/content/` (bypasses `.htaccess` block) — draft thumbnails only through signed URLs or to users logged in to the Panel
- Panel button on every image to open the **ICC comparison tool**
- Maintenance route and ICC tool reserved to users logged in to the Panel, with plugin [permissions](#permissions)
- Drop-in migration from per-project `define(THUMB_ROOT, ...)` + duplicated hooks

## Requirements

- Kirby 4, 5 or 6
- PHP 8.1+
- ImageMagick (`convert`) — required for ICC preservation, optional otherwise (falls back to Kirby's GD driver)

## Installation

**Via Composer** (recommended — installs automatically into `site/plugins/`):

```bash
composer require rllngr/kirby-thumbzer
```

**Manually** — clone or copy into `site/plugins/kirby-thumbzer`.

## Upgrading to 1.5

1.5 closes the plugin's public endpoints. Nothing changes for published pages: their thumbnail URLs and cache headers are the same.

### What changes

| | Before | From 1.5 |
|---|---|---|
| `GET /thumbzer/regenerate` | anyone | admins, or roles with the `regenerate` [permission](#permissions); `403` otherwise |
| `GET /thumbzer/icc-info` | anyone, any absolute `?path=` on the server | Panel users with the `icc` permission; `?file=<file id>` only, for files they can open in the Panel |
| `/test-icc` page | anyone | Panel users with the `icc` permission; `403` page otherwise |
| Panel ICC button | every role | hidden for roles without the `icc` permission |
| Draft thumbnails (`content/…/_drafts/…/thumbs/…`) | anyone, cached for a year | signed URL or Panel user, never cached; `404` otherwise |
| Snippet, draft page | original image | thumbnails (signed URLs) |

### On each site

1. `composer update rllngr/kirby-thumbzer`.
2. Search the site's own code for thumbnail URLs built by hand — `grep -rn "thumbs/" site/` — and replace them with `ThumbGenerator::thumbUrl($image, $width, $format)`. Otherwise draft previews lose those images for logged-out visitors.
3. Search for links or scripts calling `/thumbzer/regenerate` (blueprint `info` fields, cron jobs, deploy scripts): only a browser logged in as an admin works now. Grant `regenerate: true` to the roles that must use it (see [Permissions](#permissions)); a cron job cannot call it any more.
4. A site-level guard written for `/thumbzer/*` (a route that answers `404` without a session) is now redundant and can be removed.
5. Optional: set `content.salt` to a random secret in `config.php` (see [Drafts](#thumbnails-directory)).

### What to test

Use a private window for "logged out".

- [ ] A published page with images, logged out: images load, the `srcset` URLs have no `?t=`.
- [ ] A draft page with images, through the Panel's preview while logged in: images load.
- [ ] The same draft, through the preview link opened in a private window: images load, their URLs end with `?t=…`.
- [ ] A draft thumbnail URL with the `?t=…` part removed, in a private window: `404`.
- [ ] `/thumbzer/regenerate` in a private window: `403`. Logged in as an admin: it lists the images and ends with the JSON summary.
- [ ] `/thumbzer/icc-info?path=/etc/passwd` in a private window: `403`.
- [ ] Panel, any image using the ICC section: the **Comparer les profils ICC** button opens `/test-icc`, and the colorspace / ICC cards fill in.
- [ ] `/test-icc` in a private window: `403` page with a link to the Panel.
- [ ] As a non-admin editor: the ICC button and tool work (unless the role has `icc: false`), `/thumbzer/regenerate` answers `403` (unless the role has `regenerate: true`).

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

    // Optional, Kirby option: secret for signed URLs (Kirby's draft previews,
    // and thumbzer's draft thumbnails). Any long random string, kept private.
    'content' => [
        'salt' => 'a-long-random-secret',
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
    'alt'        => 'My caption',   // Fallback alt text, when the file has none (kirby-visibilizer's altText() first)
    'lazy'       => true,           // Native lazy loading (default: true)
]) ?>
```

> **Migrating from `snippet('includes/srcset-img', ...)`**: replace with `snippet('rllngr/kirby-thumbzer', ...)` — params are identical.

The snippet renders a `<picture>` element with a `<source type="image/webp">` and an `<img>` fallback. For GIFs and SVGs it falls back to a plain `<img>`. Drafts get their thumbnails too, through signed URLs (see below). For video files it renders a `<video>` with autoplay.

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

They are served through a Kirby route (`content/*/thumbs/*`) — no `.htaccess` modifications needed. Published thumbnails are cached for a year (`public, immutable`).

**Drafts.** Thumbnails of draft pages (and of the pages inside a draft) are generated on upload too, but their folder path is predictable, so they are not public:

- `ThumbGenerator::thumbUrl()` — used by the snippet — signs their URL: `…/thumbs/my-project-1200-photo.webp?t=3f9a…`. Whoever sees the draft page (a Panel user, or a client opening the Panel's preview link) loads its thumbnails; nobody can guess them.
- Without a valid signature, they are only served to users logged in to the Panel. Anyone else gets the same `404` as for a missing file.
- They are never cached (`private, no-store`).

The signature uses Kirby's preview-token HMAC. Its default secret is derived from the server path of `/content`: like for Kirby's own draft previews, set the `content.salt` option to a random secret value for the strongest guarantee (changing it changes every draft thumbnail URL, which only matters for previews already open).

> Build thumbnail URLs with `ThumbGenerator::thumbUrl()`, never by hand (`url('content/' . $page->diruri() . '/thumbs/…')`): a hand-built draft URL has no signature, so its images break for logged-out visitors.

## Thumbnails tab (optional)

The plugin registers a ready-to-use panel tab blueprint under `tabs/thumbzer-thumbnails`. Add it to any page blueprint to get a dedicated tab showing source files and generated thumbnails:

```yaml
# site/blueprints/pages/project.yml
tabs:
  content:   tabs/my-content-tab
  thumbnails: tabs/thumbzer-thumbnails
  seo:       seo/page
```

The tab has two sections:
- **Fichiers source** — uploaded images (with the ICC button)
- **Images optimisées** — the `thumbs` sub-page cards showing generated thumbnails

The `thumbs/` sub-page and its `thumbnails.txt` content file are created automatically the first time an image is uploaded to the page. The panel tab will appear as soon as the page has at least one image.

## Permissions

The regenerate route and the ICC comparison tool only answer users logged in to the Panel. Anonymous visitors, and logged-in users whose role has no Panel access, get a `403`.

| Permission | Grants | Default |
|---|---|---|
| `rllngr.kirby-thumbzer.regenerate` | `GET /thumbzer/regenerate` | admins only |
| `rllngr.kirby-thumbzer.icc` | ICC comparison tool: the `test-icc` page, its `/thumbzer/icc-info` calls and the Panel button | all Panel users |

Admins always have both. Change the defaults of another role in its user blueprint:

```yaml
# site/blueprints/users/editor.yml
title: Editor
permissions:
  rllngr.kirby-thumbzer:
    regenerate: true   # may run /thumbzer/regenerate
    icc: false         # no ICC tool, the Panel button is hidden
```

The ICC tool only opens images the user can open in the Panel: the `access` options of page and file blueprints apply.

## Regenerate all thumbnails

To generate the missing thumbnails of every listed and unlisted page at once (drafts are skipped), open this URL in a browser where you are logged in to the Panel as an admin, or with the `regenerate` [permission](#permissions):

```
GET /thumbzer/regenerate
```

It prints one line per image, then a JSON summary: `{ "generated": 12, "skipped": 48, "errors": [] }`. Anyone else gets a `403`.

This route also creates any missing `thumbs/thumbnails.txt` files, so running it once on an existing project is enough to bring all pages up to date.

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

The page is available at `yourdomain.com/test-icc`, hidden from the panel navigation, and only to users logged in to the Panel with the `icc` [permission](#permissions) (all Panel users by default). Anyone else gets a `403` page with a link to the Panel login.

### 2 — Create the file blueprint

Create `site/blueprints/files/image.yml` — this is the blueprint used for all uploaded images. It includes the ICC button section, hidden for roles without the `icc` permission:

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
- Metadata cards show file size, colorspace, and ICC profile presence (loaded async from `/thumbzer/icc-info` — no delay on page open)
- `/thumbzer/icc-info?file=<file id>[&thumb=<width>]` takes a Kirby file id or UUID, never a path, and runs `identify` only on that image or one of its thumbs, inside `/content`
- Adapts to light and dark mode, matching the Kirby panel appearance
- Opens in the same tab — browser back button returns to the panel

## License

MIT — [Nicolas Rollinger](https://rollinger.design)
