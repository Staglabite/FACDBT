import axios from 'axios';
import { _formToJson, _showInvalidError, toast } from '../../../../helpers';

export default class UnitCostForm {
    // global variables
    #unitCostId = null; // using part_no as the primary identifier
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
            $('#part_no').prop('readonly', false); // Allow editing part_no on create
            this.#unitCostModal.show();
        } else {
            // Edit Unit Cost
            $('#unitcost-title').text('Edit Unit Cost');
            $('#part_no').prop('readonly', true); // Prevent editing primary key on update
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
                $('#part_no').val(data.part_no);
                $('#inventory_part_description').val(data.inventory_part_description);
                $('#inventory_uom').val(data.inventory_uom);
                $('#accounting_group').val(data.accounting_group);
                $('#on_hand_qty').val(data.on_hand_qty);
                $('#mat_cost').val(data.mat_cost);
                $('#lab_cost').val(data.lab_cost);
                $('#foh_cost').val(data.foh_cost);
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