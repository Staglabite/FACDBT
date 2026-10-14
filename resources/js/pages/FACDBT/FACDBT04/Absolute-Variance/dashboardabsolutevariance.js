import axios from 'axios';
import ApexCharts from 'apexcharts';

const ENDPOINT = 'FACDBT/ds/absolute-variance-dashboard';

// Tabel detail part (DataTable Yajra) dan endpoint ajax-nya.
// URL harus diawali "/" karena DataTables memakai jQuery ajax (tidak ikut baseURL axios),
// sehingga URL relatif akan ditempel ke URL halaman (FACDBT/ds/FACDBT/ds/...).
const TABLES = {
    positive: { id: 'facdbtd005-positive-table', url: `/${ENDPOINT}/positive-table` },
    negative: { id: 'facdbtd005-negative-table', url: `/${ENDPOINT}/negative-table` }
};

// Tinggi chart di baris atas (kotak TIME, chart, dan card Absolute Var memakai tinggi yang sama)
const TOP_CHART_HEIGHT = 205;

const THEME = {
    absolute: '#0F1C6E',
    increase: '#6A7BFF',
    decrease: '#FF0000',
    total: '#2BE02B',
    label: '#595959'
};

class DashboardAbsoluteVariance {
    constructor() {
        this.selectedTime = null;
        this.selectedAreas = new Set();
        this.charts = {};
        this.dataRequest = null;
    }

    init() {
        this.bindTableControls();
        this.loadFilters();
        this.loadData();
    }

    // -------------------------------------------------------------
    // Filter TIME (satu pilihan) & AREA (boleh lebih dari satu)
    // -------------------------------------------------------------
    loadFilters() {
        axios.get(`${ENDPOINT}/filters`)
            .then(res => {
                const data = res.data || {};

                this.renderFilterList('av-time-filter', (data.times || []).map(t => ({ value: t.full_time, label: t.label })), value => {
                    this.selectedTime = this.selectedTime === value ? null : value;
                    return new Set(this.selectedTime ? [this.selectedTime] : []);
                });

                this.renderFilterList('av-area-filter', (data.areas || []).map(a => ({ value: a, label: a })), value => {
                    if (this.selectedAreas.has(value)) {
                        this.selectedAreas.delete(value);
                    } else {
                        this.selectedAreas.add(value);
                    }
                    return this.selectedAreas;
                });
            })
            .catch(err => console.error('Error load filters:', err));
    }

    // onToggle mengembalikan Set nilai yang aktif setelah item diklik
    renderFilterList(containerId, items, onToggle) {
        const container = document.getElementById(containerId);
        if (!container) return;

        container.innerHTML = '';
        const elements = items.map(item => {
            const el = document.createElement('div');
            el.className = 'av-filter-item d-flex align-items-center gap-2 small text-dark';
            el.dataset.value = item.value;
            el.title = item.label;

            const box = document.createElement('span');
            box.className = 'av-box';
            const text = document.createElement('span');
            text.className = 'text-truncate';
            text.textContent = item.label;
            el.append(box, text);

            el.addEventListener('click', () => {
                const active = onToggle(item.value);
                elements.forEach(e => e.classList.toggle('active', active.has(e.dataset.value)));
                this.loadData();
            });

            container.appendChild(el);
            return el;
        });
    }

    // -------------------------------------------------------------
    // Data
    // -------------------------------------------------------------
    loadData() {
        const params = {};
        if (this.selectedTime) {
            params.time = this.selectedTime;
        }
        if (this.selectedAreas.size > 0) {
            params.areas = Array.from(this.selectedAreas);
        }

        // Batalkan request sebelumnya supaya klik filter beruntun tidak menumpuk request
        if (this.dataRequest) {
            this.dataRequest.abort();
        }
        const controller = new AbortController();
        this.dataRequest = controller;

        axios.get(`${ENDPOINT}/data`, { params, signal: controller.signal, timeout: 60000 })
            .then(res => this.render(res.data || {}))
            .catch(err => {
                if (axios.isCancel(err)) return;
                console.error('Error load absolute variance:', err);
            })
            .finally(() => {
                if (this.dataRequest === controller) {
                    this.dataRequest = null;
                }
            });
    }

    render(data) {
        const positive = data.positive || {};
        const negative = data.negative || {};

        this.setText('av-absolute-qty', this.fmtNumber(data.absolute_qty));
        this.setText('av-absolute-amount', this.fmtMillion(data.absolute_amount));
        this.setText('av-positive-qty', this.fmtThousand(positive.qty));
        this.setText('av-positive-amount', this.fmtMillion(positive.amount));
        this.setText('av-negative-qty', this.fmtThousand(negative.qty));
        this.setText('av-negative-amount', this.fmtMillion(negative.amount));

        this.renderPosNeg(Number(positive.amount || 0), Number(negative.amount || 0));
        this.renderAreaWaterfall(data.by_area || [], Number(data.absolute_amount || 0));
        // Tabel dimuat setelah ringkasan selesai supaya memakai hasil perhitungan yang sudah di-cache
        this.reloadTables();
    }

    setText(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
    }

    // Contoh: -57123456 -> "-57M" ("+ 0" supaya nilai kecil negatif tidak tampil "-0M")
    fmtMillion(v) {
        return `${(Math.round(Number(v || 0) / 1e6) + 0).toLocaleString('en-US')}M`;
    }

    // Contoh: 12280 -> "12.28K"
    fmtThousand(v) {
        const k = Math.round(Number(v || 0) / 10) / 100 + 0;
        return `${k.toFixed(2)}K`;
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

    waterfallOptions(points, height, extra = {}) {
        return {
            series: [{ name: 'Variance', data: points }],
            chart: {
                type: 'rangeBar',
                height,
                fontFamily: 'inherit',
                toolbar: { show: false },
                zoom: { enabled: false },
                animations: { enabled: true, speed: 500 }
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

    // Var (+) naik dari 0, Var (-) (nilai absolut) menumpuk di atasnya, ditutup bar Total
    renderPosNeg(positive, negative) {
        const el = document.getElementById('av-chart-posneg');
        if (!el) return;

        this.destroyChart('posneg');
        el.innerHTML = '';

        const total = positive + negative;
        const points = [
            { x: 'Var (+)', y: [0, positive], fillColor: THEME.increase, value: positive },
            { x: 'Var (-)', y: [positive, total], fillColor: THEME.decrease, value: negative },
            { x: 'Total', y: [0, total], fillColor: THEME.total, value: total }
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

    // Waterfall absolute variance per area (urut dari terbesar) ditutup bar Total
    renderAreaWaterfall(items, total) {
        const el = document.getElementById('av-chart-area');
        if (!el) return;

        this.destroyChart('area');

        if (items.length === 0) {
            el.innerHTML = '<p class="text-center text-muted my-5">No Data Available</p>';
            return;
        }

        el.innerHTML = '';
        let running = 0;
        const points = items.map(d => {
            const value = Number(d.value || 0);
            const start = running;
            running += value;

            return {
                x: d.area,
                y: [Math.min(start, running), Math.max(start, running)],
                fillColor: THEME.absolute,
                value
            };
        });
        points.push({ x: 'Total', y: [Math.min(0, total), Math.max(0, total)], fillColor: THEME.total, value: total });

        this.charts.area = new ApexCharts(el, this.waterfallOptions(points, TOP_CHART_HEIGHT, {
            xaxis: {
                type: 'category',
                axisBorder: { show: false },
                axisTicks: { show: false },
                tooltip: { enabled: false },
                labels: {
                    rotate: -45,
                    rotateAlways: true,
                    trim: true,
                    maxHeight: 60,
                    hideOverlappingLabels: false,
                    style: { fontSize: '10px', colors: '#555555' }
                }
            },
            legend: {
                show: true,
                showForSingleSeries: true,
                position: 'bottom',
                fontSize: '11px',
                customLegendItems: ['Absolute Variance', 'Total'],
                markers: { size: 5, shape: 'circle', strokeWidth: 0, fillColors: [THEME.absolute, THEME.total] },
                itemMargin: { horizontal: 6, vertical: 0 }
            }
        }));
        this.charts.area.render();
    }

    // -------------------------------------------------------------
    // Tabel detail part (DataTable Yajra, template seperti FACDBT01)
    // -------------------------------------------------------------
    filterParams() {
        const params = new URLSearchParams();
        if (this.selectedTime) {
            params.set('time', this.selectedTime);
        }
        this.selectedAreas.forEach(area => params.append('areas[]', area));
        return params;
    }

    tableParams(tableId) {
        const params = this.filterParams();
        const keyword = $(`.av-search[data-table="${tableId}"]`).val();
        if (keyword) {
            params.set('keyword', keyword);
        }
        return params;
    }

    reloadTable({ id, url }) {
        if (!$.fn.dataTable.isDataTable(`#${id}`)) return;

        const query = this.tableParams(id).toString();
        $(`#${id}`).DataTable().ajax.url(query ? `${url}?${query}` : url).load();
    }

    reloadTables() {
        Object.values(TABLES).forEach(table => this.reloadTable(table));
    }

    bindTableControls() {
        $(document).on('change', '.av-entries', e => {
            const id = $(e.target).data('table');
            if ($.fn.dataTable.isDataTable(`#${id}`)) {
                $(`#${id}`).DataTable().page.len(Number($(e.target).val())).draw();
            }
        });

        let searchTimeout;
        $(document).on('keyup', '.av-search', e => {
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
    new DashboardAbsoluteVariance().init();
});
