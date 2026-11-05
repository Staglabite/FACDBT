import { Notyf } from 'notyf';

export const _showInvalidError = errors => {
    Object.keys(errors).forEach(field => {
        // Try multiple selectors to find the input
        const selectors = [
            `input[name="${field}"]`,
            `select[name="${field}"]`,
            `textarea[name="${field}"]`,
            `#${field}` // fallback for inputs with id matching field name
        ];

        let input = null;
        for (const selector of selectors) {
            input = document.querySelector(selector);
            if (input) break;
        }

        if (input) {
            input.classList.add('is-invalid');

            // Remove any existing invalid-feedback for this input
            const existingFeedback = input.parentNode.querySelector('.invalid-feedback');
            if (existingFeedback) {
                existingFeedback.remove();
            }

            // Check if the input is inside an input-group
            const inputGroup = input.closest('.input-group');
            if (inputGroup) {
                // For input-group, append feedback to the group
                const feedback = document.createElement('div');
                feedback.classList.add('invalid-feedback');
                feedback.innerText = errors[field][0];
                inputGroup.appendChild(feedback);
            } else {
                // Check if this is a Select2 element
                const select2Container = input.parentNode.querySelector('.select2-container');
                if (select2Container) {
                    // For Select2, insert feedback after the Select2 container
                    const feedback = document.createElement('div');
                    feedback.classList.add('invalid-feedback');
                    feedback.innerText = errors[field][0];

                    if (select2Container.nextSibling) {
                        input.parentNode.insertBefore(feedback, select2Container.nextSibling);
                    } else {
                        input.parentNode.appendChild(feedback);
                    }
                } else {
                    // For regular inputs, insert feedback after the input
                    const feedback = document.createElement('div');
                    feedback.classList.add('invalid-feedback');
                    feedback.innerText = errors[field][0];

                    // Insert after the input element
                    if (input.nextSibling) {
                        input.parentNode.insertBefore(feedback, input.nextSibling);
                    } else {
                        input.parentNode.appendChild(feedback);
                    }
                }
            }

            input.focus();
        }
    });
};

export const _sanitizeMins = () => {
    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('keydown', function (e) {
            if (e.key === '-' || e.key === 'e') {
                e.preventDefault();
            }
        });
        input.addEventListener('input', function () {
            if (this.value < 0) {
                this.value = 0;
            }
        });
    });
};

export const _formToJson = form => {
    const formData = new FormData(form);
    const jsonData = {};
    formData.forEach((value, key) => {
        jsonData[key] = value;
    });

    return jsonData;
};

// Custom Notyf class to allow HTML content in messages
class CustomNotyf extends Notyf {
    _renderNotification(options) {
        const notification = super._renderNotification(options);

        // Replace textContent with innerHTML to render HTML content
        if (options.message) {
            notification.message.innerHTML = options.message;
        }

        return notification;
    }
}

export const toast = new CustomNotyf({
    duration: 3000,
    ripple: true,
    dismissible: false,
    position: { x: 'right', y: 'top' },
    types: [
        {
            type: 'info',
            background: config.colors.info,
            className: 'notyf__info',
            icon: {
                className: 'icon-base ti tabler-info-circle-filled icon-md text-white',
                tagName: 'i'
            }
        },
        {
            type: 'warning',
            background: config.colors.warning,
            className: 'notyf__warning',
            icon: {
                className: 'icon-base ti tabler-alert-triangle-filled icon-md text-white',
                tagName: 'i'
            }
        },
        {
            type: 'success',
            background: config.colors.success,
            className: 'notyf__success',
            icon: {
                className: 'icon-base ti tabler-circle-check-filled icon-md text-white',
                tagName: 'i'
            }
        },
        {
            type: 'error',
            background: config.colors.danger,
            className: 'notyf__error',
            icon: {
                className: 'icon-base ti tabler-xbox-x-filled icon-md text-white',
                tagName: 'i'
            }
        }
    ]
});
