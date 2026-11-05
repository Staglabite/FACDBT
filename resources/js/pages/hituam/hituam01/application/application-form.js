import axios from 'axios';
import { _formToJson, _showInvalidError, toast } from '../../../../helpers';

export default class ApplicationForm {
    // global variables
    #appId = null;
    #appModal = new bootstrap.Modal(document.getElementById('application-modal'));
    #appForm = document.getElementById('application-form');
    #onSuccessCallback = null;
    #appEndpoint = 'HITUAM/bd/master-application';

    constructor(onSuccessCallback = null) {
        this.#onSuccessCallback = onSuccessCallback;
        this.#events();
    }

    openModal(appId = null) {
        $('.invalid-feedback').remove();
        $('.is-invalid').removeClass('is-invalid');

        this.#appForm.reset();
        this.#appId = appId;

        if (appId === null) {
            // New application
            this.#appModal.show();
        } else {
            // Edit application
            this.#getApplicationData();
            this.#appModal.show();
        }
    }

    #events() {
        this.#appForm.addEventListener('submit', event => {
            event.preventDefault();
            this.#submitForm();
        });
    }

    #getApplicationData() {
        axios
            .get(`${this.#appEndpoint}/${this.#appId}`)
            .then(response => {
                const data = response.data.data;
                $('#code').val(data.code);
                $('#desc').val(data.desc);
                $('#prefix').val(data.prefix);
                $('#pic').val(data.pic);
                $('#portal').val(data.portal);
                $('#operational').val(data.operational);
                $('#std').val(data.std);
                $('#portal_access').val(data.portal_access);
                $('#host').val(data.host ?? data.url);
                $('#publish').val(data.publish);
                $('#database').val(data.database);
                $('#order').val(data.order);
                $('#icon').val(data.icon);
                $('#icon').val(data.icon);
                $('#isEmbedded').prop('checked', data.is_embedded);
                $('#isNotEmbedded').prop('checked', !data.is_embedded);
            })
            .catch(error => {
                toast.error('Failed to fetch application data');
            });
    }

    #submitForm() {
        const jsonData = _formToJson(this.#appForm);

        if (this.#appId === null) {
            axios
                .post(this.#appEndpoint, jsonData)
                .then(response => {
                    toast.success(response.data.message);
                    this.#appModal.hide();
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
            .put(`${this.#appEndpoint}/${this.#appId}`, jsonData)
            .then(response => {
                toast.success(response.data.message);
                this.#appModal.hide();
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
