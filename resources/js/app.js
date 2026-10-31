import './bootstrap';
import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;

Alpine.data('countryModal', () => ({
    open: false,
    mode: 'create',
    action: '',
    name: '',
    code: '',
    currency: 'MAD',
    description: '',
    is_active: '1',
    openCreate(action, currency) {
        this.mode = 'create';
        this.action = action;
        this.name = '';
        this.code = '';
        this.currency = currency || 'MAD';
        this.description = '';
        this.is_active = '1';
        this.open = true;
    },
    openEdit(action, country) {
        this.mode = 'edit';
        this.action = action;
        this.name = country.name;
        this.code = country.code;
        this.currency = country.currency;
        this.description = country.description || '';
        this.is_active = country.is_active ? '1' : '0';
        this.open = true;
    },
}));

Alpine.data('validationBoard', (ids) => ({
    selected: [],
    rejectOpen: false,
    rejectAction: '',
    rejectReference: '',
    ids,
    toggleAll(checked) {
        this.selected = checked ? [...this.ids] : [];
    },
    openReject(action, reference) {
        this.rejectAction = action;
        this.rejectReference = reference;
        this.rejectOpen = true;
    },
}));

Alpine.start();

document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
    if (!canvas.dataset.chart) {
        return;
    }

    const config = JSON.parse(canvas.dataset.chart);
    new Chart(canvas, config);
});
