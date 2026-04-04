<?php
/**
 * Test ICC Profile — Original vs thumb existant
 */

use Rllngr\Thumbzer\ThumbGenerator;

if ($fileId = get('file')) {
    $parts      = explode('/', $fileId);
    $filename   = array_pop($parts);
    $sourcePage = page(implode('/', $parts));
    $img        = $sourcePage ? $sourcePage->file($filename) : null;
    $testImages = $img ? [$img] : [];
} else {
    $testImages = $page->images()->filterBy('extension', 'in', ['jpg', 'jpeg'])->toArray(fn($img) => $img);
}

function icc_fileKb(string $path): string
{
    return file_exists($path) ? round(filesize($path) / 1024) . ' KB' : '—';
}

$items = [];
foreach ($testImages as $img) {
    $sizes     = ThumbGenerator::sizes();
    $format    = ThumbGenerator::format();
    $thumbPath = null;
    $thumbUrl  = null;
    $thumbSize = null;

    foreach (array_reverse($sizes, true) as $key => $width) {
        $path = ThumbGenerator::thumbPath($img, $width, $format);
        if (file_exists($path)) {
            $thumbPath = $path;
            $thumbUrl  = ThumbGenerator::thumbUrl($img, $width, $format);
            $thumbSize = $width;
            break;
        }
    }

    $items[] = [
        'label'       => $img->filename(),
        'page'        => $img->page()->title()->value(),
        'urlOrig'     => $img->url(),
        'urlThumb'    => $thumbUrl,
        'pathOrig'    => $img->root(),
        'pathThumb'   => $thumbPath,
        'thumbSize'   => $thumbSize,
        'thumbFormat' => strtoupper($format),
        'sizeOrig'    => icc_fileKb($img->root()),
        'sizeThumb'   => $thumbPath ? icc_fileKb($thumbPath) : '—',
        'hasThumb'    => $thumbPath !== null,
        'ext'         => strtoupper(pathinfo($img->filename(), PATHINFO_EXTENSION)),
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ICC Test — <?= !empty($items) ? htmlspecialchars($items[0]['label']) : 'Thumbzer' ?></title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    /* ── Kirby panel exact color system ─────────────────────────────────────── */
    :root {
      color-scheme: light dark;

      /* Gray scale — light mode (h=0, s=0%) */
      --k-gray-100: #f9f9f9;
      --k-gray-200: #efefef;
      --k-gray-300: #e0e0e0;
      --k-gray-400: #cccccc;
      --k-gray-500: #b2b2b2;
      --k-gray-600: #999999;
      --k-gray-700: #727272;
      --k-gray-800: #4c4c4c;
      --k-gray-850: #353535;
      --k-gray-900: #262626;
      --k-gray-950: #111111;

      /* Semantic — light */
      --bg:        var(--k-gray-200);
      --surface:   var(--k-gray-100);
      --surface-2: var(--k-gray-200);
      --border:    var(--k-gray-300);
      --text:      #000000;
      --text-2:    var(--k-gray-700);
      --text-3:    var(--k-gray-500);

      /* Accents — light */
      --blue:      #3d89d5;
      --blue-dim:  rgba(61, 137, 213, .1);
      --green:     #89b72d;
      --green-dim: #f2f8e6;
      --red:       #ce1616;
      --red-dim:   #fbe3e3;

      /* Typography (exact Kirby panel values) */
      --font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      --mono: "SFMono-Regular", Consolas, Liberation Mono, Menlo, Courier, monospace;

      /* Spacing & shape */
      --radius:    .25rem;
      --radius-lg: .375rem;
      --radius-xl: .5rem;
    }

    @media (prefers-color-scheme: dark) {
      :root {
        /* Gray scale — dark mode */
        --k-gray-100: #f2f2f2;
        --k-gray-200: #dbdbdb;
        --k-gray-300: #bcbcbc;
        --k-gray-400: #adadad;
        --k-gray-500: #a3a3a3;
        --k-gray-600: #898989;
        --k-gray-700: #5e5e5e;
        --k-gray-800: #3f3f3f;
        --k-gray-850: #303030;
        --k-gray-900: #1e1e1e;
        --k-gray-950: #1c1c1c;

        /* Semantic — dark */
        --bg:        var(--k-gray-900);
        --surface:   var(--k-gray-850);
        --surface-2: var(--k-gray-800);
        --border:    var(--k-gray-800);
        --text:      #ffffff;
        --text-2:    var(--k-gray-400);
        --text-3:    var(--k-gray-700);

        /* Accents — dark (Kirby exact values) */
        --blue:      #3d89d5;
        --blue-dim:  rgba(61, 137, 213, .12);
        --green:     #b5da6c;
        --green-dim: #24300c;
        --red:       #ec5959;
        --red-dim:   #370606;
      }
    }

    /* ── Base ────────────────────────────────────────────────────────────────── */
    html, body {
      height: 100%;
      font-family: var(--font);
      font-size: .875rem;
      line-height: 1.5;
      background: var(--bg);
      color: var(--text);
    }

    /* ── Topbar ──────────────────────────────────────────────────────────────── */
    .bar {
      position: sticky; top: 0; z-index: 10;
      height: 2.5rem;
      background: var(--bg);
      border-bottom: 1px solid var(--border);
      display: flex; align-items: center;
      padding: 0 1.25rem; gap: .625rem;
    }
    .bar__back {
      display: inline-flex; align-items: center; gap: .3rem;
      color: var(--text-2); font-size: .8125rem; text-decoration: none;
      padding: .25rem .5rem; border-radius: var(--radius);
      transition: background .12s, color .12s;
    }
    .bar__back:hover { background: var(--surface); color: var(--text); }
    .bar__sep { color: var(--text-3); font-size: .75rem; }
    .bar__crumb { font-size: .8125rem; color: var(--text-2); }
    .bar__crumb strong { color: var(--text); font-weight: 500; }
    .bar__right { margin-left: auto; display: flex; align-items: center; gap: .5rem; }
    .bar__tag {
      font-size: .6875rem; font-family: var(--mono);
      color: var(--text-3); background: var(--surface);
      padding: .2em .55em; border-radius: var(--radius);
      border: 1px solid var(--border);
    }

    /* ── Layout ──────────────────────────────────────────────────────────────── */
    .page { display: flex; flex-direction: column; min-height: calc(100vh - 2.5rem); }

    .block {
      flex: 1; display: flex; flex-direction: column;
      gap: .75rem; padding: 1.25rem;
    }

    /* ── Comparateur ─────────────────────────────────────────────────────────── */
    .cmp {
      flex: 1; min-height: 0;
      position: relative; overflow: hidden;
      cursor: ew-resize; user-select: none; touch-action: none;
      border-radius: var(--radius-lg);
      background: #111;
      border: 1px solid var(--border);
    }
    .cmp__img {
      position: absolute; inset: 0;
      width: 100%; height: 100%;
      object-fit: contain; pointer-events: none;
    }
    .cmp__left {
      z-index: 2;
      clip-path: inset(0 calc(100% - var(--split, 50%)) 0 0);
    }
    .cmp__divider {
      position: absolute; top: 0; bottom: 0;
      left: var(--split, 50%); transform: translateX(-50%);
      width: 1px; background: rgba(255,255,255,.35);
      z-index: 3; pointer-events: none;
    }
    .cmp__divider::after {
      content: '';
      position: absolute; top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      width: 1.625rem; height: 1.625rem; border-radius: 50%;
      background: var(--surface);
      border: 1px solid var(--border);
      box-shadow: 0 1px 6px rgba(0,0,0,.4);
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none' stroke='%23888' stroke-width='1.5' stroke-linecap='round'%3E%3Cpath d='M7 5l-4 5 4 5M13 5l4 5-4 5'/%3E%3C/svg%3E");
      background-size: 14px; background-position: center; background-repeat: no-repeat;
    }
    .cmp__label {
      position: absolute; top: .5rem; z-index: 4;
      font-size: .6875rem; font-weight: 500; letter-spacing: .03em;
      padding: .2em .5em; border-radius: var(--radius);
      pointer-events: none;
      background: rgba(0,0,0,.55); backdrop-filter: blur(6px);
      color: rgba(255,255,255,.7);
    }
    .cmp__label--l { left: .5rem; }
    .cmp__label--r { right: .5rem; }

    /* ── Meta cards ──────────────────────────────────────────────────────────── */
    .meta { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }

    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: .875rem 1rem;
    }
    .card__title {
      font-size: .6875rem; font-weight: 600; letter-spacing: .06em;
      text-transform: uppercase; color: var(--text-3);
      margin-bottom: .65rem; padding-bottom: .5rem;
      border-bottom: 1px solid var(--border);
    }
    .card__rows { display: flex; flex-direction: column; gap: .4rem; }
    .card__row { display: flex; justify-content: space-between; align-items: center; font-size: .8125rem; }
    .card__key { color: var(--text-2); }
    .card__val { font-family: var(--mono); font-size: .75rem; color: var(--text); }

    /* ── Skeleton loader ─────────────────────────────────────────────────────── */
    .skel {
      display: inline-block;
      width: 4rem; height: .75em; border-radius: var(--radius);
      background: var(--border);
      animation: pulse 1.4s ease-in-out infinite;
      vertical-align: middle;
    }
    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50%       { opacity: .4; }
    }

    /* ── Badges (exact Kirby theme system) ───────────────────────────────────── */
    .badge {
      display: inline-flex; align-items: center; gap: .3em;
      padding: .2em .55em; border-radius: var(--radius);
      font-size: .6875rem; font-weight: 500; font-family: var(--mono);
    }
    .badge::before {
      content: ''; width: 5px; height: 5px; border-radius: 50%; display: inline-block;
    }
    .badge--ok  { background: var(--green-dim); color: var(--green); }
    .badge--ok::before  { background: var(--green); }
    .badge--ko  { background: var(--red-dim);   color: var(--red); }
    .badge--ko::before  { background: var(--red); }

    /* ── Notice ──────────────────────────────────────────────────────────────── */
    .notice {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: var(--radius-lg); padding: 1.25rem;
      font-size: .8125rem; color: var(--text-2); text-align: center;
    }
    .notice code { font-family: var(--mono); color: var(--text); }

    /* ── Empty ───────────────────────────────────────────────────────────────── */
    .empty {
      flex: 1; display: flex; align-items: center; justify-content: center;
      font-size: .8125rem; color: var(--text-3); padding: 4rem; text-align: center;
    }

    hr { border: none; border-top: 1px solid var(--border); }
  </style>
</head>
<body>

<div class="bar">
  <a class="bar__back" href="javascript:history.back()">
    <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M10 3L5 8l5 5"/></svg>
    Retour
  </a>
  <span class="bar__sep">/</span>
  <span class="bar__crumb">ICC Profile Test</span>
  <?php if (!empty($items)): ?>
  <div class="bar__right">
    <span class="bar__tag"><?= htmlspecialchars($items[0]['label']) ?><?= $items[0]['page'] ? ' — ' . htmlspecialchars($items[0]['page']) : '' ?></span>
  </div>
  <?php endif ?>
</div>

<div class="page">

<?php if (empty($items)): ?>
  <div class="empty">
    Aucune image — ouvre depuis le panel ou dépose des JPG dans <code>content/test-icc/</code>
  </div>

<?php else: ?>
  <?php foreach ($items as $i => $item): ?>
    <?php if ($i > 0): ?><hr><?php endif ?>
    <div class="block">

      <?php if ($item['hasThumb']): ?>
        <div class="cmp" id="cmp-<?= $i ?>" style="--split:50%">
          <img class="cmp__img" src="<?= $item['urlThumb'] ?>" alt="" loading="eager">
          <img class="cmp__img cmp__left" src="<?= $item['urlOrig'] ?>" alt="" loading="eager">
          <div class="cmp__divider"></div>
          <span class="cmp__label cmp__label--l">Original <?= $item['ext'] ?></span>
          <span class="cmp__label cmp__label--r"><?= $item['thumbFormat'] ?> <?= $item['thumbSize'] ?>px</span>
        </div>
      <?php else: ?>
        <div class="notice">
          Aucun thumb trouvé pour <code><?= htmlspecialchars($item['label']) ?></code> — uploade l'image depuis le panel.
        </div>
      <?php endif ?>

      <div class="meta">

        <div class="card" data-icc-path="<?= htmlspecialchars($item['pathOrig']) ?>">
          <div class="card__title">Original — <?= $item['ext'] ?></div>
          <div class="card__rows">
            <div class="card__row">
              <span class="card__key">Taille</span>
              <span class="card__val"><?= $item['sizeOrig'] ?></span>
            </div>
            <div class="card__row">
              <span class="card__key">Colorspace</span>
              <span class="card__val" data-icc-cs><span class="skel"></span></span>
            </div>
            <div class="card__row">
              <span class="card__key">Profil ICC</span>
              <span data-icc-badge><span class="skel"></span></span>
            </div>
          </div>
        </div>

        <div class="card" data-icc-path="<?= $item['pathThumb'] ? htmlspecialchars($item['pathThumb']) : '' ?>">
          <div class="card__title">Thumb — <?= $item['hasThumb'] ? $item['thumbFormat'] . ' ' . $item['thumbSize'] . 'px' : 'non généré' ?></div>
          <div class="card__rows">
            <div class="card__row">
              <span class="card__key">Taille</span>
              <span class="card__val"><?= $item['sizeThumb'] ?></span>
            </div>
            <div class="card__row">
              <span class="card__key">Colorspace</span>
              <span class="card__val" data-icc-cs><?php if ($item['hasThumb']): ?><span class="skel"></span><?php else: ?>—<?php endif ?></span>
            </div>
            <div class="card__row">
              <span class="card__key">Profil ICC</span>
              <span data-icc-badge><?php if ($item['hasThumb']): ?><span class="skel"></span><?php else: ?>—<?php endif ?></span>
            </div>
          </div>
        </div>

      </div>
    </div>
  <?php endforeach ?>
<?php endif ?>

</div>

<script>
// ── Slider ────────────────────────────────────────────────────────────────────
document.querySelectorAll('.cmp').forEach(cmp => {
  let active = false;
  function update(x) {
    const r = cmp.getBoundingClientRect();
    const p = Math.max(2, Math.min(98, (x - r.left) / r.width * 100));
    cmp.style.setProperty('--split', p + '%');
  }
  cmp.addEventListener('mousedown',  e => { active = true; update(e.clientX); });
  cmp.addEventListener('touchstart', e => { active = true; update(e.touches[0].clientX); }, { passive: true });
  window.addEventListener('mousemove',  e => { if (active) update(e.clientX); });
  window.addEventListener('touchmove',  e => { if (active) update(e.touches[0].clientX); }, { passive: true });
  window.addEventListener('mouseup',  () => active = false);
  window.addEventListener('touchend', () => active = false);
});

// ── ICC info async ────────────────────────────────────────────────────────────
function renderBadge(icc) {
  const ok = icc !== 'absent';
  return `<span class="badge ${ok ? 'badge--ok' : 'badge--ko'}">${ok ? icc : 'absent'}</span>`;
}

document.querySelectorAll('.card[data-icc-path]').forEach(async card => {
  const path = card.dataset.iccPath;
  if (!path) return;

  try {
    const res  = await fetch(`<?= kirby()->url() ?>/thumbzer/icc-info?path=${encodeURIComponent(path)}`);
    const data = await res.json();

    const csEl    = card.querySelector('[data-icc-cs]');
    const badgeEl = card.querySelector('[data-icc-badge]');

    if (csEl)    csEl.textContent    = data.colorspace;
    if (badgeEl) badgeEl.innerHTML   = renderBadge(data.icc);
  } catch (e) {
    card.querySelectorAll('.skel').forEach(s => s.textContent = '—');
  }
});
</script>
</body>
</html>
