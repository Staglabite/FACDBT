import axios from 'axios';
import ConfigurationForm from './configuration-form.js';
import { toast } from '../../../../helpers.js';
import Swal from 'sweetalert2';

class Configuration {
    #configurationTable = $('#hituamf010-table');

    constructor() {
        this.form = new ConfigurationForm(() => {
            this.#configurationTable.DataTable().ajax.reload();
        });
    }

    init() {
        const self = this;
        // DataTable init
        this.#configurationTable.on('init.dt', function () {
            var tfoot = self.#configurationTable.find('tfoot tr');
            var thead = self.#configurationTable.find('thead');

            // Move the tfoot row into thead (after the header row)
            tfoot.appendTo(thead);
        });

        this.#events();
    }

    #events() {
        const self = this;
        $(document).on('click', '#btn-create-configuration', function (e) {
            e.preventDefault();
            self.form.openModal();
        });

        $(document).on('click', '.edit-configuration', function () {
            self.form.openModal($(this).data('id'));
        });

        $(document).on('click', '.delete-configuration', function () {
            self.#deleteData($(this).data('id'));
        });

        $(document).on('change', '#entries', e => {
            const perPage = $(e.target).val();
            this.#configurationTable.DataTable().page.len(perPage).draw();
        });
    }

    #deleteData(configId) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You sure want to delete this data? You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!',
            customClass: {
                confirmButton: 'btn btn-outline-danger',
                cancelButton: 'btn btn-primary'
            }
        }).then(result => {
            if (result.isConfirmed) {
                axios
                    .delete(`HITUAM/bd/configuration/${configId}`)
                    .then(response => {
                        toast.success(response.data.message);
                        this.#configurationTable.DataTable().ajax.reload();
                    })
                    .catch(error => {
                        toast.error(error.response.data.message);
                    });
            }
        });
    }
}

new Configuration().init();
