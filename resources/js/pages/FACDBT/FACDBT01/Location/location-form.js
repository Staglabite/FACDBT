import axios from 'axios';
import { _formToJson, _showInvalidError, toast } from '../../../../helpers';

export default class LocationForm {
    // global variables
    #locationId = null; // IID
    #locationModal = new bootstrap.Modal(document.getElementById('location-modal'));
    #locationForm = document.getElementById('location-form');
    #onSuccessCallback = null;
    
    // Adjust the endpoint to match your FACDBT route structure
    #locationEndpoint = '/FACDBT/bd/master-location';

    constructor(onSuccessCallback = null) {
        this.#onSuccessCallback = onSuccessCallback;
        this.#events();
    }

    openModal(locationId = null) {
        $('.invalid-feedback').remove();$('.is-invalid').removeClass('is-invalid');

        this.#locationForm.reset();
        this.#locationId = locationId;

        if (locationId === null) {
            // New location
            $('#location-title').text('Add Location');
            $('#VLOCATIONNO').prop('readonly', false); // Location No bisa diisi saat tambah
            this.#locationModal.show();
        } else {
            // Edit location
            $('#location-title').text('Edit Location');
            $('#VLOCATIONNO').prop('readonly', true); // Location No / Part No tidak diubah saat edit
            this.#getLocationData();
            this.#locationModal.show();
        }
    }

    #events() {
        this.#locationForm.addEventListener('submit', event => {
            event.preventDefault();
            this.#submitForm();
        });
    }

    #getLocationData() {
        axios
            .get(`${this.#locationEndpoint}/${this.#locationId}`)
            .then(response => {
                const data = response.data.data;
                
                // Map the form IDs to the data keys returned from LocationResource
                $('#VLOCATIONNO').val(data.VLOCATIONNO);
                $('#VLOCGROUP').val(data.VLOCGROUP);
                $('#VSITE').val(data.VSITE);
                $('#VWAREHOUSE').val(data.VWAREHOUSE);
                $('#VLOCDESC').val(data.VLOCDESC);
                $('#VLOCGROUPDESC').val(data.VLOCGROUPDESC);
            })
            .catch(error => {
                toast.error('Failed to fetch location data');
            });
    }

    #submitForm() {
        const jsonData = _formToJson(this.#locationForm);

        if (this.#locationId === null) {
            axios
                .post(this.#locationEndpoint, jsonData)
                .then(response => {
                    toast.success(response.data.message);
                    this.#locationModal.hide();
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
            .put(`${this.#locationEndpoint}/${this.#locationId}`, jsonData)
            .then(response => {
                toast.success(response.data.message);
                this.#locationModal.hide();
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