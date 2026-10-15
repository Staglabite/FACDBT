import axios from 'axios';
import UnitCostForm from './unitcost-form';
import { toast } from '../../../../helpers';
import { exportTableToExcel } from '../shared/exportExcel';
import Swal from 'sweetalert2';

class UnitCost {
    #unitCostTable = $('#facdbtf003-table'); // ID disesuaikan dengan file DataTable FACDBT003
    #selectedUnitCostIds = new Set();
    #selectAllPromise = null;
    
    constructor() {
        this.form = new UnitCostForm(() => {
            this.#unitCostTable.DataTable().ajax.reload();
        });
    }

    init() {
        const self = this;
        // DataTable init
        this.#unitCostTable.on('init.dt', function () {
            var tfoot = self.#unitCostTable.find('tfoot tr');
            var thead = self.#unitCostTable.find('thead');

            // Move the tfoot row into thead (after the header row)
            tfoot.appendTo(thead);
        });

        this.#filterEvents();
        this.#events();
        this.#selectionEvents();
    }

    #events() {
        const self = this;
        $(document).on('click', '#btn-create-unitcost', function (e) {
            e.preventDefault();
            self.form.openModal();
        });

        $(document).on('click', '.edit-unitcost', function () {
            self.form.openModal($(this).data('id'));
        });

        $(document).on('click', '.delete-unitcost', function () {
            self.#deleteData($(this).data('id'));
        });

        $(document).on('click', '#btn-delete-selected', function () {
            self.#handleDeleteSelected();
        });

        $(document).on('click', '#export-excel', function () {
            exportTableToExcel(self.#unitCostTable);
        });

        $(document).on('click', '#download-template', function () {
            // Adjust the URL route based on your web routes for downloading the template
            window.location.href = '/FACDBT/bd/master-unitcost/download-template';
        });
    }

    #filterEvents() {
        $(document).on('change', '#entries', e => {
            const perPage = $(e.target).val();
            const table = this.#unitCostTable.DataTable();

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

        this.#unitCostTable.on('draw.dt', function () {
            self.#applySelectionToVisibleRows();
            self.#refreshSelectionUI();
        });

        $(document).on('change', '#select-all', async function () {
            if ($(this).is(':checked')) {
                await self.#selectAllAcrossPages();
                return;
            }

            self.#selectedUnitCostIds.clear();
            self.#applySelectionToVisibleRows();
            self.#refreshSelectionUI();
        });

        $(document).on('change', 'input[name="selected[]"]', function () {
            const id = $(this).val();

            if (!id) {
                return;
            }

            if ($(this).is(':checked')) {
                self.#selectedUnitCostIds.add(String(id));
            } else {
                self.#selectedUnitCostIds.delete(String(id));
            }

            self.#refreshSelectionUI();
        });
    }

    async #selectAllAcrossPages() {
        this.#selectAllPromise = (async () => {
            const params = this.#unitCostTable.DataTable().ajax.params() || {};
            try {
                const response = await axios.get(window.location.pathname, {
                    params: {
                        ...params,
                        start: 0,
                        length: -1
                    }
                });

                this.#selectedUnitCostIds.clear();

                (response.data?.data || []).forEach((row) => {
                    // Update field key based on the primary key for the unitcost model (part_no)
                    if (row?.part_no) { 
                        this.#selectedUnitCostIds.add(String(row.part_no));
                    }
                });

                this.#applySelectionToVisibleRows();
                this.#refreshSelectionUI();
            } catch (error) {
                $('#select-all').prop('checked', false);
                toast.error(error.response?.data?.message || 'Failed to select all unit cost data.');
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

        this.#deleteMultiple(Array.from(this.#selectedUnitCostIds));
    }

    #applySelectionToVisibleRows() {
        $('input[name="selected[]"]').each((_, element) => {
            const $checkbox =$(element);
            $checkbox.prop('checked', this.#selectedUnitCostIds.has(String($checkbox.val())));
        });
    }

    #refreshSelectionUI() {
        const visibleCheckboxes = $('input[name="selected[]"]');
        const visibleChecked = visibleCheckboxes.filter(':checked').length;
        const allVisibleChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.length === visibleChecked;

        $('#btn-delete-selected').toggleClass('d-none', this.#selectedUnitCostIds.size === 0);
        $('#select-all').prop('checked', allVisibleChecked && this.#selectedUnitCostIds.size > 0);
    }

    #updateQuery(params) {
        const table = this.#unitCostTable.DataTable();
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

    #deleteMultiple(unitCostIds) {
        if (unitCostIds.length === 0) {
            toast.error('No unit costs selected for deletion.');
            return;
        }

        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete ${unitCostIds.length} unit cost(s). This action cannot be undone!`,
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
                axios.post('/FACDBT/bd/master-unitcost/delete-multiple', {
                    ids: unitCostIds
                })
                    .then(response => {
                        toast.success(response.data.message);
                        unitCostIds.forEach((id) => this.#selectedUnitCostIds.delete(String(id)));
                        this.#unitCostTable.DataTable().ajax.reload();
                    })
                    .catch(error => {
                        toast.error(error.response.data.message);
                    });
            }
        });
    }

    #deleteData(unitCostId) {
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
                    .delete(`/FACDBT/bd/master-unitcost/${unitCostId}`)
                    .then(response => {
                        toast.success(response.data.message);
                        this.#selectedUnitCostIds.delete(String(unitCostId));
                        this.#unitCostTable.DataTable().ajax.reload();
                    })
                    .catch(error => {
                        toast.error(error.response.data.message);
                    });
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    new UnitCost().init();
});