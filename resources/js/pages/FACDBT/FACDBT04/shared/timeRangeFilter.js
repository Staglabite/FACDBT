/**
 * Filter rentang waktu taking (Dari - Sampai).
 * - Hanya data taking yang di-scan di rentang ini yang dihitung.
 * - Tanggal tidak diketik: dipilih dari hari periode taking (Hari 1, Hari 2, ...).
 *   Bila taking hanya 1 hari, pilihan hari disembunyikan.
 * - Belum diterapkan / di-reset = seluruh periode taking.
 *
 * Pemakaian:
 *   const filter = new TimeRangeFilter(container, () => this.loadData(), { vertical: true });
 *   - vertical : semua isian & tombol bersusun ke bawah (kolom sempit)
 *   - inline   : satu baris tanpa turun baris, tombol tepat di sebelah isian jam
 *   filter.setDays(res.data.days);
 *   axios.get(url, { params: { ...filter.params() } });
 */
export class TimeRangeFilter {
    constructor(container, onChange, { vertical = false, inline = false } = {}) {
        this.container = container;
        this.onChange = onChange;
        this.vertical = vertical;
        this.inline = inline;
        this.days = [];
        this.applied = null; // { from: 'Y-m-d H:i', to: 'Y-m-d H:i' }
    }

    setDays(days) {
        this.days = days || [];
        this.render();
    }

    params() {
        return this.applied ? { ...this.applied } : {};
    }

    render() {
        if (!this.container) return;

        const single = this.days.length <= 1;
        const options = this.days
            .map(d => `<option value="${d.value}">${d.label}</option>`)
            .join('');
        const daySelect = role => `<select class="form-select form-select-sm trf-day${single ? ' d-none' : ''}" data-role="${role}" aria-label="Hari">${options}</select>`;
        const timeInput = (role, value) => `<input type="time" class="form-control form-control-sm" data-role="${role}" value="${value}" aria-label="Jam">`;
        const label = text => `<span class="small fw-semibold text-dark">${text}</span>`;

        const group = this.vertical
            ? 'd-flex flex-column gap-1'
            : `d-flex align-items-center gap-1${this.inline ? ' flex-shrink-0' : ''}`;
        const wrapper = this.vertical
            ? 'd-flex flex-column gap-2'
            : this.inline
                ? 'd-flex flex-nowrap align-items-center gap-2 overflow-auto'
                : 'd-flex flex-wrap align-items-center justify-content-center gap-2';
        const buttons = this.vertical
            ? 'd-flex flex-column gap-1'
            : `d-flex gap-1 flex-shrink-0${this.inline ? '' : ' align-self-end'}`;

        this.container.innerHTML = `
            <div class="${wrapper}">
                <div class="${group}">${label('Dari')}${daySelect('from-day')}${timeInput('from-time', '00:00')}</div>
                <div class="${group}">${label('Sampai')}${daySelect('to-day')}${timeInput('to-time', '23:59')}</div>
                <div class="${buttons}">
                    <button type="button" class="btn btn-sm btn-dark" data-role="apply">Terapkan</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-role="reset" title="Seluruh periode taking">Reset</button>
                </div>
            </div>
            <div class="small text-muted text-center mt-1" data-role="status">Seluruh periode taking</div>`;

        // Default: dari hari pertama, sampai hari terakhir
        const toDay = this.el('to-day');
        if (toDay && this.days.length) toDay.value = this.days[this.days.length - 1].value;

        // Hari "Sampai" tidak boleh sebelum hari "Dari"
        this.el('from-day')?.addEventListener('change', () => {
            if (this.el('to-day').value < this.el('from-day').value) {
                this.el('to-day').value = this.el('from-day').value;
            }
        });

        this.el('apply').addEventListener('click', () => this.apply());
        this.el('reset').addEventListener('click', () => this.reset());
    }

    el(role) {
        return this.container.querySelector(`[data-role="${role}"]`);
    }

    value(side) {
        const day = this.el(`${side}-day`)?.value || this.days[0]?.value;
        const time = this.el(`${side}-time`)?.value;
        return day && time ? `${day} ${time}` : null;
    }

    apply() {
        const from = this.value('from');
        const to = this.value('to');
        const invalid = !from || !to || from > to;

        this.el('from-time').classList.toggle('is-invalid', invalid);
        this.el('to-time').classList.toggle('is-invalid', invalid);
        if (invalid) {
            this.setStatus('Jam "Dari" harus sebelum "Sampai"', true);
            return;
        }

        this.applied = { from, to };
        this.setStatus(`${this.describe(from)} – ${this.describe(to)}`);
        this.onChange();
    }

    reset() {
        this.render();
        const wasApplied = this.applied !== null;
        this.applied = null;
        if (wasApplied) this.onChange();
    }

    // "2026-03-06 13:00" -> "Hari 1 13:00" (tanpa label hari bila taking hanya 1 hari)
    describe(value) {
        const [day, time] = value.split(' ');
        if (this.days.length <= 1) return time;

        const index = this.days.findIndex(d => d.value === day);
        return `Hari ${index + 1} ${time}`;
    }

    setStatus(text, isError = false) {
        const status = this.el('status');
        if (!status) return;
        status.textContent = text;
        status.classList.toggle('text-danger', isError);
        status.classList.toggle('text-muted', !isError);
    }
}
