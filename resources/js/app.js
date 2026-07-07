import './bootstrap';
import ApexCharts from 'apexcharts';
import { obioraConfirm, obioraConfirmWire, obioraSubmitInstallSetup, obioraToast } from './obiora-swal';

window.ApexCharts = ApexCharts;
window.obioraToast = obioraToast;
window.obioraConfirm = obioraConfirm;
window.obioraConfirmWire = obioraConfirmWire;
window.obioraSubmitInstallSetup = obioraSubmitInstallSetup;

document.addEventListener('livewire:init', () => {
    Livewire.on('notify', ({ type, message }) => {
        obioraToast(type, message);
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const flash = document.getElementById('obiora-flash-success');

    if (flash?.dataset.message) {
        obioraToast('success', flash.dataset.message);
    }

    const flashError = document.getElementById('obiora-flash-error');

    if (flashError?.dataset.message) {
        obioraToast('danger', flashError.dataset.message);
    }
});
