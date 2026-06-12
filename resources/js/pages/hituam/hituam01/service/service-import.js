import axios from "axios";
import { toast } from "../../../../helpers";

class serviceImport {
    #serviceEndpoint = 'HITUAM/bd/master-service';
    #serviceModalImport = new bootstrap.Modal(document.getElementById('service-import-modal'));
    #importErrorModal = new bootstrap.Modal(document.getElementById('error-import-modal'));
    #serviceFormImport = document.getElementById('service-import');
    #errorTable = null;

    async init() {
        this.#events();
        this.#initErrorTable();
    }

    #events() {
        $(document).on('click', '#import-excel', e => {
            e.preventDefault();
            this.#openModalImport();
        });

        $(document).on('click', '#btn-submit-import', e => {
            e.preventDefault();
            this.#submitFormImport();
        });

        $('#error-import-modal').on('shown.bs.modal', () => {
            if (this.#errorTable) {
                this.#errorTable.columns.adjust().draw(false);
            }
        });
    }

    #openModalImport() {
        this.#serviceModalImport.show();
        this.#serviceFormImport.reset();
    }

    #openImportErrorModal(data) {
        this.#serviceModalImport.hide();
        this.#importErrorModal.show();

        const errorData = data.error_data.map(item => ({
            ...item,
            errors: `<ul class="text-danger">${item.errors.map(err => `<li>${err}</li>`).join('')}</ul>`
        }));

        this.#errorTable.clear().rows.add(errorData).draw();
    }

    #submitFormImport() {
        const formData = new FormData(this.#serviceFormImport);
        axios.post(this.#serviceEndpoint + '/import', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        }).then(response => {
            $('#hituamf004-table').DataTable().ajax.reload();
            toast.success(response.data.message);

            this.#serviceModalImport.hide();
        }).catch(error => {
            if (error.response && error.response.status === 400) {
                this.#openImportErrorModal(error.response.data.data);
            } else {
                toast.error(error.response.data.message);
                this.#serviceFormImport.reset();
            }
        });
    }

    #initErrorTable() {
        this.#errorTable = $('#import-error-table').DataTable({
            data: [],
            columns: [
                { title: 'Service Name', data: 'service_name' },
                { title: 'Service Description', data: 'service_description' },
                { title: 'Service URL', data: 'service_url' },
                { title: 'Http Method', data: 'http_method' },
                { title: 'Menu Name', data: 'menu_name' },
                { title: 'Begin Effective Date', data: 'begin_effective_date' },
                { title: 'End Effective Date', data: 'end_effective_date' },
                { title: 'Errors', data: 'errors', className: 'dt-nowrap' }
            ],
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            paging: true,
            autoWidth: false,
            pagingType: 'full_numbers'
        });
    }
}

new serviceImport().init();
