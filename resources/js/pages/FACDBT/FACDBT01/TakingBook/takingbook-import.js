import axios from 'axios';
import { toast } from '../../../../helpers';

class TakingBookImport {
    #takingBookEndpoint = '/FACDBT/bd/master-takingbook';
    #takingBookModalImport = new window.bootstrap.Modal(document.getElementById('takingbook-import-modal'));
    #importErrorModal = new window.bootstrap.Modal(document.getElementById('takingbook-error-import-modal'));
    #takingBookFormImport = document.getElementById('takingbook-import');
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

        $(document).on('click', '#btn-submit-takingbook-import', e => {
            e.preventDefault();
            this.#submitFormImport();
        });

        $('#takingbook-error-import-modal').on('shown.bs.modal', () => {
            if (this.#errorTable) {
                this.#errorTable.columns.adjust().draw(false);
            }
        });
    }

    #openModalImport() {
        this.#takingBookModalImport.show();
        this.#takingBookFormImport.reset();
    }

    #openImportErrorModal(data) {
        this.#takingBookModalImport.hide();
        this.#importErrorModal.show();

        const errorData = data.error_data.map(item => ({
            INV_LIST_NO: item.INV_LIST_NO ?? '',
            SEQ: item.SEQ ?? '',
            PART_NO: item.PART_NO ?? '',
            LOCATION_NO: item.LOCATION_NO ?? '',
            LOT_BATCH_NO: item.LOT_BATCH_NO ?? '',
            QUANTITY: item.QUANTITY ?? '',
            UNIT_COST: item.UNIT_COST ?? '',
            errors: `<ul class="text-danger">${item.errors.map(err => `<li>${err}</li>`).join('')}</ul>`
        }));

        this.#errorTable.clear().rows.add(errorData).draw();
    }

    #submitFormImport() {
        const formData = new FormData(this.#takingBookFormImport);

        axios.post(this.#takingBookEndpoint + '/import', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        }).then(response => {
            $('#facdbtf004-table').DataTable().ajax.reload(); // Memperbarui datatable utama Master Taking Book
            toast.success(response.data.message);
            this.#takingBookModalImport.hide();
        }).catch(error => {
            if (error.response && error.response.status === 400) {
                this.#openImportErrorModal(error.response.data.data);
            } else {
                toast.error(error.response?.data?.message ?? 'Failed to import taking book data.');
                this.#takingBookFormImport.reset();
            }
        });
    }

    #initErrorTable() {
        this.#errorTable = $('#takingbook-import-error-table').DataTable({
            data: [],
            columns: [
                { title: 'Inv List No', data: 'INV_LIST_NO', defaultContent: '' },
                { title: 'Seq', data: 'SEQ', defaultContent: '' },
                { title: 'Part No', data: 'PART_NO', defaultContent: '' },
                { title: 'Location No', data: 'LOCATION_NO', defaultContent: '' },
                { title: 'Lot/Batch No', data: 'LOT_BATCH_NO', defaultContent: '' },
                { title: 'Quantity', data: 'QUANTITY', defaultContent: '' },
                { title: 'Unit Cost', data: 'UNIT_COST', defaultContent: '' },
                { title: 'Errors', data: 'errors', className: 'dt-nowrap', defaultContent: '' }
            ],
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            paging: true,
            autoWidth: false,
            scrollX: true, // Ditambahkan agar tabel tidak rusak jika kolom terlalu lebar
            pagingType: 'full_numbers'
        });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    new TakingBookImport().init();
});