import axios from 'axios';
import { _formToJson, _showInvalidError, toast } from '../../../../helpers';

export default class RoleForm {
    #roleId = null;
    #appIds = [];
    #roleModal = new bootstrap.Modal(document.getElementById('role-modal'));
    #roleForm = document.getElementById('role-form');

    #roleEndpoint = 'HITUAM/bd/master-role';
    #onSuccessCallback = null;

    constructor(onSuccessCallback = null) {
        this.#onSuccessCallback = onSuccessCallback;
        this.#events();
    }

    async openModal(roleId = null) {
        $('.invalid-feedback').remove();
        $('.is-invalid').removeClass('is-invalid');
        $('#role').empty().trigger('change');

        await this.#drawMenus();

        this.#roleForm.reset();
        this.#roleId = roleId;

        if (this.#roleId) {
            await this.#getRoleById();
        }

        this.#roleModal.show();
    }

    #events() {
        this.#roleForm.addEventListener('submit', event => {
            event.preventDefault();
            this.#submitForm();
        });
    }

    #submitForm() {
        const formData = new FormData(this.#roleForm);

        const data = {};
        formData.forEach((value, key) => {
            if (key !== 'admin-access') {
                data[key] = value;
            }
        });

        data.status = $('#status').prop('checked');

        const checkedMenus = $('.menu-list:checked')
            .map(function () {
                return $(this).val();
            })
            .get(); // Convert to an array

        const uncheckedMenus = $('.menu-list:not(:checked)')
            .map(function () {
                return $(this).val();
            })
            .get(); // Get unchecked values as an array

        const checkedServices = $('.service-list:checked')
            .map(function () {
                return $(this).val();
            })
            .get();

        const uncheckedServices = $('.service-list:not(:checked)')
            .map(function () {
                return $(this).val();
            })
            .get(); // Get unchecked values as an array

        data.menus = checkedMenus;
        data.services = checkedServices;
        data.unmenus = uncheckedMenus;
        data.unservices = uncheckedServices;

        console.log(this.#roleId);
        if (this.#roleId) {
            data['_method'] = 'PUT';
        }

        const url = this.#roleEndpoint + (this.#roleId ? `/${this.#roleId}` : '');

        axios
            .post(url, data, {})
            .then(response => {
                $('#hituamf004-table').DataTable().ajax.reload();
                toast.success(response.data.message);
                if (this.#onSuccessCallback) {
                    this.#onSuccessCallback();
                }

                this.#roleModal.hide();
            })
            .catch(error => {
                if (error.response && error.response.status === 422) {
                    _showInvalidError(error.response.data.errors);
                } else {
                    toast.error(error.response.data.message);
                }
            });
    }

    async #drawMenus() {
        const self = this;
        const menus = await this.#getMenuData();

        let html = '';
        let accordion = '';

        menus.forEach(app => {
            self.#appIds.push(app.IID);

            const menuTree = self.#buildMenuTree(app.menus);

            menuTree.forEach(menu => {
                html += self.#menusContent(menu, app.IID);
            });

            accordion += `
                <div class="accordion mb-3">
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button type="button" class="accordion-button" data-bs-toggle="collapse" data-bs-target="#accordion-${app.IID}" aria-expanded="true" aria-controls="accordion-${app.IID}">
                            ${app.VPROJECTDESC}
                            </button>
                        </h3>
                        <div id="accordion-${app.IID}" class="accordion-collapse collapse show" data-bs-parent="#accordionExample" style="">
                            <div class="accordion-body">
                                <table class="table accordion-table">
                                    <tbody>
                                        <tr>
                                            <td style="text-align:right">
                                                <input type="checkbox" class="form-check-input app-check" data-id="app-${app.IID}">
                                                <label class="form-check-label">Select All</label>
                                            </td>
                                        </tr>
                                        ${html}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            html = '';
        });

        $('.accordion').remove();

        $('#menu-list').append(accordion);

        this.#tidyMenusContent();
        this.#menuListEvents();
    }

    #menusContent(menu, appId, level = 0) {
        const buttons = menu.services || [];

        let buttonHtml = '';

        buttons.forEach(button => {
            buttonHtml += `
            <td>
                <input type="checkbox"
                       class="form-check-input service-list"
                       id="menu-button-${button.IID}"
                       data-id="app-${appId}"
                       value="${button.IID}">

                <label for="menu-button-${button.IID}"
                       class="form-check-label">
                    ${this.#accessNameBeautify(button.VNAME)}
                </label>
            </td>
        `;
        });

        // Indent untuk submenu
        const paddingLeft = level * 25;

        let html = `
        <tr class="menu-list-row">
            <td style="padding-left: ${paddingLeft}px;">
                ${level > 0 ? '└─ ' : ''}
                ${menu.VAPPDESC}
            </td>

            <td>
                <input type="checkbox"
                       class="form-check-input menu-list"
                       id="menu-${menu.IID}"
                       data-id="app-${appId}"
                       value="${menu.IID}">

                <label for="menu-${menu.IID}"
                       class="form-check-label">
                    Read
                </label>
            </td>

            ${buttonHtml}
        </tr>
    `;

        // Cari child menu
        const children = menu.children || [];

        children.forEach(child => {
            html += this.#menusContent(child, appId, level + 1);
        });

        return html;
    }

    #buildMenuTree(menus) {
        const menuMap = {};
        const rootMenus = [];

        // Buat map berdasarkan VAPPID
        menus.forEach(menu => {
            menu.children = [];
            menuMap[menu.VAPPID] = menu;
        });

        // Bentuk parent-child
        menus.forEach(menu => {
            if (menu.VPARENT) {
                const parent = menuMap[menu.VPARENT];

                if (parent) {
                    parent.children.push(menu);
                }
            } else {
                rootMenus.push(menu);
            }
        });

        return rootMenus;
    }

    #triggerChecked(services, accesses) {
        // Ambil array ID untuk matching
        const accessIds = accesses.map(a => a.IID);
        const serviceIds = services.map(s => s.IID);

        // Centang menu-list berdasarkan accesses
        $('.menu-list').each(function () {
            const val = parseInt($(this).val());
            if (accessIds.includes(val)) {
                $(this).prop('checked', true);
            }
        });

        $('.service-list').each(function () {
            const val = parseInt($(this).val());
            if (serviceIds.includes(val)) {
                $(this).prop('checked', true);
            }
        });

        // Cek dan centang app-check untuk setiap aplikasi
        this.#appIds.forEach(appId => {
            const dataId = `app-${appId}`;
            const totalMenus = $(`.menu-list[data-id="${dataId}"]`).length;
            const checkedMenus = $(`.menu-list[data-id="${dataId}"]:checked`).length;
            const totalServices = $(`.service-list[data-id="${dataId}"]`).length;
            const checkedServices = $(`.service-list[data-id="${dataId}"]:checked`).length;

            // Jika semua checkbox di app tersebut tercentang, centang app-check
            if (totalMenus > 0 && totalMenus === checkedMenus && totalServices === checkedServices) {
                $(`.app-check[data-id="${dataId}"]`).prop('checked', true);
            }
        });

        // Cek dan centang admin-access jika semua checkbox tercentang
        const totalAllMenus = $('.menu-list').length;
        const checkedAllMenus = $('.menu-list:checked').length;
        const totalAllServices = $('.service-list').length;
        const checkedAllServices = $('.service-list:checked').length;

        if (totalAllMenus > 0 && totalAllMenus === checkedAllMenus && totalAllServices === checkedAllServices) {
            $('#admin-access').prop('checked', true);
        }
    }

    #menuListEvents() {
        const self = this;

        $('#admin-access').on('change', function () {
            const isChecked = $(this).prop('checked'); // Check the status of the admin-access checkbox

            // Set all checkboxes with class 'menu-list' and 'service list' to the same state
            $('.menu-list').prop('checked', isChecked);
            $('.service-list').prop('checked', isChecked);
            $('.app-check').prop('checked', isChecked);

            $('input.form-check-input.service-list[type="checkbox"]').prop('disabled', false);
            $('input.form-check-input.menu-list[type="checkbox"]').prop('disabled', false);
            $('input.form-check-input.app-check[type="checkbox"]').prop('disabled', false);
        });

        $('.app-check').on('change', function () {
            const isChecked = $(this).prop('checked'); // Check the status of the admin-access checkbox
            const dataId = $(this).data('id');

            $(`input[type="checkbox"][data-id="${dataId}"]`).prop('checked', isChecked);
            const checkedDataId = $(
                `input.form-check-input.menu-list[type="checkbox"][data-id="${dataId}"]:checked`
            ).length;
            const numberId = parseInt(dataId.replace('app-', ''));
            // self.#disableNotAppId(checkedDataId, numberId);

            const menuAndServiceList = $('.menu-list').length + $('.service-list').length;
            const checkedMenuAndServiceList = $('.menu-list:checked').length + $('.service-list:checked').length;
            const allChecked = menuAndServiceList === checkedMenuAndServiceList;
            $('#admin-access').prop('checked', allChecked);
        });

        //Change select all if menu selected all
        $('.menu-list').on('change', function () {
            const dataId = $(this).data('id');
            const dataIdList = $(`input.form-check-input.menu-list[type="checkbox"][data-id="${dataId}"]`).length;
            const checkedDataId = $(
                `input.form-check-input.menu-list[type="checkbox"][data-id="${dataId}"]:checked`
            ).length;
            const dataServiceList = $(
                `input.form-check-input.service-list[type="checkbox"][data-id="${dataId}"]`
            ).length;
            const checkedDataService = $(
                `input.form-check-input.service-list[type="checkbox"][data-id="${dataId}"]:checked`
            ).length;
            const allCheckedDataId = dataIdList === checkedDataId && dataServiceList === checkedDataService;
            $(`input.form-check-input.app-check[data-id="${dataId}"]`).prop('checked', allCheckedDataId);

            const numberId = parseInt(dataId.replace('app-', ''));

            // self.#disableNotAppId(checkedDataId, numberId);

            // Update admin-access checkbox
            const menuAndServiceList = $('.menu-list').length + $('.service-list').length;
            const checkedMenuAndServiceList = $('.menu-list:checked').length + $('.service-list:checked').length;
            const allChecked = menuAndServiceList === checkedMenuAndServiceList;
            $('#admin-access').prop('checked', allChecked);
        });

        $('.service-list').on('change', function () {
            const dataId = $(this).data('id');
            const dataIdList = $(`input.form-check-input.service-list[type="checkbox"][data-id="${dataId}"]`).length;
            const checkedDataId = $(
                `input.form-check-input.service-list[type="checkbox"][data-id="${dataId}"]:checked`
            ).length;
            const dataMenuList = $(`input.form-check-input.menu-list[type="checkbox"][data-id="${dataId}"]`).length;
            const checkedDataMenu = $(
                `input.form-check-input.menu-list[type="checkbox"][data-id="${dataId}"]:checked`
            ).length;
            const allCheckedDataId = dataIdList === checkedDataId && dataMenuList === checkedDataMenu;
            $(`input.form-check-input.app-check[data-id="${dataId}"]`).prop('checked', allCheckedDataId);

            const numberId = parseInt(dataId.replace('app-', ''));

            // self.#disableNotAppId(checkedDataId, numberId);

            // Update admin-access checkbox
            const menuAndServiceList = $('.menu-list').length + $('.service-list').length;
            const checkedMenuAndServiceList = $('.menu-list:checked').length + $('.service-list:checked').length;
            const allChecked = menuAndServiceList === checkedMenuAndServiceList;
            $('#admin-access').prop('checked', allChecked);
        });
    }

    #disableNotAppId(checkedDataId, numberId) {
        if (checkedDataId > 0) {
            document.querySelectorAll('input[type="checkbox"][data-id^="app-"]').forEach(checkbox => {
                // Extract the number from data-id (e.g., "app-2" → 2)
                let number = parseInt(checkbox.getAttribute('data-id').replace('app-', ''), 10);

                // Disable checkbox if number is not in allowedNumbers
                if (number !== numberId) {
                    checkbox.disabled = true;
                    checkbox.checked = false;
                }
            });
        } else {
            document.querySelectorAll('input[type="checkbox"][data-id^="app-"]').forEach(checkbox => {
                // Extract the number from data-id (e.g., "app-2" → 2)
                let number = parseInt(checkbox.getAttribute('data-id').replace('app-', ''), 10);

                // Disable checkbox if number is not in allowedNumbers
                if (number !== numberId) {
                    checkbox.disabled = false;
                }
            });
        }
    }

    #tidyMenusContent() {
        const rows = $('.accordion-table tr');
        const maxCols = Math.max(...rows.map((_, row) => $(row).find('td').length).get());

        rows.each(function () {
            const $row = $(this);
            const tdCount = $row.find('td').length;

            if (tdCount < maxCols) {
                const lastCell = $row.find('td:last');
                lastCell.attr('colspan', maxCols - tdCount + 1);
            }
        });
    }

    #accessNameBeautify(string) {
        const parts = string.split('-');

        const firstPart = parts[0];

        const middlePart = parts[1] ?? '';

        const action = parts.length > 2 ? parts[2] : '';

        const capitalize = str => str.charAt(0).toUpperCase() + str.slice(1);

        // Capitalize the middle part and the action
        const capitalizedMiddlePart = capitalize(middlePart);
        const capitalizedAction = capitalize(action);

        // Combine the results, you can add more logic here if needed to format the result
        const result = `${capitalizedMiddlePart}${capitalizedAction}`;

        return result;
    }

    async #getMenuData() {
        const response = await axios.get('/general/all-menus');

        return response.data.data;
    }

    async #getRoleById() {
        const response = await axios.get(this.#roleEndpoint + `/${this.#roleId}`);
        const data = response.data.data;

        $('#name').val(data.VROLENAME);
        $('#description').val(data.VROLEDESC);

        this.#triggerChecked(data.services, data.accesses);
    }
}
