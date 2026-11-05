import axios from 'axios';
import ApexCharts from 'apexcharts';
import { autoRefresh, track } from '../shared/dashboardStatus';
import { TimeRangeFilter } from '../shared/timeRangeFilter';

const ENDPOINT = 'FACDBT/ds/taking-qty-amount';

// Tabel detail part (DataTable Yajra) dan endpoint ajax-nya.
// URL harus diawali "/" karena DataTables memakai jQuery ajax (tidak ikut baseURL axios),
// sehingga URL relatif akan ditempel ke URL halaman (FACDBT/ds/FACDBT/ds/...).
const TABLES = {
    positive: { id: 'facdbtd003-positive-table', url: `/${ENDPOINT}/positive-table` },
    negative: { id: 'facdbtd003-negative-table', url: `/${ENDPOINT}/negative-table` }
};

// Tinggi chart di baris atas (chart dan card Nett Var memakai tinggi yang sama)
const TOP_CHART_HEIGHT = 255;

const THEME = {
    increase: '#6A7BFF',
    decrease: '#FF0000',
    total: '#2BE02B',
    label: '#595959'
};

class DashboardNettVariance {
    constructor() {
        this.timeFilter = new TimeRangeFilter(document.getElementById('nv-time-filter'), () => this.loadData());
        this.selectedAreas = new Set();
        this.charts = {};
        this.dataRequest = null;
    }

    init() {
        document.getElementById('nv-selected-area')?.addEventListener('click', () => {
            const [area] = this.selectedAreas;
            if (area) this.selectArea(area);
        });
        this.bindTableControls();
        this.loadFilters();
        this.loadData();

        // Perbarui ringkasan, chart, dan tabel secara berkala; halaman tabel yang dibuka tidak di-reset
        autoRefresh(() => this.loadData({ keepTablePage: true }));
    }

    // -------------------------------------------------------------
    // Filter TIME (rentang Dari - Sampai); AREA dipilih dengan klik chart Nett Variance by Area
    // -------------------------------------------------------------
    loadFilters() {
        axios.get(`${ENDPOINT}/filters`)
            .then(res => {
                const data = res.data || {};

                this.timeFilter.setDays(data.time_range?.days);
            })
            .catch(err => console.error('Error load filters:', err));
    }

    // Klik area di chart Nett Variance by Area: filter area (klik lagi untuk batal)
    selectArea(area) {
        const same = this.selectedAreas.has(area);
        this.selectedAreas.clear();
        if (!same) this.selectedAreas.add(area);

        const chip = document.getElementById('nv-selected-area');
        if (chip) {
            chip.classList.toggle('d-none', this.selectedAreas.size === 0);
            chip.querySelector('span').textContent = same ? '' : `Area ${area}`;
        }
        // Ganti area: tabel kembali ke urutan awal (Var Amount paling positif / paling minus)
        this.loadData({ resetOrder: true });
    }

    // -------------------------------------------------------------
    // Data
    // -------------------------------------------------------------
    loadData({ keepTablePage = false, resetOrder = false } = {}) {
        const params = this.timeFilter.params();
        if (this.selectedAreas.size > 0) {
            params.areas = Array.from(this.selectedAreas);
        }

        // Batalkan request sebelumnya supaya klik filter beruntun tidak menumpuk request
        if (this.dataRequest) {
            this.dataRequest.abort();
        }
        const controller = new AbortController();
        this.dataRequest = controller;

        track(axios.get(`${ENDPOINT}/data`, { params, signal: controller.signal, timeout: 60000 }))
            .then(res => this.render(res.data || {}, keepTablePage, resetOrder))
            .catch(err => {
                if (axios.isCancel(err)) return;
                console.error('Error load nett variance:', err);
            })
            .finally(() => {
                if (this.dataRequest === controller) {
                    this.dataRequest = null;
                }
            });
    }

    render(data, keepTablePage = false, resetOrder = false) {
        const positive = data.positive || {};
        const negative = data.negative || {};

        // Angka besar di card hitam dikecilkan supaya tetap satu baris
        this.setText('nv-nett-qty', this.fmtNumber(data.nett_qty), true);
        this.setText('nv-nett-amount', this.fmtMillion(data.nett_amount), true);
        this.setText('nv-positive-qty', this.fmtNumber(positive.qty));
        this.setText('nv-positive-amount', this.fmtShort(positive.amount));
        this.setText('nv-negative-qty', this.fmtNumber(negative.qty));
        this.setText('nv-negative-amount', this.fmtShort(negative.amount));

        this.renderPosNeg(Number(positive.amount || 0), Number(negative.amount || 0));
        this.renderAreaWaterfall(data.by_area || []);
        // Tabel dimuat setelah ringkasan selesai supaya memakai hasil perhitungan yang sudah di-cache
        this.reloadTables(!keepTablePage, resetOrder);
    }

    setText(id, text, fit = false) {
        const el = document.getElementById(id);
        if (!el) return;

        el.textContent = text;
        if (fit) {
            this.fitText(el);
            this.watchFit(el);
        }
    }

    // Satu baris: ukuran huruf diperkecil bertahap sampai angka muat di lebar card.
    // Class fs-3 Bootstrap memakai !important, jadi ukuran inline juga harus !important supaya berlaku.
    fitText(el) {
        el.style.whiteSpace = 'nowrap';
        el.style.removeProperty('font-size');
        let size = parseFloat(getComputedStyle(el).fontSize);
        while (el.scrollWidth > el.clientWidth && size > 12) {
            size -= 1;
            el.style.setProperty('font-size', `${size}px`, 'important');
        }
    }

    // Sesuaikan lagi saat lebar card berubah (resize window, sidebar dibuka / ditutup)
    watchFit(el) {
        if (el.dataset.fitWatched || typeof ResizeObserver === 'undefined') return;
        el.dataset.fitWatched = '1';

        let width = el.parentElement.clientWidth;
        new ResizeObserver(() => {
            if (el.parentElement.clientWidth === width) return;
            width = el.parentElement.clientWidth;
            this.fitText(el);
        }).observe(el.parentElement);
    }

    // Contoh: -57123456 -> "-57M" ("+ 0" supaya nilai kecil negatif tidak tampil "-0M")
    fmtMillion(v) {
        return `${(Math.round(Number(v || 0) / 1e6) + 0).toLocaleString('en-US')}M`;
    }

    // Disingkat tanpa desimal: -143331250123 -> "-143,331M", 523400 -> "523K", 870 -> "870"
    fmtShort(v) {
        const n = Number(v || 0);
        const abs = Math.abs(n);
        const [value, unit] = abs >= 1e6 ? [n / 1e6, 'M'] : abs >= 1e3 ? [n / 1e3, 'K'] : [n, ''];
        return `${(Math.round(value) + 0).toLocaleString('en-US')}${unit}`;
    }

    // Contoh: -8170 -> "-8,170"
    fmtNumber(v) {
        return (Math.round(Number(v || 0)) + 0).toLocaleString('en-US');
    }

    // -------------------------------------------------------------
    // Chart
    // -------------------------------------------------------------
    destroyChart(id) {
        if (this.charts[id]) {
            this.charts[id].destroy();
            delete this.charts[id];
        }
    }

    // Skala sumbu Y mengikuti ujung bar terendah/tertinggi supaya bar memakai seluruh tinggi chart
    yRange(points) {
        const min = Math.min(0, ...points.map(p => p.y[0]));
        const max = Math.max(0, ...points.map(p => p.y[1]));
        const pad = (max - min) * 0.08 || 1;

        return { show: false, min: min - pad, max: max + pad };
    }

    // extra.events = event ApexCharts (mis. klik), sisanya menimpa opsi chart
    waterfallOptions(points, height, { events = {}, ...extra } = {}) {
        return {
            series: [{ name: 'Variance', data: points }],
            chart: {
                type: 'rangeBar',
                height,
                fontFamily: 'inherit',
                toolbar: { show: false },
                zoom: { enabled: false },
                animations: { enabled: true, speed: 500 },
                events
            },
            plotOptions: {
                bar: { horizontal: false, columnWidth: '65%', borderRadius: 0 }
            },
            dataLabels: {
                enabled: true,
                formatter: (val, opts) => this.fmtMillion(points[opts.dataPointIndex].value),
                // Saat background aktif, style.colors dipakai sebagai warna kotak label
                style: { fontSize: '11px', fontWeight: 600, colors: [THEME.label] },
                background: {
                    enabled: true,
                    foreColor: '#FFFFFF',
                    borderRadius: 3,
                    borderWidth: 0,
                    padding: 4,
                    opacity: 1,
                    dropShadow: { enabled: false }
                }
            },
            yaxis: this.yRange(points),
            grid: { show: false, padding: { left: 25, right: 10 } },
            tooltip: {
                custom: ({ dataPointIndex }) => {
                    const p = points[dataPointIndex];
                    return `<div class="px-2 py-1"><strong>${p.x}</strong>: ${this.fmtMillion(p.value)}</div>`;
                }
            },
            ...extra
        };
    }

    // Var (+) naik dari 0, Var (-) turun dari puncak Var (+)
    renderPosNeg(positive, negative) {
        const el = document.getElementById('nv-chart-posneg');
        if (!el) return;

        this.destroyChart('posneg');
        el.innerHTML = '';

        const end = positive + negative;
        const points = [
            { x: 'Var (+)', y: [0, positive], fillColor: THEME.increase, value: positive },
            { x: 'Var (-)', y: [Math.min(positive, end), Math.max(positive, end)], fillColor: THEME.decrease, value: negative }
        ];

        this.charts.posneg = new ApexCharts(el, this.waterfallOptions(points, TOP_CHART_HEIGHT, {
            xaxis: {
                type: 'category',
                axisBorder: { show: false },
                axisTicks: { show: false },
                tooltip: { enabled: false },
                labels: { style: { fontSize: '11px', colors: '#555555' } }
            },
            legend: { show: false }
        }));
        this.charts.posneg.render();
    }

    // Waterfall per area (urut dari variance terbesar) ditutup bar Total. Selalu memuat semua area;
    // klik area = filter area, area lain dipudarkan.
    renderAreaWaterfall(items) {
        const el = document.getElementById('nv-chart-area');
        if (!el) return;

        this.destroyChart('area');

        if (items.length === 0) {
            el.innerHTML = '<p class="text-center text-muted my-5">No Data Available</p>';
            return;
        }

        el.innerHTML = '';
        el.style.cursor = 'pointer';
        let running = 0;
        const points = items.map(d => {
            const value = Number(d.value || 0);
            const start = running;
            running += value;
            const color = value < 0 ? THEME.decrease : THEME.increase;
            const dimmed = this.selectedAreas.size > 0 && !this.selectedAreas.has(d.area);

            return {
                x: d.area,
                y: [Math.min(start, running), Math.max(start, running)],
                fillColor: dimmed ? `${color}40` : color,
                value
            };
        });
        const total = running;
        const select = index => {
            if (items[index]) this.selectArea(items[index].area);
        };
        points.push({ x: 'Total', y: [Math.min(0, total), Math.max(0, total)], fillColor: THEME.total, value: total });

        this.charts.area = new ApexCharts(el, this.waterfallOptions(points, TOP_CHART_HEIGHT, {
            events: {
                click: (event, ctx, config) => select(config.dataPointIndex),
                xAxisLabelClick: (event, ctx, config) => select(config.labelIndex)
            },
            xaxis: {
                type: 'category',
                axisBorder: { show: false },
                axisTicks: { show: false },
                tooltip: { enabled: false },
                labels: {
                    rotate: -45,
                    rotateAlways: true,
                    // Nama area ditampilkan lengkap (tidak dipotong)
                    trim: false,
                    maxHeight: 120,
                    hideOverlappingLabels: false,
                    style: { fontSize: '10px', colors: '#555555' }
                }
            },
            legend: {
                show: true,
                showForSingleSeries: true,
                position: 'bottom',
                fontSize: '11px',
                customLegendItems: ['Increase', 'Decrease', 'Total'],
                markers: { size: 5, shape: 'circle', strokeWidth: 0, fillColors: [THEME.increase, THEME.decrease, THEME.total] },
                itemMargin: { horizontal: 6, vertical: 0 }
            }
        }));
        this.charts.area.render();
    }

    // -------------------------------------------------------------
    // Tabel detail part (DataTable Yajra, template seperti FACDBT01)
    // -------------------------------------------------------------
    filterParams() {
        const params = new URLSearchParams(this.timeFilter.params());
        this.selectedAreas.forEach(area => params.append('areas[]', area));
        return params;
    }

    tableParams(tableId) {
        const params = this.filterParams();
        const keyword = $(`.nv-search[data-table="${tableId}"]`).val();
        if (keyword) {
            params.set('keyword', keyword);
        }
        return params;
    }

    // resetOrder: kembali ke urutan awal tabel; selain itu urutan yang dipilih lewat judul kolom dipertahankan
    reloadTable({ id, url }, resetPaging = true, resetOrder = false) {
        if (!$.fn.dataTable.isDataTable(`#${id}`)) return;

        const table = $(`#${id}`).DataTable();
        if (resetOrder) {
            table.order(table.init().order || []);
        }

        const query = this.tableParams(id).toString();
        table.ajax.url(query ? `${url}?${query}` : url).load(null, resetPaging);
    }

    reloadTables(resetPaging = true, resetOrder = false) {
        Object.values(TABLES).forEach(table => this.reloadTable(table, resetPaging, resetOrder));
    }

    bindTableControls() {
        $(document).on('change', '.nv-entries', e => {
            const id = $(e.target).data('table');
            if ($.fn.dataTable.isDataTable(`#${id}`)) {
                $(`#${id}`).DataTable().page.len(Number($(e.target).val())).draw();
            }
        });

        let searchTimeout;
        $(document).on('keyup', '.nv-search', e => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const id = $(e.target).data('table');
                const table = Object.values(TABLES).find(t => t.id === id);
                if (table) this.reloadTable(table);
            }, 500);
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new DashboardNettVariance().init();
});
