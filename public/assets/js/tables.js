/* Local table controls: no external library or network connection is required. */
(function () {
  'use strict';

  var isArabic = document.documentElement.lang === 'ar';
  var labels = isArabic ? {
    search: '\u0628\u062d\u062b', rows: '\u0633\u062c\u0644\u0627\u062a \u0641\u064a \u0627\u0644\u0635\u0641\u062d\u0629',
    showing: '\u0625\u0638\u0647\u0627\u0631', of: '\u0645\u0646', records: '\u0633\u062c\u0644',
    previous: '\u0627\u0644\u0633\u0627\u0628\u0642', next: '\u0627\u0644\u062a\u0627\u0644\u064a',
    noRecords: '\u0644\u0627 \u062a\u0648\u062c\u062f \u0633\u062c\u0644\u0627\u062a \u0645\u0637\u0627\u0628\u0642\u0629'
  } : {
    search: 'Search', rows: 'rows per page', showing: 'Showing', of: 'of',
    records: 'records', previous: 'Previous', next: 'Next', noRecords: 'No matching records'
  };

  function element(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function setupTable(table) {
    if (!table.parentElement.classList.contains('table-responsive')) {
      var responsive = element('div', 'table-responsive');
      table.parentNode.insertBefore(responsive, table);
      responsive.appendChild(table);
    }
    var body = table.tBodies[0];
    if (!body) return;
    var rows = Array.prototype.slice.call(body.rows);
    var source = table.getAttribute('data-source');
    var fields = (table.getAttribute('data-fields') || '').split(',').filter(Boolean);
    var state = { page: 1, pageSize: 10, query: '' };
    var toolbar = element('div', 'table-controls');
    var searchLabel = element('label', 'table-search-label', labels.search);
    var search = element('input', 'form-control table-search');
    var lengthLabel = element('label', 'table-length-label', labels.rows);
    var length = element('select', 'form-select table-length');
    var footer = element('div', 'table-pagination');
    var info = element('span', 'table-info');
    var buttons = element('div', 'table-pages');

    search.type = 'search';
    search.setAttribute('aria-label', labels.search);
    [10, 25, 50, 100].forEach(function (value) {
      var option = element('option', '', value);
      option.value = value;
      length.appendChild(option);
    });
    searchLabel.appendChild(search);
    lengthLabel.appendChild(length);
    toolbar.appendChild(searchLabel);
    toolbar.appendChild(lengthLabel);
    footer.appendChild(info);
    footer.appendChild(buttons);
    table.parentNode.insertBefore(toolbar, table);
    table.parentNode.insertBefore(footer, table.nextSibling);

    function loadServerPage() {
      var url = new URL(source, window.location.origin);
      url.searchParams.set('page', state.page);
      url.searchParams.set('per_page', state.pageSize);
      url.searchParams.set('q', state.query);
      fetch(url.toString(), { credentials: 'same-origin' }).then(function (response) { return response.json(); }).then(function (response) {
        var payload = response.data || {};
        body.textContent = '';
        (payload.rows || []).forEach(function (row) {
          var tr = document.createElement('tr');
          if (Number(row.quantity) <= Number(row.alert_quantity)) tr.className = 'table-warning';
          fields.forEach(function (field) {
            var td = document.createElement('td');
            if (field === 'action' && row.edit_url) {
              var link = document.createElement('a'); link.href = row.edit_url; link.className = 'btn btn-sm'; link.textContent = row.edit_label || 'Edit'; td.appendChild(link);
            } else if (field === 'number' && row.show_url) {
              var number = document.createElement('a'); number.href = row.show_url; number.textContent = row.invoice_number; td.appendChild(number);
            } else {
              td.textContent = row[field] === null || row[field] === undefined ? '' : row[field];
            }
            tr.appendChild(td);
          });
          body.appendChild(tr);
        });
        rows = Array.prototype.slice.call(body.rows);
        state.total = payload.pagination ? payload.pagination.total : rows.length;
        state.totalPages = payload.pagination ? payload.pagination.total_pages : 1;
        render(true);
      });
    }

    function matchingRows() {
      var query = state.query.toLowerCase();
      return rows.filter(function (row) {
        return !query || row.textContent.toLowerCase().indexOf(query) !== -1;
      });
    }

    function pageButton(text, page, disabled, current) {
      var button = element('button', current ? 'btn btn-primary btn-sm' : 'btn btn-outline-secondary btn-sm', text);
      button.type = 'button';
      button.disabled = disabled;
      button.addEventListener('click', function () { state.page = page; render(); });
      return button;
    }

    function render(fromServer) {
      if (source && !fromServer) { loadServerPage(); return; }
      var visible = matchingRows();
      var totalPages = source ? state.totalPages : Math.max(1, Math.ceil(visible.length / state.pageSize));
      state.page = Math.min(state.page, totalPages);
      var start = (state.page - 1) * state.pageSize;
      if (!source) { rows.forEach(function (row) { row.hidden = true; }); visible.slice(start, start + state.pageSize).forEach(function (row) { row.hidden = false; }); }
      var total = source ? state.total : visible.length;
      info.textContent = total ? labels.showing + ' ' + (start + 1) + '–' + Math.min(start + state.pageSize, total) + ' ' + labels.of + ' ' + total + ' ' + labels.records : labels.noRecords;
      buttons.textContent = '';
      buttons.appendChild(pageButton(labels.previous, state.page - 1, state.page === 1));
      var firstPage = Math.max(1, state.page - 2);
      var lastPage = Math.min(totalPages, state.page + 2);
      if (firstPage > 1) {
        buttons.appendChild(pageButton('1', 1, false, state.page === 1));
        if (firstPage > 2) { buttons.appendChild(pageButton('…', 1, true, false)); }
      }
      for (var page = firstPage; page <= lastPage; page += 1) {
        buttons.appendChild(pageButton(String(page), page, false, page === state.page));
      }
      if (lastPage < totalPages) {
        if (lastPage < totalPages - 1) { buttons.appendChild(pageButton('…', totalPages, true, false)); }
        buttons.appendChild(pageButton(String(totalPages), totalPages, false, state.page === totalPages));
      }
      buttons.appendChild(pageButton(labels.next, state.page + 1, state.page === totalPages));
    }

    search.addEventListener('input', function () { state.query = search.value.trim(); state.page = 1; render(); });
    length.addEventListener('change', function () { state.pageSize = Number(length.value); state.page = 1; render(); });
    render();
  }

  document.addEventListener('DOMContentLoaded', function () {
    Array.prototype.forEach.call(document.querySelectorAll('.data-table'), setupTable);
  });
})();
