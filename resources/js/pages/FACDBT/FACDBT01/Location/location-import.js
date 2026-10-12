import axios from 'axios';
import { toast } from '../../../../helpers';

class LocationImport {
    #locationEndpoint = '/FACDBT/bd/master-location';
    #locationModalImport = new bootstrap.Modal(document.getElementById('location-import-modal'));
    #importErrorModal = new bootstrap.Modal(document.getElementById('location-error-import-modal'));
    #locationFormImport = document.getElementById('location-import');
    #errorTable = null;

    init() {
        this.#events();
        this.#initErrorTable();
    }

    #events() {
        $(document).on('click', '#import-excel', e => {
            e.preventDefault();
            this.#openModalImport();
        });

        $(document).on('click', '#btn-submit-location-import', e => {
            e.preventDefault();
            this.#submitFormImport();
        });

        $('#location-error-import-modal').on('shown.bs.modal', () => {
            if (this.#errorTable) {
                this.#errorTable.columns.adjust().draw(false);
            }
        });
    }

    #openModalImport() {
        this.#locationModalImport.show();
        this.#locationFormImport.reset();
    }

    #openImportErrorModal(data) {
        this.#locationModalImport.hide();
        this.#importErrorModal.show();

        const errorData = data.error_data.map(item => ({
            location_no: item.location_no ?? '',
            location_group: item.location_group ?? '',
            site: item.site ?? '',
            warehouse: item.warehouse ?? '',
            location_description: item.location_description ?? '',
            location_group_description: item.location_group_description ?? '',
            errors: `<ul class="text-danger">${item.errors.map(err => `<li>${err}</li>`).join('')}</ul>`
        }));

        this.#errorTable.clear().rows.add(errorData).draw();
    }

    #submitFormImport() {
        const formData = new FormData(this.#locationFormImport);

        axios.post(this.#locationEndpoint + '/import', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        }).then(response => {
            $('#facdbtf001-table').DataTable().ajax.reload(); // Memperbarui datatable utama
            toast.success(response.data.message);
            this.#locationModalImport.hide();
        }).catch(error => {
            if (error.response && error.response.status === 400) {
                this.#openImportErrorModal(error.response.data.data);
            } else {
                toast.error(error.response?.data?.message ?? 'Failed to import location data.');
                this.#locationFormImport.reset();
            }
        });
    }

    #initErrorTable() {
        this.#errorTable = $('#location-import-error-table').DataTable({
            data: [],
            columns: [
                { title: 'Location No', data: 'location_no', defaultContent: '' },
                { title: 'Location Group', data: 'location_group', defaultContent: '' },
                { title: 'Site', data: 'site', defaultContent: '' },
                { title: 'Warehouse', data: 'warehouse', defaultContent: '' },
                { title: 'Description', data: 'location_description', defaultContent: '' },
                { title: 'Group Description', data: 'location_group_description', defaultContent: '' },
                { title: 'Errors', data: 'errors', className: 'dt-nowrap', defaultContent: '' }
            ],
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            paging: true,
            autoWidth: false,
            pagingType: 'full_numbers'
        });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    new LocationImport().init();
});