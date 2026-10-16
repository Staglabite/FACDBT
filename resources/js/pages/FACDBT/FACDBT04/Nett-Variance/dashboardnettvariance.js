import axios from 'axios';
import ApexCharts from 'apexcharts';
import { autoRefresh, track } from '../shared/dashboardStatus';
import { TimeRangeFilter } from '../shared/timeRangeFilter';

const ENDPOINT = 'FACDBT/ds/nett-variance-dashboard';

// Tabel detail part (DataTable Yajra) dan endpoint ajax-nya.
// URL harus diawali "/" karena DataTables memakai jQuery ajax (tidak ikut baseURL axios),
// sehingga URL relatif akan ditempel ke URL halaman (FACDBT/ds/FACDBT/ds/...).
const TABLES = {
    positive: { id: 'facdbtd004-positive-table', url: `/${ENDPOINT}/positive-table` },
    negative: { id: 'facdbtd004-negative-table', url: `/${ENDPOINT}/negative-table` }
};

// Tinggi chart di baris atas (kotak TIME, chart, dan card Nett Var memakai tinggi yang sama)
const TOP_CHART_HEIGHT = 205;

const THEME = {
    increase: '#6A7BFF',
    decrease: '#FF0000',
    total: '#2BE02B',
    label: '#595959'
};

class DashboardNettVariance {
    constructor() {
        this.timeFilter = new TimeRangeFilter(document.getElementById('nv-time-filter'), () => this.loadData(), { vertical: true });
        this.selectedAreas = new Set();
        this.charts = {};
        this.dataRequest = null;
    }

    init() {
        this.bindTableControls();
        this.loadFilters();
        this.loadData();

        // Perbarui ringkasan, chart, dan tabel secara berkala; halaman tabel yang dibuka tidak di-reset
        autoRefresh(() => this.loadData({ keepTablePage: true }));
    }

    // -------------------------------------------------------------
    // Filter TIME (rentang Dari - Sampai) & AREA (boleh lebih dari satu)
    // -------------------------------------------------------------
    loadFilters() {
        axios.get(`${ENDPOINT}/filters`)
            .then(res => {
                const data = res.data || {};

                this.timeFilter.setDays(data.time_range?.days);

                this.renderFilterList('nv-area-filter', (data.areas || []).map(a => ({ value: a, label: a })), value => {
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
            el.className = 'nv-filter-item d-flex align-items-center gap-2 small text-dark';
            el.dataset.value = item.value;
            el.title = item.label;

            const box = document.createElement('span');
            box.className = 'nv-box';
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
    loadData({ keepTablePage = false } = {}) {
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
            .then(res => this.render(res.data || {}, keepTablePage))
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

    render(data, keepTablePage = false) {
        const positive = data.positive || {};
        const negative = data.negative || {};

        this.setText('nv-nett-qty', this.fmtNumber(data.nett_qty));
        this.setText('nv-nett-amount', this.fmtMillion(data.nett_amount));
        this.setText('nv-positive-qty', this.fmtThousand(positive.qty));
        this.setText('nv-positive-amount', this.fmtMillion(positive.amount));
        this.setText('nv-negative-qty', this.fmtThousand(negative.qty));
        this.setText('nv-negative-amount', this.fmtMillion(negative.amount));

        this.renderPosNeg(Number(positive.amount || 0), Number(negative.amount || 0));
        this.renderAreaWaterfall(data.by_area || [], Number(data.nett_amount || 0));
        // Tabel dimuat setelah ringkasan selesai supaya memakai hasil perhitungan yang sudah di-cache
        this.reloadTables(!keepTablePage);
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

    // Waterfall per area (urut dari variance terbesar) ditutup bar Total
    renderAreaWaterfall(items, total) {
        const el = document.getElementById('nv-chart-area');
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
                fillColor: value < 0 ? THEME.decrease : THEME.increase,
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

    reloadTable({ id, url }, resetPaging = true) {
        if (!$.fn.dataTable.isDataTable(`#${id}`)) return;

        const query = this.tableParams(id).toString();
        $(`#${id}`).DataTable().ajax.url(query ? `${url}?${query}` : url).load(null, resetPaging);
    }

    reloadTables(resetPaging = true) {
        Object.values(TABLES).forEach(table => this.reloadTable(table, resetPaging));
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
