/**
 * Inline edit for project Work Items table (same API as /tasks: field-update, status, core-update).
 */
(function () {
  const root = document.getElementById('wi-work-items-table');
  if (!root) return;

  const _fieldUpdateQueues = new Map();
  const _fieldPendingValues = new Map();
  const _fieldVersions = new Map();

  function _applyCsrfFromResponse(data) {
    if (data?.csrf && typeof updateAppCsrf === 'function') updateAppCsrf(data.csrf);
    else if (data?.csrf && window.appCsrf) window.appCsrf.val = data.csrf;
  }

  function _csrfHeaderName() {
    return (
      (window.__wi && window.__wi.csrfHeader) ||
      (window.__taskExtras && window.__taskExtras.csrfHeader) ||
      'X-CSRF-TOKEN'
    );
  }

  async function _postJson(url, payload) {
    const csrf = typeof getAppCsrf === 'function' ? getAppCsrf() : window.appCsrf || {};
    if (!csrf.key || csrf.val === undefined || csrf.val === null || csrf.val === '') {
      throw new Error('CSRF tidak tersedia. Muat ulang halaman (F5) lalu coba lagi.');
    }
    const bodyObj = { ...payload, [csrf.key]: csrf.val };
    const headers = {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    };
    headers[_csrfHeaderName()] = csrf.val;
    const res = await fetch(url, {
      method: 'POST',
      headers: headers,
      body: JSON.stringify(bodyObj),
      credentials: 'same-origin',
    });
    let data = null;
    try {
      data = await res.json();
    } catch (e) {}
    if (data?.csrf) _applyCsrfFromResponse(data);
    if (!res.ok) {
      const err = new Error(data?.message || `HTTP ${res.status}`);
      err.payload = data;
      throw err;
    }
    return data;
  }

  function _queueFieldUpdate(taskId, fieldKey, value) {
    const qKey = `${taskId}::${fieldKey}`;
    _fieldPendingValues.set(qKey, value);
    if (_fieldUpdateQueues.get(qKey)) return _fieldUpdateQueues.get(qKey);

    const run = async () => {
      let lastResult = null;
      while (_fieldPendingValues.has(qKey)) {
        const nextValue = _fieldPendingValues.get(qKey);
        _fieldPendingValues.delete(qKey);
        lastResult = await _postJson(`/tasks/${taskId}/field-update`, {
          field_key: fieldKey,
          value: nextValue,
          expected_updated_at: _fieldVersions.get(qKey) || null,
        });
        if (lastResult?.server_updated_at) _fieldVersions.set(qKey, lastResult.server_updated_at);
      }
      _fieldUpdateQueues.delete(qKey);
      return lastResult;
    };

    const promise = run().catch((err) => {
      _fieldUpdateQueues.delete(qKey);
      throw err;
    });
    _fieldUpdateQueues.set(qKey, promise);
    return promise;
  }

  function _ifmEsc(text) {
    const d = document.createElement('div');
    d.textContent = text ?? '';
    return d.innerHTML;
  }

  function _wiFmtDisplayTs(mysql) {
    if (!mysql) return '—';
    const s = String(mysql).replace(' ', 'T');
    const dt = new Date(s);
    if (Number.isNaN(dt.getTime())) return mysql;
    return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function _wiToDatetimeLocal(mysql) {
    if (!mysql) return '';
    return String(mysql).replace(' ', 'T').slice(0, 16);
  }

  function _truncateText(val, max) {
    const str = String(val ?? '');
    if (str.length <= max) return str;
    return str.slice(0, max) + '…';
  }

  function _positionFixedDropdown(anchorEl, dropEl) {
    if (!anchorEl || !dropEl) return;
    const rect = anchorEl.getBoundingClientRect();
    dropEl.style.visibility = 'hidden';
    dropEl.classList.add('open');
    const desiredHeight = Math.min(dropEl.scrollHeight || 160, 240);
    const desiredWidth = Math.max(dropEl.offsetWidth || 150, rect.width);
    const viewportW = window.innerWidth;
    const viewportH = window.innerHeight;
    const gap = 6;
    const spaceBelow = viewportH - rect.bottom - gap;
    const spaceAbove = rect.top - gap;
    const openUp = spaceBelow < Math.min(140, desiredHeight) && spaceAbove > spaceBelow;
    let top = openUp ? rect.top - desiredHeight - gap : rect.bottom + gap;
    let left = rect.left;
    if (left + desiredWidth > viewportW - 8) left = Math.max(8, viewportW - desiredWidth - 8);
    if (top < 8) top = 8;
    if (top + desiredHeight > viewportH - 8) top = Math.max(8, viewportH - desiredHeight - 8);
    const maxHeight = openUp ? Math.max(120, spaceAbove - 8) : Math.max(120, spaceBelow - 8);
    dropEl.style.top = `${Math.round(top)}px`;
    dropEl.style.left = `${Math.round(left)}px`;
    dropEl.style.minWidth = `${Math.round(Math.max(130, rect.width))}px`;
    dropEl.style.maxHeight = `${Math.round(Math.min(260, maxHeight))}px`;
    dropEl.style.visibility = '';
  }

  let _activeBsel = null;
  function closeBselDrop() {
    if (_activeBsel) {
      const drop = _activeBsel.querySelector('.bsel-drop');
      if (drop) drop.classList.remove('open');
      _activeBsel = null;
    }
  }

  document.addEventListener('click', (e) => {
    if (_activeBsel && !_activeBsel.contains(e.target)) closeBselDrop();
  });

  // ── Badge-select (priority) ─────────────────────────────────────
  root.querySelectorAll('.bsel').forEach((bsel) => {
    const isPrioBsel = bsel.classList.contains('wi-prio-bsel');
    const emptyBselLabel = isPrioBsel ? 'None' : '— pilih —';
    const val0 = bsel.dataset.value || '';
    let options = [];
    let palettes = [];
    try {
      options = JSON.parse(bsel.dataset.options || '[]');
    } catch (e) {
      options = [];
    }
    try {
      palettes = JSON.parse(bsel.dataset.palette || '[]');
    } catch (e) {
      palettes = [];
    }
    const drop = bsel.querySelector('.bsel-drop');
    const valEl = bsel.querySelector('.bsel-val');
    if (!drop || !valEl || !options.length) return;

    options.forEach((opt, i) => {
      const p = palettes[i] || { bg: '#f5f5f5', text: '#555' };
      const div = document.createElement('div');
      div.className = 'bsel-opt';
      const optStr = opt === undefined || opt === null ? '' : String(opt);
      div.dataset.val = optStr;
      div.style.cssText = `background:${p.bg};color:${p.text}`;
      const label = optStr.trim() === '' ? 'None' : optStr;
      div.innerHTML = `<span class="bsel-opt-dot"></span>${_ifmEsc(label)}`;
      if (optStr === val0) div.style.outline = `2px solid ${p.text}33`;
      drop.appendChild(div);
    });

    valEl.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = drop.classList.contains('open');
      closeBselDrop();
      if (isOpen) return;
      _positionFixedDropdown(valEl, drop);
      drop.classList.add('open');
      _activeBsel = bsel;
    });

    drop.querySelectorAll('.bsel-opt').forEach((opt, i) => {
      opt.addEventListener('click', async (e) => {
        e.stopPropagation();
        const prevVal = bsel.dataset.value || '';
        const newVal = opt.dataset.val ?? '';
        const p = palettes[i] || { bg: '#f5f5f5', text: '#555' };
        const taskId = bsel.dataset.taskId;
        const fieldKey = bsel.dataset.fieldKey;
        const qKey = `${taskId}::${fieldKey}`;
        if (!_fieldVersions.has(qKey)) _fieldVersions.set(qKey, bsel.dataset.updatedAt || null);
        closeBselDrop();

        valEl.style.background = p.bg;
        valEl.style.color = p.text;
        const dotCls = isPrioBsel ? 'wi-prio-dot' : 'bsel-dot';
        const showDot = Boolean(String(newVal || '').trim());
        const disp = String(newVal || '').trim() ? newVal : emptyBselLabel;
        valEl.innerHTML = `${showDot ? `<span class="${dotCls}" aria-hidden="true"></span>` : ''}${_ifmEsc(disp)}<i class="fa-solid fa-chevron-down bsel-caret" aria-hidden="true"></i>`;
        bsel.dataset.value = newVal;
        drop.querySelectorAll('.bsel-opt').forEach((o) => {
          o.style.outline = '';
        });
        opt.style.outline = `2px solid ${p.text}33`;

        try {
          const data = await _queueFieldUpdate(taskId, fieldKey, newVal);
          if (data?.server_updated_at) bsel.dataset.updatedAt = data.server_updated_at;
          showToast(
            data?.success ? `${fieldKey} disimpan` : (data?.message || 'Gagal simpan'),
            data?.success ? 'success' : 'error'
          );
        } catch (err) {
          if (err?.payload?.conflict) {
            showToast('Konflik penyimpanan. Memuat ulang…', 'error');
            location.reload();
            return;
          }
          const prevIndex = options.findIndex((v) => v === prevVal);
          const prevPalette = palettes[prevIndex] || { bg: 'var(--surface-2)', text: 'var(--text-3)' };
          valEl.style.background = prevVal ? prevPalette.bg : 'var(--surface-2)';
          valEl.style.color = prevVal ? prevPalette.text : 'var(--text-3)';
          const pv = String(prevVal || '').trim();
          const prevDot = pv ? (isPrioBsel ? 'wi-prio-dot' : 'bsel-dot') : '';
          valEl.innerHTML = `${pv ? `<span class="${prevDot}" aria-hidden="true"></span>` : ''}${pv ? _ifmEsc(prevVal) : emptyBselLabel}<i class="fa-solid fa-chevron-down bsel-caret" aria-hidden="true"></i>`;
          bsel.dataset.value = prevVal;
          drop.querySelectorAll('.bsel-opt').forEach((o) => {
            o.style.outline = '';
          });
          if (prevIndex >= 0) drop.querySelectorAll('.bsel-opt')[prevIndex].style.outline = `2px solid ${prevPalette.text}33`;
          showToast(err?.message || err?.payload?.message || 'Gagal simpan', 'error');
        }
      });
    });
  });

  // ── Inline field modal (title / text fields) ────────────────────
  const ifm = document.getElementById('wiInlineFieldModal');
  if (ifm) {
    const ifmClose = document.getElementById('wiIfmClose');
    const ifmCancel = document.getElementById('wiIfmCancel');
    const ifmSave = document.getElementById('wiIfmSave');
    const ifmTitle = document.getElementById('wiIfmTitle');
    const ifmInputWrap = document.getElementById('wiIfmInputWrap');
    let _ifmState = null;

    function _ifmOpen() {
      ifm.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }
    function _ifmCloseFn() {
      ifm.style.display = 'none';
      document.body.style.overflow = '';
      _ifmState = null;
    }

    root.querySelectorAll('.inline-cell-trigger').forEach((btn) => {
      btn.addEventListener('click', () => {
        _ifmState = {
          taskId: btn.dataset.taskId,
          fieldKey: btn.dataset.fieldKey,
          type: btn.dataset.fieldType || 'text',
          label: btn.dataset.fieldLabel || btn.dataset.fieldKey,
          value: btn.dataset.value ?? '',
          updatedAt: btn.dataset.updatedAt || null,
          el: btn,
        };
        ifmTitle.textContent = 'Edit ' + String(_ifmState.label || '');
        const t = _ifmState.type;
        if (t === 'textarea') {
          ifmInputWrap.innerHTML = `<textarea id="wiIfmValue" class="form-control ifm-textarea">${_ifmEsc(_ifmState.value)}</textarea>`;
        } else {
          const inputType = ['date', 'number', 'email'].includes(t) ? t : 'text';
          ifmInputWrap.innerHTML = `<input id="wiIfmValue" type="${inputType}" class="form-control" value="${_ifmEsc(_ifmState.value)}">`;
        }
        _ifmOpen();
        setTimeout(() => document.getElementById('wiIfmValue')?.focus(), 20);
      });
    });

    ifmClose?.addEventListener('click', _ifmCloseFn);
    ifmCancel?.addEventListener('click', _ifmCloseFn);
    ifm.addEventListener('click', (e) => {
      if (e.target === ifm) _ifmCloseFn();
    });

    ifmSave?.addEventListener('click', async () => {
      if (!_ifmState) return;
      const inp = document.getElementById('wiIfmValue');
      if (!inp) return;
      const newVal = inp.value ?? '';
      ifmSave.disabled = true;
      ifmSave.textContent = 'Menyimpan…';
      try {
        const qKey = `${_ifmState.taskId}::${_ifmState.fieldKey}`;
        if (!_fieldVersions.has(qKey)) _fieldVersions.set(qKey, _ifmState.updatedAt || null);
        const data = await _queueFieldUpdate(_ifmState.taskId, _ifmState.fieldKey, newVal);
        if (!data?.success) {
          showToast(data?.message || 'Gagal simpan', 'error');
          return;
        }
        _ifmState.el.dataset.value = newVal;
        if (data?.server_updated_at) {
          _ifmState.el.dataset.updatedAt = data.server_updated_at;
        }
        if (!String(newVal).trim()) {
          _ifmState.el.innerHTML = '<span class="wi-work-untitle">(tanpa judul)</span>';
        } else {
          _ifmState.el.textContent = _truncateText(newVal, 80);
        }
        showToast(`${_ifmState.fieldKey} disimpan`, 'success');
        _ifmCloseFn();
      } catch (e) {
        if (e?.payload?.conflict) {
          showToast('Data berubah. Memuat ulang…', 'error');
          location.reload();
        } else {
          showToast(e?.message || e?.payload?.message || 'Gagal simpan', 'error');
        }
      } finally {
        ifmSave.disabled = false;
        ifmSave.textContent = 'Simpan';
      }
    });
  }

  // ── Status select ───────────────────────────────────────────────
  root.querySelectorAll('.wi-status-select').forEach((sel) => {
    sel.addEventListener('change', async function () {
      const id = this.dataset.taskId;
      const status = this.value;
      const prevClass = this.className;
      this.className = 'wi-state-select status-select wi-status-select badge badge-' + status;
      try {
        const data = await _postJson(`/tasks/${id}/status`, { status });
        showToast(data.success ? 'Status diupdate' : 'Gagal', data.success ? 'success' : 'error');
      } catch (e) {
        this.className = prevClass;
        showToast('Gagal update status', 'error');
      }
    });
  });

  // ── Core popover: deadline + created_at + updated_at ────────────
  const cpop = document.getElementById('wiCorePopover');
  if (cpop) {
    const cpopLbl = document.getElementById('wiCorePopLabel');
    const cpopWrap = document.getElementById('wiCorePopInputWrap');
    const cpopCancel = document.getElementById('wiCorePopCancel');
    const cpopSave = document.getElementById('wiCorePopSave');
    let cpopState = null;

    function closeCpop() {
      cpop.style.display = 'none';
      cpopState = null;
    }

    document.addEventListener('click', (e) => {
      if (cpopState && !cpop.contains(e.target) && !e.target.closest('.wi-deadline-btn') && !e.target.closest('.wi-ts-btn')) {
        closeCpop();
      }
    });

    function openCpop(triggerEl, kind, taskId, currentVal) {
      cpopState = { kind, taskId, triggerEl };
      if (kind === 'deadline') {
        cpopLbl.textContent = 'Deadline';
        const d = currentVal ? String(currentVal).slice(0, 10) : '';
        cpopWrap.innerHTML = `<input type="date" id="wiCpopInput" class="form-control" value="${_ifmEsc(d)}">`;
      } else {
        cpopLbl.textContent = kind === 'created_at' ? 'Created on' : 'Updated on';
        const v = _wiToDatetimeLocal(currentVal);
        cpopWrap.innerHTML = `<input type="datetime-local" id="wiCpopInput" class="form-control" step="60" value="${_ifmEsc(v)}">`;
      }
      const rect = triggerEl.getBoundingClientRect();
      const popW = 280;
      let top = rect.bottom + 6;
      let left = rect.left;
      if (left + popW > window.innerWidth - 12) left = window.innerWidth - popW - 12;
      if (top + 160 > window.innerHeight - 12) top = rect.top - 160 - 6;
      cpop.style.top = `${top}px`;
      cpop.style.left = `${left}px`;
      cpop.style.display = 'block';
      document.getElementById('wiCpopInput')?.focus();
    }

    root.querySelectorAll('.wi-deadline-btn').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        openCpop(btn, 'deadline', btn.dataset.taskId, btn.dataset.deadline || '');
      });
    });

    root.querySelectorAll('.wi-ts-btn').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const k = btn.dataset.wiTs;
        openCpop(btn, k, btn.dataset.taskId, btn.dataset.wiValue || '');
      });
    });

    cpopCancel?.addEventListener('click', closeCpop);

    cpopSave?.addEventListener('click', async () => {
      if (!cpopState) return;
      const inp = document.getElementById('wiCpopInput');
      const raw = inp?.value ?? '';
      const payload = {};
      if (cpopState.kind === 'deadline') {
        payload.deadline = raw;
      } else {
        payload[cpopState.kind] = raw;
      }
      cpopSave.disabled = true;
      cpopSave.textContent = '…';
      try {
        const data = await _postJson(`/tasks/${cpopState.taskId}/core-update`, payload);
        if (!data?.success) {
          showToast('Gagal simpan', 'error');
          return;
        }
        const d = data.data || {};
        const tr = cpopState.triggerEl;
        if (cpopState.kind === 'deadline') {
          const dl = d.deadline !== undefined && d.deadline !== null ? String(d.deadline).slice(0, 10) : (raw || '');
          tr.dataset.deadline = dl || '';
          if (!dl) {
            tr.innerHTML =
              '<span class="dl-empty"><i class="fa-regular fa-calendar icon-xs" aria-hidden="true"></i> —</span>';
            tr.className = 'task-deadline-btn wi-deadline-btn';
          } else {
            tr.innerHTML = `<i class="fa-solid fa-calendar-days icon-xs" aria-hidden="true"></i> ${_ifmEsc(_wiFmtDisplayTs(dl))}`;
            tr.className = 'task-deadline-btn wi-deadline-btn dl-ok';
          }
          showToast('Deadline diperbarui', 'success');
        } else {
          const key = cpopState.kind;
          let v = d[key];
          if (v == null || v === '') {
            const norm = raw.includes('T') ? raw.replace('T', ' ') : raw;
            v = norm.length === 10 ? norm + ' 00:00:00' : norm + (norm.length < 17 ? ':00' : '');
          }
          tr.dataset.wiValue = String(v);
          tr.textContent = _wiFmtDisplayTs(String(v));
          showToast('Tanggal disimpan', 'success');
        }
        closeCpop();
      } catch (e) {
        showToast('Gagal simpan', 'error');
      } finally {
        cpopSave.disabled = false;
        cpopSave.textContent = 'Simpan';
      }
    });
  }

  // ── Assignee PIC dropdown (Team Users directory + TaskExtras) ───
  const apop = document.getElementById('wiAssigneePopover');
  const wiCfg = window.__wi || {};
  const assigneeDirectoryUrl = wiCfg.assigneeDirectoryUrl || '/team/users/directory';
  const csrfHdrName = wiCfg.csrfHeader || 'X-CSRF-TOKEN';

  let _assigneePopState = null;
  let _dirUsersCache = null;
  let _dirUsersPromise = null;

  function _appendCsrfFd(fd) {
    const csrf = typeof getAppCsrf === 'function' ? getAppCsrf() : window.appCsrf || {};
    if (csrf.key && csrf.val) fd.append(csrf.key, csrf.val);
    return fd;
  }

  async function _postFormAssignee(url, fd) {
    const csrf = typeof getAppCsrf === 'function' ? getAppCsrf() : window.appCsrf || {};
    const h = {
      'X-Requested-With': 'XMLHttpRequest',
      Accept: 'application/json',
    };
    if (csrf.val) h[csrfHdrName] = csrf.val;
    const res = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: h });
    let data = {};
    try {
      data = await res.json();
    } catch (e) {}
    _applyCsrfFromResponse(data);
    return { ok: res.ok, data };
  }

  function _wiAvatarSrc(avatar, name) {
    const n = String(name || '?').trim() || '?';
    const av = avatar != null && String(avatar).trim() !== '' ? String(avatar).trim() : '';
    if (av) return '/uploads/avatars/' + encodeURIComponent(av);
    const initial = n.charAt(0).toUpperCase();
    return (
      'https://ui-avatars.com/api/?name=' +
      encodeURIComponent(initial) +
      '&background=4f46e5&color=fff&size=80&bold=true'
    );
  }

  function _normalizeAssignee(a) {
    const uid = parseInt(a.user_id ?? a.id, 10);
    if (!uid) return null;
    return {
      user_id: uid,
      nickname: String(a.nickname || ''),
      username: String(a.username || ''),
      avatar: a.avatar ?? null,
    };
  }

  function _parseAssignees(btn) {
    try {
      const raw = JSON.parse(btn.dataset.assignees || '[]');
      return (Array.isArray(raw) ? raw : []).map(_normalizeAssignee).filter(Boolean);
    } catch (e) {
      return [];
    }
  }

  function _wiRenderAssigneeButtonInner(btn, rawList) {
    const assignees = (Array.isArray(rawList) ? rawList : []).map(_normalizeAssignee).filter(Boolean);
    btn.dataset.assignees = JSON.stringify(assignees);
    if (assignees.length === 0) {
      btn.innerHTML =
        '<div class="wi-pic-empty" title="Belum ada PIC"><span class="wi-pic-empty-avatar"><i class="fa-regular fa-user" aria-hidden="true"></i></span><span class="wi-pic-empty-txt">PIC</span></div>';
      return;
    }
    if (assignees.length === 1) {
      const a = assignees[0];
      const nick = a.nickname || a.username || '?';
      const src = _wiAvatarSrc(a.avatar, nick);
      btn.innerHTML = `<span class="wi-pic-chip-link"><span class="pic-chip wi-pic-chip"><img src="${_ifmEsc(src)}" alt="" class="wi-pic-chip-img" width="22" height="22" loading="lazy" /><span class="pic-chip-text">${_ifmEsc(nick)}</span></span></span>`;
      return;
    }
    const show = assignees.slice(0, 5);
    const moreN = assignees.length - show.length;
    const title = assignees
      .map((x) => x.nickname || x.username)
      .filter(Boolean)
      .join(', ');
    let bubbles = '';
    show.forEach((x) => {
      const nick = x.nickname || x.username || '?';
      bubbles += `<span class="wi-pic-bubble"><img src="${_ifmEsc(_wiAvatarSrc(x.avatar, nick))}" alt="${_ifmEsc(nick)}" width="28" height="28" loading="lazy" /></span>`;
    });
    if (moreN > 0) bubbles += `<span class="wi-pic-more" title="${moreN} PIC lainnya">+${moreN}</span>`;
    btn.innerHTML = `<div class="wi-pic-multi" title="${_ifmEsc(title)}"><div class="wi-pic-stack" role="group" aria-label="PIC">${bubbles}</div><span class="wi-pic-multi-hint">${assignees.length} PIC</span></div>`;
  }

  function closeAssigneePop() {
    if (!apop) return;
    apop.style.display = 'none';
    apop.classList.remove('open');
    if (_assigneePopState?.trigger) _assigneePopState.trigger.setAttribute('aria-expanded', 'false');
    _assigneePopState = null;
  }

  function positionAssigneePop(anchor) {
    if (!apop || !anchor) return;
    const rect = anchor.getBoundingClientRect();
    const popW = 288;
    let top = rect.bottom + 6;
    let left = Math.min(rect.left, window.innerWidth - popW - 12);
    if (left < 8) left = 8;
    apop.style.width = `${popW}px`;
    apop.style.top = `${Math.round(top)}px`;
    apop.style.left = `${Math.round(left)}px`;
    const maxH = window.innerHeight - top - 16;
    const listEl = apop.querySelector('.wi-assignee-list');
    if (listEl) listEl.style.maxHeight = `${Math.max(120, Math.min(260, maxH - 90))}px`;
  }

  async function ensureDirectoryUsers() {
    if (_dirUsersCache) return _dirUsersCache;
    if (_dirUsersPromise) return _dirUsersPromise;
    _dirUsersPromise = fetch(assigneeDirectoryUrl, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then((r) => r.json())
      .then((d) => {
        _applyCsrfFromResponse(d);
        _dirUsersCache = Array.isArray(d.users) ? d.users : [];
        _dirUsersPromise = null;
        return _dirUsersCache;
      })
      .catch(() => {
        _dirUsersPromise = null;
        return [];
      });
    return _dirUsersPromise;
  }

  function renderAssigneeList(users, assignedIds) {
    const listEl = apop.querySelector('.wi-assignee-list');
    if (!listEl) return;
    const set = new Set(assignedIds);
    listEl.innerHTML = users
      .map((u) => {
        const id = parseInt(u.id, 10);
        if (!id) return '';
        const label = String(u.nickname || u.username || '#' + id);
        const checked = set.has(id) ? ' checked' : '';
        const src = _wiAvatarSrc(u.avatar, label);
        return `<label class="wi-assignee-row" data-user-id="${id}"><input type="checkbox"${checked} /><img class="wi-assignee-row-av" src="${_ifmEsc(src)}" width="24" height="24" alt="" loading="lazy" /><span class="wi-assignee-row-txt">${_ifmEsc(label)}</span></label>`;
      })
      .join('');
  }

  function filterAssigneeRows(q) {
    if (!apop) return;
    const s = String(q).trim().toLowerCase();
    apop.querySelectorAll('.wi-assignee-row').forEach((row) => {
      const t = row.textContent.toLowerCase();
      row.style.display = !s || t.includes(s) ? '' : 'none';
    });
  }

  if (apop) {
    const filterInp = apop.querySelector('.wi-assignee-filter');
    filterInp?.addEventListener('input', () => filterAssigneeRows(filterInp.value));

    apop.addEventListener('change', async (e) => {
      const inp = e.target;
      if (inp.type !== 'checkbox' || !_assigneePopState) return;
      const row = inp.closest('.wi-assignee-row');
      const userId = parseInt(row?.dataset.userId, 10);
      if (!userId) return;
      const taskId = _assigneePopState.taskId;
      const users = _dirUsersCache || [];
      const userObj = users.find((u) => parseInt(u.id, 10) === userId) || {};
      inp.disabled = true;
      try {
        if (inp.checked) {
          const fd = _appendCsrfFd(new FormData());
          fd.append('user_id', String(userId));
          const { data } = await _postFormAssignee(`/tasks/${taskId}/assignees`, fd);
          if (data.success) {
            if (!_assigneePopState.assignees.some((a) => a.user_id === userId)) {
              _assigneePopState.assignees.push({
                user_id: userId,
                nickname: String(userObj.nickname || ''),
                username: String(userObj.username || ''),
                avatar: userObj.avatar ?? null,
              });
            }
            _wiRenderAssigneeButtonInner(_assigneePopState.trigger, _assigneePopState.assignees);
            showToast('PIC ditambahkan', 'success');
          } else {
            inp.checked = false;
            showToast(data.message || data.error || 'Gagal menambah PIC', 'error');
          }
        } else {
          const fd = _appendCsrfFd(new FormData());
          const { data } = await _postFormAssignee(`/tasks/${taskId}/assignees/${userId}/remove`, fd);
          if (data.success) {
            _assigneePopState.assignees = _assigneePopState.assignees.filter((a) => a.user_id !== userId);
            _wiRenderAssigneeButtonInner(_assigneePopState.trigger, _assigneePopState.assignees);
            showToast('PIC dihapus', 'success');
          } else {
            inp.checked = true;
            showToast(data.message || data.error || 'Gagal menghapus PIC', 'error');
          }
        }
      } catch (err) {
        inp.checked = !inp.checked;
        showToast('Gagal menyimpan PIC', 'error');
      } finally {
        inp.disabled = false;
      }
    });

    document.addEventListener('click', (e) => {
      if (!apop.classList.contains('open')) return;
      if (!apop.contains(e.target) && !e.target.closest('.wi-assignee-trigger')) {
        closeAssigneePop();
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && apop.classList.contains('open')) closeAssigneePop();
    });

    root.querySelectorAll('.wi-assignee-trigger').forEach((btn) => {
      btn.addEventListener('click', async (ev) => {
        ev.stopPropagation();
        closeBselDrop();
        const cpopEl = document.getElementById('wiCorePopover');
        if (cpopEl) cpopEl.style.display = 'none';
        const isToggleClose = _assigneePopState?.trigger === btn;
        if (isToggleClose) {
          closeAssigneePop();
          return;
        }
        closeAssigneePop();
        const taskId = parseInt(btn.dataset.taskId, 10);
        if (!taskId) return;
        _assigneePopState = { trigger: btn, taskId, assignees: _parseAssignees(btn) };
        btn.setAttribute('aria-expanded', 'true');
        const users = await ensureDirectoryUsers();
        if (!users.length) {
          showToast('Tidak bisa memuat daftar user.', 'error');
          closeAssigneePop();
          return;
        }
        const assignedIds = _assigneePopState.assignees.map((a) => a.user_id);
        renderAssigneeList(users, assignedIds);
        apop.style.display = 'block';
        apop.classList.add('open');
        positionAssigneePop(btn);
        if (filterInp) {
          filterInp.value = '';
          filterAssigneeRows('');
          filterInp.focus();
        }
      });
    });
  }

  // Seed field versions from DOM
  root.querySelectorAll('.bsel').forEach((bsel) => {
    const q = `${bsel.dataset.taskId}::${bsel.dataset.fieldKey}`;
    _fieldVersions.set(q, bsel.dataset.updatedAt || null);
  });
  root.querySelectorAll('.inline-cell-trigger').forEach((btn) => {
    const q = `${btn.dataset.taskId}::${btn.dataset.fieldKey}`;
    _fieldVersions.set(q, btn.dataset.updatedAt || null);
  });
})();
