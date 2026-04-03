<?php
/**
 * Snippet: rllngr/srcset-img
 *
 * Renders a responsive <picture> element using pre-generated thumbnails.
 * Falls back to the original file for drafts, GIFs, SVGs, and missing thumbs.
 *
 * Variables:
 *   $image      Kirby\Cms\File   required  The image (or video) file object
 *   $sizes      string|array               CSS sizes attribute  e.g. '100vw' or '(min-width: 992px) 50vw, 100vw'
 *   $alt        string                     Alt text prefix — appended with the file's own alt field
 *   $class      string                     CSS class (defaults to $image->orientation())
 *   $breakpoint string                     Largest size key to include (defaults to 'extra-large')
 *   $lazy       bool                       Native lazy loading (defaults to true)
 */

use Rllngr\Thumbzer\ThumbGenerator;

if (!$image) return;

$class     = $class ?? $image->orientation();
$imageAlt  = trim(($alt ?? '') . ' ' . $image->alt()->value());
$lazy      = $lazy ?? true;
$breakpoint = $breakpoint ?? 'extra-large';
$sizeAttr  = is_array($sizes ?? null) ? implode(', ', $sizes) : ($sizes ?? '');

// ── Video ────────────────────────────────────────────────────────────────────
if ($image->type() === 'video'):
    $poster = method_exists($image, 'poster') ? $image->poster()->toFile() : null;
?>
<video
    class="<?= $class ?>"
    src="<?= $image->url() ?>"
    <?php if ($poster): ?>poster="<?= $poster->url() ?>"<?php endif ?>
    autoplay muted playsinline loop disableRemotePlayback>
</video>
<?php return; endif;

if ($image->type() !== 'image') return;

// ── Fallback: draft, GIF, SVG ────────────────────────────────────────────────
$isDraft = method_exists($image->page(), 'isDraft') && $image->page()->isDraft();
if ($isDraft || in_array($image->extension(), ['gif', 'svg'])):
?>
<img
    class="<?= $class ?>"
    src="<?= $image->url() ?>"
    alt="<?= $imageAlt ?>"
    <?php if ($lazy): ?>loading="lazy"<?php endif ?>
>
<?php return; endif;

// ── Build srcsets ─────────────────────────────────────────────────────────────
$csizes  = ThumbGenerator::sizes();
$format  = ThumbGenerator::format();
$srcsets = ['main' => [], 'webp' => []];

foreach ($csizes as $key => $width):
    $w = min((int) $width, $image->width());
    $url = ThumbGenerator::thumbUrl($image, $width, $format);

    $srcsets['main'][] = $url . " {$w}w";

    // When format is not already webp, also build a parallel webp srcset
    if ($format !== 'webp'):
        $srcsets['webp'][] = ThumbGenerator::thumbUrl($image, $width, 'webp') . " {$w}w";
    endif;

    if ($key === $breakpoint) break;
endforeach;
?>
<picture>
    <?php if (!empty($srcsets['webp'])): ?>
    <source
        type="image/webp"
        sizes="<?= $sizeAttr ?>"
        srcset="<?= implode(', ', $srcsets['webp']) ?>"
    >
    <?php endif ?>
    <img
        sizes="<?= $sizeAttr ?>"
        srcset="<?= implode(', ', $srcsets['main']) ?>"
        src="<?= $image->url() ?>"
        class="<?= $class ?>"
        alt="<?= $imageAlt ?>"
        width="<?= $image->width() ?>"
        height="<?= $image->height() ?>"
        <?php if ($lazy): ?>loading="lazy" decoding="async"<?php endif ?>
    >
</picture>
