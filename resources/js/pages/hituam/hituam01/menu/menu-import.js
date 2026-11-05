import axios from 'axios';
import { toast } from '../../../../helpers';

class MenuImport {
    #menuEndpoint = 'HITUAM/bd/master-menu';
    #menuModalImport = new bootstrap.Modal(document.getElementById('menu-import-modal'));
    #importErrorModal = new bootstrap.Modal(document.getElementById('menu-error-import-modal'));
    #menuFormImport = document.getElementById('menu-import');
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

        $(document).on('click', '#btn-submit-menu-import', e => {
            e.preventDefault();
            this.#submitFormImport();
        });

        $('#menu-error-import-modal').on('shown.bs.modal', () => {
            if (this.#errorTable) {
                this.#errorTable.columns.adjust().draw(false);
            }
        });
    }

    #openModalImport() {
        this.#menuModalImport.show();
        this.#menuFormImport.reset();
    }

    #openImportErrorModal(data) {
        this.#menuModalImport.hide();
        this.#importErrorModal.show();

        const errorData = data.error_data.map(item => ({
            app_id: item.app_id ?? '',
            name: item.name ?? '',
            description: item.description ?? '',
            flag: item.flag ?? '',
            url: item.url ?? '',
            icon: item.icon ?? '',
            order: item.order ?? '',
            parent_menu: item.parent_menu ?? '',
            application_name: item.application_name ?? '',
            type: item.type ?? '',
            errors: `<ul class="text-danger">${item.errors.map(err => `<li>${err}</li>`).join('')}</ul>`
        }));

        this.#errorTable.clear().rows.add(errorData).draw();
    }

    #submitFormImport() {
        const formData = new FormData(this.#menuFormImport);

        axios.post(this.#menuEndpoint + '/import', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        }).then(response => {
            $('#hituamf002-table').DataTable().ajax.reload();
            toast.success(response.data.message);

            this.#menuModalImport.hide();
        }).catch(error => {
            if (error.response && error.response.status === 400) {
                this.#openImportErrorModal(error.response.data.data);
            } else {
                toast.error(error.response?.data?.message ?? 'Failed to import menu data.');
                this.#menuFormImport.reset();
            }
        });
    }

    #initErrorTable() {
        this.#errorTable = $('#menu-import-error-table').DataTable({
            data: [],
            columns: [
                { title: 'App ID', data: 'app_id', defaultContent: '' },
                { title: 'Name', data: 'name', defaultContent: '' },
                { title: 'Description', data: 'description', defaultContent: '' },
                { title: 'Flag', data: 'flag', defaultContent: '' },
                { title: 'URL', data: 'url', defaultContent: '' },
                { title: 'Icon', data: 'icon', defaultContent: '' },
                { title: 'Order', data: 'order', defaultContent: '' },
                { title: 'Parent Menu', data: 'parent_menu', defaultContent: '' },
                { title: 'Application Name', data: 'application_name', defaultContent: '' },
                { title: 'Type', data: 'type', defaultContent: '' },
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
    new MenuImport().init();
});
