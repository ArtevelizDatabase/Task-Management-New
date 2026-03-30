<?php
/**
 * @var list<array<string,mixed>> $groups
 * @var list<array<string,mixed>> $platforms
 * @var list<array<string,mixed>> $fileTypes
 * @var array<int, list<int>> $groupPlatformAssign
 * @var array<int, list<int>> $groupFileTypeAssign
 * @var array<int, list<array<string,mixed>>> $groupPlatforms
 * @var array<int, list<array<string,mixed>>> $groupFileTypes
 * @var bool $junctionReady
 * @var bool $scopedUpload
 * @var list<array<string,mixed>> $scopedPlatformsFlat
 * @var list<array<string,mixed>> $scopedFileTypesFlat
 */
$groups                = $groups ?? [];
$platforms             = $platforms ?? [];
$fileTypes             = $fileTypes ?? [];
$groupPlatformAssign   = $groupPlatformAssign ?? [];
$groupFileTypeAssign   = $groupFileTypeAssign ?? [];
$groupPlatforms        = $groupPlatforms ?? [];
$groupFileTypes        = $groupFileTypes ?? [];
$junctionReady         = $junctionReady ?? false;
$scopedUpload          = $scopedUpload ?? false;
$scopedPlatformsFlat   = $scopedPlatformsFlat ?? [];
$scopedFileTypesFlat   = $scopedFileTypesFlat ?? [];
helper('form');

$activePlatforms = array_values(array_filter($platforms, static fn (array $p): bool => (int) ($p['status'] ?? 0) === 1));
$activeFileTypes = array_values(array_filter($fileTypes, static fn (array $f): bool => (int) ($f['status'] ?? 0) === 1));

$scopedPivotRows = [];
if ($scopedUpload) {
    foreach ($scopedPlatformsFlat as $p) {
        $scopedPivotRows[] = ['kind' => 'pl', 'row' => $p];
    }
    foreach ($scopedFileTypesFlat as $f) {
        $scopedPivotRows[] = ['kind' => 'ft', 'row' => $f];
    }
}
?>

<link rel="stylesheet" href="/assets/css/pages/upload-config.css" />

<div class="upload-cfg-page">
  <div class="page-header">
    <div class="page-header-left">
      <h2 class="page-title">Konfigurasi status upload</h2>
      <p class="page-sub">
        <?php if ($scopedUpload): ?>
          Setiap <strong>platform</strong> dan <strong>tipe file</strong> tersimpan dengan <code class="upload-cfg-inline-code">product_group_id</code> — jelas milik grup mana. Antar grup boleh singkatan sama (mis. “IG” di dua grup berbeda).
        <?php else: ?>
          Atur grup pivot; jika memakai master global, entri platform/tipe bisa dipakai bersama antar grup (sebelum migrasi scoped).
        <?php endif; ?>
      </p>
    </div>
    <div class="page-header-right">
      <button type="button" class="btn btn-primary" id="upload-cfg-btn-open-create">
        <i class="fa-solid fa-plus icon-xs" aria-hidden="true"></i>
        Tambah grup
      </button>
      <a href="/tasks/submissions" class="btn btn-secondary">Lihat Daftar Setor</a>
    </div>
  </div>

  <?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'development'): ?>
    <p class="upload-cfg-dev-hint" title="Hanya lingkungan development">
      Contoh grup dummy: jalankan <code class="upload-cfg-inline-code">php spark db:seed UploadConfigDummySeeder</code>
      (idempoten; abbr <code class="upload-cfg-inline-code">DUMMY_SM</code>).
    </p>
  <?php endif; ?>

  <div class="card upload-cfg-table-card">
    <div class="upload-cfg-card-head">
      <h2>Grup produk</h2>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Nama</th>
            <th>Abbr</th>
            <?php if ($scopedUpload): ?>
              <th>Baris pivot</th>
            <?php endif; ?>
            <th>Dim. platform</th>
            <th>Dim. tipe file</th>
            <th>Urutan</th>
            <th>Status</th>
            <th class="upload-cfg-actions">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($groups as $g):
            $gid = (int) ($g['id'] ?? 0);
            $nPl = 0;
            $nFt = 0;
            if ($scopedUpload && $gid > 0) {
                foreach ($groupPlatforms[$gid] ?? [] as $px) {
                    if ((int) ($px['status'] ?? 0) === 1) {
                        ++$nPl;
                    }
                }
                foreach ($groupFileTypes[$gid] ?? [] as $fx) {
                    if ((int) ($fx['status'] ?? 0) === 1) {
                        ++$nFt;
                    }
                }
            }
            ?>
            <tr>
              <td><?= esc($g['name'] ?? '') ?></td>
              <td><?= esc($g['abbr'] ?? '—') ?></td>
              <?php if ($scopedUpload): ?>
                <td class="p-muted" style="font-size:12px;">
                  <?= (int) ($g['has_platform'] ?? 1) === 1 ? (string) $nPl . ' platform' : '—' ?>
                  <span class="p-muted"> · </span>
                  <?= (int) ($g['has_file_types'] ?? 0) === 1 ? (string) $nFt . ' tipe' : '—' ?>
                </td>
              <?php endif; ?>
              <td><?= ((int) ($g['has_platform'] ?? 1) === 1) ? 'Ya' : 'Tidak' ?></td>
              <td><?= ((int) ($g['has_file_types'] ?? 0) === 1) ? 'Ya' : 'Tidak' ?></td>
              <td><?= (int) ($g['order_no'] ?? 0) ?></td>
              <td><?= ((int) ($g['status'] ?? 0) === 1) ? 'Aktif' : 'Nonaktif' ?></td>
              <td class="upload-cfg-actions">
                <div class="btn-wrap-row">
                <?= form_open('/settings/upload-config/group/' . $gid . '/toggle') ?>
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-sm btn-secondary">
                    <?= ((int) ($g['status'] ?? 0) === 1) ? 'Nonaktifkan' : 'Aktifkan' ?>
                  </button>
                <?= form_close() ?>
                <button type="button" class="btn btn-sm btn-primary" onclick="openModal('ucModalGroup<?= $gid ?>')">Edit</button>
                <?= form_open('/settings/upload-config/group/' . $gid . '/delete', [
                    'data-confirm'            => 'Hapus grup ini beserta platform/tipe scoped, assignment, dan sel status pivot terkait? Tindakan tidak bisa dibatalkan.',
                    'data-confirm-title'      => 'Hapus grup',
                    'data-confirm-ok-variant' => 'danger',
                ]) ?>
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                <?= form_close() ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if ($groups === []): ?>
            <tr><td colspan="<?= $scopedUpload ? 8 : 7 ?>" class="p-muted" style="text-align:center;padding:20px;">Belum ada grup. Klik <strong>Tambah grup</strong>.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($scopedUpload): ?>
    <div class="card upload-cfg-table-card">
      <div class="upload-cfg-card-head">
        <h2>Baris pivot (platform &amp; tipe file per grup)</h2>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Grup</th>
              <th>Jenis</th>
              <th>Nama</th>
              <th>Abbr</th>
              <th>Urutan</th>
              <th>Status</th>
              <th class="upload-cfg-actions">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($scopedPivotRows as $item):
              $kind = $item['kind'];
              $r    = $item['row'];
              $rid  = (int) ($r['id'] ?? 0);
              $gl   = (string) ($r['_group_label'] ?? '');
              if ($kind === 'pl'): ?>
                <tr>
                  <td><?= esc($gl) ?></td>
                  <td><span class="upload-cfg-badge-type upload-cfg-badge-pl">Platform</span></td>
                  <td><?= esc($r['name'] ?? '') ?></td>
                  <td><?= esc($r['abbr'] ?? '') ?></td>
                  <td><?= (int) ($r['order_no'] ?? 0) ?></td>
                  <td><?= ((int) ($r['status'] ?? 0) === 1) ? 'Aktif' : 'Nonaktif' ?></td>
                  <td class="upload-cfg-actions">
                    <div class="btn-wrap-row">
                    <?= form_open('/settings/upload-config/platform/' . $rid . '/toggle') ?>
                      <?= csrf_field() ?>
                      <button type="submit" class="btn btn-sm btn-secondary"><?= ((int) ($r['status'] ?? 0) === 1) ? 'Off' : 'On' ?></button>
                    <?= form_close() ?>
                    <button type="button" class="btn btn-sm btn-primary uc-open-pl" data-id="<?= $rid ?>"
                      data-name="<?= esc($r['name'] ?? '', 'attr') ?>"
                      data-abbr="<?= esc($r['abbr'] ?? '', 'attr') ?>"
                      data-ord="<?= (int) ($r['order_no'] ?? 0) ?>"
                      data-group="<?= esc($gl, 'attr') ?>">Edit</button>
                    <?= form_open('/settings/upload-config/platform/' . $rid . '/delete', [
                        'data-confirm'            => 'Hapus platform ini? Baris status pivot yang memakai platform ini akan ikut terhapus.',
                        'data-confirm-title'      => 'Hapus platform',
                        'data-confirm-ok-variant' => 'danger',
                    ]) ?>
                      <?= csrf_field() ?>
                      <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                    <?= form_close() ?>
                    </div>
                  </td>
                </tr>
              <?php else: ?>
                <tr>
                  <td><?= esc($gl) ?></td>
                  <td><span class="upload-cfg-badge-type upload-cfg-badge-ft">Tipe file</span></td>
                  <td><?= esc($r['name'] ?? '') ?></td>
                  <td><?= esc($r['abbr'] ?? '') ?></td>
                  <td><?= (int) ($r['order_no'] ?? 0) ?></td>
                  <td><?= ((int) ($r['status'] ?? 0) === 1) ? 'Aktif' : 'Nonaktif' ?></td>
                  <td class="upload-cfg-actions">
                    <div class="btn-wrap-row">
                    <?= form_open('/settings/upload-config/filetype/' . $rid . '/toggle') ?>
                      <?= csrf_field() ?>
                      <button type="submit" class="btn btn-sm btn-secondary"><?= ((int) ($r['status'] ?? 0) === 1) ? 'Off' : 'On' ?></button>
                    <?= form_close() ?>
                    <button type="button" class="btn btn-sm btn-primary uc-open-ft" data-id="<?= $rid ?>"
                      data-name="<?= esc($r['name'] ?? '', 'attr') ?>"
                      data-abbr="<?= esc($r['abbr'] ?? '', 'attr') ?>"
                      data-ord="<?= (int) ($r['order_no'] ?? 0) ?>"
                      data-group="<?= esc($gl, 'attr') ?>">Edit</button>
                    <?= form_open('/settings/upload-config/filetype/' . $rid . '/delete', [
                        'data-confirm'            => 'Hapus tipe file ini? Baris status pivot yang memakai tipe ini akan ikut terhapus.',
                        'data-confirm-title'      => 'Hapus tipe file',
                        'data-confirm-ok-variant' => 'danger',
                    ]) ?>
                      <?= csrf_field() ?>
                      <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                    <?= form_close() ?>
                    </div>
                  </td>
                </tr>
              <?php endif;
            endforeach; ?>
            <?php if ($scopedPivotRows === []): ?>
              <tr><td colspan="7" class="p-muted" style="text-align:center;padding:16px;">Belum ada baris pivot. Ubah grup untuk menambah platform/tipe.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <?php if (! $scopedUpload): ?>
    <div class="card upload-cfg-table-card">
      <div class="upload-cfg-card-head"><h2>Platform (global)</h2></div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Nama</th>
              <th>Abbr</th>
              <th>Urutan</th>
              <th>Status</th>
              <th class="upload-cfg-actions">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($platforms as $p):
              $pid = (int) ($p['id'] ?? 0);
              $gn  = '';
              ?>
              <tr>
                <td><?= esc($p['name'] ?? '') ?></td>
                <td><?= esc($p['abbr'] ?? '') ?></td>
                <td><?= (int) ($p['order_no'] ?? 0) ?></td>
                <td><?= ((int) ($p['status'] ?? 0) === 1) ? 'Aktif' : 'Nonaktif' ?></td>
                <td class="upload-cfg-actions">
                  <div class="btn-wrap-row">
                  <?= form_open('/settings/upload-config/platform/' . $pid . '/toggle') ?>
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-secondary"><?= ((int) ($p['status'] ?? 0) === 1) ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                  <?= form_close() ?>
                  <button type="button" class="btn btn-sm btn-primary uc-open-pl" data-id="<?= $pid ?>"
                    data-name="<?= esc($p['name'] ?? '', 'attr') ?>"
                    data-abbr="<?= esc($p['abbr'] ?? '', 'attr') ?>"
                    data-ord="<?= (int) ($p['order_no'] ?? 0) ?>"
                    data-group="<?= esc($gn, 'attr') ?>">Edit</button>
                  <?= form_open('/settings/upload-config/platform/' . $pid . '/delete', [
                      'data-confirm'            => 'Hapus platform global ini? Assignment grup dan sel pivot terkait akan ikut terhapus.',
                      'data-confirm-title'      => 'Hapus platform',
                      'data-confirm-ok-variant' => 'danger',
                  ]) ?>
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                  <?= form_close() ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if ($platforms === []): ?>
              <tr><td colspan="5" class="p-muted" style="text-align:center;padding:20px;">—</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card upload-cfg-table-card">
      <div class="upload-cfg-card-head"><h2>Tipe file (global)</h2></div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Nama</th>
              <th>Abbr</th>
              <th>Urutan</th>
              <th>Status</th>
              <th class="upload-cfg-actions">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($fileTypes as $f):
              $fid = (int) ($f['id'] ?? 0);
              $gn  = '';
              ?>
              <tr>
                <td><?= esc($f['name'] ?? '') ?></td>
                <td><?= esc($f['abbr'] ?? '') ?></td>
                <td><?= (int) ($f['order_no'] ?? 0) ?></td>
                <td><?= ((int) ($f['status'] ?? 0) === 1) ? 'Aktif' : 'Nonaktif' ?></td>
                <td class="upload-cfg-actions">
                  <div class="btn-wrap-row">
                  <?= form_open('/settings/upload-config/filetype/' . $fid . '/toggle') ?>
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-secondary"><?= ((int) ($f['status'] ?? 0) === 1) ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                  <?= form_close() ?>
                  <button type="button" class="btn btn-sm btn-primary uc-open-ft" data-id="<?= $fid ?>"
                    data-name="<?= esc($f['name'] ?? '', 'attr') ?>"
                    data-abbr="<?= esc($f['abbr'] ?? '', 'attr') ?>"
                    data-ord="<?= (int) ($f['order_no'] ?? 0) ?>"
                    data-group="<?= esc($gn, 'attr') ?>">Edit</button>
                  <?= form_open('/settings/upload-config/filetype/' . $fid . '/delete', [
                      'data-confirm'            => 'Hapus tipe file global ini? Assignment grup dan sel pivot terkait akan ikut terhapus.',
                      'data-confirm-title'      => 'Hapus tipe file',
                      'data-confirm-ok-variant' => 'danger',
                  ]) ?>
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                  <?= form_close() ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if ($fileTypes === []): ?>
              <tr><td colspan="5" class="p-muted" style="text-align:center;padding:20px;">—</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <details class="card" style="padding:16px;margin-bottom:16px;">
      <summary style="cursor:pointer;font-weight:600;font-size:13px;color:var(--accent);list-style:none;">Tambah ke master (global)</summary>
      <p style="font-size:12px;color:var(--text-3);margin:8px 0 0;">Entri dipakai bersama antar grup (mode lama).</p>
      <div class="upload-cfg-master-grid">
        <div style="padding:12px;background:var(--surface-2);border-radius:8px;border:1px solid var(--border);">
          <h4 style="font-size:13px;font-weight:600;margin:0 0 10px;">Tambah platform</h4>
          <?= form_open('/settings/upload-config/platform') ?>
            <?= csrf_field() ?>
            <div class="upload-cfg-form-stack">
              <input type="text" name="name" class="form-control" required maxlength="100" placeholder="Nama" />
              <input type="text" name="abbr" class="form-control" required maxlength="20" placeholder="Singkatan" />
              <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
            </div>
          <?= form_close() ?>
        </div>
        <div style="padding:12px;background:var(--surface-2);border-radius:8px;border:1px solid var(--border);">
          <h4 style="font-size:13px;font-weight:600;margin:0 0 10px;">Tambah tipe file</h4>
          <?= form_open('/settings/upload-config/filetype') ?>
            <?= csrf_field() ?>
            <div class="upload-cfg-form-stack">
              <input type="text" name="name" class="form-control" required maxlength="100" placeholder="Nama" />
              <input type="text" name="abbr" class="form-control" required maxlength="20" placeholder="Singkatan" />
              <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
            </div>
          <?= form_close() ?>
        </div>
      </div>
    </details>
  <?php endif; ?>
</div>

<!-- Modal: buat grup + pivot -->
<div id="uploadCfgModalCreate" class="modal-overlay upload-cfg-modal-wide">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Buat grup produk + pivot</span>
      <button type="button" class="upload-cfg-modal-close" onclick="closeModal('uploadCfgModalCreate')" aria-label="Tutup">&times;</button>
    </div>
    <?php if ($junctionReady): ?>
      <?= form_open('/settings/upload-config/group', ['id' => 'upload-cfg-create-group-form']) ?>
        <?= csrf_field() ?>
        <div class="modal-body upload-cfg-form-stack">
          <label class="form-label">Nama grup</label>
          <input type="text" name="name" class="form-control" required maxlength="120" />
          <label class="form-label">Singkatan (opsional)</label>
          <input type="text" name="abbr" class="form-control" maxlength="30" />
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;">
            <input type="checkbox" name="has_platform" id="upload-cfg-create-has-pt" value="1" checked /> Kolom platform
          </label>
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;">
            <input type="checkbox" name="has_file_types" id="upload-cfg-create-has-ft" value="1" /> Kolom tipe file
          </label>
          <p class="p-muted" style="font-size:11px;margin:0;">Keduanya off = satu status per grup saja.</p>

          <?php if ($scopedUpload): ?>
            <div id="upload-cfg-create-platform-wrap" class="upload-cfg-sep">
              <div class="upload-cfg-sep-title">Platform untuk grup ini</div>
              <p class="p-muted" style="font-size:11px;margin:0 0 8px;">Nama + singkatan per baris. Urutan kolom = urutan baris.</p>
              <div id="upload-cfg-pl-rows">
                <div class="upload-cfg-pair-row" data-upload-cfg-pl-row>
                  <input type="text" name="new_platform_name[]" class="form-control" placeholder="Nama platform" maxlength="100" />
                  <input type="text" name="new_platform_abbr[]" class="form-control" placeholder="Singkatan" maxlength="20" style="max-width:120px;" />
                  <button type="button" class="btn btn-sm btn-secondary upload-cfg-pl-remove" style="display:none;" title="Hapus">✕</button>
                </div>
              </div>
              <button type="button" class="btn btn-sm btn-secondary" id="upload-cfg-pl-add">+ Platform lain</button>
            </div>
            <div id="upload-cfg-create-filetype-wrap" class="upload-cfg-sep" style="display:none;">
              <div class="upload-cfg-sep-title">Tipe file untuk grup ini</div>
              <p class="p-muted" style="font-size:11px;margin:0 0 8px;">Per baris nama + singkatan.</p>
              <div id="upload-cfg-ft-rows-scoped">
                <div class="upload-cfg-pair-row" data-upload-cfg-ft-row-scoped>
                  <input type="text" name="new_file_type_name[]" class="form-control" placeholder="Nama tipe file" maxlength="100" />
                  <input type="text" name="new_file_type_abbr[]" class="form-control" placeholder="Singkatan" maxlength="20" style="max-width:120px;" />
                  <button type="button" class="btn btn-sm btn-secondary upload-cfg-ft-scoped-remove" style="display:none;" title="Hapus">✕</button>
                </div>
              </div>
              <button type="button" class="btn btn-sm btn-secondary" id="upload-cfg-ft-scoped-add">+ Tipe file lain</button>
            </div>
          <?php else: ?>
            <div id="upload-cfg-create-platform-wrap" class="upload-cfg-sep">
              <div class="upload-cfg-sep-title">Platform (centang untuk grup ini)</div>
              <?php if ($activePlatforms === []): ?>
                <p class="p-muted" style="font-size:12px;">Belum ada platform aktif. Tambah di master dulu.</p>
              <?php else: ?>
                <div class="upload-cfg-check-list">
                  <?php foreach ($activePlatforms as $p):
                    $pid = (int) ($p['id'] ?? 0);
                    if ($pid < 1) {
                        continue;
                    }
                    ?>
                    <label style="display:flex;align-items:center;gap:8px;font-size:12px;cursor:pointer;">
                      <input type="checkbox" name="platform_ids[]" value="<?= $pid ?>" checked />
                      <?= esc($p['name'] ?? '') ?> <span class="p-muted">(<?= esc($p['abbr'] ?? '') ?>)</span>
                    </label>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
            <div id="upload-cfg-create-filetype-wrap" class="upload-cfg-sep" style="display:none;">
              <div class="upload-cfg-sep-title">Tipe file</div>
              <?php if ($activeFileTypes === []): ?>
                <p class="p-muted" style="font-size:12px;">Belum ada tipe aktif.</p>
              <?php else: ?>
                <div id="upload-cfg-ft-rows">
                  <div class="upload-cfg-ft-row" data-upload-cfg-ft-row>
                    <select name="file_type_ids[]" class="form-control" aria-label="Tipe file">
                      <option value="">— Pilih —</option>
                      <?php foreach ($activeFileTypes as $ft):
                        $fid = (int) ($ft['id'] ?? 0);
                        if ($fid < 1) {
                            continue;
                        }
                        ?>
                        <option value="<?= $fid ?>"><?= esc($ft['name'] ?? '') ?> (<?= esc($ft['abbr'] ?? '') ?>)</option>
                      <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-sm btn-secondary upload-cfg-ft-remove" style="display:none;">✕</button>
                  </div>
                </div>
                <button type="button" class="btn btn-sm btn-secondary" id="upload-cfg-ft-add">+ Tipe lain</button>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('uploadCfgModalCreate')">Batal</button>
          <button type="submit" class="btn btn-primary btn-sm">Simpan grup</button>
        </div>
      <?= form_close() ?>
    <?php else: ?>
      <div class="modal-body">
        <p class="p-muted" style="font-size:13px;">Jalankan migrasi database untuk mengaktifkan form ini.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('uploadCfgModalCreate')">Tutup</button>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal: ubah platform -->
<div id="uploadCfgModalPlEdit" class="modal-overlay upload-cfg-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit platform</span>
      <button type="button" class="upload-cfg-modal-close" onclick="closeModal('uploadCfgModalPlEdit')" aria-label="Tutup">&times;</button>
    </div>
    <div class="modal-body">
      <p id="uploadCfgPlSubtitle" class="p-muted" style="font-size:12px;margin:0 0 12px;"></p>
      <?= form_open('#', ['id' => 'uploadCfgFormPlEdit', 'class' => 'upload-cfg-form-stack']) ?>
        <?= csrf_field() ?>
        <label class="form-label">Nama</label>
        <input type="text" name="name" id="ucPlName" class="form-control" required maxlength="100" />
        <label class="form-label">Singkatan</label>
        <input type="text" name="abbr" id="ucPlAbbr" class="form-control" required maxlength="20" />
        <label class="form-label">Urutan</label>
        <input type="number" name="order_no" id="ucPlOrd" class="form-control" min="0" value="0" />
        <button type="submit" class="btn btn-primary btn-sm" style="margin-top:8px;">Simpan</button>
      <?= form_close() ?>
      <?= form_open('#', ['id' => 'uploadCfgFormPlDelete', 'data-confirm' => 'Hapus platform ini? Sel terkait di pivot ikut terhapus.', 'data-confirm-title' => 'Hapus platform']) ?>
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-danger" style="margin-top:8px;">Hapus platform</button>
      <?= form_close() ?>
    </div>
  </div>
</div>

<!-- Modal: ubah tipe file -->
<div id="uploadCfgModalFtEdit" class="modal-overlay upload-cfg-modal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit tipe file</span>
      <button type="button" class="upload-cfg-modal-close" onclick="closeModal('uploadCfgModalFtEdit')" aria-label="Tutup">&times;</button>
    </div>
    <div class="modal-body">
      <p id="uploadCfgFtSubtitle" class="p-muted" style="font-size:12px;margin:0 0 12px;"></p>
      <?= form_open('#', ['id' => 'uploadCfgFormFtEdit', 'class' => 'upload-cfg-form-stack']) ?>
        <?= csrf_field() ?>
        <label class="form-label">Nama</label>
        <input type="text" name="name" id="ucFtName" class="form-control" required maxlength="100" />
        <label class="form-label">Singkatan</label>
        <input type="text" name="abbr" id="ucFtAbbr" class="form-control" required maxlength="20" />
        <label class="form-label">Urutan</label>
        <input type="number" name="order_no" id="ucFtOrd" class="form-control" min="0" value="0" />
        <button type="submit" class="btn btn-primary btn-sm" style="margin-top:8px;">Simpan</button>
      <?= form_close() ?>
      <?= form_open('#', ['id' => 'uploadCfgFormFtDelete', 'data-confirm' => 'Hapus tipe file ini? Sel terkait di pivot ikut terhapus.', 'data-confirm-title' => 'Hapus tipe file']) ?>
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-danger" style="margin-top:8px;">Hapus tipe file</button>
      <?= form_close() ?>
    </div>
  </div>
</div>

<?php foreach ($groups as $g):
  $gid = (int) ($g['id'] ?? 0);
  if ($gid < 1) {
      continue;
  }
  $platSel = $groupPlatformAssign[$gid] ?? [];
  $ftSel   = $groupFileTypeAssign[$gid] ?? [];
  $gPlat   = $groupPlatforms[$gid] ?? [];
  $gFt     = $groupFileTypes[$gid] ?? [];
  ?>
  <div id="ucModalGroup<?= $gid ?>" class="modal-overlay upload-cfg-modal-wide">
    <div class="modal">
      <div class="modal-header">
        <span class="modal-title">Edit grup: <?= esc($g['name'] ?? '') ?></span>
        <button type="button" class="upload-cfg-modal-close" onclick="closeModal('ucModalGroup<?= $gid ?>')" aria-label="Tutup">&times;</button>
      </div>
      <div class="modal-body upload-cfg-form-stack">
        <?= form_open('/settings/upload-config/group/' . $gid . '/update') ?>
          <?= csrf_field() ?>
          <label class="form-label">Nama</label>
          <input type="text" name="name" class="form-control" required maxlength="120" value="<?= esc($g['name'] ?? '') ?>" />
          <label class="form-label">Singkatan</label>
          <input type="text" name="abbr" class="form-control" maxlength="30" value="<?= esc($g['abbr'] ?? '') ?>" />
          <label class="form-label">Urutan</label>
          <input type="number" name="order_no" class="form-control" min="0" value="<?= (int) ($g['order_no'] ?? 0) ?>" />
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-top:8px;">
            <input type="checkbox" name="has_platform" value="1" <?= ((int) ($g['has_platform'] ?? 1) === 1) ? 'checked' : '' ?> /> Kolom platform
          </label>
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;">
            <input type="checkbox" name="has_file_types" value="1" <?= ((int) ($g['has_file_types'] ?? 0) === 1) ? 'checked' : '' ?> /> Kolom tipe file
          </label>
          <button type="submit" class="btn btn-primary btn-sm" style="margin-top:10px;">Simpan grup</button>
        <?= form_close() ?>

        <?php if ($junctionReady): ?>
          <?php if ((int) ($g['has_platform'] ?? 1) === 1): ?>
            <div class="upload-cfg-sep">
              <div class="upload-cfg-sep-title">Platform dipakai di pivot</div>
              <p class="p-muted" style="font-size:11px;margin:0 0 8px;"><?= $scopedUpload ? 'Centang = aktif di tabel. Tidak dicentang = nonaktif (data sel tetap).' : 'Urutan kolom = urutan daftar.' ?></p>
              <?= form_open('/settings/upload-config/group/' . $gid . '/platforms') ?>
                <?= csrf_field() ?>
                <div class="upload-cfg-check-list">
                  <?php
                  $platList = $scopedUpload ? $gPlat : $platforms;
                  foreach ($platList as $p):
                    if (! $scopedUpload && (int) ($p['status'] ?? 0) !== 1) {
                        continue;
                    }
                    $pid = (int) ($p['id'] ?? 0);
                    if ($pid < 1) {
                        continue;
                    }
                    $chk = in_array($pid, $platSel, true);
                    $st  = (int) ($p['status'] ?? 0);
                    ?>
                    <label style="display:flex;align-items:center;gap:8px;font-size:12px;cursor:pointer;<?= $st !== 1 ? 'opacity:.75;' : '' ?>">
                      <input type="checkbox" name="platform_ids[]" value="<?= $pid ?>" <?= $chk ? 'checked' : '' ?> />
                      <?= esc($p['name'] ?? '') ?> <span class="p-muted">(<?= esc($p['abbr'] ?? '') ?>)</span>
                    </label>
                  <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-sm btn-secondary">Simpan platform</button>
              <?= form_close() ?>
              <?php if ($scopedUpload): ?>
                <?= form_open('/settings/upload-config/platform') ?>
                  <?= csrf_field() ?>
                  <input type="hidden" name="product_group_id" value="<?= $gid ?>" />
                  <div class="upload-cfg-sep-title" style="margin-top:12px;">Tambah platform baru</div>
                  <input type="text" name="name" class="form-control" placeholder="Nama" required maxlength="100" />
                  <input type="text" name="abbr" class="form-control" placeholder="Singkatan" required maxlength="20" />
                  <button type="submit" class="btn btn-sm btn-secondary" style="margin-top:6px;">Tambah</button>
                <?= form_close() ?>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <?php if ((int) ($g['has_file_types'] ?? 0) === 1): ?>
            <div class="upload-cfg-sep">
              <div class="upload-cfg-sep-title">Tipe file dipakai di pivot</div>
              <p class="p-muted" style="font-size:11px;margin:0 0 8px;"><?= $scopedUpload ? 'Centang = aktif di pivot.' : 'Urutan kolom = urutan daftar.' ?></p>
              <?= form_open('/settings/upload-config/group/' . $gid . '/filetypes') ?>
                <?= csrf_field() ?>
                <div class="upload-cfg-check-list">
                  <?php
                  $ftList = $scopedUpload ? $gFt : $fileTypes;
                  foreach ($ftList as $ft):
                    if (! $scopedUpload && (int) ($ft['status'] ?? 0) !== 1) {
                        continue;
                    }
                    $fid = (int) ($ft['id'] ?? 0);
                    if ($fid < 1) {
                        continue;
                    }
                    $chk = in_array($fid, $ftSel, true);
                    $st  = (int) ($ft['status'] ?? 0);
                    ?>
                    <label style="display:flex;align-items:center;gap:8px;font-size:12px;cursor:pointer;<?= $st !== 1 ? 'opacity:.75;' : '' ?>">
                      <input type="checkbox" name="file_type_ids[]" value="<?= $fid ?>" <?= $chk ? 'checked' : '' ?> />
                      <?= esc($ft['name'] ?? '') ?> <span class="p-muted">(<?= esc($ft['abbr'] ?? '') ?>)</span>
                    </label>
                  <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-sm btn-secondary">Simpan tipe file</button>
              <?= form_close() ?>
              <?php if ($scopedUpload): ?>
                <?= form_open('/settings/upload-config/filetype') ?>
                  <?= csrf_field() ?>
                  <input type="hidden" name="product_group_id" value="<?= $gid ?>" />
                  <div class="upload-cfg-sep-title" style="margin-top:12px;">Tambah tipe file baru</div>
                  <input type="text" name="name" class="form-control" placeholder="Nama" required maxlength="100" />
                  <input type="text" name="abbr" class="form-control" placeholder="Singkatan" required maxlength="20" />
                  <button type="submit" class="btn btn-sm btn-secondary" style="margin-top:6px;">Tambah</button>
                <?= form_close() ?>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <?= form_open('/settings/upload-config/group/' . $gid . '/delete', [
            'data-confirm'       => 'Hapus grup beserta baris platform/tipe dan sel pivot grup ini?',
            'data-confirm-title' => 'Hapus grup',
            'data-confirm-ok-variant' => 'danger',
        ]) ?>
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-sm btn-danger" style="margin-top:16px;">Hapus grup</button>
        <?= form_close() ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<script>
(function () {
  document.getElementById('upload-cfg-btn-open-create')?.addEventListener('click', function () {
    window.openUploadCfgCreateModal && window.openUploadCfgCreateModal();
  });

  window.openUploadCfgCreateModal = function () {
    const form = document.getElementById('upload-cfg-create-group-form');
    if (form) {
      form.reset();
      const cbPt = document.getElementById('upload-cfg-create-has-pt');
      const cbFt = document.getElementById('upload-cfg-create-has-ft');
      if (cbPt) cbPt.checked = true;
      if (cbFt) cbFt.checked = false;
      const plRows = document.getElementById('upload-cfg-pl-rows');
      if (plRows) {
        const tpl = plRows.querySelector('[data-upload-cfg-pl-row]');
        plRows.querySelectorAll('[data-upload-cfg-pl-row]').forEach((row, i) => { if (i > 0) row.remove(); });
        if (tpl) tpl.querySelectorAll('input').forEach((i) => { i.value = ''; });
      }
      const ftSc = document.getElementById('upload-cfg-ft-rows-scoped');
      if (ftSc) {
        const tpl = ftSc.querySelector('[data-upload-cfg-ft-row-scoped]');
        ftSc.querySelectorAll('[data-upload-cfg-ft-row-scoped]').forEach((row, i) => { if (i > 0) row.remove(); });
        if (tpl) tpl.querySelectorAll('input').forEach((i) => { i.value = ''; });
      }
      const ftRows = document.getElementById('upload-cfg-ft-rows');
      if (ftRows) {
        ftRows.querySelectorAll('[data-upload-cfg-ft-row]').forEach((row, i) => { if (i > 0) row.remove(); });
        const first = ftRows.querySelector('[data-upload-cfg-ft-row] select');
        if (first) first.value = '';
      }
    }
    if (typeof syncUploadCfgCreateSections === 'function') syncUploadCfgCreateSections();
    openModal('uploadCfgModalCreate');
  };

  const cbPt = document.getElementById('upload-cfg-create-has-pt');
  const cbFt = document.getElementById('upload-cfg-create-has-ft');
  const wrapP = document.getElementById('upload-cfg-create-platform-wrap');
  const wrapF = document.getElementById('upload-cfg-create-filetype-wrap');
  const rows = document.getElementById('upload-cfg-ft-rows');
  const btnAdd = document.getElementById('upload-cfg-ft-add');
  const plRows = document.getElementById('upload-cfg-pl-rows');
  const plAdd = document.getElementById('upload-cfg-pl-add');
  const ftScopedRows = document.getElementById('upload-cfg-ft-rows-scoped');
  const ftScopedAdd = document.getElementById('upload-cfg-ft-scoped-add');

  window.syncUploadCfgCreateSections = function syncUploadCfgCreateSections() {
    if (wrapP && cbPt) wrapP.style.display = cbPt.checked ? '' : 'none';
    if (wrapF && cbFt) wrapF.style.display = cbFt.checked ? '' : 'none';
    if (wrapP) {
      const on = cbPt && cbPt.checked;
      wrapP.querySelectorAll('input[name="platform_ids[]"]').forEach((el) => { el.disabled = !on; });
      wrapP.querySelectorAll('input[name="new_platform_name[]"], input[name="new_platform_abbr[]"]').forEach((el) => { el.disabled = !on; });
    }
    if (rows) {
      const on = cbFt && cbFt.checked;
      rows.querySelectorAll('select[name="file_type_ids[]"]').forEach((el) => { el.disabled = !on; });
    }
    if (ftScopedRows) {
      const on = cbFt && cbFt.checked;
      ftScopedRows.querySelectorAll('input[name="new_file_type_name[]"], input[name="new_file_type_abbr[]"]').forEach((el) => { el.disabled = !on; });
    }
  };

  if (cbPt) cbPt.addEventListener('change', syncUploadCfgCreateSections);
  if (cbFt) cbFt.addEventListener('change', syncUploadCfgCreateSections);
  syncUploadCfgCreateSections();

  function bindRepeatable(container, rowSel, removeSel, addBtn) {
    if (!container || !addBtn) return;
    function refreshRm() {
      const list = container.querySelectorAll(rowSel);
      list.forEach((row) => {
        const rm = row.querySelector(removeSel);
        if (rm) rm.style.display = list.length > 1 ? '' : 'none';
      });
    }
    addBtn.addEventListener('click', function () {
      const first = container.querySelector(rowSel);
      if (!first) return;
      const clone = first.cloneNode(true);
      clone.querySelectorAll('input').forEach((inp) => { inp.value = ''; });
      clone.querySelectorAll('select').forEach((s) => { s.value = ''; });
      container.appendChild(clone);
      clone.querySelector(removeSel)?.addEventListener('click', function () {
        if (container.querySelectorAll(rowSel).length <= 1) return;
        clone.remove();
        refreshRm();
      });
      refreshRm();
    });
    container.querySelectorAll(removeSel).forEach((btn) => {
      btn.addEventListener('click', function () {
        const row = btn.closest(rowSel);
        if (!row || container.querySelectorAll(rowSel).length <= 1) return;
        row.remove();
        refreshRm();
      });
    });
    refreshRm();
  }

  bindRepeatable(plRows, '[data-upload-cfg-pl-row]', '.upload-cfg-pl-remove', plAdd);
  bindRepeatable(ftScopedRows, '[data-upload-cfg-ft-row-scoped]', '.upload-cfg-ft-scoped-remove', ftScopedAdd);

  function refreshFtRemoveButtons() {
    if (!rows) return;
    const list = rows.querySelectorAll('[data-upload-cfg-ft-row]');
    list.forEach((row) => {
      const rm = row.querySelector('.upload-cfg-ft-remove');
      if (rm) rm.style.display = list.length > 1 ? '' : 'none';
    });
  }

  if (btnAdd && rows) {
    btnAdd.addEventListener('click', function () {
      const first = rows.querySelector('[data-upload-cfg-ft-row]');
      if (!first) return;
      const clone = first.cloneNode(true);
      clone.querySelectorAll('select').forEach((s) => { s.value = ''; });
      rows.appendChild(clone);
      clone.querySelector('.upload-cfg-ft-remove')?.addEventListener('click', function () {
        if (rows.querySelectorAll('[data-upload-cfg-ft-row]').length <= 1) return;
        clone.remove();
        refreshFtRemoveButtons();
      });
      refreshFtRemoveButtons();
    });
    rows.querySelectorAll('.upload-cfg-ft-remove').forEach((btn) => {
      btn.addEventListener('click', function () {
        const row = btn.closest('[data-upload-cfg-ft-row]');
        if (!row || rows.querySelectorAll('[data-upload-cfg-ft-row]').length <= 1) return;
        row.remove();
        refreshFtRemoveButtons();
      });
    });
    refreshFtRemoveButtons();
  }

  const formPl = document.getElementById('uploadCfgFormPlEdit');
  const formPlDel = document.getElementById('uploadCfgFormPlDelete');
  document.querySelectorAll('.uc-open-pl').forEach((btn) => {
    btn.addEventListener('click', function () {
      const id = btn.getAttribute('data-id');
      formPl.action = '/settings/upload-config/platform/' + id + '/update';
      formPlDel.action = '/settings/upload-config/platform/' + id + '/delete';
      document.getElementById('ucPlName').value = btn.getAttribute('data-name') || '';
      document.getElementById('ucPlAbbr').value = btn.getAttribute('data-abbr') || '';
      document.getElementById('ucPlOrd').value = btn.getAttribute('data-ord') || '0';
      const g = btn.getAttribute('data-group');
      document.getElementById('uploadCfgPlSubtitle').textContent = g ? ('Grup: ' + g) : 'Master global';
      openModal('uploadCfgModalPlEdit');
    });
  });

  const formFt = document.getElementById('uploadCfgFormFtEdit');
  const formFtDel = document.getElementById('uploadCfgFormFtDelete');
  document.querySelectorAll('.uc-open-ft').forEach((btn) => {
    btn.addEventListener('click', function () {
      const id = btn.getAttribute('data-id');
      formFt.action = '/settings/upload-config/filetype/' + id + '/update';
      formFtDel.action = '/settings/upload-config/filetype/' + id + '/delete';
      document.getElementById('ucFtName').value = btn.getAttribute('data-name') || '';
      document.getElementById('ucFtAbbr').value = btn.getAttribute('data-abbr') || '';
      document.getElementById('ucFtOrd').value = btn.getAttribute('data-ord') || '0';
      const g = btn.getAttribute('data-group');
      document.getElementById('uploadCfgFtSubtitle').textContent = g ? ('Grup: ' + g) : 'Master global';
      openModal('uploadCfgModalFtEdit');
    });
  });
})();
</script>
