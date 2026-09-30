import axios from 'axios';
import { _formToJson, _showInvalidError, toast } from '../../../../helpers';

export default class ServiceForm {
    #serviceId = null;
    #serviceModal = new bootstrap.Modal(document.getElementById('service-modal'));
    #serviceForm = document.getElementById('service-form');
    #onSuccessCallback = null;
    #serviceEndpoint = 'HITUAM/bd/master-service';

    constructor(onSuccessCallback = null) {
        this.#onSuccessCallback = onSuccessCallback;
        this.#events();
    }

    async openModal(serviceId = null) {
        $('.invalid-feedback').remove();
        $('.is-invalid').removeClass('is-invalid');

        // Clear Select2 values
        $('#application').val(null).trigger('change');
        $('#menu').val(null).trigger('change');

        $('#service-title').text(serviceId ? 'Edit Service' : 'Create Service');

        this.#serviceForm.reset();
        this.#serviceId = serviceId;

        // Jika create mode
        if (!this.#serviceId) {
            const today = new Date();

            // Format YYYY-MM-DD
            const beginDate = today.toISOString().split('T')[0];

            $('#begin').val(beginDate);
            $('#end').val('2999-12-31');
        }

        if (this.#serviceId) {
            try {
                await this.#getServiceById();
            } catch (error) {
                console.error('Error in openModal:', error);
                toast.error('Failed to open service');
                return;
            }
        }

        try {
            this.#serviceModal.show();
        } catch (error) {
            console.error('Error showing modal:', error);
            toast.error('Failed to display modal');
        }
    }

    #events() {
        document.getElementById('end').addEventListener('change', () => {
            this.#validateDateRange();
        });

        this.#serviceForm.addEventListener('submit', event => {
            event.preventDefault();

            if (!this.#validateDateRange()) {
                return;
            }

            this.#submitForm();
        });
    }

    #validateDateRange() {
        const beginDate = document.getElementById('begin').value.slice(0, 10);
        const endDate = document.getElementById('end').value.slice(0, 10);

        if (!beginDate || !endDate || endDate >= beginDate) {
            return true;
        }

        toast.open({
            type: 'warning',
            message: 'End Eff Date can not be earlier than Begind Eff Date.'
        });

        return false;
    }

    #submitForm() {
        const formData = new FormData(this.#serviceForm);

        const data = {};
        formData.forEach((value, key) => {
            data[key] = value;
        });

        if (this.#serviceId) {
            data['_method'] = 'PUT';
        }

        const url = this.#serviceEndpoint + (this.#serviceId ? `/${this.#serviceId}` : '');

        axios
            .post(url, data, {})
            .then(response => {
                $('#hituamf003-table').DataTable().ajax.reload();
                toast.success(response.data.message);
                if (this.#onSuccessCallback) {
                    this.#onSuccessCallback();
                }

                this.#serviceModal.hide();
            })
            .catch(error => {
                if (error.response && error.response.status === 422) {
                    _showInvalidError(error.response.data.errors);
                } else {
                    toast.error(error.response.data.message);
                }
            });
    }

    async #getServiceById() {
        try {
            const response = await axios.get(this.#serviceEndpoint + `/${this.#serviceId}`);
            const data = response.data.data;

            $('#name').val(data.VNAME);
            $('#description').val(data.VDESC);
            $('#url').val(data.VURL);
            $('#method').val(data.VMETHOD);
            $('#begin').val(data.DBEGINEFF);
            $('#end').val(data.DENDEFF);

            // Load application - handle if null
            if (data.application && data.application.IID) {
                const applicationSelect = $('#application');
                let applicationOption = new Option(data.application.VPROJECTDESC, data.application.IID, true, true);
                applicationSelect.append(applicationOption).trigger('change');
            }

            // Load menu - handle if null
            if (data.menu && data.menu.IID) {
                const menuSelect = $('#menu');
                let menuOption = new Option(data.menu.VAPPDESC, data.menu.IID, true, true);
                menuSelect.append(menuOption).trigger('change');
            }
        } catch (error) {
            console.error('Error loading service:', error);
            toast.error('Failed to load service data');
        }
    }
}
