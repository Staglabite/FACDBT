import axios from 'axios';
import { toast } from '../../../../helpers';

class PartImport {
    #partEndpoint = '/FACDBT/bd/master-part';
    #partModalImport = new window.bootstrap.Modal(document.getElementById('part-import-modal'));
    #importErrorModal = new window.bootstrap.Modal(document.getElementById('part-error-import-modal'));
    #partFormImport = document.getElementById('part-import');
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

        $(document).on('click', '#btn-submit-part-import', e => {
            e.preventDefault();
            this.#submitFormImport();
        });

        $('#part-error-import-modal').on('shown.bs.modal', () => {
            if (this.#errorTable) {
                this.#errorTable.columns.adjust().draw(false);
            }
        });
    }

    #openModalImport() {
        this.#partModalImport.show();
        this.#partFormImport.reset();
    }

    #openImportErrorModal(data) {
        this.#partModalImport.hide();
        this.#importErrorModal.show();

        const errorData = data.error_data.map(item => ({
            part_no: item.part_no ?? '',
            inventory_part_description: item.inventory_part_description ?? '',
            accounting_group: item.accounting_group ?? '',
            seg: item.seg ?? '',
            prod_group: item.prod_group ?? '',
            part_group: item.part_group ?? '',
            errors: `<ul class="text-danger">${item.errors.map(err => `<li>${err}</li>`).join('')}</ul>`
        }));

        this.#errorTable.clear().rows.add(errorData).draw();
    }

    #submitFormImport() {
        const formData = new FormData(this.#partFormImport);

        axios.post(this.#partEndpoint + '/import', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        }).then(response => {
            $('#facdbtf002-table').DataTable().ajax.reload(); // Memperbarui datatable utama Master Part
            toast.success(response.data.message);
            this.#partModalImport.hide();
        }).catch(error => {
            if (error.response && error.response.status === 400) {
                this.#openImportErrorModal(error.response.data.data);
            } else {
                toast.error(error.response?.data?.message ?? 'Failed to import part data.');
                this.#partFormImport.reset();
            }
        });
    }

    #initErrorTable() {
        this.#errorTable = $('#part-import-error-table').DataTable({
            data: [],
            columns: [
                { title: 'Part No', data: 'part_no', defaultContent: '' },
                { title: 'Description', data: 'inventory_part_description', defaultContent: '' },
                { title: 'Accounting Group', data: 'accounting_group', defaultContent: '' },
                { title: 'Seg', data: 'seg', defaultContent: '' },
                { title: 'Prod Group', data: 'prod_group', defaultContent: '' },
                { title: 'Part Group', data: 'part_group', defaultContent: '' },
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
    new PartImport().init();
});