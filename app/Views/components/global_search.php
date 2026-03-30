<div id="searchOverlay" onclick="closeGlobalSearch(event)">
    <div id="searchBox" onclick="event.stopPropagation()">
        <input
            type="text"
            id="searchInput"
            placeholder="Judul / isi field task, ID angka, klien, project, komentar…"
            autocomplete="off"
            oninput="onGlobalSearchInput(this.value)"
            onkeydown="onGlobalSearchKey(event)"
        >
        <div id="searchResults">
            <div id="searchEmpty" style="display:none;"></div>
        </div>
        <div id="searchFooter">
            <span class="sr-hint"><kbd>↑</kbd><kbd>↓</kbd> navigasi</span>
            <span class="sr-hint"><kbd>Enter</kbd> buka</span>
            <span class="sr-hint"><kbd>Esc</kbd> tutup</span>
        </div>
    </div>
</div>

<script>
(function () {
  const BASE_URL = <?= json_encode(rtrim(base_url(), '/') . '/') ?>;
  let searchTimer = null;
  let selectedIdx = -1;
  let searchItems = [];
  let searchAbort = null;

  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
      e.preventDefault();
      openGlobalSearch();
    }
    if (e.key === 'Escape') closeGlobalSearch(null);
  });

  window.openGlobalSearch = function () {
    if (searchAbort) {
      searchAbort.abort();
      searchAbort = null;
    }
    var el = document.getElementById('searchOverlay');
    var inp = document.getElementById('searchInput');
    if (!el || !inp) return;
    el.classList.add('open');
    inp.value = '';
    inp.focus();
    var res = document.getElementById('searchResults');
    if (res) res.innerHTML = '<div id="searchEmpty" style="display:none;"></div>';
    selectedIdx = -1;
    searchItems = [];
  };

  window.closeGlobalSearch = function (e) {
    if (e && e.type === 'click' && e.target !== document.getElementById('searchOverlay')) return;
    var el = document.getElementById('searchOverlay');
    if (el) el.classList.remove('open');
  };

  window.onGlobalSearchInput = function (q) {
    clearTimeout(searchTimer);
    if (q.length < 2) {
      var res = document.getElementById('searchResults');
      if (res) res.innerHTML = '<div id="searchEmpty" style="display:none;"></div>';
      return;
    }
    searchTimer = setTimeout(function () { doGlobalSearch(q); }, 280);
  };

  async function doGlobalSearch(q) {
    if (searchAbort) {
      searchAbort.abort();
    }
    searchAbort = new AbortController();
    var signal = searchAbort.signal;
    try {
      var r = await fetch(BASE_URL + 'search?q=' + encodeURIComponent(q), {
        method: 'GET',
        credentials: 'same-origin',
        signal: signal,
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
      });
      if (!r.ok) {
        var container = document.getElementById('searchResults');
        if (container) {
          container.innerHTML = '<div style="padding:1.5rem;font-size:.875rem;color:var(--danger,#b91c1c);">Pencarian gagal (' + r.status + '). Muat ulang halaman jika baru login atau sesi habis.</div>';
        }
        return;
      }
      var d = {};
      try {
        d = await r.json();
      } catch (e) {
        return;
      }
      renderGlobalResults(d.data || []);
    } catch (e) {
      if (e && e.name === 'AbortError') return;
      throw e;
    }
  }

  function escHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function renderGlobalResults(items) {
    var container = document.getElementById('searchResults');
    if (!container) return;
    searchItems = items;
    selectedIdx = -1;

    if (!items.length) {
      container.innerHTML = '<div id="searchEmpty" style="display:block; padding:2rem 1rem; text-align:center; font-size:.875rem; color:var(--text-muted,#9ca3af);">Tidak ada hasil.</div>';
      return;
    }

    var icons = { task: '📋', client: '🏢', project: '📁', comment: '💬' };
    var labels = { task: 'Task', client: 'Klien', project: 'Project', comment: 'Komentar' };

    var html = '';
    var lastType = null;

    items.forEach(function (item, i) {
      if (item.type !== lastType) {
        html += '<div class="sr-group">' + (labels[item.type] || item.type) + '</div>';
        lastType = item.type;
      }
      html += '<a class="sr-item" href="' + escHtml(item.url) + '" data-idx="' + i + '">' +
        '<div class="sr-icon">' + (icons[item.type] || '•') + '</div>' +
        '<span class="sr-label">' + escHtml(item.label) + '</span>' +
        '<span class="sr-meta">' + escHtml(item.meta || '') + '</span></a>';
    });

    container.innerHTML = html;
  }

  window.onGlobalSearchKey = function (e) {
    var items = document.querySelectorAll('.sr-item');
    if (!items.length) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      selectedIdx = Math.min(selectedIdx + 1, items.length - 1);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      selectedIdx = Math.max(selectedIdx - 1, 0);
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (selectedIdx >= 0 && items[selectedIdx]) {
        window.location.href = items[selectedIdx].getAttribute('href');
      }
      return;
    } else {
      return;
    }

    items.forEach(function (el, i) {
      el.classList.toggle('selected', i === selectedIdx);
    });
    if (items[selectedIdx]) items[selectedIdx].scrollIntoView({ block: 'nearest' });
  };
})();
</script>
