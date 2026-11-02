import axios from 'axios';
import { _formToJson, _showInvalidError, toast } from '../../../../helpers';

export default class GroupLocationForm {
    // global variables
    #groupLocationId = null; // using IID
    #groupLocationModal = new bootstrap.Modal(document.getElementById('group-location-modal'));
    #groupLocationForm = document.getElementById('group-location-form');
    #onSuccessCallback = null;
    
    #groupLocationEndpoint = '/FACDBT/bd/master-grouplocation';

    constructor(onSuccessCallback = null) {
        this.#onSuccessCallback = onSuccessCallback;
        this.#events();
    }

    openModal(groupLocationId = null) {
        $('.invalid-feedback').remove();$('.is-invalid').removeClass('is-invalid');

        this.#groupLocationForm.reset();
        this.#groupLocationId = groupLocationId;

        if (groupLocationId === null) {
            // New group location
            $('#group-location-title').text('Add Group Location');
            this.#groupLocationModal.show();
        } else {
            // Edit group location
            $('#group-location-title').text('Edit Group Location');
            this.#getGroupLocationData();
            this.#groupLocationModal.show();
        }
    }

    #events() {
        this.#groupLocationForm.addEventListener('submit', event => {
            event.preventDefault();
            this.#submitForm();
        });
    }

    #getGroupLocationData() {
        axios
            .get(`${this.#groupLocationEndpoint}/${this.#groupLocationId}`)
            .then(response => {
                const data = response.data.data;
                
                $('#VLOCATIONNO').val(data.VLOCATIONNO);
                $('#VIDLOC').val(data.VIDLOC);
                $('#VLOCGROUP').val(data.VLOCGROUP);
                $('#VSTREAM').val(data.VSTREAM);
            })
            .catch(error => {
                toast.error('Failed to fetch group location data');
            });
    }

    #submitForm() {
        const jsonData = _formToJson(this.#groupLocationForm);

        if (this.#groupLocationId === null) {
            axios
                .post(this.#groupLocationEndpoint, jsonData)
                .then(response => {
                    toast.success(response.data.message);
                    this.#groupLocationModal.hide();
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
            .put(`${this.#groupLocationEndpoint}/${this.#groupLocationId}`, jsonData)
            .then(response => {
                toast.success(response.data.message);
                this.#groupLocationModal.hide();
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