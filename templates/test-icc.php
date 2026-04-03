<?php
/**
 * Test ICC Profile — WebP Comparison
 */

$bin      = kirby()->option('thumbs.bin', 'convert');
$identify = str_replace('convert', 'identify', $bin);
$width    = 1200;
$quality  = 90;

$thumbDir = kirby()->root('content') . '/test-icc/thumbs/';
$thumbUrl = kirby()->url() . '/content/test-icc/thumbs/';

// ?file=projects/mon-projet/image.jpg  →  image spécifique depuis le panel
// sinon  →  images déposées dans content/test-icc/
if ($fileId = get('file')) {
    // file.id format = "projects/page-slug/image.jpg"
    $parts    = explode('/', $fileId);
    $filename = array_pop($parts);
    $sourcePage = page(implode('/', $parts));
    $img = $sourcePage ? $sourcePage->file($filename) : null;
    $testImages = $img ? [$img] : [];
} else {
    $testImages = $page->images()->filterBy('extension', 'in', ['jpg', 'jpeg'])->toArray(fn($img) => $img);
}

function generateThumb(string $bin, string $src, string $dst, int $width, int $quality, bool $strip): bool
{
    if (file_exists($dst)) return true;
    $stripFlag = $strip ? '-strip' : '';
    exec("{$bin} " . escapeshellarg($src) . " -resize {$width}x {$stripFlag} -quality {$quality} " . escapeshellarg($dst) . " 2>&1", $out, $code);
    return $code === 0;
}

function getProfileInfo(string $identify, string $file): array
{
    if (!file_exists($file)) return ['colorspace' => '—', 'icc' => 'absent'];
    $colorspace = trim(shell_exec("{$identify} -format '%[colorspace]' " . escapeshellarg($file) . " 2>/dev/null") ?? '—');
    $icc = trim(shell_exec("{$identify} -verbose " . escapeshellarg($file) . " 2>/dev/null | grep -i 'Profile-icc' | awk '{print $2}'") ?? '');
    return ['colorspace' => $colorspace ?: '—', 'icc' => $icc ? $icc . 'B' : 'absent'];
}

function fileKb(string $path): string
{
    return file_exists($path) ? round(filesize($path) / 1024) . ' KB' : '—';
}

// Génère aussi un JPG redimensionné pour que les 3 sources aient les mêmes dimensions
$items = [];
foreach ($testImages as $img) {
    $slug        = 'test';
    $safeName    = $img->name();
    $src         = $img->root();
    $dstOrig     = $thumbDir . $slug . '-orig-'     . $safeName . '.jpg';
    $dstStripped = $thumbDir . $slug . '-stripped-' . $safeName . '.webp';
    $dstIcc      = $thumbDir . $slug . '-icc-'      . $safeName . '.webp';

    generateThumb($bin, $src, $dstOrig,     $width, $quality, false); // JPG redim, sans strip
    generateThumb($bin, $src, $dstStripped, $width, $quality, true);
    generateThumb($bin, $src, $dstIcc,      $width, $quality, false);

    // Aspect ratio à partir du fichier redimensionné pour un container stable
    $ratio = $img->height() > 0 ? round(($img->height() / $img->width()) * 100, 4) : 66.66;

    $items[] = [
        'label'        => $img->filename(),
        'ratio'        => $ratio,
        'urlOrig'      => $thumbUrl . $slug . '-orig-'     . $safeName . '.jpg',
        'urlStripped'  => $thumbUrl . $slug . '-stripped-' . $safeName . '.webp',
        'urlIcc'       => $thumbUrl . $slug . '-icc-'      . $safeName . '.webp',
        'infoOrig'     => getProfileInfo($identify, $dstOrig),
        'infoStripped' => getProfileInfo($identify, $dstStripped),
        'infoIcc'      => getProfileInfo($identify, $dstIcc),
        'sizeOrig'     => fileKb($dstOrig),
        'sizeStripped' => fileKb($dstStripped),
        'sizeIcc'      => fileKb($dstIcc),
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Test ICC — 6Real</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Courier New', monospace;
      background: #0e0e0e;
      color: #ccc;
      padding: 2rem;
    }

    .header { margin-bottom: 3rem; padding-bottom: 1rem; border-bottom: 1px solid #1e1e1e; }
    .header h1 { font-size: 0.7rem; color: #3a3a3a; letter-spacing: 0.12em; text-transform: uppercase; }
    .header p  { font-size: 0.6rem; color: #2a2a2a; margin-top: 0.4rem; }

    .block {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 2rem 0;
    }
    .block__title { font-size: 0.6rem; color: #2e2e2e; letter-spacing: 0.06em; margin-bottom: 1rem; }
    hr { border: none; border-top: 1px solid #1a1a1a; margin: 0; }

    /* ── Controls ── */
    .controls {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 0.75rem;
    }
    .controls__label { font-size: 0.55rem; color: #2e2e2e; text-transform: uppercase; letter-spacing: 0.1em; margin-right: 0.25rem; }

    .btn {
      font-family: inherit;
      font-size: 0.6rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      padding: 0.35em 0.9em;
      border: 1px solid #222;
      background: transparent;
      color: #3a3a3a;
      cursor: pointer;
      border-radius: 2px;
      transition: background 0.1s, color 0.1s, border-color 0.1s;
    }
    .btn:hover   { border-color: #3a3a3a; color: #666; }
    .btn.active  { background: #1c1c1c; border-color: #555; color: #ccc; }

    /* ── Comparateur ── */
    .cmp {
      position: relative;
      overflow: hidden;
      cursor: ew-resize;
      user-select: none;
      touch-action: none;
      background: #111;
      flex: 1;
      min-height: 0; /* essentiel pour que flex:1 respecte la hauteur parent */
    }

    /* Toutes les images occupent exactement le même espace */
    .cmp__img {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: contain; /* contain = image entière visible, sans crop */
      pointer-events: none;
      display: block;
    }

    /* Droite : deux sources pré-chargées, crossfade CSS */
    .cmp__right { transition: opacity 0.25s ease; }
    .cmp__right--stripped { z-index: 1; }
    .cmp__right--icc      { z-index: 1; opacity: 0; }

    .cmp[data-mode="icc"] .cmp__right--stripped { opacity: 0; }
    .cmp[data-mode="icc"] .cmp__right--icc      { opacity: 1; }

    /* Gauche : original clippé — z-index 2 pour être au-dessus */
    .cmp__left {
      z-index: 2;
      clip-path: inset(0 calc(100% - var(--split, 50%)) 0 0);
    }

    /* Handle */
    .cmp__handle {
      position: absolute;
      top: 0; bottom: 0;
      left: var(--split, 50%);
      transform: translateX(-50%);
      width: 1px;
      background: rgba(255,255,255,0.5);
      z-index: 3;
      pointer-events: none;
    }
    .cmp__handle::after {
      content: '';
      position: absolute;
      top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      width: 32px; height: 32px;
      border-radius: 50%;
      background: rgba(255,255,255,0.9);
      box-shadow: 0 1px 6px rgba(0,0,0,0.5);
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none' stroke='%23555' stroke-width='1.5' stroke-linecap='round'%3E%3Cpath d='M7 5l-4 5 4 5M13 5l4 5-4 5'/%3E%3C/svg%3E");
      background-size: 18px;
      background-position: center;
      background-repeat: no-repeat;
    }

    /* Labels */
    .cmp__tag {
      position: absolute;
      top: 0.75rem;
      z-index: 4;
      font-size: 0.55rem;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      padding: 0.25em 0.65em;
      border-radius: 2px;
      pointer-events: none;
      background: rgba(0,0,0,0.6);
      backdrop-filter: blur(4px);
    }
    .cmp__tag--l { left: 0.75rem;  color: #888; }
    .cmp__tag--r { right: 0.75rem; color: #888; }

    /* ── Meta ── */
    .meta {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
      margin-top: 0.75rem;
    }
    .meta__col { font-size: 0.6rem; line-height: 2; color: #333; }
    .meta__col span { color: #666; }
    .badge {
      display: inline-block;
      padding: 0.15em 0.55em;
      border-radius: 2px;
      font-size: 0.55rem;
      letter-spacing: 0.05em;
    }
    .badge--ok { background: #0a1a0a; color: #3a8a3a; }
    .badge--ko { background: #1a0a0a; color: #8a3a3a; }
  </style>
</head>
<body>

<div class="header">
  <h1>ICC Profile Comparison — 6Real / Cyril Gourdin</h1>
  <p>← Glisser pour comparer &nbsp;·&nbsp; Boutons pour switcher la source droite</p>
</div>

<?php foreach ($items as $i => $item): ?>
  <?php if ($i > 0): ?><hr><?php endif ?>

  <div class="block">
    <div class="block__title"><?= htmlspecialchars($item['label']) ?></div>

    <div class="controls">
      <span class="controls__label">Source droite</span>

      <button class="btn active"
        data-cmp="cmp-<?= $i ?>"
        data-mode="stripped"
        data-label="WebP −strip"
        data-src="<?= $item['urlStripped'] ?>"
        data-size="<?= $item['sizeStripped'] ?>"
        data-colorspace="<?= $item['infoStripped']['colorspace'] ?>"
        data-icc="<?= $item['infoStripped']['icc'] ?>">
        WebP −strip
      </button>

      <button class="btn"
        data-cmp="cmp-<?= $i ?>"
        data-mode="icc"
        data-label="WebP +ICC"
        data-src="<?= $item['urlIcc'] ?>"
        data-size="<?= $item['sizeIcc'] ?>"
        data-colorspace="<?= $item['infoIcc']['colorspace'] ?>"
        data-icc="<?= $item['infoIcc']['icc'] ?>">
        WebP +ICC
      </button>
    </div>

    <!-- Comparateur -->
    <div class="cmp" id="cmp-<?= $i ?>" data-mode="stripped" style="--split: 50%">

      <!-- Droite : deux sources superposées -->
      <img class="cmp__img cmp__right cmp__right--stripped"
        src="<?= $item['urlStripped'] ?>" alt="" loading="eager">
      <img class="cmp__img cmp__right cmp__right--icc"
        src="<?= $item['urlIcc'] ?>" alt="" loading="eager">

      <!-- Gauche : original, clippé -->
      <img class="cmp__img cmp__left"
        src="<?= $item['urlOrig'] ?>" alt="" loading="eager">

      <div class="cmp__handle"></div>
      <span class="cmp__tag cmp__tag--l">Original JPG</span>
      <span class="cmp__tag cmp__tag--r" id="tag-r-<?= $i ?>">WebP −strip</span>
    </div>

    <!-- Meta -->
    <div class="meta">
      <div class="meta__col">
        Original JPG (redim.) &nbsp;·&nbsp;
        <span><?= $item['sizeOrig'] ?></span> &nbsp;·&nbsp;
        <span><?= $item['infoOrig']['colorspace'] ?></span> &nbsp;·&nbsp;
        ICC <span><?= $item['infoOrig']['icc'] ?></span>
        <?php $ok = $item['infoOrig']['icc'] !== 'absent'; ?>
        <span class="badge <?= $ok ? 'badge--ok' : 'badge--ko' ?>"><?= $ok ? 'profil présent' : 'absent' ?></span>
      </div>
      <div class="meta__col" id="meta-r-<?= $i ?>">
        WebP −strip &nbsp;·&nbsp;
        <span><?= $item['sizeStripped'] ?></span> &nbsp;·&nbsp;
        <span><?= $item['infoStripped']['colorspace'] ?></span> &nbsp;·&nbsp;
        ICC <span><?= $item['infoStripped']['icc'] ?></span>
        <?php $ok = $item['infoStripped']['icc'] !== 'absent'; ?>
        <span class="badge <?= $ok ? 'badge--ok' : 'badge--ko' ?>"><?= $ok ? 'profil présent' : 'absent' ?></span>
      </div>
    </div>

  </div>
<?php endforeach ?>

<script>
// ── Slider ──────────────────────────────────────────────────────────────────
document.querySelectorAll('.cmp').forEach(cmp => {
  let active = false;

  function update(clientX) {
    const r   = cmp.getBoundingClientRect();
    const pct = Math.max(2, Math.min(98, (clientX - r.left) / r.width * 100));
    cmp.style.setProperty('--split', pct + '%');
  }

  cmp.addEventListener('mousedown',  e => { active = true; update(e.clientX); });
  cmp.addEventListener('touchstart', e => { active = true; update(e.touches[0].clientX); }, { passive: true });
  window.addEventListener('mousemove',  e => { if (active) update(e.clientX); });
  window.addEventListener('touchmove',  e => { if (active) update(e.touches[0].clientX); }, { passive: true });
  window.addEventListener('mouseup',  () => active = false);
  window.addEventListener('touchend', () => active = false);
});

// ── Boutons switch ───────────────────────────────────────────────────────────
document.querySelectorAll('.btn[data-cmp]').forEach(btn => {
  btn.addEventListener('click', () => {
    const id    = btn.dataset.cmp;
    const cmp   = document.getElementById(id);
    const idx   = id.split('-')[1];
    const mode  = btn.dataset.mode;

    // Switch mode (CSS fait le crossfade)
    cmp.dataset.mode = mode;

    // Mise à jour label droite
    document.getElementById('tag-r-' + idx).textContent = btn.dataset.label;

    // Mise à jour méta droite
    const hasIcc = btn.dataset.icc !== 'absent';
    document.getElementById('meta-r-' + idx).innerHTML =
      btn.dataset.label + ' &nbsp;·&nbsp; ' +
      '<span>' + btn.dataset.size + '</span> &nbsp;·&nbsp; ' +
      '<span>' + btn.dataset.colorspace + '</span> &nbsp;·&nbsp; ' +
      'ICC <span>' + btn.dataset.icc + '</span> ' +
      '<span class="badge ' + (hasIcc ? 'badge--ok' : 'badge--ko') + '">' +
      (hasIcc ? 'profil présent' : 'absent') + '</span>';

    // Active state boutons
    btn.closest('.controls').querySelectorAll('.btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
  });
});
</script>
</body>
</html>
