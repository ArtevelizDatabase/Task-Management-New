<?php
/**
 * @var list<array{value:string,label:string,abbr:string}> $pivotStatusOptions
 * @var string                                              $curStat
 * @var int                                                 $gid
 * @var int                                                 $pid
 * @var int|null                                            $ftid
 * @var string                                              $titleBase
 */
$pivotStatusOptions = $pivotStatusOptions ?? [];
$curStat            = $curStat ?? '';
$gid                = (int) ($gid ?? 0);
$pid                = (int) ($pid ?? 0);
$ftid               = isset($ftid) && $ftid !== null ? (int) $ftid : null;
$titleBase          = (string) ($titleBase ?? '');
$ftAttr             = ($ftid !== null && $ftid > 0) ? (string) $ftid : '';
$isEmpty            = $curStat === '';
$cellTitle          = $titleBase !== '' ? $titleBase . ' — klik untuk pilih status' : 'Klik untuk pilih status';
?>
<div class="pivot-status-wrap<?= $isEmpty ? ' is-empty' : '' ?>" title="<?= esc($cellTitle) ?>">
<select
  class="pivot-status-select"
  data-group-id="<?= $gid ?>"
  data-platform-id="<?= $pid ?>"
  data-filetype-id="<?= esc($ftAttr, 'attr') ?>"
  aria-label="<?= esc($titleBase !== '' ? $titleBase . ' — status upload' : 'Status upload') ?>"
>
  <option value="" <?= $isEmpty ? 'selected' : '' ?> title="<?= esc('Kosong / hapus status') ?>">&#8203;</option>
  <?php foreach ($pivotStatusOptions as $opt):
      $v = (string) ($opt['value'] ?? '');
      if ($v === '') {
          continue;
      }
      ?>
    <?php
      $optLabel = (string) ($opt['label'] ?? '');
      $optAbbr  = (string) ($opt['abbr'] ?? '');
      $optHint  = $optAbbr !== '' ? ($optLabel . ' (' . $optAbbr . ')') : $optLabel;
    ?>
    <option value="<?= esc($v, 'attr') ?>" <?= $curStat === $v ? 'selected' : '' ?> title="<?= esc($optHint) ?>">
      <?= esc($optLabel) ?>
    </option>
  <?php endforeach; ?>
</select>
</div>
