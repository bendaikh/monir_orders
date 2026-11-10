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

Alpine.data('countryServicesMenu', () => ({
    open: false,
    style: {},
    toggle() {
        if (this.open) {
            this.open = false;
            return;
        }

        this.place();
        this.open = true;
        this.$nextTick(() => this.place());
    },
    place() {
        const button = this.$refs.btn;
        if (!button) {
            return;
        }

        const rect = button.getBoundingClientRect();
        const width = Math.min(340, window.innerWidth - 16);
        const left = Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8));
        const panel = this.$refs.panel;
        const height = panel && panel.offsetHeight ? panel.offsetHeight : 220;
        const spaceBelow = window.innerHeight - rect.bottom;
        const openAbove = spaceBelow < height + 12 && rect.top > spaceBelow;
        const top = openAbove ? Math.max(8, rect.top - height - 6) : rect.bottom + 6;

        this.style = { top: `${top}px`, left: `${left}px`, width: `${width}px` };
    },
    init() {
        this.onClick = (event) => {
            if (!this.open) {
                return;
            }

            if (this.$refs.btn?.contains(event.target) || this.$refs.panel?.contains(event.target)) {
                return;
            }

            this.open = false;
        };
        this.onKey = (event) => {
            if (event.key === 'Escape') {
                this.open = false;
            }
        };
        this.onMove = () => {
            if (this.open) {
                this.place();
            }
        };
        document.addEventListener('click', this.onClick);
        window.addEventListener('keydown', this.onKey);
        window.addEventListener('resize', this.onMove);
        window.addEventListener('scroll', this.onMove, true);
    },
    destroy() {
        document.removeEventListener('click', this.onClick);
        window.removeEventListener('keydown', this.onKey);
        window.removeEventListener('resize', this.onMove);
        window.removeEventListener('scroll', this.onMove, true);
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
