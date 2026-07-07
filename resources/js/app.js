import './bootstrap';
import ApexCharts from 'apexcharts';
import { obioraConfirm, obioraToast } from './obiora-swal';

window.ApexCharts = ApexCharts;
window.obioraToast = obioraToast;
window.obioraConfirm = obioraConfirm;

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
});
