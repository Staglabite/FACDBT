import axios from 'axios';
import { _formToJson, _showInvalidError, toast } from '../../../../helpers';

export default class MenuForm {
    #menuId = null;
    #menuModal = new bootstrap.Modal(document.getElementById('menu-modal'));
    #menuForm = document.getElementById('menu-form');
    #onSuccessCallback = null;
    #menuEndpoint = 'HITUAM/bd/master-menu';

    constructor(onSuccessCallback = null) {
        this.#onSuccessCallback = onSuccessCallback;
        this.#events();
    }

    async openModal(menuId = null) {
        $('.invalid-feedback').remove();
        $('.is-invalid').removeClass('is-invalid');
        $('#parent').empty().trigger('change');
        $('#application').empty().trigger('change');

        this.#menuForm.reset();
        this.#menuId = menuId;

        if (this.#menuId) {
            await this.#getMenuById();
        }

        this.#menuModal.show();
    }

    #events() {
        this.#menuForm.addEventListener('submit', event => {
            event.preventDefault();
            this.#submitForm();
        });
    }

    #submitForm() {
        const formData = new FormData(this.#menuForm);

        const data = {};
        formData.forEach((value, key) => {
            data[key] = value;
        });

        if (this.#menuId) {
            data['_method'] = 'PUT';
        }

        const url = this.#menuEndpoint + (this.#menuId ? `/${this.#menuId}` : '');

        axios
            .post(url, data, {})
            .then(response => {
                $('#hituamf002-table').DataTable().ajax.reload();
                toast.success(response.data.message);
                if (this.#onSuccessCallback) {
                    this.#onSuccessCallback();
                }

                this.#menuModal.hide();
            })
            .catch(error => {
                if (error.response && error.response.status === 422) {
                    _showInvalidError(error.response.data.errors);
                } else {
                    toast.error(error.response.data.message);
                }
            });
    }

    async #getMenuById() {
        const response = await axios.get(this.#menuEndpoint + `/${this.#menuId}`);
        const data = response.data.data;

        $('#app_id').val(data.VAPPID);
        $('#name').val(data.VAPPDESC);
        $('#description').val(data.VDESC);
        $('#url').val(data.VURL);
        $('#method').val(data.VMETHOD);
        $('#flag').val(data.VFLAG).trigger('change');
        $('#icon').val(data.VICON);
        $('#order').val(data.NSORTAPP);
        $('#type').val(data.VTYPEAPP);
        $('#env_app').val(data.VENVAPP);

        const selectApplication = $('#application');
        const selectParent = $('#parent');

        let optionApplication = new Option(data.application.VPROJECTDESC, data.application.IID, true, true);
        selectApplication.append(optionApplication).trigger('change');

        if (data.parent) {
            let optionParent = new Option(data.parent.VAPPDESC, data.parent.VAPPID, true, true);
            selectParent.append(optionParent).trigger('change');
        }
    }
}
