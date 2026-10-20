import axios from 'axios';
import { toast } from '../../../../helpers';
import { exportMasterData } from '../../FACDBT01/shared/exportExcel';

const ENDPOINT = '/FACDBT/ds/taking-by-location';

class TakingByLocation {
  #table = $('#facdbtd007-table');
  #selectedIds = new Set();
  #keyword = '';

  init() {
    const self = this;

    // Pindahkan baris pencarian per kolom (tfoot) ke thead
    this.#table.on('init.dt', function () {
      self.#table.find('tfoot tr').appendTo(self.#table.find('thead'));
    });

    this.#filterEvents();
    this.#selectionEvents();

    // Tanpa centang: semua data sesuai pencarian saat ini (Search + pencarian per kolom)
    $(document).on('click', '#export-excel', () => {
      const { columns } = this.#table.DataTable().ajax.params() || {};
      exportMasterData(`${ENDPOINT}/export`, this.#selectedIds, $('#export-excel'), { keyword: this.#keyword, columns });
    });
  }

  #filterEvents() {
    $(document).on('change', '#entries', e => {
      this.#table.DataTable().page.len(Number($(e.target).val())).draw();
    });

    let searchTimeout;
    $(document).on('keyup', '#search-input', e => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        this.#keyword = $(e.target).val();

        // Ganti pencarian = pilihan centang dimulai dari awal
        this.#selectedIds.clear();
        this.#refreshSelectionUI();

        const url = this.#keyword ? `${window.location.pathname}?${new URLSearchParams({ keyword: this.#keyword })}` : window.location.pathname;
        this.#table.DataTable().ajax.url(url).load();
      }, 500);
    });
  }

  // -------------------------------------------------------------
  // Centang baris (dipakai untuk export data terpilih)
  // -------------------------------------------------------------
  #selectionEvents() {
    const self = this;

    this.#table.on('draw.dt', function () {
      self.#applySelectionToVisibleRows();
      self.#refreshSelectionUI();
    });

    $(document).on('change', '#select-all', async function () {
      if ($(this).is(':checked')) {
        await self.#selectAllAcrossPages();
        return;
      }

      self.#selectedIds.clear();
      self.#applySelectionToVisibleRows();
      self.#refreshSelectionUI();
    });

    $(document).on('change', 'input[name="selected[]"]', function () {
      const id = $(this).val();
      if (!id) return;

      if ($(this).is(':checked')) {
        self.#selectedIds.add(String(id));
      } else {
        self.#selectedIds.delete(String(id));
      }

      self.#refreshSelectionUI();
    });
  }

  async #selectAllAcrossPages() {
    const table = this.#table.DataTable();
    const query = $.param({
      ...(table.ajax.params() || {}),
      keyword: this.#keyword,
      ids_only: 1
    });

    try {
      const response = await axios.get(`${window.location.pathname}?${query}`);

      this.#selectedIds.clear();
      (response.data?.ids || []).forEach(id => this.#selectedIds.add(String(id)));

      this.#applySelectionToVisibleRows();
      this.#refreshSelectionUI();
    } catch (error) {
      $('#select-all').prop('checked', false);
      toast.error(error.response?.data?.message || 'Failed to select all taking data.');
    }
  }

  #applySelectionToVisibleRows() {
    $('input[name="selected[]"]').each((_, element) => {
      const $checkbox = $(element);
      $checkbox.prop('checked', this.#selectedIds.has(String($checkbox.val())));
    });
  }

  #refreshSelectionUI() {
    const visibleCheckboxes = $('input[name="selected[]"]');
    const visibleChecked = visibleCheckboxes.filter(':checked').length;
    const allVisibleChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.length === visibleChecked;

    $('#select-all').prop('checked', allVisibleChecked && this.#selectedIds.size > 0);
  }
}

document.addEventListener('DOMContentLoaded', function () {
  new TakingByLocation().init();
});
