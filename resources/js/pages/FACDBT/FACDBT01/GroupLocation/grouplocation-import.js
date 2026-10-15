import axios from 'axios';
import { toast } from '../../../../helpers';

class GroupLocationImport {
    #groupLocationEndpoint = '/FACDBT/bd/master-grouplocation';
    #groupLocationModalImport = new bootstrap.Modal(document.getElementById('group-location-import-modal'));
    #importErrorModal = new bootstrap.Modal(document.getElementById('group-location-error-import-modal'));
    #groupLocationFormImport = document.getElementById('group-location-import');
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

        $(document).on('click', '#btn-submit-group-location-import', e => {
            e.preventDefault();
            this.#submitFormImport();
        });

        $('#group-location-error-import-modal').on('shown.bs.modal', () => {
            if (this.#errorTable) {
                this.#errorTable.columns.adjust().draw(false);
            }
        });
    }

    #openModalImport() {
        this.#groupLocationModalImport.show();
        this.#groupLocationFormImport.reset();
    }

    #openImportErrorModal(data) {
        this.#groupLocationModalImport.hide();
        this.#importErrorModal.show();

        const errorData = data.error_data.map(item => ({
            location_no: item.location_no ?? '',
            id_loc: item.id_loc ?? '',
            location_group: item.location_group ?? '',
            stream: item.stream ?? '',
            errors: `<ul class="text-danger">${item.errors.map(err => `<li>${err}</li>`).join('')}</ul>`
        }));

        this.#errorTable.clear().rows.add(errorData).draw();
    }

    #submitFormImport() {
        const formData = new FormData(this.#groupLocationFormImport);

        axios.post(this.#groupLocationEndpoint + '/import', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        }).then(response => {
            $('#facdbtf005-table').DataTable().ajax.reload(); // Memperbarui datatable utama Master Group Location
            toast.success(response.data.message);
            this.#groupLocationModalImport.hide();
        }).catch(error => {
            if (error.response && error.response.status === 400) {
                this.#openImportErrorModal(error.response.data.data);
            } else {
                toast.error(error.response?.data?.message ?? 'Failed to import group location data.');
                this.#groupLocationFormImport.reset();
            }
        });
    }

    #initErrorTable() {
        this.#errorTable = $('#group-location-import-error-table').DataTable({
            data: [],
            columns: [
                { title: 'Location No', data: 'location_no', defaultContent: '' },
                { title: 'ID Loc', data: 'id_loc', defaultContent: '' },
                { title: 'Location Group', data: 'location_group', defaultContent: '' },
                { title: 'Stream', data: 'stream', defaultContent: '' },
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
    new GroupLocationImport().init();
});