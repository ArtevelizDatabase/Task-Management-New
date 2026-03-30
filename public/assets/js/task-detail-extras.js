/**
 * Task detail tabs + extras (comments, revisions, attachments, assignees, relations).
 * Requires a wrapper .task-detail-extras-root[data-task-id] and window.__taskExtras { baseUrl, csrfHeader }.
 */
(function (global) {
  let rootRef = null;

  function cfg() {
    return global.__taskExtras || {};
  }

  function baseUrl() {
    const b = cfg().baseUrl || '';
    if (b) return b.endsWith('/') ? b : b + '/';
    const o = global.location && global.location.origin ? global.location.origin : '';
    return o ? o + '/' : '/';
  }

  function csrfHeaderName() {
    return cfg().csrfHeader || 'X-CSRF-TOKEN';
  }

  function getCsrf() {
    if (typeof global.getAppCsrf === 'function') return global.getAppCsrf();
    return global.appCsrf || { key: '', val: '' };
  }

  function appendCsrf(fd) {
    const c = getCsrf();
    if (!c.key || c.val === undefined || c.val === null || c.val === '') {
      console.warn('task-detail-extras: CSRF token tidak tersedia; muat ulang halaman.');
      return fd;
    }
    fd.append(c.key, c.val);
    return fd;
  }

  function applyCsrfFromResponse(d) {
    if (d && d.csrf && global.appCsrf) global.appCsrf.val = d.csrf;
    if (d && d.csrf && typeof global.updateAppCsrf === 'function') global.updateAppCsrf(d.csrf);
  }

  function extrasFetchHeaders() {
    const c = getCsrf();
    const h = {
      'X-Requested-With': 'XMLHttpRequest',
      Accept: 'application/json',
    };
    if (c.val) h[csrfHeaderName()] = c.val;
    return h;
  }

  async function postFormExtras(url, formData) {
    const r = await fetch(url, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
      headers: extrasFetchHeaders(),
    });
    const text = await r.text();
    let d = {};
    try {
      d = text ? JSON.parse(text) : {};
    } catch (e) {
      if (text.indexOf('not allowed') !== -1 || text.indexOf('disallowedAction') !== -1) {
        alert('Sesi keamanan (CSRF) tidak valid atau kedaluwarsa. Muat ulang halaman (F5) lalu coba lagi.');
      } else {
        alert('Respons server tidak bisa dibaca. Muat ulang halaman jika masalah berlanjut.');
      }
      return {};
    }
    applyCsrfFromResponse(d);
    return d;
  }

  function $(sel) {
    return rootRef ? rootRef.querySelector(sel) : null;
  }

  function $$ (sel) {
    return rootRef ? Array.from(rootRef.querySelectorAll(sel)) : [];
  }

  function afterMutation(taskId) {
    let doFullReload = true;
    global.dispatchEvent(
      new CustomEvent('task-detail-extras-reload', {
        detail: {
          taskId: parseInt(taskId, 10),
          skipFullPageReload: function () {
            doFullReload = false;
          },
        },
      })
    );
    if (doFullReload) global.location.reload();
  }

  function bindTabs() {
    $$('.extras-tab').forEach(function (tab) {
      tab.addEventListener('click', function () {
        const name = tab.getAttribute('data-tab');
        $$('.extras-tab').forEach(function (t) {
          t.classList.toggle('active', t === tab);
        });
        $$('.extras-panel').forEach(function (p) {
          p.classList.remove('active');
        });
        const panel = rootRef.querySelector('#panel-' + name);
        if (panel) panel.classList.add('active');
      });
    });
  }

  let relationAcTimer = null;
  let relationAcAbort = null;

  function escAc(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function bindRelationTaskAutocomplete(taskId) {
    const hid = $('#relatedTaskId');
    const inp = $('#relatedTaskSearch');
    const list = $('#relatedTaskAcList');
    const wrap = rootRef && rootRef.querySelector('.relation-task-ac-wrap');
    if (!hid || !inp || !list || !wrap) return;

    function closeList() {
      list.hidden = true;
      list.innerHTML = '';
      inp.setAttribute('aria-expanded', 'false');
    }

    function setSelection(item) {
      hid.value = String(item.id);
      inp.value = '#' + item.id + ' ' + (item.judul || '');
      closeList();
    }

    async function runSearch(q) {
      if (relationAcAbort) relationAcAbort.abort();
      relationAcAbort = new AbortController();
      const url =
        baseUrl() +
        'tasks/' +
        taskId +
        '/relation-tasks?q=' +
        encodeURIComponent(q);
      try {
        const r = await fetch(url, {
          method: 'GET',
          credentials: 'same-origin',
          signal: relationAcAbort.signal,
          headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
        const text = await r.text();
        let d = {};
        try {
          d = text ? JSON.parse(text) : {};
        } catch (e) {
          return;
        }
        applyCsrfFromResponse(d);
        const items = d.data || [];
        if (!items.length) {
          list.innerHTML =
            '<div class="relation-task-ac-item" style="color:var(--text-muted);cursor:default;">Tidak ada task.</div>';
          list.hidden = false;
          inp.setAttribute('aria-expanded', 'true');
          return;
        }
        list.innerHTML = items
          .map(function (it) {
            const label = escAc((it.judul || '').slice(0, 80));
            return (
              '<div class="relation-task-ac-item" role="option" tabindex="0" data-id="' +
              parseInt(it.id, 10) +
              '"><strong>#' +
              parseInt(it.id, 10) +
              '</strong> <small>' +
              label +
              '</small></div>'
            );
          })
          .join('');
        list.hidden = false;
        inp.setAttribute('aria-expanded', 'true');
        list.querySelectorAll('.relation-task-ac-item').forEach(function (el) {
          el.addEventListener('mousedown', function (e) {
            e.preventDefault();
          });
          el.addEventListener('click', function () {
            const id = parseInt(el.getAttribute('data-id'), 10);
            const sm = el.querySelector('small');
            const judul = sm ? sm.textContent : '';
            if (id) setSelection({ id: id, judul: judul });
          });
        });
      } catch (e) {
        if (e && e.name === 'AbortError') return;
      }
    }

    inp.addEventListener('input', function () {
      hid.value = '';
      const q = (inp.value || '').trim();
      clearTimeout(relationAcTimer);
      relationAcTimer = setTimeout(function () {
        runSearch(q);
      }, 280);
    });

    inp.addEventListener('focus', function () {
      const q = (inp.value || '').trim();
      clearTimeout(relationAcTimer);
      relationAcTimer = setTimeout(function () {
        runSearch(q);
      }, 80);
    });

    document.addEventListener('click', function (e) {
      if (!wrap.contains(e.target)) closeList();
    });
  }

  function init(rootEl) {
    if (!rootEl || !rootEl.classList.contains('task-detail-extras-root')) {
      return;
    }
    rootRef = rootEl;
    bindTabs();
    const tid = parseInt(rootEl.getAttribute('data-task-id'), 10);
    if (tid) bindRelationTaskAutocomplete(tid);
  }

  global.submitComment = async function (taskId) {
    const ta = $('#commentBody');
    const body = (ta && ta.value ? ta.value : '').trim();
    if (!body) return alert('Komentar tidak boleh kosong.');
    const fd = appendCsrf(new FormData());
    fd.append('body', body);
    const d = await postFormExtras(baseUrl() + 'tasks/' + taskId + '/comments', fd);
    if (d.success) afterMutation(taskId);
    else if (Object.keys(d).length) alert(d.error || d.message || 'Gagal mengirim komentar.');
  };

  global.deleteComment = async function (commentId, taskId) {
    if (!confirm('Hapus komentar ini?')) return;
    const fd = appendCsrf(new FormData());
    const d = await postFormExtras(baseUrl() + 'tasks/' + taskId + '/comments/' + commentId + '/delete', fd);
    if (d.success) {
      const el = rootRef && rootRef.querySelector('#comment-' + commentId);
      if (el) el.remove();
    } else if (Object.keys(d).length) alert(d.error || 'Gagal menghapus.');
  };

  global.submitRevision = async function (taskId) {
    const fd = appendCsrf(new FormData());
    fd.append('requested_by', ($('#rev_by') && $('#rev_by').value) || '');
    fd.append('description', ($('#rev_desc') && $('#rev_desc').value) || '');
    fd.append('requested_at', ($('#rev_date') && $('#rev_date').value) || '');
    fd.append('due_date', ($('#rev_due') && $('#rev_due').value) || '');
    const d = await postFormExtras(baseUrl() + 'tasks/' + taskId + '/revisions', fd);
    if (d.success) afterMutation(taskId);
    else if (Object.keys(d).length) alert(d.error || d.message || 'Gagal menyimpan revisi.');
  };

  global.updateRevStatus = async function (taskId, revId, status) {
    const note = status === 'rejected' ? (prompt('Alasan penolakan:') || '') : '';
    const fd = appendCsrf(new FormData());
    fd.append('status', status);
    fd.append('note', note);
    const d = await postFormExtras(baseUrl() + 'tasks/' + taskId + '/revisions/' + revId + '/status', fd);
    if (d.success) afterMutation(taskId);
    else if (Object.keys(d).length) alert(d.error || 'Gagal update status.');
  };

  global.uploadAttachment = async function (taskId) {
    const fileInput = $('#attachFile');
    if (!fileInput || !fileInput.files.length) return alert('Pilih file terlebih dahulu.');
    let ok = true;
    for (let i = 0; i < fileInput.files.length; i++) {
      const file = fileInput.files[i];
      const fd = appendCsrf(new FormData());
      fd.append('file', file);
      const d = await postFormExtras(baseUrl() + 'tasks/' + taskId + '/attachments', fd);
      if (!d.success) {
        ok = false;
        alert('Gagal upload ' + file.name + ': ' + (d.error || d.message || 'unknown'));
      }
    }
    if (ok) afterMutation(taskId);
  };

  global.deleteAttachment = async function (taskId, attId) {
    if (!confirm('Hapus lampiran ini?')) return;
    const fd = appendCsrf(new FormData());
    const d = await postFormExtras(baseUrl() + 'tasks/' + taskId + '/attachments/' + attId + '/delete', fd);
    if (d.success) {
      const el = rootRef && rootRef.querySelector('#att-' + attId);
      if (el) el.remove();
    } else if (Object.keys(d).length) alert(d.error || 'Gagal menghapus.');
  };

  global.addAssignee = async function (taskId) {
    const sel = $('#assigneeSelect');
    const userId = sel ? sel.value : '';
    if (!userId) return alert('Pilih user terlebih dahulu.');
    const fd = appendCsrf(new FormData());
    fd.append('user_id', userId);
    const d = await postFormExtras(baseUrl() + 'tasks/' + taskId + '/assignees', fd);
    if (d.success) afterMutation(taskId);
    else if (Object.keys(d).length) alert(d.message || d.error || 'Gagal menambah assignee.');
  };

  global.removeAssignee = async function (taskId, userId) {
    const fd = appendCsrf(new FormData());
    const d = await postFormExtras(baseUrl() + 'tasks/' + taskId + '/assignees/' + userId + '/remove', fd);
    if (d.success) {
      const el = rootRef && rootRef.querySelector('#chip-' + userId);
      if (el) el.remove();
    }
  };

  global.addRelation = async function (taskId) {
    const rel = $('#relatedTaskId');
    const typ = $('#relationType');
    const relatedId = rel ? String(rel.value || '').trim() : '';
    const type = typ ? typ.value : '';
    if (!relatedId) return alert('Pilih task terlebih dahulu.');
    const fd = appendCsrf(new FormData());
    fd.append('related_task_id', relatedId);
    fd.append('relation_type', type);
    const d = await postFormExtras(baseUrl() + 'tasks/' + taskId + '/relations', fd);
    if (d.success) afterMutation(taskId);
    else if (Object.keys(d).length) alert(d.message || d.error || 'Gagal menambah relasi.');
  };

  global.deleteRelation = async function (taskId, relId) {
    const fd = appendCsrf(new FormData());
    const d = await postFormExtras(baseUrl() + 'tasks/' + taskId + '/relations/' + relId + '/delete', fd);
    if (d.success) {
      const el = rootRef && rootRef.querySelector('#rel-' + relId);
      if (el) el.remove();
    }
  };

  global.toggleFav = async function (taskId) {
    const fd = appendCsrf(new FormData());
    fd.append('entity_type', 'task');
    fd.append('entity_id', String(taskId));
    const d = await postFormExtras(baseUrl() + 'favorites/toggle', fd);
    if (d.success) {
      const btn = $('#btnFav');
      if (btn) {
        btn.classList.toggle('active', d.is_favorited);
        btn.textContent = d.is_favorited ? '★ Favorit' : '☆ Tambah ke Favorit';
      }
    }
  };

  global.TaskDetailExtras = { init };

  document.addEventListener('DOMContentLoaded', function () {
    if (global.__projectDrawer) {
      return;
    }
    const root = document.querySelector('.task-detail-extras-root');
    if (root) {
      init(root);
    }
  });
})(window);
