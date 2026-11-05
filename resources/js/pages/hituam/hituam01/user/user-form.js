import axios from 'axios';
import { _showInvalidError, toast } from '../../../../helpers';
export default class UserForm {
    #userId = null;
    #userModal = new bootstrap.Modal(document.getElementById('user-modal'));
    #userForm = document.getElementById('user-form');
    #userEndpoint = 'HITUAM/bd/master-user';
    #onSuccessCallback = null;

    constructor(onSuccessCallback = null) {
        this.#onSuccessCallback = onSuccessCallback;
        this.#select2();
        this.#events();
    }

    async openModal(userId = null) {
        $('.invalid-feedback').remove();
        $('.is-invalid').removeClass('is-invalid');
        $('#role').empty().trigger('change');
        $('#supplier').empty().trigger('change');
        $('#user_supplier').empty().trigger('change');
        $('#supplier-input-group').addClass('d-none');

        // Clear select2 cache
        $('#user_supplier').data('select2')?.clearCache?.();
        $('#supplier').data('select2')?.clearCache?.();

        this.#userForm.reset();
        this.#userId = userId;

        if (userId) {
            await this.#getUserById();
        }

        this.#userModal.show();
    }

    #select2() {
        $('#role').select2({
            placeholder: 'Select role',
            dropdownParent: $('#user-modal'),
            allowClear: true,
            ajax: {
                url: '/general/all-roles', // your API route
                dataType: 'json',
                delay: 250, // delays requests for better performance
                data: function (params) {
                    return {
                        search: params.term // search term
                    };
                },
                processResults: function (response) {
                    const data = response.data;
                    return {
                        results: data.map(function (item) {
                            return {
                                id: item.NID,
                                text: item.VROLENAME
                            };
                        })
                    };
                },
                cache: true
            }
        });

        $('#supplier').select2({
            placeholder: 'Select supplier',
            dropdownParent: $('#user-modal'),
            allowClear: true,
            ajax: {
                url: '/general/all-suppliers', // your API route
                dataType: 'json',
                delay: 250, // delays requests for better performance
                data: function (params) {
                    return {
                        search: params.term // search term
                    };
                },
                processResults: function (response) {
                    const data = response.data;
                    return {
                        results: data.map(function (item) {
                            return {
                                id: item.IID,
                                text: `${item.VSUPPLIER_CODE}-${item.VNAME}`
                            };
                        })
                    };
                },
                cache: true
            }
        });

        $('#user_supplier').select2({
            placeholder: 'Select supplier',
            dropdownParent: $('#user-modal'),
            allowClear: true,
            ajax: {
                url: '/general/supplier-users',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        search: params.term,
                        supplier: $('#supplier').val()
                    };
                },
                processResults: function (response) {
                    const data = response.data;
                    return {
                        results: data.map(function (item) {
                            return {
                                id: item.IID,
                                text: `${item.VSUPPLIER_CODE}-${item.VNAME}`,
                                name: item.VNAME,
                                email: item.VVALUE,
                                disabled: item.disabled
                            };
                        })
                    };
                },
                cache: false
            },
            templateResult: function (data) {
                if (!data.id) return data.text;

                if (data.disabled) {
                    return $('<span style="opacity: 0.5; text-decoration: line-through;">' + data.text + ' (Already assigned)</span>');
                }
                return data.text;
            }
        });
    }

    #events() {
        this.#userForm.addEventListener('submit', event => {
            event.preventDefault();
            this.#submitForm();
        });

        $(document).on('change', 'input[name="user_type"]', function () {
            const val = $(this).val();

            if (val === 'external') {
                $('#npk').attr('required', false);
                $('#email').attr('readonly', true);
                $('#supplier-input-group').removeClass('d-none');
            } else {
                $('#npk').attr('required', true);
                $('#email').attr('readonly', false);
                $('#username').prop('disabled', false);
                $('#supplier-input-group').addClass('d-none');
            }
        });

        $(document)
            .on('select2:select', '#supplier', function () {
                $('#user_supplier').prop('disabled', false);
            })
            .on('select2:clear', '#supplier', function () {
                $('#user_supplier').prop('disabled', true);
                $('#user_supplier').empty().trigger('change');
            });

        $(document)
            .on('select2:select', '#user_supplier', function (e) {
                const data = e.params.data;
                $('#nameId').val(data.name);
                $('#email').val(data.email);
            })
            .on('select2:clear', '#user_supplier', function () {
                $('#nameId').val('');
                $('#email').val('');
            });
    }

    #submitForm() {
        const formData = new FormData(this.#userForm);

        const data = {};

        formData.forEach((value, key) => {
            // If key already exists, push into array
            if (data[key]) {
                // Convert to array if not yet
                if (!Array.isArray(data[key])) {
                    data[key] = [data[key]];
                }
                data[key].push(value);
            } else {
                // First value → always wrap in array IF key ends with []
                if (key.endsWith('[]')) {
                    data[key] = [value];
                } else {
                    data[key] = value;
                }
            }
        });

        if (this.#userId) {
            data['_method'] = 'PUT';
        }

        const url = this.#userEndpoint + (this.#userId ? `/${this.#userId}` : '');

        axios
            .post(url, data, {})
            .then(response => {
                $('#hituamf005-table').DataTable().ajax.reload();
                toast.success(response.data.message);
                if (this.#onSuccessCallback) {
                    this.#onSuccessCallback();
                }

                this.#userModal.hide();
            })
            .catch(error => {
                if (error.response && error.response.status === 422) {
                    _showInvalidError(error.response.data.errors);
                } else {
                    toast.error(error.response.data.message);
                }
            });
    }

    async #getUserById() {
        const response = await axios.get(this.#userEndpoint + `/${this.#userId}`);
        const data = response.data.data;

        $('#username').val(data.VUSERNAME);
        $('#npk').val(data.VEMPNO);
        $('#email').val(data.VEMAIL);

        const select = $('#role');
        const selectSupplier = $('#supplier');
        const selectSupplierUser = $('#user_supplier');

        data.roles.forEach(role => {
            let option = new Option(role.VROLENAME, role.NID, true, true);
            select.append(option).trigger('change');
        });

        if (data.supplier_user) {
            const supplier = data.supplier_user;

            $('input[name="user_type"][value="external"]').prop('checked', true).trigger('change');
            const optionSupplier = new Option(
                `${supplier.VSUPPLIER_CODE}-${supplier.VSUPPLIER_NAME}`,
                supplier.ISUPPLIER_ID,
                true,
                true
            );
            const optionSupplierUser = new Option(
                `${supplier.VSUPPLIER_CODE}-${supplier.VNAME}`,
                supplier.IID,
                true,
                true
            );
            selectSupplier.append(optionSupplier).trigger('select2:select');
            selectSupplierUser.append(optionSupplierUser).trigger('change');
        } else {
            // Jika tidak ada supplier_user, set user_type ke internal
            $('input[name="user_type"][value="internal"]').prop('checked', true).trigger('change');
        }
    }
}
