import Swal from "sweetalert2";

export function showLoadingSwal(title = 'Please wait...') {
    Swal.fire({
        title: title,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        },
    });
}

export function closeSwal() {
    Swal.close();
}
