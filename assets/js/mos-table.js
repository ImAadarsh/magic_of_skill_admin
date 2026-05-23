/**
 * MOS Admin — Modern Table & Filter Enhancement JS
 * mos-table.js
 */
(function () {
  'use strict';

  /* ── Constants ── */
  const SEARCH_DEBOUNCE_MS = 600;
  const FILTER_LABELS = {
    search: 'Search',
    user_type: 'User Type',
    grade: 'Grade',
    school: 'School',
    city: 'City',
    joined: 'Joined',
    status: 'Status',
    hide_incomplete: 'Hide Incomplete',
    category: 'Category',
    trainer: 'Trainer',
    level: 'Level',
    min_price: 'Min Price',
    max_price: 'Max Price',
    start_date: 'From',
    end_date: 'To',
    payment_status: 'Payment Status',
    workshop_id: 'Workshop',
    workshop_type: 'Workshop Type',
    min_amount: 'Min ₹',
    max_amount: 'Max ₹',
    created: 'Created',
    quiz_id: 'Quiz',
    rating: 'Rating',
  };
  const IGNORED_PARAMS = ['page', 'per_page'];

  /* ────────────────────────────────────────────────────
   * 1. Filter Toggle (mobile)
   * ──────────────────────────────────────────────────── */
  function initFilterToggle() {
    const toggleBtns = document.querySelectorAll('.mos-filter-toggle');
    toggleBtns.forEach(btn => {
      const targetId = btn.dataset.target;
      const body = document.getElementById(targetId);
      if (!body) return;
      btn.addEventListener('click', () => {
        const isOpen = body.classList.toggle('mos-filter-open');
        btn.setAttribute('aria-expanded', isOpen);
        btn.querySelector('.toggle-label').textContent = isOpen ? 'Hide Filters' : 'Filters';
      });
    });
  }

  /* ────────────────────────────────────────────────────
   * 2. Active Filter Badges
   * ──────────────────────────────────────────────────── */
  function initActiveFilterBadges() {
    const containers = document.querySelectorAll('.mos-active-filters');
    containers.forEach(container => {
      const params = new URLSearchParams(window.location.search);
      let count = 0;
      const pills = [];

      params.forEach((value, key) => {
        if (IGNORED_PARAMS.includes(key) || !value) return;
        const label = FILTER_LABELS[key] || key;
        count++;
        pills.push({ key, value, label });
      });

      // Update toggle badge count
      const toggleBtn = document.querySelector('.mos-filter-toggle');
      if (toggleBtn) {
        const badge = toggleBtn.querySelector('.filter-count-badge');
        if (count > 0) {
          toggleBtn.classList.add('has-filters');
          if (badge) badge.textContent = count;
        }
      }

      if (count === 0) {
        container.style.display = 'none';
        return;
      }

      // Build pills
      const labelEl = document.createElement('span');
      labelEl.className = 'mos-active-filters-label';
      labelEl.textContent = 'Active Filters:';
      container.appendChild(labelEl);

      pills.forEach(({ key, value, label }) => {
        const pill = document.createElement('span');
        pill.className = 'mos-filter-pill';

        let displayValue = value;
        // Resolve select label from the form if present
        const sel = document.querySelector(`select[name="${key}"]`);
        if (sel) {
          const opt = sel.querySelector(`option[value="${CSS.escape(value)}"]`);
          if (opt) displayValue = opt.textContent.trim();
        }
        if (key === 'hide_incomplete' && value === '1') displayValue = 'Yes';

        pill.innerHTML = `<span>${label}: <strong>${displayValue}</strong></span>
          <button class="pill-remove" aria-label="Remove ${label} filter" data-param="${key}">×</button>`;
        container.appendChild(pill);
      });

      // Clear all button
      const clearAll = document.createElement('button');
      clearAll.className = 'mos-clear-all-btn';
      clearAll.textContent = 'Clear All';
      clearAll.addEventListener('click', () => {
        const base = window.location.pathname;
        window.location.href = base;
      });
      container.appendChild(clearAll);

      // Individual remove
      container.querySelectorAll('.pill-remove').forEach(btn => {
        btn.addEventListener('click', () => {
          const p = btn.dataset.param;
          params.delete(p);
          // Also remove related params
          if (p === 'joined') { params.delete('start_date'); params.delete('end_date'); }
          if (p === 'created') { params.delete('start_date'); params.delete('end_date'); }
          params.set('page', '1');
          window.location.search = params.toString();
        });
      });
    });
  }

  /* ────────────────────────────────────────────────────
   * 3. Search Enhancement (debounce + clear button)
   * ──────────────────────────────────────────────────── */
  function initSearchEnhancement() {
    document.querySelectorAll('.mos-search-wrap').forEach(wrap => {
      const input = wrap.querySelector('input[type="text"]');
      const clearBtn = wrap.querySelector('.mos-search-clear');
      if (!input) return;

      function updateClear() {
        if (input.value.length > 0) {
          wrap.classList.add('has-value');
        } else {
          wrap.classList.remove('has-value');
        }
      }
      updateClear();

      // Debounced form submit on typing
      let debounceTimer;
      input.addEventListener('input', () => {
        updateClear();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
          const form = input.closest('form');
          if (form) form.submit();
        }, SEARCH_DEBOUNCE_MS);
      });

      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          input.value = '';
          updateClear();
          const form = input.closest('form');
          if (form) form.submit();
        });
      }
    });
  }

  /* ────────────────────────────────────────────────────
   * 4. Client-side Table Sort
   * ──────────────────────────────────────────────────── */
  function initSortableTable() {
    document.querySelectorAll('table').forEach(table => {
      const headers = table.querySelectorAll('th[data-sortable]');
      if (!headers.length) return;

      // Inject sort icons
      headers.forEach(th => {
        const icon = document.createElement('span');
        icon.className = 'sort-icon';
        th.appendChild(icon);
      });

      headers.forEach((th, colIndex) => {
        th.addEventListener('click', () => {
          const tbody = table.querySelector('tbody');
          if (!tbody) return;

          const isAsc = th.classList.contains('sort-asc');
          // Clear all
          headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
          th.classList.add(isAsc ? 'sort-desc' : 'sort-asc');

          const rows = Array.from(tbody.querySelectorAll('tr'));
          const sortDir = isAsc ? -1 : 1;

          rows.sort((a, b) => {
            const aCell = a.cells[colIndex];
            const bCell = b.cells[colIndex];
            if (!aCell || !bCell) return 0;
            const aText = aCell.textContent.trim();
            const bText = bCell.textContent.trim();

            // Numeric sort
            const aNum = parseFloat(aText.replace(/[^0-9.\-]/g, ''));
            const bNum = parseFloat(bText.replace(/[^0-9.\-]/g, ''));
            if (!isNaN(aNum) && !isNaN(bNum)) {
              return (aNum - bNum) * sortDir;
            }

            // Date sort (d M Y format or ISO)
            const aDate = new Date(aText);
            const bDate = new Date(bText);
            if (!isNaN(aDate) && !isNaN(bDate)) {
              return (aDate - bDate) * sortDir;
            }

            // String sort
            return aText.localeCompare(bText) * sortDir;
          });

          rows.forEach(row => tbody.appendChild(row));
        });
      });
    });
  }

  /* ────────────────────────────────────────────────────
   * 5. Search Term Highlight
   * ──────────────────────────────────────────────────── */
  function initSearchHighlight() {
    const params = new URLSearchParams(window.location.search);
    const term = params.get('search');
    if (!term || term.length < 2) return;

    const escaped = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp(`(${escaped})`, 'gi');

    document.querySelectorAll('table tbody td').forEach(td => {
      // Skip action columns
      if (td.querySelector('a, button')) return;
      if (td.textContent.trim().length === 0) return;
      td.innerHTML = td.innerHTML.replace(regex, '<mark class="mos-highlight">$1</mark>');
    });
  }

  /* ────────────────────────────────────────────────────
   * 6. Custom date range visibility
   * ──────────────────────────────────────────────────── */
  function initDateRangeToggle() {
    const joinedSel = document.querySelector('select[name="joined"], select[name="created"]');
    if (!joinedSel) return;
    const startInput = document.querySelector('input[name="start_date"]');
    const endInput = document.querySelector('input[name="end_date"]');
    if (!startInput || !endInput) return;

    function updateDateInputs() {
      const show = joinedSel.value === 'custom';
      startInput.closest('.mos-date-range-wrap')
        ? startInput.closest('.mos-date-range-wrap').style.display = show ? '' : 'none'
        : [startInput, endInput].forEach(el => el.style.display = show ? '' : 'none');
    }
    joinedSel.addEventListener('change', updateDateInputs);
    updateDateInputs();
  }

  /* ────────────────────────────────────────────────────
   * 7. Per-page select — auto submit
   * ──────────────────────────────────────────────────── */
  function initPerPageAutoSubmit() {
    document.querySelectorAll('select[name="per_page"]').forEach(sel => {
      sel.addEventListener('change', () => {
        const form = sel.closest('form');
        if (form) form.submit();
      });
    });
  }

  /* ────────────────────────────────────────────────────
   * 8. Row count / entry info
   * ──────────────────────────────────────────────────── */
  function initTableRowCount() {
    document.querySelectorAll('.mos-table-info-bar[data-total]').forEach(bar => {
      // Already rendered server-side, just ensure it's visible
      bar.style.display = '';
    });
  }

  /* ────────────────────────────────────────────────────
   * Boot
   * ──────────────────────────────────────────────────── */
  function init() {
    initFilterToggle();
    initActiveFilterBadges();
    initSearchEnhancement();
    initSortableTable();
    initSearchHighlight();
    initDateRangeToggle();
    initPerPageAutoSubmit();
    initTableRowCount();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
