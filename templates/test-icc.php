<?php
/**
 * Test ICC Profile — Original vs thumb existant
 * Pas de génération à l'ouverture — utilise les fichiers déjà présents.
 */

use Rllngr\Thumbzer\ThumbGenerator;

// ── Résolution de l'image ────────────────────────────────────────────────────
if ($fileId = get('file')) {
    $parts      = explode('/', $fileId);
    $filename   = array_pop($parts);
    $sourcePage = page(implode('/', $parts));
    $img        = $sourcePage ? $sourcePage->file($filename) : null;
    $testImages = $img ? [$img] : [];
} else {
    $testImages = $page->images()->filterBy('extension', 'in', ['jpg', 'jpeg'])->toArray(fn($img) => $img);
}

// ── Données par image ────────────────────────────────────────────────────────
$identify = str_replace('convert', 'identify', kirby()->option('thumbs.bin', 'convert'));

function icc_profileInfo(string $identify, string $file): array
{
    if (!file_exists($file)) return ['colorspace' => '—', 'icc' => 'absent'];
    $colorspace = trim(shell_exec("{$identify} -format '%[colorspace]' " . escapeshellarg($file) . " 2>/dev/null") ?? '—');
    $icc = trim(shell_exec("{$identify} -verbose " . escapeshellarg($file) . " 2>/dev/null | grep -i 'Profile-icc' | awk '{print $2}'") ?? '');
    return ['colorspace' => $colorspace ?: '—', 'icc' => $icc ? $icc . 'B' : 'absent'];
}

function icc_fileKb(string $path): string
{
    return file_exists($path) ? round(filesize($path) / 1024) . ' KB' : '—';
}

$items = [];
foreach ($testImages as $img) {
    // Thumb existant le plus grand disponible
    $sizes     = ThumbGenerator::sizes();
    $format    = ThumbGenerator::format();
    $thumbPath = null;
    $thumbUrl  = null;

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
        'rootOrig'    => $img->root(),
        'urlThumb'    => $thumbUrl,
        'rootThumb'   => $thumbPath,
        'thumbSize'   => $thumbSize ?? null,
        'thumbFormat' => strtoupper($format),
        'sizeOrig'    => icc_fileKb($img->root()),
        'sizeThumb'   => $thumbPath ? icc_fileKb($thumbPath) : '—',
        'infoOrig'    => icc_profileInfo($identify, $img->root()),
        'infoThumb'   => $thumbPath ? icc_profileInfo($identify, $thumbPath) : ['colorspace' => '—', 'icc' => 'absent'],
        'hasThumb'    => $thumbPath !== null,
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ICC Test — Kirby Thumbzer</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --color-gray-100: hsl(210, 16%, 96%);
      --color-gray-200: hsl(210, 14%, 89%);
      --color-gray-300: hsl(210, 12%, 80%);
      --color-gray-400: hsl(210, 10%, 60%);
      --color-gray-500: hsl(210, 9%,  45%);
      --color-gray-600: hsl(210, 9%,  35%);
      --color-gray-700: hsl(210, 9%,  25%);
      --color-gray-800: hsl(210, 10%, 16%);
      --color-gray-900: hsl(210, 12%, 10%);
      --color-white: #fff;
      --color-green-400: hsl(142, 52%, 42%);
      --color-green-100: hsl(142, 52%, 95%);
      --color-red-400:   hsl(0, 65%, 50%);
      --color-red-100:   hsl(0, 65%, 95%);
      --font-sans: -apple-system, BlinkMacSystemFont, "Inter", "Segoe UI", sans-serif;
      --font-mono: "SF Mono", "Fira Code", monospace;
      --text-xs: 0.65rem;
      --text-sm: 0.75rem;
      --text-md: 0.875rem;
      --rounded-sm: 3px;
      --rounded: 6px;
      --shadow: 0 1px 3px rgba(0,0,0,.08), 0 0 0 1px rgba(0,0,0,.06);
    }

    body {
      font-family: var(--font-sans);
      font-size: var(--text-md);
      background: var(--color-gray-100);
      color: var(--color-gray-800);
      min-height: 100vh;
    }

    /* ── Topbar ── */
    .topbar {
      position: sticky; top: 0; z-index: 10;
      background: var(--color-white);
      border-bottom: 1px solid var(--color-gray-200);
      display: flex; align-items: center; gap: 1rem;
      padding: 0 1.5rem; height: 3rem;
    }
    .topbar__back {
      display: flex; align-items: center; gap: .4rem;
      font-size: var(--text-sm); color: var(--color-gray-500);
      text-decoration: none; padding: .35rem .6rem;
      border-radius: var(--rounded-sm);
      transition: background .1s, color .1s;
    }
    .topbar__back:hover { background: var(--color-gray-100); color: var(--color-gray-800); }
    .topbar__back svg { width: 14px; height: 14px; }
    .topbar__title { font-size: var(--text-sm); font-weight: 500; color: var(--color-gray-700); }
    .topbar__file  { font-size: var(--text-xs); color: var(--color-gray-400); font-family: var(--font-mono); margin-left: auto; }

    /* ── Page ── */
    .page { min-height: calc(100vh - 3rem); display: flex; flex-direction: column; }

    /* ── Block ── */
    .block { flex: 1; display: flex; flex-direction: column; padding: 1.5rem; gap: 1rem; }

    /* ── Comparateur ── */
    .cmp {
      position: relative; overflow: hidden;
      cursor: ew-resize; user-select: none; touch-action: none;
      flex: 1; min-height: 0;
      border-radius: var(--rounded);
      background: var(--color-gray-900);
      box-shadow: var(--shadow);
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
    .cmp__handle {
      position: absolute; top: 0; bottom: 0;
      left: var(--split, 50%);
      transform: translateX(-50%);
      width: 2px; background: rgba(255,255,255,.6);
      z-index: 3; pointer-events: none;
    }
    .cmp__handle::after {
      content: '';
      position: absolute; top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      width: 28px; height: 28px; border-radius: 50%;
      background: var(--color-white);
      box-shadow: 0 1px 6px rgba(0,0,0,.3);
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none' stroke='%23888' stroke-width='1.5' stroke-linecap='round'%3E%3Cpath d='M7 5l-4 5 4 5M13 5l4 5-4 5'/%3E%3C/svg%3E");
      background-size: 16px; background-position: center; background-repeat: no-repeat;
    }
    .cmp__tag {
      position: absolute; top: .75rem; z-index: 4;
      font-size: var(--text-xs); font-weight: 500;
      padding: .25em .65em; border-radius: var(--rounded-sm);
      pointer-events: none;
      background: rgba(0,0,0,.5); backdrop-filter: blur(6px);
      color: rgba(255,255,255,.8);
    }
    .cmp__tag--l { left: .75rem; }
    .cmp__tag--r { right: .75rem; }

    /* ── Meta ── */
    .meta { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
    .meta__card {
      background: var(--color-white); border-radius: var(--rounded);
      box-shadow: var(--shadow); padding: .875rem 1rem;
    }
    .meta__card-title {
      font-size: var(--text-xs); font-weight: 600;
      text-transform: uppercase; letter-spacing: .06em;
      color: var(--color-gray-400); margin-bottom: .6rem;
    }
    .meta__rows { display: flex; flex-direction: column; gap: .3rem; }
    .meta__row { display: flex; justify-content: space-between; align-items: center; font-size: var(--text-sm); }
    .meta__key { color: var(--color-gray-500); }
    .meta__val { font-family: var(--font-mono); font-size: var(--text-xs); color: var(--color-gray-700); }
    .badge {
      display: inline-flex; align-items: center; gap: .25em;
      padding: .2em .55em; border-radius: var(--rounded-sm);
      font-size: var(--text-xs); font-weight: 500;
    }
    .badge::before { content: ''; display: inline-block; width: 6px; height: 6px; border-radius: 50%; }
    .badge--ok  { background: var(--color-green-100); color: var(--color-green-400); }
    .badge--ok::before { background: var(--color-green-400); }
    .badge--ko  { background: var(--color-red-100);   color: var(--color-red-400); }
    .badge--ko::before { background: var(--color-red-400); }

    /* ── No thumb warning ── */
    .no-thumb {
      background: var(--color-white); border-radius: var(--rounded);
      box-shadow: var(--shadow); padding: 1.5rem;
      font-size: var(--text-sm); color: var(--color-gray-500);
      text-align: center;
    }
    .no-thumb code { font-family: var(--font-mono); color: var(--color-gray-600); }

    hr { border: none; border-top: 1px solid var(--color-gray-200); }

    .empty {
      flex: 1; display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      gap: .75rem; color: var(--color-gray-400);
      font-size: var(--text-sm); padding: 4rem; text-align: center;
    }
  </style>
</head>
<body>

<div class="topbar">
  <a class="topbar__back" href="javascript:history.back()">
    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M10 3L5 8l5 5"/></svg>
    Retour
  </a>
  <span class="topbar__title">ICC Profile Test</span>
  <?php if (!empty($items)): ?>
    <span class="topbar__file"><?= htmlspecialchars($items[0]['label']) ?><?php if ($items[0]['page']): ?> — <?= htmlspecialchars($items[0]['page']) ?><?php endif ?></span>
  <?php endif ?>
</div>

<div class="page">

<?php if (empty($items)): ?>
  <div class="empty">
    <p>Aucune image — ouvre depuis le panel ou dépose des JPG dans <code>content/test-icc/</code>.</p>
  </div>

<?php else: ?>
  <?php foreach ($items as $i => $item): ?>
    <?php if ($i > 0): ?><hr><?php endif ?>

    <div class="block">

      <?php if (!$item['hasThumb']): ?>
        <div class="no-thumb">
          Aucun thumb trouvé pour <code><?= htmlspecialchars($item['label']) ?></code> — upload l'image depuis le panel pour le générer.
        </div>
      <?php else: ?>

        <div class="cmp" id="cmp-<?= $i ?>" style="--split: 50%">
          <img class="cmp__img" src="<?= $item['urlThumb'] ?>" alt="" loading="eager">
          <img class="cmp__img cmp__left" src="<?= $item['urlOrig'] ?>" alt="" loading="eager">
          <div class="cmp__handle"></div>
          <span class="cmp__tag cmp__tag--l">Original</span>
          <span class="cmp__tag cmp__tag--r"><?= $item['thumbFormat'] ?> <?= $item['thumbSize'] ?>px</span>
        </div>

      <?php endif ?>

      <div class="meta">
        <div class="meta__card">
          <div class="meta__card-title">Original — <?= strtoupper(pathinfo($item['label'], PATHINFO_EXTENSION)) ?></div>
          <div class="meta__rows">
            <div class="meta__row">
              <span class="meta__key">Taille fichier</span>
              <span class="meta__val"><?= $item['sizeOrig'] ?></span>
            </div>
            <div class="meta__row">
              <span class="meta__key">Colorspace</span>
              <span class="meta__val"><?= $item['infoOrig']['colorspace'] ?></span>
            </div>
            <div class="meta__row">
              <span class="meta__key">Profil ICC</span>
              <?php $ok = $item['infoOrig']['icc'] !== 'absent'; ?>
              <span class="badge <?= $ok ? 'badge--ok' : 'badge--ko' ?>"><?= $ok ? $item['infoOrig']['icc'] : 'absent' ?></span>
            </div>
          </div>
        </div>

        <div class="meta__card">
          <div class="meta__card-title">Thumb — <?= $item['hasThumb'] ? $item['thumbFormat'] . ' ' . $item['thumbSize'] . 'px' : 'non généré' ?></div>
          <div class="meta__rows">
            <div class="meta__row">
              <span class="meta__key">Taille fichier</span>
              <span class="meta__val"><?= $item['sizeThumb'] ?></span>
            </div>
            <div class="meta__row">
              <span class="meta__key">Colorspace</span>
              <span class="meta__val"><?= $item['infoThumb']['colorspace'] ?></span>
            </div>
            <div class="meta__row">
              <span class="meta__key">Profil ICC</span>
              <?php $ok = $item['infoThumb']['icc'] !== 'absent'; ?>
              <span class="badge <?= $ok ? 'badge--ok' : 'badge--ko' ?>"><?= $ok ? $item['infoThumb']['icc'] : 'absent' ?></span>
            </div>
          </div>
        </div>
      </div>

    </div>
  <?php endforeach ?>
<?php endif ?>

</div>

<script>
document.querySelectorAll('.cmp').forEach(cmp => {
  let active = false;
  function update(x) {
    const r   = cmp.getBoundingClientRect();
    const pct = Math.max(2, Math.min(98, (x - r.left) / r.width * 100));
    cmp.style.setProperty('--split', pct + '%');
  }
  cmp.addEventListener('mousedown',  e => { active = true; update(e.clientX); });
  cmp.addEventListener('touchstart', e => { active = true; update(e.touches[0].clientX); }, { passive: true });
  window.addEventListener('mousemove',  e => { if (active) update(e.clientX); });
  window.addEventListener('touchmove',  e => { if (active) update(e.touches[0].clientX); }, { passive: true });
  window.addEventListener('mouseup',  () => active = false);
  window.addEventListener('touchend', () => active = false);
});
</script>
</body>
</html>
