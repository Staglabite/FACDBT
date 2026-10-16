import axios from 'axios';
import { toast } from '../../../../helpers';
import { exportMasterData } from '../../FACDBT01/shared/exportExcel';

const ENDPOINT = '/FACDBT/ds/taking-history';

const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

class TakingHistory {
  #table = $('#facdbtd006-table');
  #selectedIds = new Set();
  #selectAllPromise = null;
  #events = [];
  // Filter yang dikirim ke DataTable & export
  #filters = { year: '', month: '', taking_date: '', keyword: '' };

  init() {
    const self = this;

    // Pindahkan baris pencarian per kolom (tfoot) ke thead
    this.#table.on('init.dt', function () {
      self.#table.find('tfoot tr').appendTo(self.#table.find('thead'));
    });

    this.#loadPeriods();
    this.#filterEvents();
    this.#selectionEvents();

    $(document).on('click', '#export-excel', () => {
      exportMasterData(`${ENDPOINT}/export`, this.#selectedIds, $('#export-excel'), this.#filters);
    });
  }

  // -------------------------------------------------------------
  // Filter periode Year -> Month -> Event
  // -------------------------------------------------------------
  #loadPeriods() {
    axios.get(`${ENDPOINT}/filters`)
      .then(res => {
        this.#events = res.data?.events || [];
        const years = [...new Set(this.#events.map(e => e.year))];
        $('#filter-year').append(years.map(y => `<option value="${y}">${y}</option>`).join(''));
      })
      .catch(() => toast.error('Failed to load history periods.'));
  }

  #onYearChange(year) {
    const $month = $('#filter-month');
    $month.html('<option value="">All Month</option>').prop('disabled', !year);

    if (year) {
      const months = [...new Set(this.#events.filter(e => String(e.year) === year).map(e => e.month))];
      $month.append(months.map(m => `<option value="${m}">${MONTH_NAMES[m - 1]}</option>`).join(''));
    }

    this.#onMonthChange(year, '');
  }

  #onMonthChange(year, month) {
    const $event = $('#filter-event');
    const events = month
      ? this.#events.filter(e => String(e.year) === year && String(e.month) === month)
      : [];

    // Pilihan Event hanya ditampilkan bila bulan itu punya lebih dari satu taking
    $event.html(events.map(e => `<option value="${e.taking_date}">${e.label}</option>`).join(''))
      .toggleClass('d-none', events.length <= 1);
    if (events.length) $event.val(events[events.length - 1].taking_date);

    this.#updateQuery({ year, month, taking_date: events.length ? $event.val() : '' });
  }

  #filterEvents() {
    $(document).on('change', '#filter-year', e => this.#onYearChange($(e.target).val()));
    $(document).on('change', '#filter-month', e => this.#onMonthChange($('#filter-year').val(), $(e.target).val()));
    $(document).on('change', '#filter-event', e => this.#updateQuery({ taking_date: $(e.target).val() }));

    $(document).on('change', '#entries', e => {
      this.#table.DataTable().page.len(Number($(e.target).val())).draw();
    });

    let searchTimeout;
    $(document).on('keyup', '#search-input', e => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => this.#updateQuery({ keyword: $(e.target).val() }), 500);
    });
  }

  #updateQuery(params) {
    Object.assign(this.#filters, params);

    // Ganti filter = pilihan centang dimulai dari awal
    this.#selectedIds.clear();
    this.#refreshSelectionUI();

    const query = new URLSearchParams(Object.entries(this.#filters).filter(([, v]) => v !== '' && v !== null));
    const url = query.toString() ? `${window.location.pathname}?${query}` : window.location.pathname;
    this.#table.DataTable().ajax.url(url).load();
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
    this.#selectAllPromise = (async () => {
      const table = this.#table.DataTable();
      // Filter tabel saat ini + filter periode & keyword dari URL ajax; server hanya mengembalikan ID-nya
      const ajaxUrl = new URL(table.ajax.url() || window.location.href, window.location.origin);
      const query = $.param({
        ...(table.ajax.params() || {}),
        ...Object.fromEntries(ajaxUrl.searchParams),
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
        toast.error(error.response?.data?.message || 'Failed to select all history data.');
      } finally {
        this.#selectAllPromise = null;
      }
    })();

    await this.#selectAllPromise;
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
  new TakingHistory().init();
});
