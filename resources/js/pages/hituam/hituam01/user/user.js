import axios from "axios";
import { _showInvalidError, toast } from "../../../../helpers";
import { diffDates } from "@fullcalendar/core/internal";

class User {
    #userEndpoint = 'HITUAM/bd/master-user';
    #userId = window.location.pathname.split('/').pop();
    #usernform = document.getElementById('userProfileForm');
    #passwordform = document.getElementById('changePasswordForm');

    init() {
        this.#event();
    }

    #event() {
        $(document).on('click', '#update-user', e => {
            e.preventDefault();
            this.#updateCre('Username Or Email', 1);
        });

        $(document).on('click', '#update-user-password', e => {
            e.preventDefault();
            this.#updateCre('Password', 0);
        });
    }

    #updateCre(message, when) {
        const formData = when === 1 ? new FormData(this.#usernform) : new FormData(this.#passwordform);

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

        const url = this.#userEndpoint + (data.new_password != null ? '/password' : '') + `/${this.#userId}`;
        console.log(url);

        axios.post(url, data, {})
            .then(response => {
                toast.success(message + ' Was Updated!');
                setTimeout(() => {
                    window.location.href = '/' + this.#userEndpoint + '/profile/' + this.#userId;
                }, 1000);
            }).catch(error => {
                if (error.response && error.response.status === 422) {
                    _showInvalidError(error.response.data.errors);
                } else {
                    toast.error(error.response.data.message);
                }
            });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    new User().init();
});
