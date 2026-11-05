// resources/js/components/swal.js
import Swal from 'sweetalert2';

export default class SweetAlertHelper {
    static showToastFromLaravel(swalData) {
        if (swalData) {
            const Toast = Swal.mixin({
                toast: true,
                position: swalData.position || 'top-end',
                showConfirmButton: false,
                timer: swalData.timer || 3000,
                timerProgressBar: true,
                didOpen: toast => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });

            Toast.fire({
                icon: swalData.type || 'info',
                html: `<span style="font-size:14px;line-height:1.2;">
                  <strong>${swalData.title || ''} <br> </strong> ${swalData.text || ''}
                </span>`
            });
        }
    }

    // Method untuk menginisialisasi Toast dari session
    static init() {
        // Cek apakah ada data swal di window object
        if (window.swalData) {
            this.showToastFromLaravel(window.swalData);
        }
    }

    // Static method untuk toast manual
    static showToast(type, title, text, options = {}) {
        const Toast = Swal.mixin({
            toast: true,
            position: options.position || 'top-end',
            showConfirmButton: false,
            timer: options.timer || 3000,
            timerProgressBar: true,
            didOpen: toast => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        Toast.fire({
            icon: type,
            title: title,
            text: text
        });
    }
}

// Auto initialize ketika DOM loaded
document.addEventListener('DOMContentLoaded', () => {
    SweetAlertHelper.init();
});
