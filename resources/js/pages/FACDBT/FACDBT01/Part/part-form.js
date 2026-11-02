import axios from 'axios';
import { _formToJson, _showInvalidError, toast } from '../../../../helpers';

export default class PartForm {
    // global variables
    #partId = null; // IID
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
            $('#VPARTNO').prop('readonly', false); // Part No bisa diisi saat tambah
            this.#partModal.show();
        } else {
            // Edit part
            $('#part-title').text('Edit Part');
            $('#VPARTNO').prop('readonly', true); // Location No / Part No tidak diubah saat edit
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
                $('#VPARTNO').val(data.VPARTNO);
                $('#VPARTDESC').val(data.VPARTDESC);
                $('#VACCGROUP').val(data.VACCGROUP);
                $('#VSEG').val(data.VSEG);
                $('#VPRODGROUP').val(data.VPRODGROUP);
                $('#VPARTGROUP').val(data.VPARTGROUP);
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