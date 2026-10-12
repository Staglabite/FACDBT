import axios from 'axios';
import TakingBookForm from './takingbook-form';
import { toast } from '../../../../helpers';
import Swal from 'sweetalert2';

class TakingBook {
    #takingBookTable = $('#facdbtf004-table'); // ID disesuaikan dengan file DataTable FACDBT004
    #selectedTakingBookIds = new Set();
    #selectAllPromise = null;
    
    constructor() {
        this.form = new TakingBookForm(() => {
            this.#takingBookTable.DataTable().ajax.reload();
        });
    }

    init() {
        const self = this;
        // DataTable init
        this.#takingBookTable.on('init.dt', function () {
            var tfoot = self.#takingBookTable.find('tfoot tr');
            var thead = self.#takingBookTable.find('thead');

            // Move the tfoot row into thead (after the header row)
            tfoot.appendTo(thead);
        });

        this.#filterEvents();
        this.#events();
        this.#selectionEvents();
    }

    #events() {
        const self = this;
        $(document).on('click', '#btn-create-takingbook', function (e) {
            e.preventDefault();
            self.form.openModal();
        });

        $(document).on('click', '.edit-takingbook', function () {
            self.form.openModal($(this).data('id'));
        });

        $(document).on('click', '.delete-takingbook', function () {
            self.#deleteData($(this).data('id'));
        });

        $(document).on('click', '#btn-delete-selected', function () {
            self.#handleDeleteSelected();
        });

        // $(document).on('click', '#export-excel', function () {
        //     self.#takingBookTable.DataTable().button('.buttons-excel').trigger();
        // });

        $(document).on('click', '#download-template', function () {
            // Sesuaikan route export dengan routes web.php untuk Taking Book
            window.location.href = '/FACDBT/bd/master-takingbook/download-template';
        });
    }

    #filterEvents() {
        $(document).on('change', '#entries', e => {
            const perPage = $(e.target).val();
            const table = this.#takingBookTable.DataTable();

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

        this.#takingBookTable.on('draw.dt', function () {
            self.#applySelectionToVisibleRows();
            self.#refreshSelectionUI();
        });

        $(document).on('change', '#select-all', async function () {
            if ($(this).is(':checked')) {
                await self.#selectAllAcrossPages();
                return;
            }

            self.#selectedTakingBookIds.clear();
            self.#applySelectionToVisibleRows();
            self.#refreshSelectionUI();
        });

        $(document).on('change', 'input[name="selected[]"]', function () {
            const id = $(this).val();

            if (!id) {
                return;
            }

            if ($(this).is(':checked')) {
                self.#selectedTakingBookIds.add(String(id));
            } else {
                self.#selectedTakingBookIds.delete(String(id));
            }

            self.#refreshSelectionUI();
        });
    }

    async #selectAllAcrossPages() {
        this.#selectAllPromise = (async () => {
            const params = this.#takingBookTable.DataTable().ajax.params() || {};
            try {
                const response = await axios.get(window.location.pathname, {
                    params: {
                        ...params,
                        start: 0,
                        length: -1
                    }
                });

                this.#selectedTakingBookIds.clear();

                (response.data?.data || []).forEach((row) => {
                    // Update field key based on the primary key for the Taking Book model (INV_LIST_NO)
                    if (row?.INV_LIST_NO) { 
                        this.#selectedTakingBookIds.add(String(row.INV_LIST_NO));
                    }
                });

                this.#applySelectionToVisibleRows();
                this.#refreshSelectionUI();
            } catch (error) {
                $('#select-all').prop('checked', false);
                toast.error(error.response?.data?.message || 'Failed to select all taking book data.');
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

        this.#deleteMultiple(Array.from(this.#selectedTakingBookIds));
    }

    #applySelectionToVisibleRows() {
        $('input[name="selected[]"]').each((_, element) => {
            const $checkbox =$(element);
            $checkbox.prop('checked', this.#selectedTakingBookIds.has(String($checkbox.val())));
        });
    }

    #refreshSelectionUI() {
        const visibleCheckboxes = $('input[name="selected[]"]');
        const visibleChecked = visibleCheckboxes.filter(':checked').length;
        const allVisibleChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.length === visibleChecked;

        $('#btn-delete-selected').toggleClass('d-none', this.#selectedTakingBookIds.size === 0);
        $('#select-all').prop('checked', allVisibleChecked && this.#selectedTakingBookIds.size > 0);
    }

    #updateQuery(params) {
        const table = this.#takingBookTable.DataTable();
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

    #deleteMultiple(takingBookIds) {
        if (takingBookIds.length === 0) {
            toast.error('No taking book data selected for deletion.');
            return;
        }

        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete ${takingBookIds.length} data. This action cannot be undone!`,
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
                axios.post('/FACDBT/bd/master-takingbook/delete-multiple', {
                    ids: takingBookIds
                })
                    .then(response => {
                        toast.success(response.data.message);
                        takingBookIds.forEach((id) => this.#selectedTakingBookIds.delete(String(id)));
                        this.#takingBookTable.DataTable().ajax.reload();
                    })
                    .catch(error => {
                        toast.error(error.response.data.message);
                    });
            }
        });
    }

    #deleteData(takingBookId) {
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
                    .delete(`/FACDBT/bd/master-takingbook/${takingBookId}`)
                    .then(response => {
                        toast.success(response.data.message);
                        this.#selectedTakingBookIds.delete(String(takingBookId));
                        this.#takingBookTable.DataTable().ajax.reload();
                    })
                    .catch(error => {
                        toast.error(error.response.data.message);
                    });
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    new TakingBook().init();
});