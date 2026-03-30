/**
 * Inline Editor.js for task fields: richtext (JSON), textarea (plain multiline), text (plain single line).
 * Depends on global EditorJS + tools from layouts/main.php and window.__taskExtras (optional baseUrl).
 */
(function (global) {
  const editors = new Map();

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
        config: { levels: [1, 2, 3], defaultLevel: 2 },
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

  function plainTextToEditorDataSingleLine(text) {
    const t = String(text || '');
    if (!t.trim()) {
      return undefined;
    }
    return {
      blocks: [{ type: 'paragraph', data: { text: t } }],
    };
  }

  function plainTextToEditorDataMultiline(text) {
    const s = String(text || '');
    if (!s.trim()) {
      return undefined;
    }
    const parts = s.split(/\n+/);
    const blocks = [];
    parts.forEach(function (line) {
      if (line.length) {
        blocks.push({ type: 'paragraph', data: { text: line } });
      }
    });
    if (blocks.length === 0) {
      return undefined;
    }
    return { blocks: blocks };
  }

  function editorJsToPlainText(output) {
    const blocks = output && output.blocks ? output.blocks : [];
    const lines = [];
    blocks.forEach(function (b) {
      if (!b || !b.type) {
        return;
      }
      const d = b.data || {};
      if (b.type === 'paragraph' && d.text) {
        lines.push(d.text);
      } else if (b.type === 'header' && d.text) {
        lines.push(d.text);
      } else if (b.type === 'quote' && d.text) {
        lines.push(d.text);
      } else if (b.type === 'list' && d.items) {
        (d.items || []).forEach(function (item) {
          if (typeof item === 'string') {
            lines.push(item);
          } else if (item && item.content) {
            lines.push(item.content);
          }
        });
      }
    });
    return lines.join('\n\n');
  }

  function editorJsToSingleLineText(output) {
    return editorJsToPlainText(output).replace(/\s+/g, ' ').trim();
  }

  /**
   * Parse JSON from script tag; branch by storageFormat (editorjs | multiline | singleline).
   */
  function parseInitialScript(initialEl, storageFormat) {
    if (!initialEl) {
      return undefined;
    }
    let raw;
    try {
      raw = JSON.parse(initialEl.textContent);
    } catch (e) {
      return undefined;
    }
    if (storageFormat === 'editorjs') {
      if (typeof raw === 'string') {
        if (!raw.trim()) {
          return undefined;
        }
        try {
          return JSON.parse(raw);
        } catch (e2) {
          return undefined;
        }
      }
      if (raw && typeof raw === 'object' && raw.blocks) {
        return raw;
      }
      return undefined;
    }
    var s = typeof raw === 'string' ? raw : '';
    if (storageFormat === 'multiline') {
      return plainTextToEditorDataMultiline(s);
    }
    return plainTextToEditorDataSingleLine(s);
  }

  function serializeForSave(output, storageFormat) {
    if (storageFormat === 'editorjs') {
      return JSON.stringify(output);
    }
    if (storageFormat === 'multiline') {
      return editorJsToPlainText(output);
    }
    return editorJsToSingleLineText(output);
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

  function destroyIn(container) {
    if (!container || !container.querySelectorAll) {
      return;
    }
    container.querySelectorAll('.task-show-richtext-editor-wrap[data-editor-holder-id]').forEach(function (wrap) {
      const hid = wrap.getAttribute('data-editor-holder-id');
      if (hid && editors.has(hid)) {
        const ed = editors.get(hid);
        editors.delete(hid);
        if (ed && typeof ed.destroy === 'function') {
          ed.destroy().catch(function () {});
        }
      }
    });
  }

  function placeholderForFormat(storageFormat) {
    if (storageFormat === 'singleline') {
      return 'Ketik nilai field…';
    }
    if (storageFormat === 'multiline') {
      return 'Ketik di sini… Blok + di kiri untuk paragraf baru.';
    }
    return 'Ketik di sini… + di kiri untuk blok; geser urutan lewat grip.';
  }

  function initIn(container) {
    if (!container || !container.querySelectorAll) {
      return;
    }
    if (!global.EditorJS) {
      console.warn('[task-show-richtext] EditorJS belum tersedia (tunggu CDN atau refresh).');
      return;
    }
    destroyIn(container);
    const tools = toolConfig();
    if (!tools.paragraph && !tools.header) {
      console.warn('[task-show-richtext] Tool Paragraph/Header tidak dimuat.');
      return;
    }

    container.querySelectorAll('.task-show-richtext-editor-wrap[data-can-mutate="1"]').forEach(function (wrap) {
      const holder = wrap.querySelector('.task-show-richtext-holder');
      const taskId = wrap.getAttribute('data-task-id');
      const fieldKey = wrap.getAttribute('data-field-key');
      const initialEl = wrap.querySelector('.task-show-richtext-initial');
      const saveBtn = wrap.querySelector('.task-show-richtext-save');
      const storageFormat = wrap.getAttribute('data-storage-format') || 'editorjs';
      if (!holder || !taskId || !fieldKey || !holder.id) {
        return;
      }

      const data = parseInitialScript(initialEl, storageFormat);
      const holderId = holder.id;

      let editor;
      try {
        editor = new global.EditorJS({
          holder: holderId,
          readOnly: false,
          autofocus: false,
          data: data,
          defaultBlock: tools.paragraph ? 'paragraph' : undefined,
          tools: tools,
          placeholder: placeholderForFormat(storageFormat),
        });
      } catch (e) {
        console.error('EditorJS init failed', e);
        holder.innerHTML = '<p class="task-show-richtext-error">Editor tidak bisa dimuat. Muat ulang halaman.</p>';
        return;
      }
      editors.set(holderId, editor);

      if (saveBtn) {
        saveBtn.addEventListener('click', async function () {
          const ed = editors.get(holderId);
          if (!ed) {
            return;
          }
          saveBtn.disabled = true;
          try {
            const output = await ed.save();
            const value = serializeForSave(output, storageFormat);
            const c = getCsrf();
            if (!c.key || c.val === undefined || c.val === null || c.val === '') {
              if (typeof global.showToast === 'function') {
                global.showToast('Sesi keamanan tidak valid. Muat ulang halaman (F5).', 'error');
              } else {
                alert('Sesi keamanan tidak valid. Muat ulang halaman (F5).');
              }
              return;
            }
            const headers = {
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
            };
            headers[csrfHeaderName()] = c.val;
            const body = {
              field_key: fieldKey,
              value: value,
              expected_updated_at: wrap.getAttribute('data-expected-updated-at') || null,
              [c.key]: c.val,
            };
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
            if (res.status === 409) {
              if (typeof global.showToast === 'function') {
                global.showToast(d.message || 'Data sudah diubah. Muat ulang panel.', 'error');
              } else {
                alert(d.message || 'Konflik versi.');
              }
              return;
            }
            if (!d.success) {
              if (typeof global.showToast === 'function') {
                global.showToast(d.message || 'Gagal menyimpan', 'error');
              } else {
                alert(d.message || 'Gagal menyimpan');
              }
              return;
            }
            if (d.server_updated_at) {
              wrap.setAttribute('data-expected-updated-at', d.server_updated_at);
            }
            if (initialEl) {
              initialEl.textContent = JSON.stringify(value);
            }
            if (typeof global.showToast === 'function') {
              global.showToast('Tersimpan', 'success');
            }
          } catch (err) {
            if (typeof global.showToast === 'function') {
              global.showToast('Gagal menyimpan', 'error');
            } else {
              alert('Gagal menyimpan');
            }
          } finally {
            saveBtn.disabled = false;
          }
        });
      }
    });
  }

  global.destroyTaskShowRichtextEditors = function (root) {
    const el = root && root.querySelectorAll ? root : document;
    destroyIn(el);
  };

  global.initTaskShowRichtextEditors = function (root) {
    const el = root && root.querySelectorAll ? root : document;
    initIn(el);
  };
})(window);
