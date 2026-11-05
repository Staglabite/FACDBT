import axios from 'axios';
import { _formToJson, _showInvalidError, toast } from '../../../../helpers';

export default class UnitCostForm {
    // global variables
    #unitCostId = null; // IID
    #unitCostModal = new window.bootstrap.Modal(document.getElementById('unitcost-modal'));
    #unitCostForm = document.getElementById('unitcost-form');
    #onSuccessCallback = null;
    
    // Endpoint disesuaikan dengan route Master Unit Cost
    #unitCostEndpoint = '/FACDBT/bd/master-unitcost';

    constructor(onSuccessCallback = null) {
        this.#onSuccessCallback = onSuccessCallback;
        this.#events();
    }

    openModal(unitCostId = null) {
        $('.invalid-feedback').remove();$('.is-invalid').removeClass('is-invalid');

        this.#unitCostForm.reset();
        this.#unitCostId = unitCostId;

        if (unitCostId === null) {
            // New Unit Cost
            $('#unitcost-title').text('Add Unit Cost');
            $('#VPARTNO').prop('readonly', false); // Part No bisa diisi saat tambah
            this.#unitCostModal.show();
        } else {
            // Edit Unit Cost
            $('#unitcost-title').text('Edit Unit Cost');
            $('#VPARTNO').prop('readonly', true); // Location No / Part No tidak diubah saat edit
            this.#getUnitCostData();
            this.#unitCostModal.show();
        }
    }

    #events() {
        this.#unitCostForm.addEventListener('submit', event => {
            event.preventDefault();
            this.#submitForm();
        });
    }

    #getUnitCostData() {
        axios
            .get(`${this.#unitCostEndpoint}/${this.#unitCostId}`)
            .then(response => {
                const data = response.data.data;
                
                // Map the form IDs to the data keys returned from UnitCost Model
                $('#VPARTNO').val(data.VPARTNO);
                $('#VPARTDESC').val(data.VPARTDESC);
                $('#VUOM').val(data.VUOM);
                $('#VACCGROUP').val(data.VACCGROUP);
                $('#NONHANDQTY').val(data.NONHANDQTY);
                $('#MMATCOST').val(data.MMATCOST);
                $('#MLABCOST').val(data.MLABCOST);
                $('#MFOHCOST').val(data.MFOHCOST);
            })
            .catch(error => {
                toast.error('Failed to fetch unit cost data');
            });
    }

    #submitForm() {
        const jsonData = _formToJson(this.#unitCostForm);

        if (this.#unitCostId === null) {
            axios
                .post(this.#unitCostEndpoint, jsonData)
                .then(response => {
                    toast.success(response.data.message);
                    this.#unitCostModal.hide();
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
            .put(`${this.#unitCostEndpoint}/${this.#unitCostId}`, jsonData)
            .then(response => {
                toast.success(response.data.message);
                this.#unitCostModal.hide();
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