import axios from 'axios';
import Swal from 'sweetalert2';
import { _formToJson, _showInvalidError, toast } from '../../../../helpers';

const ENDPOINT = '/FACDBT/bd/master-event';
const PRE = 'PRE';

// Form tambah / ubah event
class EventForm {
  #id = null;
  #modal = new bootstrap.Modal(document.getElementById('event-modal'));
  #form = document.getElementById('event-form');
  #onSuccess;

  constructor(onSuccess) {
    this.#onSuccess = onSuccess;
    this.#form.addEventListener('submit', e => {
      e.preventDefault();
      this.#submit();
    });
    $('#EVENT_TYPE').on('change', () => this.#togglePreHint());
  }

  async open(id = null) {
    $('.invalid-feedback').remove();
    $('.is-invalid').removeClass('is-invalid');
    this.#form.reset();
    this.#id = id;
    $('#event-title').text(id === null ? 'Add Event' : 'Edit Event');

    try {
      const [periods, event] = await Promise.all([
        axios.get(`${ENDPOINT}/periods`),
        id === null ? Promise.resolve(null) : axios.get(`${ENDPOINT}/${id}`)
      ]);
      const data = event?.data?.data;
      this.#fillPeriods(periods.data?.data || [], data ? String(data.TAKING_DATE).slice(0, 10) : null);

      if (data) {
        $('#EVENT_TYPE').val(data.EVENT_TYPE);
        $('#DESCRIPTION').val(data.DESCRIPTION);
      }
      this.#togglePreHint();
      this.#modal.show();
    } catch (error) {
      toast.error(error.response?.data?.message || 'Failed to load event data');
    }
  }

  // Periode yang sudah punya event lain tidak bisa dipilih (satu periode satu event)
  #fillPeriods(periods, selected) {
    const $select = $('#TAKING_DATE').empty().append('<option value="">- Select Taking Period -</option>');

    periods.forEach(p => {
      const taken = p.event_id !== null && String(p.event_id) !== String(this.#id);
      const close = p.close_date ? ` s/d ${p.close_date}` : '';
      const state = p.rowstate ? ` [${p.rowstate}]` : '';
      $select.append($('<option>', {
        value: p.taking_date,
        text: `${p.taking_date}${close} (${p.month}/${p.year})${state}${taken ? ' - already has event' : ''}`,
        disabled: taken
      }));
    });

    if (selected) {
      if ($select.find(`option[value="${selected}"]`).length === 0) {
        $select.append($('<option>', { value: selected, text: selected }));
      }
      $select.val(selected);
    }
  }

  #togglePreHint() {
    $('#event-pre-hint').toggleClass('d-none', $('#EVENT_TYPE').val() !== PRE);
  }

  #submit() {
    const data = _formToJson(this.#form);
    const request = this.#id === null ? axios.post(ENDPOINT, data) : axios.put(`${ENDPOINT}/${this.#id}`, data);

    request
      .then(response => {
        toast.success(response.data.message);
        this.#modal.hide();
        this.#onSuccess();
      })
      .catch(error => {
        if (error.response?.status === 422) {
          _showInvalidError(error.response.data.errors);
        } else {
          toast.error(error.response?.data?.message || 'Failed to save event');
        }
      });
  }
}

// Upload daftar part Pre-Taking (mengganti daftar lama)
class EventParts {
  #id = null;
  #modal = new bootstrap.Modal(document.getElementById('event-parts-modal'));
  #form = document.getElementById('event-parts-form');
  #onSuccess;

  constructor(onSuccess) {
    this.#onSuccess = onSuccess;
    $(document).on('click', '#btn-submit-event-parts', e => {
      e.preventDefault();
      this.#submit();
    });
    $(document).on('click', '#event-parts-export', () => {
      if (this.#id !== null) window.location.href = `${ENDPOINT}/${this.#id}/parts/export`;
    });
  }

  open(id, date, count) {
    this.#id = id;
    this.#form.reset();
    $('#event-parts-errors').addClass('d-none').empty();
    $('#event-parts-date').text(date || '-');
    $('#event-parts-count').text(Number(count || 0).toLocaleString('en-US'));
    this.#modal.show();
  }

  #submit() {
    const $button = $('#btn-submit-event-parts').prop('disabled', true);
    $('#event-parts-errors').addClass('d-none').empty();

    axios.post(`${ENDPOINT}/${this.#id}/parts`, new FormData(this.#form), {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
      .then(response => {
        toast.success(response.data.message);
        this.#modal.hide();
        this.#onSuccess();
      })
      .catch(error => {
        const errors = error.response?.data?.data?.error_data || [];
        if (errors.length) {
          const $list = $('#event-parts-errors').removeClass('d-none');
          errors.slice(0, 50).forEach(item => $list.append($('<li>').text(`Row ${item.row} (${item.part_no}): ${item.errors.join(', ')}`)));
        }
        const fileError = error.response?.data?.errors?.file?.[0];
        toast.error(fileError || error.response?.data?.message || 'Failed to upload part list');
      })
      .finally(() => $button.prop('disabled', false));
  }
}

class MasterEvent {
  #table = $('#facdbtf006-table');
  #selected = new Set();

  constructor() {
    const reload = () => this.#table.DataTable().ajax.reload(null, false);
    this.form = new EventForm(reload);
    this.parts = new EventParts(reload);
  }

  init() {
    const self = this;

    $(document).on('click', '#btn-create-event', e => {
      e.preventDefault();
      this.form.open();
    });
    $(document).on('click', '.edit-event', function () {
      self.form.open($(this).data('id'));
    });
    $(document).on('click', '.event-parts', function () {
      self.parts.open($(this).data('id'), $(this).data('date'), $(this).data('count'));
    });
    $(document).on('click', '.delete-event', function () {
      self.#confirmDelete([String($(this).data('id'))]);
    });
    $(document).on('click', '#btn-delete-selected', () => this.#confirmDelete(Array.from(this.#selected)));
    $(document).on('click', '#download-template', () => {
      window.location.href = `${ENDPOINT}/download-template`;
    });

    $(document).on('change', '#entries', e => this.#table.DataTable().page.len($(e.target).val()).draw());

    let searchTimeout;
    $(document).on('keyup', '#search-input', e => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        const keyword = $(e.target).val();
        const url = new URL(window.location.href);
        if (keyword) url.searchParams.set('keyword', keyword);
        else url.searchParams.delete('keyword');
        this.#table.DataTable().ajax.url(`${url.pathname}?${url.searchParams.toString()}`).load();
      }, 500);
    });

    // Pilihan baris (halaman yang terlihat)
    this.#table.on('draw.dt', () => {
      $('input[name="selected[]"]').each((_, el) => $(el).prop('checked', this.#selected.has(String($(el).val()))));
      this.#refreshSelection();
    });
    $(document).on('change', '#select-all', function () {
      const checked = $(this).is(':checked');
      $('input[name="selected[]"]').each((_, el) => {
        $(el).prop('checked', checked);
        checked ? self.#selected.add(String($(el).val())) : self.#selected.delete(String($(el).val()));
      });
      self.#refreshSelection();
    });
    $(document).on('change', 'input[name="selected[]"]', function () {
      const id = String($(this).val());
      $(this).is(':checked') ? self.#selected.add(id) : self.#selected.delete(id);
      self.#refreshSelection();
    });
  }

  #refreshSelection() {
    const boxes = $('input[name="selected[]"]');
    $('#btn-delete-selected').toggleClass('d-none', this.#selected.size === 0);
    $('#select-all').prop('checked', boxes.length > 0 && boxes.length === boxes.filter(':checked').length);
  }

  #confirmDelete(ids) {
    if (ids.length === 0) {
      toast.error('No events selected for deletion.');
      return;
    }

    Swal.fire({
      title: 'Are you sure?',
      text: `You are about to delete ${ids.length} event(s) and their part list. Dashboards will show all data for those periods.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete!',
      customClass: { confirmButton: 'btn btn-outline-danger', cancelButton: 'btn btn-primary' }
    }).then(result => {
      if (!result.isConfirmed) return;

      const request = ids.length === 1
        ? axios.delete(`${ENDPOINT}/${ids[0]}`)
        : axios.post(`${ENDPOINT}/delete-multiple`, { ids });

      request
        .then(response => {
          toast.success(response.data.message);
          ids.forEach(id => this.#selected.delete(String(id)));
          this.#table.DataTable().ajax.reload(null, false);
        })
        .catch(error => toast.error(error.response?.data?.message || 'Failed to delete event'));
    });
  }
}

document.addEventListener('DOMContentLoaded', function () {
  new MasterEvent().init();
});
