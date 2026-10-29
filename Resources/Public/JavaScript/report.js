/**
 * pxe_composer_report backend module. Loaded as an ES module (no inline JS, CSP-safe).
 *  - sparkline bar heights (set via CSSOM, no inline style attributes in the template)
 *  - severity column sort for the advisories table
 *  - opens the native <dialog> for the test mail form (the form itself posts without JS);
 *    the opening button sits in the docheader, outside the .pxecr module body
 */

const SEVERITY_RANK = { critical: 4, high: 3, medium: 2, low: 1 };

function severityRank(value) {
  return SEVERITY_RANK[(value || '').toLowerCase()] || 0;
}

function initSeveritySort() {
  const table = document.querySelector('.pxecr table[data-pxecr-sec]');
  const button = table ? table.querySelector('[data-pxecr-sort]') : null;
  const tbody = table ? table.querySelector('tbody') : null;
  if (!button || !tbody) {
    return;
  }
  let descending = true;
  button.addEventListener('click', () => {
    const rows = Array.from(tbody.querySelectorAll('tr'));
    rows.sort((a, b) => {
      const diff = severityRank(a.dataset.sev) - severityRank(b.dataset.sev);
      return descending ? -diff : diff;
    });
    rows.forEach((row) => tbody.appendChild(row));
    button.dataset.dir = descending ? 'desc' : 'asc';
    descending = !descending;
  });
}

function initTestMailDialog() {
  const openButton = document.querySelector('[data-pxecr-testmail]');
  const dialog = document.querySelector('.pxecr [data-pxecr-dialog]');
  if (!openButton || !dialog || typeof dialog.showModal !== 'function') {
    return;
  }
  openButton.addEventListener('click', () => {
    dialog.showModal();
    const input = dialog.querySelector('input[type="email"]');
    if (input) {
      input.focus();
    }
  });
  dialog.querySelectorAll('[data-pxecr-close]').forEach((el) => el.addEventListener('click', () => dialog.close()));
}

function initSparkline() {
  document.querySelectorAll('.pxecr [data-pxecr-height]').forEach((bar) => {
    const height = parseInt(bar.dataset.pxecrHeight, 10);
    if (!Number.isNaN(height)) {
      bar.style.height = Math.min(100, Math.max(2, height)) + '%';
    }
  });
}

function init() {
  initSparkline();
  initSeveritySort();
  initTestMailDialog();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
