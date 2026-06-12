import axios from 'axios';
import { toast } from '../../../../helpers';

class ApplicationImport {
    #applicationEndpoint = 'HITUAM/bd/master-application';
    #applicationModalImport = new bootstrap.Modal(document.getElementById('application-import-modal'));
    #importErrorModal = new bootstrap.Modal(document.getElementById('application-error-import-modal'));
    #applicationFormImport = document.getElementById('application-import');
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

        $(document).on('click', '#btn-submit-application-import', e => {
            e.preventDefault();
            this.#submitFormImport();
        });

        $('#application-error-import-modal').on('shown.bs.modal', () => {
            if (this.#errorTable) {
                this.#errorTable.columns.adjust().draw(false);
            }
        });
    }

    #openModalImport() {
        this.#applicationModalImport.show();
        this.#applicationFormImport.reset();
    }

    #openImportErrorModal(data) {
        this.#applicationModalImport.hide();
        this.#importErrorModal.show();

        const errorData = data.error_data.map(item => ({
            code: item.code ?? '',
            description: item.description ?? '',
            prefix: item.prefix ?? '',
            pic: item.pic ?? '',
            portal_name: item.portal_name ?? '',
            operational: item.operational ?? '',
            standardization: item.standardization ?? '',
            portal_access: item.portal_access ?? '',
            host: item.host ?? item.url ?? '',
            publish: item.publish ?? '',
            database: item.database ?? '',
            order: item.order ?? '',
            icon: item.icon ?? '',
            errors: `<ul class="text-danger">${item.errors.map(err => `<li>${err}</li>`).join('')}</ul>`
        }));

        this.#errorTable.clear().rows.add(errorData).draw();
    }

    #submitFormImport() {
        const formData = new FormData(this.#applicationFormImport);

        axios.post(this.#applicationEndpoint + '/import', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        }).then(response => {
            $('#hituamf001-table').DataTable().ajax.reload();
            toast.success(response.data.message);
            this.#applicationModalImport.hide();
        }).catch(error => {
            if (error.response && error.response.status === 400) {
                this.#openImportErrorModal(error.response.data.data);
            } else {
                toast.error(error.response?.data?.message ?? 'Failed to import application data.');
                this.#applicationFormImport.reset();
            }
        });
    }

    #initErrorTable() {
        this.#errorTable = $('#application-import-error-table').DataTable({
            data: [],
            columns: [
                { title: 'Code', data: 'code', defaultContent: '' },
                { title: 'Description', data: 'description', defaultContent: '' },
                { title: 'Prefix', data: 'prefix', defaultContent: '' },
                { title: 'PIC', data: 'pic', defaultContent: '' },
                { title: 'Portal Name', data: 'portal_name', defaultContent: '' },
                { title: 'Operational', data: 'operational', defaultContent: '' },
                { title: 'Standardization', data: 'standardization', defaultContent: '' },
                { title: 'Portal Access', data: 'portal_access', defaultContent: '' },
                { title: 'VHOST', data: 'host', defaultContent: '' },
                { title: 'Publish', data: 'publish', defaultContent: '' },
                { title: 'Database', data: 'database', defaultContent: '' },
                { title: 'Order', data: 'order', defaultContent: '' },
                { title: 'Icon', data: 'icon', defaultContent: '' },
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
    new ApplicationImport().init();
});
