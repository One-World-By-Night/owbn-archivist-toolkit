// ── Dynamic Group Row Handling for Bylaw Group Settings Page ──
document.addEventListener('DOMContentLoaded', () => {
  const table = document.getElementById('bcm-group-table');
  const addBtn = document.getElementById('bcm-add-group');

  if (addBtn && table) {
    addBtn.addEventListener('click', () => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td><input type="text" name="bcm_groups_keys[]" value="" placeholder="e.g. character" required></td>
        <td><input type="text" name="bcm_groups_labels[]" value="" placeholder="e.g. Character" required></td>
        <td><button type="button" class="bcm-remove-group">Remove</button></td>
      `;
      table.querySelector('tbody').appendChild(row);
    });

    table.addEventListener('click', (e) => {
      if (e.target.classList.contains('bcm-remove-group')) {
        e.target.closest('tr').remove();
      }
    });
  }
});

// ── Quick Edit Handler for Bylaw Clause Custom Fields ──
jQuery(function ($) {
  function populateQuickEditFields(postId) {
    const $dataDiv = $('.bcm-quickedit-data[data-id="' + postId + '"]');

    console.log('💡 Quick Edit Init');
    console.log('Post ID:', postId);
    console.log('Found data div?', $dataDiv.length);
    console.log('Data:', $dataDiv.data());

    const $editRow = $('#edit-' + postId);

    if (!$dataDiv.length || !$editRow.length) return;

    const group  = $dataDiv.data('bcm-group') || '';
    const tags   = $dataDiv.data('bcm-tags') || '';
    const parent = $dataDiv.data('bcm-parent') || '';

    console.log(`Quick Edit Values for post ${postId}:`, {
      group,
      tags,
      parent
    });

    $editRow.find('input[name="bcm_qe_tags"]').val(tags);
    $editRow.find('select[name="bcm_qe_parent_clause"]').val(parent).trigger('change');
    $editRow.find('select[name="bcm_qe_bylaw_group"]').val(group).trigger('change');

    // Init Select2 on dropdowns
    $editRow.find('select').each(function () {
      if ($.fn.select2) {
        if ($(this).hasClass('select2-hidden-accessible')) {
          $(this).select2('destroy');
        }
        $(this).select2({ width: '100%' });
      }
    });
  }

  $(document).on('click', 'button.editinline', function () {
    console.log('✅ editinline clicked');

    const $row = $(this).closest('tr');
    const postId = $row.attr('id')?.replace('post-', '');
    if (postId) {
      setTimeout(() => populateQuickEditFields(postId), 100);
    }
  });

  $(document).ajaxSuccess(function (e, xhr, settings) {
    if (settings.data && settings.data.includes('action=inline-save')) {
      setTimeout(() => {
        $('tr.inline-edit-row').each(function () {
          const postId = $(this).attr('id')?.replace('edit-', '');
          if (postId) populateQuickEditFields(postId);
        });
      }, 200);
    }
  });
});

// ── Frontend Tag Filtering for [render_bylaws] ──
document.addEventListener('DOMContentLoaded', () => {
  const clauses = document.querySelectorAll('.bylaw-clause');
  const tagSet = new Set();

  clauses.forEach(clause => {
    clause.classList.forEach(cls => {
      if (cls !== 'bylaw-clause') tagSet.add(cls);
    });
  });

  const select = document.getElementById('bcm-tag-select');
  if (!select) return;

  tagSet.forEach(tag => {
    const option = document.createElement('option');
    option.value = tag;
    option.textContent = tag.charAt(0).toUpperCase() + tag.slice(1);
    select.appendChild(option);
  });

  jQuery(select).select2({
    placeholder: 'Filter by tag',
    width: 'resolve'
  });

  function applyFilter(selected) {
    const showIds = new Set();
    const clausesById = {};

    clauses.forEach(clause => {
      const id = clause.dataset.id;
      clausesById[id] = clause;

      const classes = Array.from(clause.classList);
      const tags = classes.filter(cls => cls !== 'bylaw-clause');

      const hasAlways = tags.includes('always');
      const matchesFilter = selected.some(tag => tags.includes(tag));

      if (hasAlways || matchesFilter || selected.length === 0) {
        showIds.add(id);
        let current = clause;
        while (current && current.dataset.parent && current.dataset.parent !== '0') {
          const parentId = current.dataset.parent;
          showIds.add(parentId);
          current = clausesById[parentId];
        }
      }
    });

    clauses.forEach(clause => {
      clause.style.display = showIds.has(clause.dataset.id) ? 'block' : 'none';
    });
  }

  jQuery(select).on('change', () => {
    const selected = jQuery(select).val() || [];
    applyFilter(selected);
  });
});

// ── Global Helper to Clear Filters ──
function bcmClearFilters() {
  const select = document.getElementById('bcm-tag-select');
  if (select && jQuery(select).select2) {
    jQuery(select).val(null).trigger('change');
  }
}

// --- Bylaw Group Settings (Add/Remove rows) ---
document.addEventListener('DOMContentLoaded', function () {
    const table = document.querySelector('#bcm-group-table tbody');
    const addBtn = document.querySelector('#bcm-add-group');

    if (!table || !addBtn) return;

    addBtn.addEventListener('click', () => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="text" name="bcm_groups_keys[]" value="" required></td>
            <td><input type="text" name="bcm_groups_labels[]" value="" required></td>
            <td><button type="button" class="bcm-remove-group">Remove</button></td>
        `;
        table.appendChild(row);
    });

    table.addEventListener('click', (e) => {
        if (e.target && e.target.matches('.bcm-remove-group')) {
            e.target.closest('tr').remove();
        }
    });
});