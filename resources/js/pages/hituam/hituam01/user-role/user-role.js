import UserForm from '../user/user-form';
import RoleForm from '../role/role-form';
import axios from 'axios';
import { toast } from '../../../../helpers';
class UserRole {
    #userTable = $('#hituamf005-table');
    #roleTable = $('#hituamf004-table');
    #selectedUserIds = new Set();
    #selectedRoleIds = new Set();
    #userSelectAllPromise = null;
    #roleSelectAllPromise = null;
    #userImportModal = new bootstrap.Modal(document.getElementById('user-import-modal'));
    #userImportErrorModal = new bootstrap.Modal(document.getElementById('user-error-import-modal'));
    #userImportForm = document.getElementById('user-import');
    #userImportErrorTable = null;
    #roleImportModal = new bootstrap.Modal(document.getElementById('role-import-modal'));
    #roleImportErrorModal = new bootstrap.Modal(document.getElementById('role-error-import-modal'));
    #roleImportForm = document.getElementById('role-import');
    #roleImportErrorTable = null;

    constructor() {
        this.userForm = new UserForm();
        this.roleForm = new RoleForm();
    }

    init() {
        this.#setupTable(this.#userTable);
        this.#setupTable(this.#roleTable);
        this.#filterEvents();
        this.#clickEvents();
        this.#selectionEvents();
        this.#initUserImportErrorTable();
        this.#initRoleImportErrorTable();
    }

    #setupTable(table) {
        if (!table.length) return;

        table.on('init.dt', function () {
            var tfoot = table.find('tfoot tr');
            var thead = table.find('thead');

            // Move the tfoot row into thead (after the header row)
            tfoot.appendTo(thead);
        });
    }

    #filterEvents() {
        this.#bindEntries('#user-entries', this.#userTable);
        this.#bindEntries('#role-entries', this.#roleTable);

        this.#bindSearch('#user-search-input', this.#userTable);
        this.#bindSearch('#role-search-input', this.#roleTable);
    }

    #bindEntries(selector, table) {
        if (!table.length) return;

        $(document).on('change', selector, e => {
            const perPage = $(e.target).val();
            const dataTable = table.DataTable();

            dataTable.page.len(perPage).draw();
        });
    }

    #bindSearch(selector, table) {
        if (!table.length) return;

        let searchTimeout;
        $(document).on('keyup', selector, e => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const keyword = $(e.target).val();
                this.#updateQuery(table, { keyword });
            }, 500);
        });
    }

    #updateQuery(table, params) {
        const dataTable = table.DataTable();
        const currentUrl = new URL(dataTable.ajax.url(), window.location.origin);
        const searchParams = new URLSearchParams(currentUrl.search);

        for (const key in params) {
            if (params[key] === '' || params[key] === null || params[key] === undefined) {
                searchParams.delete(key);
            } else {
                searchParams.set(key, params[key]);
            }
        }

        searchParams.set('page', 1);

        const newUrl = `${currentUrl.pathname}?${searchParams.toString()}`;
        dataTable.ajax.url(newUrl).load();
    }

    #clickEvents() {
        const self = this;

        $(document).on('click', '#btn-create-user', function () {
            self.userForm.openModal();
        });

        $(document).on('click', '#btn-add-role', function () {
            self.roleForm.openModal();
        });

        $(document).on('click', '.edit-user', function () {
            self.userForm.openModal($(this).data('id'));
        });

        $(document).on('click', '.delete-user', async function () {
            const success = await self.#deleteData($(this).data('id'), 'master-user');

            if (success) {
                self.#userTable.DataTable().ajax.reload();
            }
        });

        $(document).on('click', '.edit-role', function () {
            self.roleForm.openModal($(this).data('id'));
        });

        $(document).on('click', '.delete-role', async function () {
            const success = await self.#deleteData($(this).data('id'), 'master-role');

            if (success) {
                self.#roleTable.DataTable().ajax.reload();
            }
        });

        $(document).on('click', '#export-excel-user', function () {
            self.#userTable.DataTable().button('.buttons-excel').trigger();
        });

        $(document).on('click', '#import-excel-user', function (e) {
            e.preventDefault();
            self.#userImportForm.reset();
            self.#userImportModal.show();
        });

        $(document).on('click', '#download-template-user', function () {
            window.location.href = '/HITUAM/bd/master-user/download-template';
        });

        $(document).on('click', '#btn-submit-user-import', function (e) {
            e.preventDefault();
            self.#submitUserImport();
        });

        $(document).on('click', '#export-excel-role', function () {
            self.#roleTable.DataTable().button('.buttons-excel').trigger();
        });

        $(document).on('click', '#import-excel-role', function (e) {
            e.preventDefault();
            self.#roleImportForm.reset();
            self.#roleImportModal.show();
        });

        $(document).on('click', '#download-template-role', function () {
            window.location.href = '/HITUAM/bd/master-role/download-template';
        });

        $(document).on('click', '#btn-submit-role-import', function (e) {
            e.preventDefault();
            self.#submitRoleImport();
        });

        $('#username').on('keyup', function () {
            const isExternal = $('input[name="user_type"]:checked').val() === 'external';
            const length = this.value.length;

            // Jika bukan external → reset
            if (!isExternal) {
                this.classList.remove('is-invalid');
                $('#message-error').text('').hide();
                $('#btn-save-user').prop('disabled', false);
                return;
            }

            // Jika kosong → kembali normal (TIDAK error)
            if (length === 0) {
                this.classList.remove('is-invalid');
                $('#message-error').text('').hide();
                $('#btn-save-user').prop('disabled', false);
                return;
            }

            // Jika 1–3 karakter → error
            if (length < 4) {
                this.classList.add('is-invalid');
                $('#message-error')
                    .text('Username must be more than 3 characters')
                    .show();
                $('#btn-save-user').prop('disabled', true);
                return;
            }

            // Jika >= 4 karakter → valid
            this.classList.remove('is-invalid');
            $('#message-error').text('').hide();
            $('#btn-save-user').prop('disabled', false);
        });

        $(document).on('click', '#btn-delete-user-selected', function () {
            self.#handleDeleteSelectedUsers();
        });

        $(document).on('click', '#btn-delete-selected-role', function () {
            self.#handleDeleteSelectedRoles();
        });
    }

    #selectionEvents() {
        const self = this;

        this.#userTable.on('draw.dt', function () {
            self.#applyUserSelectionToVisibleRows();
            self.#refreshUserSelectionUI();
        });

        this.#roleTable.on('draw.dt', function () {
            self.#applyRoleSelectionToVisibleRows();
            self.#refreshRoleSelectionUI();
        });

        $(document).on('change', '#select-all-user', async function () {
            if ($(this).is(':checked')) {
                await self.#selectAllUsersAcrossPages();
                return;
            }

            self.#selectedUserIds.clear();
            self.#applyUserSelectionToVisibleRows();
            self.#refreshUserSelectionUI();
        });

        $(document).on('change', '#select-all-role', async function () {
            if ($(this).is(':checked')) {
                await self.#selectAllRolesAcrossPages();
                return;
            }

            self.#selectedRoleIds.clear();
            self.#applyRoleSelectionToVisibleRows();
            self.#refreshRoleSelectionUI();
        });

        $(document).on('change', 'input[name="selected-user[]"]', function () {
            const id = $(this).val();

            if (!id) {
                return;
            }

            if ($(this).is(':checked')) {
                self.#selectedUserIds.add(String(id));
            } else {
                self.#selectedUserIds.delete(String(id));
            }

            self.#refreshUserSelectionUI();
        });

        $(document).on('change', 'input[name="selected-role[]"]', function () {
            const id = $(this).val();

            if (!id) {
                return;
            }

            if ($(this).is(':checked')) {
                self.#selectedRoleIds.add(String(id));
            } else {
                self.#selectedRoleIds.delete(String(id));
            }

            self.#refreshRoleSelectionUI();
        });
    }

    async #selectAllUsersAcrossPages() {
        this.#userSelectAllPromise = (async () => {
            try {
                const params = this.#userTable.DataTable().ajax.params() || {};
                const response = await axios.get(this.#userTable.DataTable().ajax.url(), {
                    params: {
                        ...params,
                        start: 0,
                        length: -1
                    }
                });

                this.#selectedUserIds.clear();

                (response.data?.data || []).forEach((row) => {
                    if (row?.IID) {
                        this.#selectedUserIds.add(String(row.IID));
                    }
                });

                this.#applyUserSelectionToVisibleRows();
                this.#refreshUserSelectionUI();
            } catch (error) {
                $('#select-all-user').prop('checked', false);
                toast.error(error.response?.data?.message || 'Failed to select all user data.');
            } finally {
                this.#userSelectAllPromise = null;
            }
        })();

        await this.#userSelectAllPromise;
    }

    async #selectAllRolesAcrossPages() {
        this.#roleSelectAllPromise = (async () => {
            try {
                const params = this.#roleTable.DataTable().ajax.params() || {};
                const response = await axios.get(this.#roleTable.DataTable().ajax.url(), {
                    params: {
                        ...params,
                        start: 0,
                        length: -1
                    }
                });

                this.#selectedRoleIds.clear();

                (response.data?.data || []).forEach((row) => {
                    if (row?.NID) {
                        this.#selectedRoleIds.add(String(row.NID));
                    }
                });

                this.#applyRoleSelectionToVisibleRows();
                this.#refreshRoleSelectionUI();
            } catch (error) {
                $('#select-all-role').prop('checked', false);
                toast.error(error.response?.data?.message || 'Failed to select all role data.');
            } finally {
                this.#roleSelectAllPromise = null;
            }
        })();

        await this.#roleSelectAllPromise;
    }

    #applyUserSelectionToVisibleRows() {
        $('input[name="selected-user[]"]').each((_, element) => {
            const $checkbox = $(element);
            $checkbox.prop('checked', this.#selectedUserIds.has(String($checkbox.val())));
        });
    }

    #applyRoleSelectionToVisibleRows() {
        $('input[name="selected-role[]"]').each((_, element) => {
            const $checkbox = $(element);
            $checkbox.prop('checked', this.#selectedRoleIds.has(String($checkbox.val())));
        });
    }

    #refreshUserSelectionUI() {
        const visibleCheckboxes = $('input[name="selected-user[]"]');
        const visibleChecked = visibleCheckboxes.filter(':checked').length;
        const allVisibleChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.length === visibleChecked;

        $('#btn-delete-user-selected').toggleClass('d-none', this.#selectedUserIds.size === 0);
        $('#select-all-user').prop('checked', allVisibleChecked && this.#selectedUserIds.size > 0);
    }

    #refreshRoleSelectionUI() {
        const visibleCheckboxes = $('input[name="selected-role[]"]');
        const visibleChecked = visibleCheckboxes.filter(':checked').length;
        const allVisibleChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.length === visibleChecked;

        $('#btn-delete-selected-role').toggleClass('d-none', this.#selectedRoleIds.size === 0);
        $('#select-all-role').prop('checked', allVisibleChecked && this.#selectedRoleIds.size > 0);
    }

    async #handleDeleteSelectedUsers() {
        if (this.#userSelectAllPromise) {
            await this.#userSelectAllPromise;
        }

        this.#deleteMultiple(Array.from(this.#selectedUserIds));
    }

    async #handleDeleteSelectedRoles() {
        if (this.#roleSelectAllPromise) {
            await this.#roleSelectAllPromise;
        }

        this.#deleteMultipleRole(Array.from(this.#selectedRoleIds));
    }

    #deleteMultiple(selectedIds) {
        if (selectedIds.length === 0) {
            toast.info('Please select at least one user to delete.');
            return;
        }

        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete ${selectedIds.length} users. This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete them!',
            customClass: {
                confirmButton: 'btn btn-outline-danger',
                cancelButton: 'btn btn-primary'
            }
        }).then(result => {
            if (result.isConfirmed) {
                axios
                    .post('HITUAM/bd/master-user/delete-multiple', { ids: selectedIds })
                    .then(response => {
                        toast.success(response.data.message);
                        selectedIds.forEach((id) => this.#selectedUserIds.delete(String(id)));
                        this.#userTable.DataTable().ajax.reload();
                    })
                    .catch(error => {
                        toast.error(error.response.data.message);
                    });
            }
        });
    }

    #deleteMultipleRole(selectedIds) {
        if (selectedIds.length === 0) {
            toast.info('Please select at least one role to delete.');
            return;
        }

        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete ${selectedIds.length} roles. This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete them!',
            customClass: {
                confirmButton: 'btn btn-outline-danger',
                cancelButton: 'btn btn-primary'
            }
        }).then(result => {
            if (result.isConfirmed) {
                axios
                    .post('HITUAM/bd/master-role/delete-multiple', { ids: selectedIds })
                    .then(response => {
                        toast.success(response.data.message);
                        selectedIds.forEach((id) => this.#selectedRoleIds.delete(String(id)));
                        this.#roleTable.DataTable().ajax.reload();
                    })
                    .catch(error => {
                        toast.error(error.response.data.message);
                    });
            }
        });
    }

    async #deleteData(id, master) {
        return new Promise((resolve, reject) => {
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
                if (!result.isConfirmed) {
                    resolve(false); // canceled
                    return;
                }

                axios
                    .delete(`HITUAM/bd/${master}/${id}`)
                    .then(response => {
                        toast.success(response.data.message);
                        if (master === 'master-user') {
                            this.#selectedUserIds.delete(String(id));
                        }
                        if (master === 'master-role') {
                            this.#selectedRoleIds.delete(String(id));
                        }
                        resolve(true); // success
                    })
                    .catch(error => {
                        toast.error(error.response.data.message);
                        reject(error); // error
                    });
            });
        });
    }

    #submitUserImport() {
        const formData = new FormData(this.#userImportForm);

        axios
            .post('HITUAM/bd/master-user/import', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            })
            .then(response => {
                this.#userTable.DataTable().ajax.reload();
                toast.success(response.data.message);
                this.#userImportModal.hide();
            })
            .catch(error => {
                if (error.response && error.response.status === 400) {
                    this.#openUserImportErrorModal(error.response.data.data);
                } else {
                    toast.error(error.response?.data?.message ?? 'Failed to import user data.');
                    this.#userImportForm.reset();
                }
            });
    }

    #openUserImportErrorModal(data) {
        this.#userImportModal.hide();
        this.#userImportErrorModal.show();

        const errorData = data.error_data.map(item => ({
            user_type: item.user_type ?? '',
            username: item.username ?? '',
            email: item.email ?? '',
            npk: item.npk ?? '',
            password: item.password ?? '',
            role_names: item.role_names ?? '',
            supplier: item.supplier ?? item.supplier_code ?? item.supplier_id ?? '',
            user_supplier: item.user_supplier ?? item.supplier_username ?? item.user_supplier_id ?? '',
            errors: `<ul class="text-danger">${item.errors.map(err => `<li>${err}</li>`).join('')}</ul>`
        }));

        this.#userImportErrorTable.clear().rows.add(errorData).draw();
    }

    #submitRoleImport() {
        const formData = new FormData(this.#roleImportForm);

        axios
            .post('HITUAM/bd/master-role/import', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            })
            .then(response => {
                this.#roleTable.DataTable().ajax.reload();
                toast.success(response.data.message);
                this.#roleImportModal.hide();
            })
            .catch(error => {
                if (error.response && error.response.status === 400) {
                    this.#openRoleImportErrorModal(error.response.data.data);
                } else {
                    toast.error(error.response?.data?.message ?? 'Failed to import role data.');
                    this.#roleImportForm.reset();
                }
            });
    }

    #openRoleImportErrorModal(data) {
        this.#roleImportModal.hide();
        this.#roleImportErrorModal.show();

        const errorData = data.error_data.map(item => ({
            role_name: item.role_name ?? '',
            description: item.description ?? '',
            errors: `<ul class="text-danger">${item.errors.map(err => `<li>${err}</li>`).join('')}</ul>`
        }));

        this.#roleImportErrorTable.clear().rows.add(errorData).draw();
    }

    #initUserImportErrorTable() {
        this.#userImportErrorTable = $('#user-import-error-table').DataTable({
            data: [],
            columns: [
                { title: 'User Type', data: 'user_type', defaultContent: '' },
                { title: 'Username', data: 'username', defaultContent: '' },
                { title: 'Email', data: 'email', defaultContent: '' },
                { title: 'NPK', data: 'npk', defaultContent: '' },
                { title: 'Password', data: 'password', defaultContent: '' },
                { title: 'Role Names', data: 'role_names', defaultContent: '' },
                { title: 'Supplier', data: 'supplier', defaultContent: '' },
                { title: 'User Supplier', data: 'user_supplier', defaultContent: '' },
                { title: 'Errors', data: 'errors', className: 'dt-nowrap', defaultContent: '' }
            ],
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            paging: true,
            autoWidth: false,
            pagingType: 'full_numbers'
        });

        $('#user-error-import-modal').on('shown.bs.modal', () => {
            if (this.#userImportErrorTable) {
                this.#userImportErrorTable.columns.adjust().draw(false);
            }
        });
    }

    #initRoleImportErrorTable() {
        this.#roleImportErrorTable = $('#role-import-error-table').DataTable({
            data: [],
            columns: [
                { title: 'Role Name', data: 'role_name', defaultContent: '' },
                { title: 'Description', data: 'description', defaultContent: '' },
                { title: 'Errors', data: 'errors', className: 'dt-nowrap', defaultContent: '' }
            ],
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            paging: true,
            autoWidth: false,
            pagingType: 'full_numbers'
        });

        $('#role-error-import-modal').on('shown.bs.modal', () => {
            if (this.#roleImportErrorTable) {
                this.#roleImportErrorTable.columns.adjust().draw(false);
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    new UserRole().init();
});
