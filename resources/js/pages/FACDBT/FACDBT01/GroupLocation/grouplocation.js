import axios from 'axios';
import GroupLocationForm from './grouplocation-form';
import { toast } from '../../../../helpers';
import { exportMasterData } from '../shared/exportExcel';
import Swal from 'sweetalert2';

class GroupLocation {
  #groupLocationTable = $('#facdbtf005-table');
  #selectedGroupLocationIds = new Set();
  #selectAllPromise = null;

  constructor() {
    this.form = new GroupLocationForm(() => {
      this.#groupLocationTable.DataTable().ajax.reload();
    });
  }

  init() {
    const self = this;
    // DataTable init
    this.#groupLocationTable.on('init.dt', function () {
      var tfoot = self.#groupLocationTable.find('tfoot tr');
      var thead = self.#groupLocationTable.find('thead');

      // Move the tfoot row into thead (after the header row)
      tfoot.appendTo(thead);
    });

    this.#filterEvents();
    this.#events();
    this.#selectionEvents();
  }

  #events() {
    const self = this;
    $(document).on('click', '#btn-create-group-location', function (e) {
      e.preventDefault();
      self.form.openModal();
    });

    $(document).on('click', '.edit-group-location', function () {
      self.form.openModal($(this).data('id'));
    });

    $(document).on('click', '.delete-group-location', function () {
      self.#deleteData($(this).data('id'));
    });

    $(document).on('click', '#btn-delete-selected', function () {
      self.#handleDeleteSelected();
    });

    $(document).on('click', '#export-excel', function () {
      exportMasterData('/FACDBT/bd/master-grouplocation/export', self.#selectedGroupLocationIds);
    });

    $(document).on('click', '#download-template', function () {
      window.location.href = '/FACDBT/bd/master-grouplocation/download-template';
    });
  }

  #filterEvents() {
    $(document).on('change', '#entries', e => {
      const perPage = $(e.target).val();
      const table = this.#groupLocationTable.DataTable();

      table.page.len(perPage).draw();
    });

    let searchTimeout;
    $(document).on('keyup', '#search-input', e => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        const keyword = $(e.target).val();
        this.#updateQuery({ keyword });
      }, 500);
    });
  }

  #selectionEvents() {
    const self = this;

    this.#groupLocationTable.on('draw.dt', function () {
      self.#applySelectionToVisibleRows();
      self.#refreshSelectionUI();
    });

    $(document).on('change', '#select-all', async function () {
      if ($(this).is(':checked')) {
        await self.#selectAllAcrossPages();
        return;
      }

      self.#selectedGroupLocationIds.clear();
      self.#applySelectionToVisibleRows();
      self.#refreshSelectionUI();
    });

    $(document).on('change', 'input[name="selected[]"]', function () {
      const id = $(this).val();

      if (!id) {
        return;
      }

      if ($(this).is(':checked')) {
        self.#selectedGroupLocationIds.add(String(id));
      } else {
        self.#selectedGroupLocationIds.delete(String(id));
      }

      self.#refreshSelectionUI();
    });
  }

  async #selectAllAcrossPages() {
    this.#selectAllPromise = (async () => {
      const table = this.#groupLocationTable.DataTable();
      // Filter tabel saat ini + keyword dari URL ajax; server hanya mengembalikan ID-nya
      const ajaxUrl = new URL(table.ajax.url() || window.location.href, window.location.origin);
      const query = $.param({
        ...(table.ajax.params() || {}),
        ...Object.fromEntries(ajaxUrl.searchParams),
        ids_only: 1
      });
      try {
        const response = await axios.get(`${window.location.pathname}?${query}`);

        this.#selectedGroupLocationIds.clear();
        (response.data?.ids || []).forEach(id => this.#selectedGroupLocationIds.add(String(id)));

        this.#applySelectionToVisibleRows();
        this.#refreshSelectionUI();
      } catch (error) {
        $('#select-all').prop('checked', false);
        toast.error(error.response?.data?.message || 'Failed to select all group location data.');
      } finally {
        this.#selectAllPromise = null;
      }
    })();

    await this.#selectAllPromise;
  }

  async #handleDeleteSelected() {
    if (this.#selectAllPromise) {
      await this.#selectAllPromise;
    }

    this.#deleteMultiple(Array.from(this.#selectedGroupLocationIds));
  }

  #applySelectionToVisibleRows() {
    $('input[name="selected[]"]').each((_, element) => {
      const $checkbox = $(element);
      $checkbox.prop('checked', this.#selectedGroupLocationIds.has(String($checkbox.val())));
    });
  }

  #refreshSelectionUI() {
    const visibleCheckboxes = $('input[name="selected[]"]');
    const visibleChecked = visibleCheckboxes.filter(':checked').length;
    const allVisibleChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.length === visibleChecked;

    $('#btn-delete-selected').toggleClass('d-none', this.#selectedGroupLocationIds.size === 0);
    $('#select-all').prop('checked', allVisibleChecked && this.#selectedGroupLocationIds.size > 0);
  }

  #updateQuery(params) {
    const table = this.#groupLocationTable.DataTable();
    const currentUrl = new URL(window.location.href);
    const searchParams = new URLSearchParams(currentUrl.search);

    // Update parameters
    for (const key in params) {
      if (params[key] === '' || params[key] === null || params[key] === undefined) {
        searchParams.delete(key);
      } else {
        searchParams.set(key, params[key]);
      }
    }

    searchParams.set('page', 1);

    // Update the AJAX URL and reload
    const newUrl = `${currentUrl.pathname}?${searchParams.toString()}`;
    table.ajax.url(newUrl).load();
  }

  #deleteMultiple(groupLocationIds) {
    if (groupLocationIds.length === 0) {
      toast.error('No group locations selected for deletion.');
      return;
    }

    Swal.fire({
      title: 'Are you sure?',
      text: `You are about to delete ${groupLocationIds.length} group location(s). This action cannot be undone!`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete them!',
      customClass: {
        confirmButton: 'btn btn-outline-danger',
        cancelButton: 'btn btn-primary'
      }
    }).then(result => {
      if (result.isConfirmed) {
        axios.post('/FACDBT/bd/master-grouplocation/delete-multiple', {
          ids: groupLocationIds
        })
          .then(response => {
            toast.success(response.data.message);
            groupLocationIds.forEach((id) => this.#selectedGroupLocationIds.delete(String(id)));
            this.#groupLocationTable.DataTable().ajax.reload();
          })
          .catch(error => {
            toast.error(error.response.data.message);
          });
      }
    });
  }

  #deleteData(groupLocationId) {
    Swal.fire({
      title: 'Are you sure?',
      text: "You sure want to delete this data? You won't be able to revert this!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it!',
      customClass: {
        confirmButton: 'btn btn-outline-danger',
        cancelButton: 'btn btn-primary'
      }
    }).then(result => {
      if (result.isConfirmed) {
        axios
          .delete(`/FACDBT/bd/master-grouplocation/${groupLocationId}`)
          .then(response => {
            toast.success(response.data.message);
            this.#selectedGroupLocationIds.delete(String(groupLocationId));
            this.#groupLocationTable.DataTable().ajax.reload();
          })
          .catch(error => {
            toast.error(error.response.data.message);
          });
      }
    });
  }
}

document.addEventListener('DOMContentLoaded', function () {
  new GroupLocation().init();
});