import axios from 'axios';
import { _formToJson, _showInvalidError, toast } from '../../../../helpers';

export default class PartForm {
    // global variables
    #partId = null; // using part_no
    #partModal = new window.bootstrap.Modal(document.getElementById('part-modal'));
    #partForm = document.getElementById('part-form');
    #onSuccessCallback = null;
    
    // Endpoint disesuaikan dengan route Master Part
    #partEndpoint = '/FACDBT/bd/master-part';

    constructor(onSuccessCallback = null) {
        this.#onSuccessCallback = onSuccessCallback;
        this.#events();
    }

    openModal(partId = null) {
        $('.invalid-feedback').remove();$('.is-invalid').removeClass('is-invalid');

        this.#partForm.reset();
        this.#partId = partId;

        if (partId === null) {
            // New part
            $('#part-title').text('Add Part');
            $('#part_no').prop('readonly', false); // Allow editing part_no on create
            this.#partModal.show();
        } else {
            // Edit part
            $('#part-title').text('Edit Part');
            $('#part_no').prop('readonly', true); // Prevent editing primary key on update
            this.#getPartData();
            this.#partModal.show();
        }
    }

    #events() {
        this.#partForm.addEventListener('submit', event => {
            event.preventDefault();
            this.#submitForm();
        });
    }

    #getPartData() {
        axios
            .get(`${this.#partEndpoint}/${this.#partId}`)
            .then(response => {
                const data = response.data.data;
                
                // Map the form IDs to the data keys returned from Part Model
                $('#part_no').val(data.part_no);
                $('#inventory_part_description').val(data.inventory_part_description);
                $('#accounting_group').val(data.accounting_group);
                $('#stream').val(data.stream);
                $('#seg').val(data.seg);
                $('#prod_group').val(data.prod_group);
                $('#part_group').val(data.part_group);
            })
            .catch(error => {
                toast.error('Failed to fetch part data');
            });
    }

    #submitForm() {
        const jsonData = _formToJson(this.#partForm);

        if (this.#partId === null) {
            axios
                .post(this.#partEndpoint, jsonData)
                .then(response => {
                    toast.success(response.data.message);
                    this.#partModal.hide();
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
            .put(`${this.#partEndpoint}/${this.#partId}`, jsonData)
            .then(response => {
                toast.success(response.data.message);
                this.#partModal.hide();
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