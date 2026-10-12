import axios from 'axios';
import { _formToJson, _showInvalidError, toast } from '../../../../helpers';

export default class LocationForm {
    // global variables
    #locationId = null; // using location_no
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
            $('#location_no').prop('readonly', false); // Allow editing location_no on create
            this.#locationModal.show();
        } else {
            // Edit location
            $('#location-title').text('Edit Location');
            $('#location_no').prop('readonly', true); // Prevent editing primary key on update
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
                $('#location_no').val(data.location_no);
                $('#location_group').val(data.location_group);
                $('#site').val(data.site);
                $('#warehouse').val(data.warehouse);
                $('#location_description').val(data.location_description);
                $('#location_group_description').val(data.location_group_description);
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