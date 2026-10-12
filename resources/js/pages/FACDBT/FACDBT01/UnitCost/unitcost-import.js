import axios from 'axios';
import { toast } from '../../../../helpers';

class UnitCostImport {
    #unitCostEndpoint = '/FACDBT/bd/master-unitcost';
    #unitCostModalImport = new window.bootstrap.Modal(document.getElementById('unitcost-import-modal'));
    #importErrorModal = new window.bootstrap.Modal(document.getElementById('unitcost-error-import-modal'));
    #unitCostFormImport = document.getElementById('unitcost-import');
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

        $(document).on('click', '#btn-submit-unitcost-import', e => {
            e.preventDefault();
            this.#submitFormImport();
        });

        $('#unitcost-error-import-modal').on('shown.bs.modal', () => {
            if (this.#errorTable) {
                this.#errorTable.columns.adjust().draw(false);
            }
        });
    }

    #openModalImport() {
        this.#unitCostModalImport.show();
        this.#unitCostFormImport.reset();
    }

    #openImportErrorModal(data) {
        this.#unitCostModalImport.hide();
        this.#importErrorModal.show();

        const errorData = data.error_data.map(item => ({
            part_no: item.part_no ?? '',
            inventory_part_description: item.inventory_part_description ?? '',
            inventory_uom: item.inventory_uom ?? '',
            accounting_group: item.accounting_group ?? '',
            on_hand_qty: item.on_hand_qty ?? '',
            mat_cost: item.mat_cost ?? '',
            lab_cost: item.lab_cost ?? '',
            foh_cost: item.foh_cost ?? '',
            errors: `<ul class="text-danger">${item.errors.map(err => `<li>${err}</li>`).join('')}</ul>`
        }));

        this.#errorTable.clear().rows.add(errorData).draw();
    }

    #submitFormImport() {
        const formData = new FormData(this.#unitCostFormImport);

        axios.post(this.#unitCostEndpoint + '/import', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        }).then(response => {
            $('#facdbtf003-table').DataTable().ajax.reload(); // Memperbarui datatable utama Master Unit Cost
            toast.success(response.data.message);
            this.#unitCostModalImport.hide();
        }).catch(error => {
            if (error.response && error.response.status === 400) {
                this.#openImportErrorModal(error.response.data.data);
            } else {
                toast.error(error.response?.data?.message ?? 'Failed to import unit cost data.');
                this.#unitCostFormImport.reset();
            }
        });
    }

    #initErrorTable() {
        this.#errorTable = $('#unitcost-import-error-table').DataTable({
            data: [],
            columns: [
                { title: 'Part No', data: 'part_no', defaultContent: '' },
                { title: 'Description', data: 'inventory_part_description', defaultContent: '' },
                { title: 'UOM', data: 'inventory_uom', defaultContent: '' },
                { title: 'Accounting Group', data: 'accounting_group', defaultContent: '' },
                { title: 'On Hand Qty', data: 'on_hand_qty', defaultContent: '' },
                { title: 'Material Cost', data: 'mat_cost', defaultContent: '' },
                { title: 'Labor Cost', data: 'lab_cost', defaultContent: '' },
                { title: 'FOH Cost', data: 'foh_cost', defaultContent: '' },
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
    new UnitCostImport().init();
});