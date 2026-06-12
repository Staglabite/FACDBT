import axios from 'axios';
import { _formToJson, _showInvalidError, toast } from '../../../../helpers.js';

export default class ConfigurationForm {
    #configId = null;
    #configModal = new bootstrap.Modal(document.getElementById('configuration-modal'));
    #configForm = document.getElementById('configuration-form');
    #onSuccessCallback = null;
    #configEndpoint = 'HITUAM/bd/configuration';

    constructor(onSuccessCallback = null) {
        this.#onSuccessCallback = onSuccessCallback;
        this.#events();
    }

    openModal(configId = null) {
        $('.invalid-feedback').remove();
        $('.is-invalid').removeClass('is-invalid');

        this.#configForm.reset();
        this.#configId = configId;

        if (configId !== null) {
            this.#getConfigData();
        }

        this.#configModal.show();
    }

    #events() {
        this.#configForm.addEventListener('submit', event => {
            event.preventDefault();
            this.#submitForm();
        });
    }

    #submitForm() {
        const jsonData = _formToJson(this.#configForm);

        if (this.#configId) {
            jsonData['_method'] = 'PUT';
        }

        const url = this.#configEndpoint + (this.#configId ? `/${this.#configId}` : '');

        axios
            .post(url, jsonData, {})
            .then(response => {
                toast.success(response.data.message);
                if (this.#onSuccessCallback) {
                    this.#onSuccessCallback();
                }

                this.#configModal.hide();
            })
            .catch(error => {
                if (error.response && error.response.status === 422) {
                    _showInvalidError(error.response.data.errors);
                } else {
                    toast.error(error.response.data.message);
                }
            });
    }

    #getConfigData() {
        axios
            .get(`${this.#configEndpoint}/${this.#configId}`)
            .then(response => {
                const data = response.data.data;
                $('#variable').val(data.VVARIABLE);
                $('#value').val(data.VVALUE);
            })
            .catch(error => {
                toast.error('Failed to fetch application data');
            });
    }
}
