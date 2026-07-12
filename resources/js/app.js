import './bootstrap';
import './echo';
import './realtime';
import './sidebar';
import './theme';
import ApexCharts from 'apexcharts';
import {
    obioraParseChartData,
    obioraRenderAreaChart,
    obioraRenderLineChart,
    obioraRenderResponseChart,
} from './obiora-charts';
import { obioraConfirm, obioraConfirmWire, obioraSubmitInstallSetup, obioraToast } from './obiora-swal';
import { obioraCopyFromButton, obioraCopyText } from './copy';

window.ApexCharts = ApexCharts;
window.obioraRenderResponseChart = obioraRenderResponseChart;
window.obioraRenderAreaChart = obioraRenderAreaChart;
window.obioraRenderLineChart = obioraRenderLineChart;
window.obioraParseChartData = obioraParseChartData;
window.obioraToast = obioraToast;
window.obioraConfirm = obioraConfirm;
window.obioraConfirmWire = obioraConfirmWire;
window.obioraSubmitInstallSetup = obioraSubmitInstallSetup;
window.obioraCopyText = obioraCopyText;
window.obioraCopyFromButton = obioraCopyFromButton;

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
