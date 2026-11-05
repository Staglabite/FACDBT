import ServiceForm from './service-form';
import flatpickr from 'flatpickr';
import { toast } from '../../../../helpers';
import axios from 'axios';

class Service {
    #serviceTable = $('#hituamf003-table');
    #selectedServiceIds = new Set();
    #selectAllPromise = null;

    constructor() {
        this.form = new ServiceForm(() => {
            this.#serviceTable.DataTable().ajax.reload();
        });
    }

    init() {
        const self = this;

        this.#serviceTable.on('init.dt', function () {
            var tfoot = self.#serviceTable.find('tfoot tr');
            var thead = self.#serviceTable.find('thead');

            // Move the tfoot row into thead (after the header row)
            tfoot.appendTo(thead);
        });

        this.#filterEvents();
        this.#events();
        this.#applicationSelect2();
        this.#menuSelect2();
        this.#flatpickr();
        this.#selectionEvents();
    }

    #flatpickr() {
        const withTodayButton = (selector) => {
            flatpickr(selector, {
                dateFormat: 'Y-m-d',
                static: true,
                onReady: function (_, __, instance) {
                    if (instance.calendarContainer.querySelector('.flatpickr-today-button')) {
                        return;
                    }

                    const footer = document.createElement('div');
                    footer.className = 'flatpickr-footer px-2 pb-2 pt-1 d-flex justify-content-end gap-2';

                    const clearButton = document.createElement('button');
                    clearButton.type = 'button';
                    clearButton.className = 'btn btn-sm btn-outline-secondary flatpickr-clear-button';
                    clearButton.textContent = 'Clear';
                    clearButton.addEventListener('click', () => {
                        instance.clear();
                        instance.close();
                    });

                    const todayButton = document.createElement('button');
                    todayButton.type = 'button';
                    todayButton.className = 'btn btn-sm btn-primary flatpickr-today-button';
                    todayButton.textContent = 'Today';
                    todayButton.addEventListener('click', () => {
                        instance.setDate(new Date(), true);
                        instance.close();
                    });

                    footer.appendChild(clearButton);
                    footer.appendChild(todayButton);
                    instance.calendarContainer.appendChild(footer);
                }
            });
        };

        $('#service-modal').on('shown.bs.modal', function () {
            withTodayButton('#begin');
            withTodayButton('#end');
        });
    }

    #applicationSelect2() {
        $('#application').select2({
            placeholder: 'Select application',
            dropdownParent: $('#service-modal'),
            allowClear: true,
            minimumInputLength: 0,
            ajax: {
                url: '/general/applications',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        search: params.term || ''
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
                cache: false
            }
        });

        // When application changes, reset and reload menu
        $('#application').on('change', function () {
            $('#menu').val(null).trigger('change');
        });
    }

    #menuSelect2() {
        $('#menu').select2({
            placeholder: 'Select menu',
            dropdownParent: $('#service-modal'),
            allowClear: true,
            ajax: {
                url: '/general/menus', // your API route
                dataType: 'json',
                delay: 250, // delays requests for better performance
                data: function (params) {
                    const applicationId = $('#application').val();
                    return {
                        search: params.term, // search term
                        application_id: applicationId // send application ID
                    };
                },
                processResults: function (response) {
                    const data = response.data;

                    return {
                        results: data.map(function (item) {
                            return {
                                id: item.IID,
                                text: item.VAPPDESC
                            };
                        })
                    };
                },
                cache: false
            }
        });
    }

    #events() {
        const self = this;
        $(document).on('click', '#btn-create-service', function (e) {
            e.preventDefault();
            self.form.openModal();
        });

        $(document).on('click', '.edit-service', function () {
            self.form.openModal($(this).data('id'));
        });

        $(document).on('click', '.delete-service', function () {
            self.#deleteData($(this).data('id'));
        });

        $(document).on('click', '#btn-delete-selected', function () {
            self.#handleDeleteSelected();
        });

        $(document).on('click', '#download-template', function () {
            window.location.href = '/HITUAM/bd/master-service/download-template';
        });

        $(document).on('click', '#export-excel', function () {
            self.#serviceTable.DataTable().button('.buttons-excel').trigger();
        });
    }

    #deleteMultiple(serviceIds) {
        if (serviceIds.length === 0) {
            toast.error('No services selected for deletion.');
            return;
        }

        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete ${serviceIds.length} service(s). This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete them!',
            customClass: {
                confirmButton: 'btn btn-outline-danger',
                cancelButton: 'btn btn-primary'
            }
        }).then(result => {
            if (result.isConfirmed) {
                axios.post('/HITUAM/bd/master-service/delete-multiple', {
                    ids: serviceIds
                })
                    .then(response => {
                        toast.success(response.data.message);
                        serviceIds.forEach((id) => this.#selectedServiceIds.delete(String(id)));
                        this.#serviceTable.DataTable().ajax.reload();
                    })
                    .catch(error => {
                        toast.error(error.response.data.message);
                    });
            }
        });
    }

    #filterEvents() {
        $(document).on('change', '#entries', e => {
            const perPage = $(e.target).val();
            const table = this.#serviceTable.DataTable();

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

        this.#serviceTable.on('draw.dt', function () {
            self.#applySelectionToVisibleRows();
            self.#refreshSelectionUI();
        });

        $(document).on('change', '#select-all-service', async function () {
            if ($(this).is(':checked')) {
                await self.#selectAllAcrossPages();
                return;
            }

            self.#selectedServiceIds.clear();
            self.#applySelectionToVisibleRows();
            self.#refreshSelectionUI();
        });

        $(document).on('change', 'input[name="selected-service[]"]', function () {
            const id = $(this).val();

            if (!id) {
                return;
            }

            if ($(this).is(':checked')) {
                self.#selectedServiceIds.add(String(id));
            } else {
                self.#selectedServiceIds.delete(String(id));
            }

            self.#refreshSelectionUI();
        });
    }

    async #selectAllAcrossPages() {
        this.#selectAllPromise = (async () => {
            const params = this.#serviceTable.DataTable().ajax.params() || {};
            try {
                const response = await axios.get(window.location.pathname, {
                    params: {
                        ...params,
                        start: 0,
                        length: -1
                    }
                });

                this.#selectedServiceIds.clear();

                (response.data?.data || []).forEach((row) => {
                    if (row?.IID) {
                        this.#selectedServiceIds.add(String(row.IID));
                    }
                });

                this.#applySelectionToVisibleRows();
                this.#refreshSelectionUI();
            } catch (error) {
                $('#select-all-service').prop('checked', false);
                toast.error(error.response?.data?.message || 'Failed to select all service data.');
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

        this.#deleteMultiple(Array.from(this.#selectedServiceIds));
    }

    #applySelectionToVisibleRows() {
        $('input[name="selected-service[]"]').each((_, element) => {
            const $checkbox = $(element);
            $checkbox.prop('checked', this.#selectedServiceIds.has(String($checkbox.val())));
        });
    }

    #refreshSelectionUI() {
        const visibleCheckboxes = $('input[name="selected-service[]"]');
        const visibleChecked = visibleCheckboxes.filter(':checked').length;
        const allVisibleChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.length === visibleChecked;

        $('#btn-delete-selected').toggleClass('d-none', this.#selectedServiceIds.size === 0);
        $('#select-all-service').prop('checked', allVisibleChecked && this.#selectedServiceIds.size > 0);
    }

    #updateQuery(params) {
        const table = this.#serviceTable.DataTable();
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

    #deleteData(serviceId) {
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
                axios.delete(`HITUAM/bd/master-service/${serviceId}`)
                    .then(response => {
                        toast.success(response.data.message);
                        this.#selectedServiceIds.delete(String(serviceId));
                        this.#serviceTable.DataTable().ajax.reload();
                    })
                    .catch(error => {
                        toast.error(error.response.data.message);
                    });
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    new Service().init();
});
