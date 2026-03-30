/**
 * Satu Editor.js = satu dokumen deskripsi work item (bukan satu heading per field).
 * Field utama disimpan ke satu field_key (primary); field teks pendukung lewat input sekunder.
 */
(function (global) {
  let editorInstance = null;
  let editorHolderId = null;

  function toolConfig() {
    const tools = {};
    const ParagraphTool = global.Paragraph || global.EditorjsParagraph;
    const HeaderTool = global.Header || global.EditorjsHeader;
    const ListTool = global.List || global.EditorjsList;
    const QuoteTool = global.Quote || global.EditorjsQuote;
    if (ParagraphTool) {
      tools.paragraph = { class: ParagraphTool, inlineToolbar: true };
    }
    if (HeaderTool) {
      tools.header = {
        class: HeaderTool,
        inlineToolbar: true,
        config: { levels: [1, 2, 3, 4, 5, 6], defaultLevel: 2 },
      };
    }
    if (ListTool) {
      tools.list = { class: ListTool, inlineToolbar: true };
    }
    if (QuoteTool) {
      tools.quote = { class: QuoteTool, inlineToolbar: true };
    }
    return tools;
  }

  function fieldUpdateUrl(taskId) {
    const cfg = global.__taskExtras || {};
    const b = cfg.baseUrl || '';
    const base = b ? (b.endsWith('/') ? b : b + '/') : (global.location && global.location.origin ? global.location.origin + '/' : '/');
    return base + 'tasks/' + taskId + '/field-update';
  }

  function csrfHeaderName() {
    return (global.__taskExtras && global.__taskExtras.csrfHeader) || 'X-CSRF-TOKEN';
  }

  function getCsrf() {
    if (typeof global.getAppCsrf === 'function') {
      return global.getAppCsrf();
    }
    return global.appCsrf || { key: '', val: '' };
  }

  function applyCsrfFromResponse(d) {
    if (d && d.csrf && global.appCsrf) {
      global.appCsrf.val = d.csrf;
    }
    if (d && d.csrf && typeof global.updateAppCsrf === 'function') {
      global.updateAppCsrf(d.csrf);
    }
  }

  function extractTextFromBlock(b) {
    if (!b || !b.data) {
      return '';
    }
    if (b.type === 'paragraph' || b.type === 'header') {
      return String(b.data.text || '').trim();
    }
    if (b.type === 'quote') {
      return String(b.data.text || '').trim();
    }
    if (b.type === 'list' && b.data.items) {
      return (b.data.items || [])
        .map(function (item) {
          if (typeof item === 'string') {
            return item;
          }
          return item && item.content ? item.content : '';
        })
        .join('\n');
    }
    return '';
  }

  /** Muat satu field utama ke data Editor.js (tanpa blok judul per field). */
  function buildEditorDataFromPrimary(primary) {
    if (!primary) {
      return { time: Date.now(), blocks: [{ type: 'paragraph', data: { text: '' } }] };
    }
    const val = primary.value || '';
    if (primary.type === 'richtext' && String(val).trim().charAt(0) === '{') {
      try {
        const doc = JSON.parse(val);
        if (doc && Array.isArray(doc.blocks) && doc.blocks.length > 0) {
          return { time: doc.time || Date.now(), blocks: doc.blocks };
        }
      } catch (e) {}
    }
    return {
      time: Date.now(),
      blocks: [{ type: 'paragraph', data: { text: val } }],
    };
  }

  /** Simpan output Editor.js ke string nilai field utama. */
  function primaryOutputToValue(output, primary) {
    const blocks = (output && output.blocks) ? output.blocks : [];
    const t = primary.type || 'text';
    if (t === 'richtext') {
      return JSON.stringify({ time: Date.now(), blocks: blocks });
    }
    if (t === 'textarea') {
      return blocks
        .map(extractTextFromBlock)
        .filter(function (x) {
          return x !== '';
        })
        .join('\n\n');
    }
    return blocks
      .map(extractTextFromBlock)
      .join(' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function collectSecondaryUpdates(wrap) {
    const out = [];
    const nodes = wrap.querySelectorAll('[data-project-task-secondary-input]');
    nodes.forEach(function (el) {
      const key = el.getAttribute('data-field-key');
      if (!key) {
        return;
      }
      const expected = el.getAttribute('data-expected-updated-at');
      let value = '';
      if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
        value = el.value;
      }
      out.push({
        field_key: key,
        value: value,
        expected_updated_at: expected === '' || expected === null ? null : expected,
      });
    });
    return out;
  }

  function applyUpdatedAtToSecondary(wrap, fieldKey, serverUpdatedAt) {
    if (!serverUpdatedAt) {
      return;
    }
    wrap.querySelectorAll('[data-project-task-secondary-input]').forEach(function (el) {
      if (el.getAttribute('data-field-key') === fieldKey) {
        el.setAttribute('data-expected-updated-at', serverUpdatedAt);
      }
    });
  }

  function parseConfig(root) {
    const el = root.querySelector('.project-task-fields-unified-config');
    if (!el) {
      return null;
    }
    try {
      return JSON.parse(el.textContent);
    } catch (e) {
      return null;
    }
  }

  function destroy() {
    if (editorInstance && editorHolderId) {
      const ed = editorInstance;
      editorInstance = null;
      editorHolderId = null;
      if (typeof ed.destroy === 'function') {
        ed.destroy().catch(function () {});
      }
    }
  }

  async function postFieldUpdate(taskId, body) {
    const c = getCsrf();
    if (!c.key || c.val === undefined || c.val === null || c.val === '') {
      throw new Error('CSRF tidak tersedia. Muat ulang halaman (F5).');
    }
    body[c.key] = c.val;
    const headers = {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    };
    headers[csrfHeaderName()] = c.val;
    const res = await fetch(fieldUpdateUrl(taskId), {
      method: 'POST',
      headers: headers,
      body: JSON.stringify(body),
      credentials: 'same-origin',
    });
    const d = await res.json().catch(function () {
      return {};
    });
    applyCsrfFromResponse(d);
    return { res: res, d: d };
  }

  function initIn(root) {
    destroy();
    const wrap = root && root.querySelector ? root.querySelector('.project-task-fields-unified-root') : null;
    if (!wrap || wrap.getAttribute('data-can-mutate') !== '1') {
      return;
    }
    const cfg = parseConfig(wrap);
    if (!cfg || !global.EditorJS) {
      return;
    }
    const holder = wrap.querySelector('.project-task-fields-unified-holder');
    const saveBtn = wrap.querySelector('.project-task-fields-unified-save');
    if (!holder || !holder.id) {
      return;
    }
    editorHolderId = holder.id;
    const tools = toolConfig();
    if (!tools.paragraph) {
      return;
    }

    const primary = cfg.primary || null;
    const hasSaveTarget = primary !== null;

    const data = buildEditorDataFromPrimary(primary);
    try {
      editorInstance = new global.EditorJS({
        holder: holder.id,
        readOnly: false,
        autofocus: false,
        data: data,
        defaultBlock: tools.paragraph ? 'paragraph' : undefined,
        tools: tools,
        placeholder: 'Tulis deskripsi work item…',
      });
    } catch (e) {
      console.error('Deskripsi editor init failed', e);
      holder.innerHTML = '<p class="task-show-richtext-error">Editor gagal dimuat.</p>';
      return;
    }

    if (saveBtn && hasSaveTarget) {
      saveBtn.addEventListener('click', async function () {
        if (!editorInstance) {
          return;
        }
        saveBtn.disabled = true;
        try {
          const c0 = getCsrf();
          if (!c0.key || c0.val === undefined || c0.val === null || c0.val === '') {
            if (typeof global.showToast === 'function') {
              global.showToast('Sesi keamanan tidak valid. Muat ulang halaman (F5).', 'error');
            } else {
              alert('Sesi keamanan tidak valid. Muat ulang halaman (F5).');
            }
            return;
          }
          const output = await editorInstance.save();
          const primaryVal = primaryOutputToValue(output, primary);
          const c = getCsrf();
          const headers = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          };
          headers[csrfHeaderName()] = c.val;

          const upPrimary = {
            field_key: primary.key,
            value: primaryVal,
            expected_updated_at: primary.updated_at || null,
            [c.key]: c.val,
          };
          let res = await fetch(fieldUpdateUrl(cfg.taskId), {
            method: 'POST',
            headers: headers,
            body: JSON.stringify(upPrimary),
            credentials: 'same-origin',
          });
          let d = await res.json().catch(function () {
            return {};
          });
          applyCsrfFromResponse(d);
          if (res.status === 409) {
            if (typeof global.showToast === 'function') {
              global.showToast(d.message || 'Konflik versi deskripsi. Muat ulang panel.', 'error');
            } else {
              alert(d.message || 'Konflik versi.');
            }
            return;
          }
          if (!d.success) {
            if (typeof global.showToast === 'function') {
              global.showToast(d.message || 'Gagal menyimpan deskripsi', 'error');
            } else {
              alert(d.message || 'Gagal menyimpan');
            }
            return;
          }
          if (d.server_updated_at) {
            primary.updated_at = d.server_updated_at;
          }
          const cfgEl = wrap.querySelector('.project-task-fields-unified-config');
          if (cfgEl) {
            cfgEl.textContent = JSON.stringify(cfg);
          }

          const secondaries = collectSecondaryUpdates(wrap);
          for (let s = 0; s < secondaries.length; s++) {
            const su = secondaries[s];
            const pr = await postFieldUpdate(cfg.taskId, {
              field_key: su.field_key,
              value: su.value,
              expected_updated_at: su.expected_updated_at,
            });
            if (pr.res.status === 409) {
              if (typeof global.showToast === 'function') {
                global.showToast(pr.d.message || 'Konflik pada field ' + su.field_key, 'error');
              } else {
                alert(pr.d.message || 'Konflik versi.');
              }
              return;
            }
            if (!pr.d.success) {
              if (typeof global.showToast === 'function') {
                global.showToast(pr.d.message || 'Gagal menyimpan ' + su.field_key, 'error');
              } else {
                alert(pr.d.message || 'Gagal menyimpan');
              }
              return;
            }
            if (pr.d.server_updated_at) {
              applyUpdatedAtToSecondary(wrap, su.field_key, pr.d.server_updated_at);
            }
          }

          if (typeof global.showToast === 'function') {
            global.showToast('Perubahan disimpan', 'success');
          }
        } catch (err) {
          const msg = err && err.message ? err.message : 'Gagal menyimpan';
          if (typeof global.showToast === 'function') {
            global.showToast(msg, 'error');
          } else {
            alert(msg);
          }
        } finally {
          saveBtn.disabled = false;
        }
      });
    }
  }

  global.destroyProjectTaskFieldsUnified = function () {
    destroy();
  };

  global.initProjectTaskFieldsUnified = function (root) {
    const el = root && root.querySelector ? root : document;
    initIn(el);
  };
})(window);
