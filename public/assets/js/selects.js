/* Accessible searchable dropdowns for regular form selects. */
(() => {
  'use strict';

  const searchPlaceholder = document.body.dataset.selectSearchPlaceholder || 'Search';
  const noResults = document.body.dataset.selectNoResults || 'No matching options';
  let sequence = 0;

  const normalized = (value) => String(value || '').trim().toLocaleLowerCase();

  function labelFor(select) {
    const parent = select.closest('.mb-2, .mb-3, .mb-4, .col-md-2, .col-md-3, .col-md-4, .col-md-6');
    return select.getAttribute('aria-label') || parent?.querySelector('label')?.textContent.trim() || select.name || searchPlaceholder;
  }

  function enhance(select) {
    if (select.dataset.searchableReady === 'true') return;
    select.dataset.searchableReady = 'true';
    const options = Array.from(select.options).map((option) => ({ value: option.value, label: option.textContent.trim(), disabled: option.disabled }));
    const initial = select.selectedIndex >= 0 ? select.options[select.selectedIndex].textContent.trim() : '';
    const id = select.id || `searchable-select-${++sequence}`;
    const inputId = `${id}-input`;
    const listId = `${id}-list`;
    const label = labelFor(select);
    const wrapper = document.createElement('div');
    const input = document.createElement('input');
    const icon = document.createElement('span');
    const list = document.createElement('div');
    let activeIndex = -1;
    let previousLabel = initial;

    select.id = id;
    select.classList.add('searchable-select-native');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    const matchingLabel = document.querySelector(`label[for="${CSS.escape(id)}"]`);
    if (matchingLabel) matchingLabel.htmlFor = inputId;

    wrapper.className = 'searchable-select';
    input.id = inputId;
    input.className = 'form-control searchable-select-input';
    input.type = 'search';
    input.autocomplete = 'off';
    input.value = initial;
    input.placeholder = searchPlaceholder;
    input.disabled = select.disabled;
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', listId);
    input.setAttribute('aria-label', label);
    icon.className = 'searchable-select-icon';
    icon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6"></circle><path d="m20 20-4-4"></path></svg>';
    list.id = listId;
    list.className = 'searchable-select-list';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', label);

    select.parentNode.insertBefore(wrapper, select);
    wrapper.append(select, input, icon, list);

    const close = () => {
      wrapper.classList.remove('is-open');
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      activeIndex = -1;
    };
    const choose = (item) => {
      const changed = select.value !== item.value;
      select.value = item.value;
      previousLabel = item.label;
      input.value = item.label;
      if (changed) select.dispatchEvent(new Event('change', { bubbles: true }));
      close();
    };
    const visibleOptions = () => options.filter((item) => !item.disabled && normalized(item.label).includes(normalized(input.value)));
    const render = () => {
      const visible = visibleOptions();
      list.textContent = '';
      if (!visible.length) {
        const empty = document.createElement('div');
        empty.className = 'searchable-select-empty';
        empty.textContent = noResults;
        list.append(empty);
        activeIndex = -1;
        return visible;
      }
      visible.forEach((item, index) => {
        const option = document.createElement('div');
        option.className = 'searchable-select-option';
        option.id = `${listId}-option-${index}`;
        option.setAttribute('role', 'option');
        option.setAttribute('aria-selected', String(select.value === item.value));
        option.textContent = item.label;
        option.addEventListener('mousedown', (event) => { event.preventDefault(); choose(item); });
        list.append(option);
      });
      return visible;
    };
    const open = () => {
      if (input.disabled) return;
      wrapper.classList.add('is-open');
      input.setAttribute('aria-expanded', 'true');
      render();
    };
    const activate = (index) => {
      const elements = list.querySelectorAll('.searchable-select-option');
      if (!elements.length) return;
      activeIndex = Math.max(0, Math.min(index, elements.length - 1));
      elements.forEach((element, itemIndex) => element.classList.toggle('is-active', itemIndex === activeIndex));
      const active = elements[activeIndex];
      input.setAttribute('aria-activedescendant', active.id);
      active.scrollIntoView({ block: 'nearest' });
    };
    const commitTypedValue = () => {
      const exact = options.find((item) => !item.disabled && normalized(item.label) === normalized(input.value));
      if (exact) choose(exact);
      else input.value = previousLabel;
    };

    input.addEventListener('focus', open);
    input.addEventListener('input', () => { open(); activeIndex = -1; render(); });
    input.addEventListener('keydown', (event) => {
      const visible = visibleOptions();
      if (event.key === 'ArrowDown') { event.preventDefault(); open(); activate(activeIndex + 1); }
      else if (event.key === 'ArrowUp') { event.preventDefault(); open(); activate(activeIndex - 1); }
      else if (event.key === 'Enter' && wrapper.classList.contains('is-open')) { event.preventDefault(); if (activeIndex >= 0 && visible[activeIndex]) choose(visible[activeIndex]); else commitTypedValue(); }
      else if (event.key === 'Escape') { event.preventDefault(); input.value = previousLabel; close(); }
      else if (event.key === 'Tab') { commitTypedValue(); close(); }
    });
    input.addEventListener('blur', () => window.setTimeout(() => { if (!wrapper.contains(document.activeElement)) { commitTypedValue(); close(); } }, 120));
    select.addEventListener('change', () => {
      const selected = select.options[select.selectedIndex];
      if (selected) { previousLabel = selected.textContent.trim(); input.value = previousLabel; }
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form select.form-select:not([data-no-search])').forEach(enhance);
  });
})();
