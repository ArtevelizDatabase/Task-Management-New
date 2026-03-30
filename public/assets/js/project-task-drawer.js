/**
 * Master–detail drawer for /projects/{id} work items (?task= + panel fragment).
 */
(function () {
  const cfg = window.__projectDrawer;
  if (!cfg || !cfg.projectId) return;

  const overlay = document.getElementById('wiTaskDrawerOverlay');
  const drawer = document.getElementById('wiTaskDrawer');
  const contentEl = document.getElementById('wiTaskDrawerContent');
  const loadingEl = document.getElementById('wiTaskDrawerLoading');
  const table = document.getElementById('wi-work-items-table');

  if (!overlay || !drawer || !contentEl || !cfg.listUrl) return;

  let currentTaskId = null;

  function panelUrl(taskId) {
    return cfg.listUrl.replace(/\/?$/, '') + '/tasks/' + taskId + '/panel';
  }

  function setUrlWithTask(taskId) {
    const u = new URL(window.location.href);
    if (taskId) u.searchParams.set('task', String(taskId));
    else u.searchParams.delete('task');
    window.history.pushState({ wiTask: taskId || null }, '', u.pathname + u.search);
  }

  function openVisual() {
    overlay.classList.add('is-open');
    overlay.setAttribute('aria-hidden', 'false');
    drawer.classList.add('is-open');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('wi-task-drawer-open');
  }

  function closeVisual() {
    overlay.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');
    drawer.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('wi-task-drawer-open');
    if (typeof window.destroyProjectTaskFieldsUnified === 'function') {
      window.destroyProjectTaskFieldsUnified();
    }
    if (typeof window.destroyTaskShowRichtextEditors === 'function') {
      window.destroyTaskShowRichtextEditors(contentEl);
    }
    contentEl.innerHTML = '';
    currentTaskId = null;
  }

  function showLoading(on) {
    if (loadingEl) loadingEl.hidden = !on;
  }

  async function loadPanel(taskId) {
    if (!taskId) return;
    currentTaskId = taskId;
    showLoading(true);
    if (typeof window.destroyProjectTaskFieldsUnified === 'function') {
      window.destroyProjectTaskFieldsUnified();
    }
    if (typeof window.destroyTaskShowRichtextEditors === 'function') {
      window.destroyTaskShowRichtextEditors(contentEl);
    }
    contentEl.innerHTML = '';
    openVisual();
    try {
      const res = await fetch(panelUrl(taskId), {
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'text/html',
        },
      });
      const html = await res.text();
      if (!res.ok) {
        contentEl.innerHTML = '<p class="wi-task-drawer-error">Gagal memuat detail (' + res.status + ').</p>';
        return;
      }
      contentEl.innerHTML = html;
      const extrasRoot = contentEl.querySelector('.task-detail-extras-root');
      if (extrasRoot && window.TaskDetailExtras) {
        window.TaskDetailExtras.init(extrasRoot);
      }
      (function schedulePanelEditors(attempt) {
        attempt = attempt || 0;
        if (!window.EditorJS || !window.Paragraph) {
          if (attempt < 30) {
            setTimeout(function () {
              schedulePanelEditors(attempt + 1);
            }, 40);
          } else {
            console.warn('[project-task-drawer] Editor.js tidak dimuat.');
          }
          return;
        }
        requestAnimationFrame(function () {
          requestAnimationFrame(function () {
            if (contentEl.querySelector('.project-task-fields-unified-root')) {
              if (typeof window.initProjectTaskFieldsUnified === 'function') {
                window.initProjectTaskFieldsUnified(contentEl);
              }
            } else if (typeof window.initTaskShowRichtextEditors === 'function') {
              window.initTaskShowRichtextEditors(contentEl);
            }
          });
        });
      })(0);
    } catch (e) {
      contentEl.innerHTML = '<p class="wi-task-drawer-error">Gagal memuat detail.</p>';
    } finally {
      showLoading(false);
    }
  }

  function openTask(taskId, { pushHistory = true } = {}) {
    const id = parseInt(taskId, 10);
    if (!id) return;
    if (pushHistory) setUrlWithTask(id);
    loadPanel(id);
  }

  function closeDrawer() {
    const u = new URL(window.location.href);
    if (u.searchParams.has('task')) {
      u.searchParams.delete('task');
      window.history.pushState({ wiTask: null }, '', u.pathname + (u.search ? u.search : ''));
    }
    closeVisual();
  }

  document.addEventListener('task-detail-extras-reload', function (ev) {
    if (typeof ev.detail?.skipFullPageReload === 'function') ev.detail.skipFullPageReload();
    const tid = ev.detail && ev.detail.taskId;
    if (tid && Number(currentTaskId) === Number(tid)) loadPanel(tid);
  });

  document.querySelectorAll('.wi-task-drawer-link').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      const u = new URL(a.href, window.location.origin);
      const tid = u.searchParams.get('task');
      if (tid) openTask(tid, { pushHistory: true });
    });
  });

  if (table) {
    table.addEventListener('click', function (e) {
      const tr = e.target.closest('tr.wi-row');
      if (!tr) return;
      if (e.target.closest('a, button, input, select, textarea, .wi-assignee-trigger, .bsel, .wi-prio-bsel')) {
        return;
      }
      const tid = tr.getAttribute('data-task-id');
      if (tid) openTask(tid, { pushHistory: true });
    });
  }

  overlay.addEventListener('click', function () {
    closeDrawer();
  });

  drawer.querySelectorAll('.wi-task-drawer-close').forEach(function (btn) {
    btn.addEventListener('click', function () {
      closeDrawer();
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && drawer.classList.contains('is-open')) {
      closeDrawer();
    }
  });

  window.addEventListener('popstate', function () {
    const u = new URL(window.location.href);
    const tid = u.searchParams.get('task');
    if (tid) {
      currentTaskId = parseInt(tid, 10);
      loadPanel(currentTaskId);
    } else {
      closeVisual();
    }
  });

  if (cfg.initialTaskId) {
    document.addEventListener('DOMContentLoaded', function () {
      openTask(cfg.initialTaskId, { pushHistory: false });
    });
  }
})();
