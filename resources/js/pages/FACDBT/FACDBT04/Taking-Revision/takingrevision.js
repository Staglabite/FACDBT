import axios from 'axios';
import ApexCharts from 'apexcharts';
import { autoRefresh, track } from '../shared/dashboardStatus';
import { dimCategories, clickedIndex } from '../shared/chartDim';

const ENDPOINT = 'FACDBT/ds/taking-data-revision';

// Tinggi chart, disesuaikan dengan tinggi card di blade
const AREA_CHART_HEIGHT = 440;
const SHARE_CHART_HEIGHT = 380;

const THEME = {
    itemBook: '#2E75B6',
    percent: '#FFD000',
    bar: '#C00000',
    label: '#404040'
};

class DashboardTakingRevision {
    constructor() {
        // Filter interaktif dari klik chart; null = semua
        // area: klik chart Taking Data Revision, modifier: klik chart Revision Performance
        this.filters = { area: null, modifier: null };
        this.areaItems = [];
        this.performanceItems = [];
        this.charts = {};
        this.dataRequest = null;
    }

    init() {
        ['area', 'modifier'].forEach(key => {
            document.getElementById(`tr-${key}-selected`)?.addEventListener('click', () => this.toggleFilter(key, this.filters[key]));
        });

        this.loadData();
        autoRefresh(() => this.loadData());
    }

    // -------------------------------------------------------------
    // Filter interaktif (klik lagi nilai yang sama = batal)
    // -------------------------------------------------------------
    toggleFilter(key, value) {
        this.filters[key] = this.filters[key] === value ? null : value;

        const chip = document.getElementById(`tr-${key}-selected`);
        if (chip) {
            chip.classList.toggle('d-none', !this.filters[key]);
            chip.querySelector('span').textContent = this.filters[key] || '';
        }

        this.applyDims();
        this.loadData();
    }

    // Kategori yang tidak dipilih dipudarkan
    applyDims() {
        if (this.charts.area) {
            dimCategories(this.charts.area, this.areaItems.length, this.areaItems.findIndex(d => d.area === this.filters.area), { seriesIndex: 1 });
        }
        if (this.charts.performance) {
            dimCategories(this.charts.performance, this.performanceItems.length, this.performanceItems.findIndex(d => d.label === this.filters.modifier), { horizontal: true });
        }
    }

    // -------------------------------------------------------------
    // Data
    // -------------------------------------------------------------
    loadData() {
        const params = {};
        if (this.filters.area) params.area = this.filters.area;
        if (this.filters.modifier) params.modifier = this.filters.modifier;

        // Batalkan request sebelumnya supaya klik filter beruntun tidak menumpuk request
        if (this.dataRequest) {
            this.dataRequest.abort();
        }
        const controller = new AbortController();
        this.dataRequest = controller;

        track(axios.get(`${ENDPOINT}/data`, { params, signal: controller.signal, timeout: 60000 }))
            .then(res => this.render(res.data || {}))
            .catch(err => {
                if (axios.isCancel(err)) return;
                console.error('Error load taking data revision:', err);
            })
            .finally(() => {
                if (this.dataRequest === controller) {
                    this.dataRequest = null;
                }
            });
    }

    render(data) {
        this.renderAreaChart(data.by_area || []);
        this.renderShareChart('reason', 'tr-chart-reason', data.reason_revision || []);
        this.renderShareChart('performance', 'tr-chart-performance', data.revision_performance || []);
    }

    destroyChart(id) {
        if (this.charts[id]) {
            this.charts[id].destroy();
            delete this.charts[id];
        }
    }

    noData(el) {
        el.innerHTML = '<p class="text-center text-muted my-5">No Data Available</p>';
    }

    // Kolom Item Book (sumbu kiri) + garis % Area (sumbu kanan), urut dari % Area terbesar
    renderAreaChart(items) {
        const el = document.getElementById('tr-chart-area');
        if (!el) return;

        this.destroyChart('area');
        if (items.length === 0) {
            this.noData(el);
            return;
        }
        el.innerHTML = '';

        const fmtPercent = v => (v === null || v === undefined ? '-' : `${Number(v).toFixed(1)}%`);
        this.areaItems = items;
        el.style.cursor = 'pointer';
        const dim = ctx => dimCategories(ctx, items.length, items.findIndex(d => d.area === this.filters.area), { seriesIndex: 1 });
        const select = index => {
            if (items[index]) this.toggleFilter('area', items[index].area);
        };

        this.charts.area = new ApexCharts(el, {
            series: [
                { name: 'Item Book', type: 'column', data: items.map(d => Number(d.item_book || 0)) },
                { name: '% Area', type: 'line', data: items.map(d => (d.percent === null ? null : Number(d.percent))) }
            ],
            chart: {
                type: 'line',
                height: AREA_CHART_HEIGHT,
                fontFamily: 'inherit',
                toolbar: { show: false },
                zoom: { enabled: false },
                animations: { enabled: true, speed: 500 },
                // Klik area = filter area chart lain
                events: {
                    mounted: dim,
                    updated: dim,
                    click: (event, ctx, config) => select(clickedIndex(ctx, config)),
                    xAxisLabelClick: (event, ctx, config) => select(config.labelIndex)
                }
            },
            colors: [THEME.itemBook, THEME.percent],
            stroke: { width: [0, 3], curve: 'straight' },
            markers: { size: [0, 4], colors: ['#FFFFFF'], strokeColors: THEME.percent, strokeWidth: 2 },
            plotOptions: { bar: { columnWidth: '55%' } },
            dataLabels: {
                enabled: true,
                enabledOnSeries: [0, 1],
                formatter: (val, { seriesIndex }) => (seriesIndex === 1 ? fmtPercent(val) : Number(val).toLocaleString('en-US')),
                offsetY: -6,
                // Label dalam kotak berwarna supaya tetap terbaca saat label kolom & garis berdekatan
                // (saat background aktif, style.colors dipakai sebagai warna kotak)
                style: { fontSize: '12px', fontWeight: 600, colors: [THEME.itemBook, '#B38F00'] },
                background: { enabled: true, foreColor: '#FFFFFF', borderRadius: 3, borderWidth: 0, padding: 3, opacity: 1, dropShadow: { enabled: false } }
            },
            xaxis: {
                categories: items.map(d => d.area),
                // Kolom + garis: tanpa 'between' area hover bergeser setengah kolom
                tickPlacement: 'between',
                axisTicks: { show: false },
                tooltip: { enabled: false },
                labels: {
                    rotate: -45,
                    rotateAlways: true,
                    // Nama area ditampilkan lengkap
                    trim: false,
                    maxHeight: 110,
                    hideOverlappingLabels: false,
                    style: { fontSize: '12px', colors: '#555555' }
                }
            },
            yaxis: [
                {
                    seriesName: 'Item Book',
                    min: 0,
                    labels: { formatter: v => Math.round(v).toLocaleString('en-US'), style: { fontSize: '12px' } }
                },
                {
                    seriesName: '% Area',
                    opposite: true,
                    min: 0,
                    labels: { formatter: v => `${Number(v).toFixed(1)}%`, style: { fontSize: '12px' } }
                }
            ],
            legend: { show: true, position: 'bottom', fontSize: '13px', markers: { size: 7, strokeWidth: 0 } },
            grid: { borderColor: '#EEEEEE', padding: { left: 5, right: 5 } },
            tooltip: {
                shared: true,
                intersect: false,
                y: {
                    formatter: (val, { seriesIndex, dataPointIndex }) => (seriesIndex === 1
                        ? `${fmtPercent(val)} (${Number(items[dataPointIndex].revisions).toLocaleString('en-US')} revisi)`
                        : Number(val).toLocaleString('en-US'))
                }
            }
        });
        this.charts.area.render();
    }

    // Bar horizontal porsi revisi (%), urut dari terbesar
    renderShareChart(id, elementId, items) {
        const el = document.getElementById(elementId);
        if (!el) return;

        this.destroyChart(id);
        if (items.length === 0) {
            this.noData(el);
            return;
        }
        el.innerHTML = '';

        const values = items.map(d => Number(d.percent || 0));
        const max = Math.max(...values, 1);

        // Revision Performance: klik nama = filter modified by; Reason Revision hanya mengikuti filter
        const clickable = id === 'performance';
        if (clickable) {
            this.performanceItems = items;
            el.style.cursor = 'pointer';
        }
        const dim = ctx => {
            if (clickable) dimCategories(ctx, items.length, items.findIndex(d => d.label === this.filters.modifier), { horizontal: true });
        };
        // Nama di sumbu Y juga bisa diklik (ApexCharts tidak punya event klik label sumbu Y)
        const bindLabels = ctx => {
            if (!clickable) return;
            ctx.el.querySelectorAll('.apexcharts-yaxis-label').forEach((label, index) => {
                if (label.dataset.bound) return;
                label.dataset.bound = '1';
                label.style.cursor = 'pointer';
                label.addEventListener('click', () => select(index));
            });
        };
        const select = index => {
            if (clickable && items[index]) this.toggleFilter('modifier', items[index].label);
        };

        this.charts[id] = new ApexCharts(el, {
            series: [{ name: 'Revisi', data: values }],
            chart: {
                type: 'bar',
                height: SHARE_CHART_HEIGHT,
                fontFamily: 'inherit',
                toolbar: { show: false },
                zoom: { enabled: false },
                animations: { enabled: true, speed: 500 },
                events: {
                    mounted: ctx => { dim(ctx); bindLabels(ctx); },
                    updated: ctx => { dim(ctx); bindLabels(ctx); },
                    // Hanya klik tepat di bar (klik nama ditangani bindLabels)
                    click: (event, ctx, config) => {
                        if (config.dataPointIndex >= 0) select(config.dataPointIndex);
                    }
                }
            },
            colors: [THEME.bar],
            plotOptions: { bar: { horizontal: true, barHeight: '55%', dataLabels: { position: 'top' } } },
            dataLabels: {
                enabled: true,
                formatter: v => `${Number(v).toFixed(1)}%`,
                offsetX: 30,
                style: { fontSize: '13px', fontWeight: 600, colors: [THEME.label] }
            },
            xaxis: {
                categories: items.map(d => d.label),
                // Ruang di kanan bar terpanjang untuk label persen
                max: max * 1.35,
                labels: { show: false },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                labels: { maxWidth: 200, style: { fontSize: '12px', colors: '#333333' } }
            },
            grid: { show: false, padding: { left: 15, right: 5 } },
            legend: { show: false },
            tooltip: {
                y: {
                    formatter: (val, { dataPointIndex }) => `${Number(val).toFixed(1)}% (${Number(items[dataPointIndex].count).toLocaleString('en-US')} revisi)`
                }
            }
        });
        this.charts[id].render();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    new DashboardTakingRevision().init();
});
