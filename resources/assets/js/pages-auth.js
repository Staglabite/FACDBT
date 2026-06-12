/**
 *  Pages Authentication
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
    (() => {
        const formAuthentication = document.querySelector('#formAuthentication');

        // Form validation for Add new record
        if (formAuthentication && typeof FormValidation !== 'undefined') {
            const plugins = {
                trigger: new FormValidation.plugins.Trigger(),
                bootstrap5: new FormValidation.plugins.Bootstrap5({
                    eleValidClass: '',
                    rowSelector: '.form-control-validation'
                }),
                submitButton: new FormValidation.plugins.SubmitButton(),
                autoFocus: new FormValidation.plugins.AutoFocus()
            };

            FormValidation.formValidation(formAuthentication, {
                fields: {
                    username: {
                        validators: {
                            notEmpty: {
                                message: 'Please enter username'
                            },
                            stringLength: {
                                min: 3,
                                message: 'Username must be more than 3 characters'
                            }
                        }
                    },
                    password: {
                        validators: {
                            notEmpty: {
                                message: 'Please enter your password'
                            },
                            stringLength: {
                                min: 5,
                                message: 'Password must be more than 6 characters'
                            }
                        }
                    }
                },
                plugins,
                init: instance => {
                    instance.on('plugins.message.placed', e => {
                        if (e.element.parentElement.classList.contains('input-group')) {
                            e.element.parentElement.insertAdjacentElement('afterend', e.messageElement);
                        }
                    });
                }
            });
        }

        // Two Steps Verification for numeral input mask
        const numeralMaskElements = document.querySelectorAll('.numeral-mask');

        // Format function for numeral mask
        const formatNumeral = value => value.replace(/\D/g, ''); // Only keep digits

        if (numeralMaskElements.length > 0) {
            numeralMaskElements.forEach(numeralMaskEl => {
                numeralMaskEl.addEventListener('input', event => {
                    numeralMaskEl.value = formatNumeral(event.target.value);
                });
            });
        }
    })();
});
