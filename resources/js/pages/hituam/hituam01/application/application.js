import axios from 'axios';
import ApplicationForm from './application-form';
import { toast } from '../../../../helpers';
import Swal from 'sweetalert2';

class Application {
    #applicationTable = $('#hituamf001-table');
    #selectedApplicationIds = new Set();
    #selectAllPromise = null;
    constructor() {
        this.form = new ApplicationForm(() => {
            this.#applicationTable.DataTable().ajax.reload();
        });
    }

    init() {
        const self = this;
        // DataTable init
        this.#applicationTable.on('init.dt', function () {
            var tfoot = self.#applicationTable.find('tfoot tr');
            var thead = self.#applicationTable.find('thead');

            // Move the tfoot row into thead (after the header row)
            tfoot.appendTo(thead);
        });

        this.#filterEvents();
        this.#events();
        this.#selectionEvents();
    }

    #events() {
        const self = this;
        $(document).on('click', '#btn-create-application', function (e) {
            e.preventDefault();
            self.form.openModal();
        });

        $(document).on('click', '.edit-application', function () {
            self.form.openModal($(this).data('id'));
        });

        $(document).on('click', '.delete-application', function () {
            self.#deleteData($(this).data('id'));
        });

        $(document).on('click', '#btn-delete-selected', function () {
            self.#handleDeleteSelected();
        });

        $(document).on('click', '#export-excel', function () {
            self.#applicationTable.DataTable().button('.buttons-excel').trigger();
        });

        $(document).on('click', '#download-template', function () {
            window.location.href = '/HITUAM/bd/master-application/download-template';
        });
    }

    #filterEvents() {
        $(document).on('change', '#entries', e => {
            const perPage = $(e.target).val();
            const table = this.#applicationTable.DataTable();

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

        this.#applicationTable.on('draw.dt', function () {
            self.#applySelectionToVisibleRows();
            self.#refreshSelectionUI();
        });

        $(document).on('change', '#select-all', async function () {
            if ($(this).is(':checked')) {
                await self.#selectAllAcrossPages();
                return;
            }

            self.#selectedApplicationIds.clear();
            self.#applySelectionToVisibleRows();
            self.#refreshSelectionUI();
        });

        $(document).on('change', 'input[name="selected[]"]', function () {
            const id = $(this).val();

            if (!id) {
                return;
            }

            if ($(this).is(':checked')) {
                self.#selectedApplicationIds.add(String(id));
            } else {
                self.#selectedApplicationIds.delete(String(id));
            }

            self.#refreshSelectionUI();
        });
    }

    async #selectAllAcrossPages() {
        this.#selectAllPromise = (async () => {
            const params = this.#applicationTable.DataTable().ajax.params() || {};
            try {
                const response = await axios.get(window.location.pathname, {
                    params: {
                        ...params,
                        start: 0,
                        length: -1
                    }
                });

                this.#selectedApplicationIds.clear();

                (response.data?.data || []).forEach((row) => {
                    if (row?.IID) {
                        this.#selectedApplicationIds.add(String(row.IID));
                    }
                });

                this.#applySelectionToVisibleRows();
                this.#refreshSelectionUI();
            } catch (error) {
                $('#select-all').prop('checked', false);
                toast.error(error.response?.data?.message || 'Failed to select all application data.');
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

        this.#deleteMultiple(Array.from(this.#selectedApplicationIds));
    }

    #applySelectionToVisibleRows() {
        $('input[name="selected[]"]').each((_, element) => {
            const $checkbox = $(element);
            $checkbox.prop('checked', this.#selectedApplicationIds.has(String($checkbox.val())));
        });
    }

    #refreshSelectionUI() {
        const visibleCheckboxes = $('input[name="selected[]"]');
        const visibleChecked = visibleCheckboxes.filter(':checked').length;
        const allVisibleChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.length === visibleChecked;

        $('#btn-delete-selected').toggleClass('d-none', this.#selectedApplicationIds.size === 0);
        $('#select-all').prop('checked', allVisibleChecked && this.#selectedApplicationIds.size > 0);
    }

    #updateQuery(params) {
        const table = this.#applicationTable.DataTable();
        const currentUrl = new URL(window.location.href);
        const searchParams = new URLSearchParams(currentUrl.search);

        // Update parameters
        for (const key in params) {
            // delete key parameters if its empty for clearance
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

    #deleteMultiple(appIds) {
        if (appIds.length === 0) {
            toast.error('No applications selected for deletion.');
            return;
        }

        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete ${appIds.length} application(s). This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete them!',
            customClass: {
                confirmButton: 'btn btn-outline-danger',
                cancelButton: 'btn btn-primary'
            }
        }).then(result => {
            if (result.isConfirmed) {
                axios.post('/HITUAM/bd/master-application/delete-multiple', {
                    ids: appIds
                })
                    .then(response => {
                        toast.success(response.data.message);
                        appIds.forEach((id) => this.#selectedApplicationIds.delete(String(id)));
                        this.#applicationTable.DataTable().ajax.reload();
                    })
                    .catch(error => {
                        toast.error(error.response.data.message);
                    });
            }
        });
    }

    #deleteData(appId) {
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
                    .delete(`HITUAM/bd/master-application/${appId}`)
                    .then(response => {
                        toast.success(response.data.message);
                        this.#selectedApplicationIds.delete(String(appId));
                        this.#applicationTable.DataTable().ajax.reload();
                    })
                    .catch(error => {
                        toast.error(error.response.data.message);
                    });
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    new Application().init();
});
