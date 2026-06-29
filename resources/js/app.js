import Alpine from 'alpinejs';

const idFormatter = new Intl.NumberFormat('id-ID');

Alpine.data('rupiah', (initial = '') => ({
    raw: '',
    display: '',
    init() {
        this.setFromDigits(String(initial ?? '').replace(/[^0-9]/g, ''));
    },
    setFromDigits(digits) {
        this.raw = digits;
        this.display = digits ? idFormatter.format(parseInt(digits, 10)) : '';
    },
    onInput(event) {
        const digits = event.target.value.replace(/[^0-9]/g, '');
        this.setFromDigits(digits);
        event.target.value = this.display;
    },
}));

window.Alpine = Alpine;
Alpine.start();
