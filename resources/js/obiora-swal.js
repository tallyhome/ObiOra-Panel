import Swal from 'sweetalert2';

const toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 4500,
    timerProgressBar: true,
    didOpen: (el) => {
        el.addEventListener('mouseenter', Swal.stopTimer);
        el.addEventListener('mouseleave', Swal.resumeTimer);
    },
});

export function obioraToast(type, message) {
    const icon = type === 'danger' ? 'error' : type;

    toast.fire({ icon, title: message });
}

export function obioraConfirm(callback, title = 'Confirmer', text = 'Cette action est irréversible.') {
    Swal.fire({
        title,
        text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3dd68c',
        cancelButtonColor: '#495057',
        confirmButtonText: 'Oui, continuer',
        cancelButtonText: 'Annuler',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed && typeof callback === 'function') {
            callback();
        }
    });
}
