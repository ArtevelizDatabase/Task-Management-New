<?php
/**
 * @var array      $pager         ['total','page','perPage','totalPages']
 * @var array      $queryParams   GET tanpa page
 * @var string     $uriPath       path relatif, mis. /tasks
 */
$pager = $pager ?? [];
$total = (int) ($pager['total'] ?? 0);
if ($total < 1) {
    return;
}

$page       = (int) ($pager['page'] ?? 1);
$totalPages = (int) ($pager['totalPages'] ?? 1);
$perPage    = (int) ($pager['perPage'] ?? 50);
$q    = $queryParams ?? [];
$path = $uriPath ?? '/';

$from = ($page - 1) * $perPage + 1;
$to   = min($page * $perPage, $total);

$buildUrl = static function (int $p) use ($path, $q): string {
    $q['page'] = $p;

    return $path . '?' . http_build_query($q);
};
?>

<nav class="table-pagination" aria-label="Navigasi halaman tabel">
  <?php if ($totalPages > 1): ?>
  <div class="table-pagination-actions">
    <?php if ($page > 1): ?>
      <a href="<?= esc($buildUrl($page - 1)) ?>" class="btn btn-ghost btn-sm">Sebelumnya</a>
    <?php else: ?>
      <span class="btn btn-ghost btn-sm" aria-disabled="true" style="opacity:.45;pointer-events:none">Sebelumnya</span>
    <?php endif; ?>

    <span class="table-pagination-pages">
      Halaman <?= $page ?> / <?= $totalPages ?>
    </span>

    <?php if ($page < $totalPages): ?>
      <a href="<?= esc($buildUrl($page + 1)) ?>" class="btn btn-ghost btn-sm">Berikutnya</a>
    <?php else: ?>
      <span class="btn btn-ghost btn-sm" aria-disabled="true" style="opacity:.45;pointer-events:none">Berikutnya</span>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <div class="table-pagination-info">
    Menampilkan <strong><?= $from ?></strong>–<strong><?= $to ?></strong> dari <strong><?= $total ?></strong>
    (maks. <?= $perPage ?> per halaman)
  </div>
</nav>
