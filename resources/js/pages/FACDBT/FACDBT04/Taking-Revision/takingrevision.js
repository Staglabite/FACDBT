import axios from 'axios';
import ApexCharts from 'apexcharts';
import { autoRefresh, track } from '../shared/dashboardStatus';

const ENDPOINT = 'FACDBT/ds/taking-data-revision';

const CHART_HEIGHT = 300;

const THEME = {
    itemBook: '#2E75B6',
    percent: '#FFD000',
    bar: '#C00000',
    label: '#404040'
};

class DashboardTakingRevision {
    constructor() {
        // Pilihan filter; kosong = semua
        this.selected = { modifier: new Set(), area: new Set() };
        this.options = { modifier: [], area: [] };
        this.charts = {};
        this.dataRequest = null;
    }

    init() {
        ['modifier', 'area'].forEach(key => {
            document.getElementById(`tr-${key}-clear`)?.addEventListener('click', () => {
                this.selected[key].clear();
                this.renderSlicer(key);
                this.loadData();
            });
        });

        this.loadData();
        autoRefresh(() => this.loadData());
    }

    // -------------------------------------------------------------
    // Filter Modified By & Area (slicer, boleh lebih dari satu)
    // -------------------------------------------------------------
    renderSlicer(key) {
        const container = document.getElementById(`tr-${key}-filter`);
        if (!container) return;

        const selected = this.selected[key];
        container.innerHTML = '';
        container.classList.toggle('has-selection', selected.size > 0);
        document.getElementById(`tr-${key}-clear`).disabled = selected.size === 0;

        this.options[key].forEach(value => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'tr-slicer-item';
            item.classList.toggle('active', selected.has(value));
            item.textContent = value;
            item.title = value;
            item.addEventListener('click', () => {
                if (selected.has(value)) {
                    selected.delete(value);
                } else {
                    selected.add(value);
                }
                this.renderSlicer(key);
                this.loadData();
            });
            container.appendChild(item);
        });
    }

    setOptions(key, values) {
        this.options[key] = (values || []).map(String);
        // Pilihan yang sudah tidak ada di daftar dibuang
        this.selected[key].forEach(v => {
            if (!this.options[key].includes(v)) this.selected[key].delete(v);
        });
        this.renderSlicer(key);
    }

    // -------------------------------------------------------------
    // Data
    // -------------------------------------------------------------
    loadData() {
        const params = {
            modifiers: Array.from(this.selected.modifier),
            areas: Array.from(this.selected.area)
        };

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
        this.setOptions('modifier', data.modifiers);
        this.setOptions('area', data.areas);

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

    // Kolom Item_Book (sumbu kiri) + garis % Area (sumbu kanan), urut dari % Area terbesar
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

        this.charts.area = new ApexCharts(el, {
            series: [
                { name: 'Item_Book', type: 'column', data: items.map(d => Number(d.item_book || 0)) },
                { name: '% Area', type: 'line', data: items.map(d => (d.percent === null ? null : Number(d.percent))) }
            ],
            chart: {
                type: 'line',
                height: CHART_HEIGHT,
                fontFamily: 'inherit',
                toolbar: { show: false },
                zoom: { enabled: false },
                animations: { enabled: true, speed: 500 }
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
                style: { fontSize: '10px', fontWeight: 600, colors: [THEME.label, THEME.label] },
                background: { enabled: false }
            },
            xaxis: {
                categories: items.map(d => d.area),
                axisTicks: { show: false },
                tooltip: { enabled: false },
                labels: {
                    rotate: -45,
                    rotateAlways: true,
                    trim: true,
                    maxHeight: 70,
                    hideOverlappingLabels: false,
                    style: { fontSize: '10px', colors: '#555555' }
                }
            },
            yaxis: [
                {
                    seriesName: 'Item_Book',
                    min: 0,
                    labels: { formatter: v => Math.round(v).toLocaleString('en-US'), style: { fontSize: '10px' } }
                },
                {
                    seriesName: '% Area',
                    opposite: true,
                    min: 0,
                    labels: { formatter: v => `${Number(v).toFixed(1)}%`, style: { fontSize: '10px' } }
                }
            ],
            legend: { show: true, position: 'bottom', fontSize: '11px', markers: { size: 6, strokeWidth: 0 } },
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

        this.charts[id] = new ApexCharts(el, {
            series: [{ name: 'Revisi', data: values }],
            chart: {
                type: 'bar',
                height: CHART_HEIGHT,
                fontFamily: 'inherit',
                toolbar: { show: false },
                zoom: { enabled: false },
                animations: { enabled: true, speed: 500 }
            },
            colors: [THEME.bar],
            plotOptions: { bar: { horizontal: true, barHeight: '45%', dataLabels: { position: 'top' } } },
            dataLabels: {
                enabled: true,
                formatter: v => `${Number(v).toFixed(1)}%`,
                offsetX: 22,
                style: { fontSize: '10px', fontWeight: 600, colors: [THEME.label] }
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
                labels: { maxWidth: 110, style: { fontSize: '10px', colors: '#333333' } }
            },
            grid: { show: false, padding: { left: 0, right: 5 } },
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
