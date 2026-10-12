import axios from 'axios';
import { _formToJson, _showInvalidError, toast } from '../../../../helpers';

export default class TakingBookForm {
    // global variables
    #takingBookId = null; // using INV_LIST_NO as the primary identifier (for now)
    #takingBookModal = new window.bootstrap.Modal(document.getElementById('takingbook-modal'));
    #takingBookForm = document.getElementById('takingbook-form');
    #onSuccessCallback = null;
    
    // Endpoint disesuaikan dengan route Taking Book
    #takingBookEndpoint = '/FACDBT/bd/master-takingbook';

    constructor(onSuccessCallback = null) {
        this.#onSuccessCallback = onSuccessCallback;
        this.#events();
    }

    openModal(takingBookId = null) {
        $('.invalid-feedback').remove();$('.is-invalid').removeClass('is-invalid');

        this.#takingBookForm.reset();
        this.#takingBookId = takingBookId;

        // Array Composite Primary Keys (Dilarang edit jika mode Update)
        const compositeKeys = [
            'INV_LIST_NO', 'SEQ', 'MONTH', 'YEAR', 
            'CONTRACT', 'PART_NO', 'LOCATION_NO', 'LOT_BATCH_NO'
        ];

        if (takingBookId === null) {
            // New Taking Book
            $('#takingbook-title').text('Add Taking Book');
            
            // Allow editing primary keys on create
            compositeKeys.forEach(key => $(`#${key}`).prop('readonly', false)); 
            
            this.#takingBookModal.show();
        } else {
            // Edit Taking Book
            $('#takingbook-title').text('Edit Taking Book');
            
            // Prevent editing primary keys on update
            compositeKeys.forEach(key => $(`#${key}`).prop('readonly', true)); 
            
            this.#getTakingBookData();
            this.#takingBookModal.show();
        }
    }

    #events() {
        this.#takingBookForm.addEventListener('submit', event => {
            event.preventDefault();
            this.#submitForm();
        });
    }

    #getTakingBookData() {
        axios
            .get(`${this.#takingBookEndpoint}/${this.#takingBookId}`)
            .then(response => {
                const data = response.data.data;
                
                // Primary Keys
                $('#INV_LIST_NO').val(data.INV_LIST_NO);
                $('#SEQ').val(data.SEQ);
                $('#MONTH').val(data.MONTH);
                $('#YEAR').val(data.YEAR);
                $('#CONTRACT').val(data.CONTRACT);
                $('#PART_NO').val(data.PART_NO);
                $('#LOCATION_NO').val(data.LOCATION_NO);
                $('#LOT_BATCH_NO').val(data.LOT_BATCH_NO);
                
                // Quantity & Cost
                $('#QUANTITY').val(data.QUANTITY);
                $('#UNIT_COST').val(data.UNIT_COST);
                $('#UNIT_MEAS').val(data.UNIT_MEAS);
                
                // Location Details
                $('#WAREHOUSE').val(data.WAREHOUSE);
                $('#BAY_NO').val(data.BAY_NO);
                $('#ROW_NO').val(data.ROW_NO);
                $('#TIER_NO').val(data.TIER_NO);
                $('#BIN_NO').val(data.BIN_NO);
                
                // Handling Unit & Status
                $('#HANDLING_UNIT_ID').val(data.HANDLING_UNIT_ID);
                $('#HANDLING_UNIT_TYPE_ID').val(data.HANDLING_UNIT_TYPE_ID);
                $('#NPK').val(data.NPK);
                $('#VERIFIED').val(data.VERIFIED);
                $('#WDR').val(data.WDR);
                $('#AVAILABILITY_CONTROL_ID').val(data.AVAILABILITY_CONTROL_ID);
                $('#AVAILABILITY_CONTROL_DESC').val(data.AVAILABILITY_CONTROL_DESC);
            })
            .catch(error => {
                toast.error('Failed to fetch taking book data');
            });
    }

    #submitForm() {
        const jsonData = _formToJson(this.#takingBookForm);

        if (this.#takingBookId === null) {
            axios
                .post(this.#takingBookEndpoint, jsonData)
                .then(response => {
                    toast.success(response.data.message);
                    this.#takingBookModal.hide();
                    if (this.#onSuccessCallback) {
                        this.#onSuccessCallback();
                    }
                })
                .catch(error => {
                    if (error.response && error.response.status === 422) {
                        _showInvalidError(error.response.data.errors);
                    } else {
                        toast.error(error.response.data.message);
                    }
                });

            return;
        }

        axios
            .put(`${this.#takingBookEndpoint}/${this.#takingBookId}`, jsonData)
            .then(response => {
                toast.success(response.data.message);
                this.#takingBookModal.hide();
                if (this.#onSuccessCallback) {
                    this.#onSuccessCallback();
                }
            })
            .catch(error => {
                if (error.response && error.response.status === 422) {
                    _showInvalidError(error.response.data.errors);
                } else {
                    toast.error(error.response.data.message);
                }
            });
    }
}