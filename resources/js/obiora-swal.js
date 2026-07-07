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

/**
 * Confirmation SweetAlert2 reliée à une méthode Livewire.
 * Fonctionne depuis onclick="" (contrairement à $wire qui n'existe que dans Alpine).
 */
export function obioraConfirmWire(button, method, title, text, ...params) {
    obioraConfirm(() => {
        const root = button?.closest?.('[wire\\:id]');

        if (! root || ! window.Livewire) {
            return;
        }

        const component = window.Livewire.find(root.getAttribute('wire:id'));

        if (component) {
            component.call(method, ...params);
        }
    }, title, text);
}
