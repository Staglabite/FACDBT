import MenuForm from './menu-form';
import axios from 'axios';
import { toast } from '../../../../helpers';
import Swal from 'sweetalert2';

class Menu {
    #menuTable = $('#hituamf002-table');
    #selectedMenuIds = new Set();
    #selectAllPromise = null;

    constructor() {
        this.form = new MenuForm(() => {
            this.#menuTable.DataTable().ajax.reload();
        });
    }

    init() {
        const self = this;

        this.#menuTable.on('init.dt', function () {
            var tfoot = self.#menuTable.find('tfoot tr');
            var thead = self.#menuTable.find('thead');

            // Move the tfoot row into thead (after the header row)
            tfoot.appendTo(thead);
        });

        this.#filterEvents();
        this.#events();
        this.#parentSelect2();
        this.#applicationSelect2();
        this.#selectionEvents();
    }

    #parentSelect2() {
        $('#parent').select2({
            placeholder: 'Select menu',
            dropdownParent: $('#menu-modal'),
            allowClear: true,
            ajax: {
                url: '/general/menus', // your API route
                dataType: 'json',
                delay: 250, // delays requests for better performance
                data: function (params) {
                    return {
                        search: params.term // search term
                    };
                },
                processResults: function (response) {
                    const data = response.data;

                    return {
                        results: data.map(function (item) {
                            return {
                                id: item.VAPPID,
                                text: item.VAPPDESC
                            };
                        })
                    };
                },
                cache: true
            }
        });
    }

    #applicationSelect2() {
        $('#application').select2({
            placeholder: 'Select Application',
            dropdownParent: $('#menu-modal'),
            allowClear: true,
            ajax: {
                url: '/general/all-menus', // your API route
                dataType: 'json',
                delay: 250, // delays requests for better performance
                data: function (params) {
                    return {
                        search: params.term // search term
                    };
                },
                processResults: function (response) {
                    const data = response.data;

                    return {
                        results: data.map(function (item) {
                            return {
                                id: item.IID,
                                text: item.VPROJECTDESC
                            };
                        })
                    };
                },
                cache: true
            }
        });
    }

    #events() {
        const self = this;
        $(document).on('click', '#btn-create-menu', function (e) {
            e.preventDefault();
            self.form.openModal();
        });

        $(document).on('click', '.edit-menu', function () {
            self.form.openModal($(this).data('id'));
        });

        $(document).on('click', '.delete-menu', function () {
            self.#deleteData($(this).data('id'));
        });

        $(document).on('click', '#btn-delete-selected', function () {
            self.#handleDeleteSelected();
        });

        $(document).on('click', '#export-excel', function () {
            self.#menuTable.DataTable().button('.buttons-excel').trigger();
        });

        $(document).on('click', '#download-template', function () {
            window.location.href = '/HITUAM/bd/master-menu/download-template';
        });
    }

    #filterEvents() {
        $(document).on('change', '#entries', e => {
            const perPage = $(e.target).val();
            const table = this.#menuTable.DataTable();

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

        this.#menuTable.on('draw.dt', function () {
            self.#applySelectionToVisibleRows();
            self.#refreshSelectionUI();
        });

        $(document).on('change', '#select-all-service', async function () {
            if ($(this).is(':checked')) {
                await self.#selectAllAcrossPages();
                return;
            }

            self.#selectedMenuIds.clear();
            self.#applySelectionToVisibleRows();
            self.#refreshSelectionUI();
        });

        $(document).on('change', 'input[name="selected-service[]"]', function () {
            const id = $(this).val();

            if (!id) {
                return;
            }

            if ($(this).is(':checked')) {
                self.#selectedMenuIds.add(String(id));
            } else {
                self.#selectedMenuIds.delete(String(id));
            }

            self.#refreshSelectionUI();
        });
    }

    async #selectAllAcrossPages() {
        this.#selectAllPromise = (async () => {
            const params = this.#menuTable.DataTable().ajax.params() || {};
            try {
                const response = await axios.get(window.location.pathname, {
                    params: {
                        ...params,
                        start: 0,
                        length: -1
                    }
                });

                this.#selectedMenuIds.clear();

                (response.data?.data || []).forEach((row) => {
                    if (row?.IID) {
                        this.#selectedMenuIds.add(String(row.IID));
                    }
                });

                this.#applySelectionToVisibleRows();
                this.#refreshSelectionUI();
            } catch (error) {
                $('#select-all-service').prop('checked', false);
                toast.error(error.response?.data?.message || 'Failed to select all menu data.');
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

        this.#deleteMultiple(Array.from(this.#selectedMenuIds));
    }

    #applySelectionToVisibleRows() {
        $('input[name="selected-service[]"]').each((_, element) => {
            const $checkbox = $(element);
            $checkbox.prop('checked', this.#selectedMenuIds.has(String($checkbox.val())));
        });
    }

    #refreshSelectionUI() {
        const visibleCheckboxes = $('input[name="selected-service[]"]');
        const visibleChecked = visibleCheckboxes.filter(':checked').length;
        const allVisibleChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.length === visibleChecked;

        $('#btn-delete-selected').toggleClass('d-none', this.#selectedMenuIds.size === 0);
        $('#select-all-service').prop('checked', allVisibleChecked && this.#selectedMenuIds.size > 0);
    }

    #updateQuery(params) {
        const table = this.#menuTable.DataTable();
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

    #deleteMultiple(menuIds) {
        if (menuIds.length === 0) {
            toast.error('No menus selected for deletion.');
            return;
        }

        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete ${menuIds.length} menu(s). This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete them!',
            customClass: {
                confirmButton: 'btn btn-outline-danger',
                cancelButton: 'btn btn-primary'
            }
        }).then(result => {
            if (result.isConfirmed) {
                axios.post('/HITUAM/bd/master-menu/delete-multiple', {
                    ids: menuIds
                })
                    .then(response => {
                        toast.success(response.data.message);
                        menuIds.forEach((id) => this.#selectedMenuIds.delete(String(id)));
                        this.#menuTable.DataTable().ajax.reload();
                    })
                    .catch(error => {
                        toast.error(error.response.data.message);
                    });
            }
        });
    }

    #deleteData(menuId) {
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
                    .delete(`HITUAM/bd/master-menu/${menuId}`)
                    .then(response => {
                        toast.success(response.data.message);
                        this.#selectedMenuIds.delete(String(menuId));
                        this.#menuTable.DataTable().ajax.reload();
                    })
                    .catch(error => {
                        toast.error(error.response.data.message);
                    });
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    new Menu().init();
});
