import axios from 'axios';
import PartForm from './part-form';
import { toast } from '../../../../helpers';
import { exportTableToExcel } from '../shared/exportExcel';
import Swal from 'sweetalert2';

class Part {
    #partTable = $('#facdbtf002-table'); // ID disesuaikan dengan file DataTable
    #selectedPartIds = new Set();
    #selectAllPromise = null;
    
    constructor() {
        this.form = new PartForm(() => {
            this.#partTable.DataTable().ajax.reload();
        });
    }

    init() {
        const self = this;
        // DataTable init
        this.#partTable.on('init.dt', function () {
            var tfoot = self.#partTable.find('tfoot tr');
            var thead = self.#partTable.find('thead');

            // Move the tfoot row into thead (after the header row)
            tfoot.appendTo(thead);
        });

        this.#filterEvents();
        this.#events();
        this.#selectionEvents();
    }

    #events() {
        const self = this;
        $(document).on('click', '#btn-create-part', function (e) {
            e.preventDefault();
            self.form.openModal();
        });

        $(document).on('click', '.edit-part', function () {
            self.form.openModal($(this).data('id'));
        });

        $(document).on('click', '.delete-part', function () {
            self.#deleteData($(this).data('id'));
        });

        $(document).on('click', '#btn-delete-selected', function () {
            self.#handleDeleteSelected();
        });

        $(document).on('click', '#export-excel', function () {
            exportTableToExcel(self.#partTable);
        });

        $(document).on('click', '#download-template', function () {
            // Adjust the URL route based on your web routes for downloading the template
            window.location.href = '/FACDBT/bd/master-part/download-template';
        });
    }

    #filterEvents() {
        $(document).on('change', '#entries', e => {
            const perPage = $(e.target).val();
            const table = this.#partTable.DataTable();

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

        this.#partTable.on('draw.dt', function () {
            self.#applySelectionToVisibleRows();
            self.#refreshSelectionUI();
        });

        $(document).on('change', '#select-all', async function () {
            if ($(this).is(':checked')) {
                await self.#selectAllAcrossPages();
                return;
            }

            self.#selectedPartIds.clear();
            self.#applySelectionToVisibleRows();
            self.#refreshSelectionUI();
        });

        $(document).on('change', 'input[name="selected[]"]', function () {
            const id = $(this).val();

            if (!id) {
                return;
            }

            if ($(this).is(':checked')) {
                self.#selectedPartIds.add(String(id));
            } else {
                self.#selectedPartIds.delete(String(id));
            }

            self.#refreshSelectionUI();
        });
    }

    async #selectAllAcrossPages() {
        this.#selectAllPromise = (async () => {
            const params = this.#partTable.DataTable().ajax.params() || {};
            try {
                const response = await axios.get(window.location.pathname, {
                    params: {
                        ...params,
                        start: 0,
                        length: -1
                    }
                });

                this.#selectedPartIds.clear();

                (response.data?.data || []).forEach((row) => {
                    // Update field key based on the primary key for the part model
                    if (row?.part_no) { 
                        this.#selectedPartIds.add(String(row.part_no));
                    }
                });

                this.#applySelectionToVisibleRows();
                this.#refreshSelectionUI();
            } catch (error) {
                $('#select-all').prop('checked', false);
                toast.error(error.response?.data?.message || 'Failed to select all part data.');
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

        this.#deleteMultiple(Array.from(this.#selectedPartIds));
    }

    #applySelectionToVisibleRows() {
        $('input[name="selected[]"]').each((_, element) => {
            const $checkbox =$(element);
            $checkbox.prop('checked', this.#selectedPartIds.has(String($checkbox.val())));
        });
    }

    #refreshSelectionUI() {
        const visibleCheckboxes = $('input[name="selected[]"]');
        const visibleChecked = visibleCheckboxes.filter(':checked').length;
        const allVisibleChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.length === visibleChecked;

        $('#btn-delete-selected').toggleClass('d-none', this.#selectedPartIds.size === 0);
        $('#select-all').prop('checked', allVisibleChecked && this.#selectedPartIds.size > 0);
    }

    #updateQuery(params) {
        const table = this.#partTable.DataTable();
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

    #deleteMultiple(partIds) {
        if (partIds.length === 0) {
            toast.error('No parts selected for deletion.');
            return;
        }

        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete ${partIds.length} part(s). This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete them!',
            customClass: {
                confirmButton: 'btn btn-outline-danger',
                cancelButton: 'btn btn-primary'
            }
        }).then(result => {
            if (result.isConfirmed) {
                // Adjust the post endpoint route based on your application
                axios.post('/FACDBT/bd/master-part/delete-multiple', {
                    ids: partIds
                })
                    .then(response => {
                        toast.success(response.data.message);
                        partIds.forEach((id) => this.#selectedPartIds.delete(String(id)));
                        this.#partTable.DataTable().ajax.reload();
                    })
                    .catch(error => {
                        toast.error(error.response.data.message);
                    });
            }
        });
    }

    #deleteData(partId) {
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
                // Adjust the delete endpoint route based on your application
                axios
                    .delete(`/FACDBT/bd/master-part/${partId}`)
                    .then(response => {
                        toast.success(response.data.message);
                        this.#selectedPartIds.delete(String(partId));
                        this.#partTable.DataTable().ajax.reload();
                    })
                    .catch(error => {
                        toast.error(error.response.data.message);
                    });
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    new Part().init();
});