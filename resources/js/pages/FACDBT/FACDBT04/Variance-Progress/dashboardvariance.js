import axios from 'axios';
import ApexCharts from 'apexcharts';
import { autoRefresh, track } from '../shared/dashboardStatus';
import { TimeRangeFilter } from '../shared/timeRangeFilter';

const ENDPOINT = 'FACDBT/ds/variance-dashboard';

const THEME = {
    positive: '#6A7BFF',
    negative: '#FF0000',
    absolute: '#0F1C6E',
    total: '#2BE02B',
    labelDark: '#262626',
    labelGrey: '#595959'
};

const CHART_HEIGHT = 240;
const POSNEG_HEIGHT = 150;

class DashboardVariance {
    constructor() {
        this.timeFilter = new TimeRangeFilter(document.getElementById('time-filter-container'), () => this.loadData());
        this.charts = {};
        this.dataRequest = null;
    }

    init() {
        this.loadTimeRange();
        this.loadData();

        // Perbarui chart secara berkala dengan rentang waktu yang sedang diterapkan
        autoRefresh(() => this.loadData());
    }

    // -------------------------------------------------------------
    // Filter rentang waktu Dari - Sampai (default: seluruh periode taking)
    // -------------------------------------------------------------
    loadTimeRange() {
        axios.get(`${ENDPOINT}/time-range`)
            .then(res => this.timeFilter.setDays(res.data?.days))
            .catch(err => console.error('Error load time range:', err));
    }

    // -------------------------------------------------------------
    // Data & Chart
    // -------------------------------------------------------------
    loadData() {
        const params = this.timeFilter.params();

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
                console.error('Error load variance by area:', err);
            })
            .finally(() => {
                if (this.dataRequest === controller) {
                    this.dataRequest = null;
                }
            });
    }

    render(data) {
        const amount = data.amount || {};
        const qty    = data.qty || {};

        document.querySelectorAll('.nett-var-qty').forEach(el => { el.textContent = this.fmtNumber(qty.nett); });
        document.querySelectorAll('.nett-var-amount').forEach(el => { el.textContent = this.fmtMillion(amount.nett); });

        const sections = [
            { key: 'amount', data: amount, fmt: v => this.fmtMillion(v), nettLabel: THEME.labelDark },
            { key: 'qty', data: qty, fmt: v => this.fmtThousand(v), nettLabel: THEME.labelGrey }
        ];

        sections.forEach(({ key, data: d, fmt, nettLabel }) => {
            const absTotal = document.getElementById(`total-${key}-absolute`);
            if (absTotal) {
                absTotal.textContent = key === 'amount' ? this.fmtMillion(d.absolute) : this.fmtNumber(d.absolute);
            }

            this.renderWaterfall(`chart-${key}-nett`, d.nett_by_area || [], {
                total: d.nett,
                fmt,
                labelBg: nettLabel,
                colorOf: v => (v < 0 ? THEME.negative : THEME.positive)
            });

            this.renderWaterfall(`chart-${key}-absolute`, d.absolute_by_area || [], {
                total: d.absolute,
                fmt,
                labelBg: THEME.labelGrey,
                colorOf: () => THEME.absolute
            });

            this.renderPosNeg(`chart-${key}-posneg`, Number(d.positive || 0), Number(d.negative || 0), fmt);
        });
    }

    // Contoh: -57123456 -> "-57M" ("+ 0" supaya nilai kecil negatif tidak tampil "-0M")
    fmtMillion(v) {
        return `${(Math.round(Number(v || 0) / 1e6) + 0).toLocaleString('en-US')}M`;
    }

    // Contoh: -14500 -> "-14.50K"
    fmtThousand(v) {
        const k = Math.round(Number(v || 0) / 10) / 100 + 0;
        return `${k.toFixed(2)}K`;
    }

    // Contoh: -8170 -> "-8,170"
    fmtNumber(v) {
        return (Math.round(Number(v || 0)) + 0).toLocaleString('en-US');
    }

    // Skala sumbu Y mengikuti ujung bar terendah/tertinggi supaya bar memakai seluruh tinggi chart
    yRange(points) {
        const lows  = points.map(p => p.y[0]);
        const highs = points.map(p => p.y[1]);
        const min = Math.min(0, ...lows);
        const max = Math.max(0, ...highs);
        const pad = (max - min) * 0.08 || 1;

        return { show: false, min: min - pad, max: max + pad };
    }

    destroyChart(id) {
        if (this.charts[id]) {
            this.charts[id].destroy();
            delete this.charts[id];
        }
    }

    // Waterfall: tiap area berangkat dari total kumulatif area sebelumnya, ditutup bar Total
    renderWaterfall(id, items, { total, fmt, labelBg, colorOf }) {
        const el = document.getElementById(id);
        if (!el) return;

        this.destroyChart(id);

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
                fillColor: colorOf(value),
                value
            };
        });

        const totalValue = Number(total || 0);
        points.push({
            x: 'Total',
            y: [Math.min(0, totalValue), Math.max(0, totalValue)],
            fillColor: THEME.total,
            value: totalValue
        });

        this.charts[id] = new ApexCharts(el, {
            series: [{ name: 'Variance', data: points }],
            chart: {
                type: 'rangeBar',
                height: CHART_HEIGHT,
                fontFamily: 'inherit',
                toolbar: { show: false },
                zoom: { enabled: false },
                animations: { enabled: true, speed: 500 }
            },
            plotOptions: {
                bar: { horizontal: false, columnWidth: '70%', borderRadius: 0 }
            },
            dataLabels: {
                enabled: true,
                formatter: (val, opts) => fmt(points[opts.dataPointIndex].value),
                // Saat background aktif, style.colors dipakai sebagai warna kotak label
                style: { fontSize: '11px', fontWeight: 600, colors: [labelBg] },
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
            xaxis: {
                type: 'category',
                axisBorder: { show: false },
                axisTicks: { show: false },
                tooltip: { enabled: false },
                labels: {
                    rotate: -45,
                    rotateAlways: true,
                    trim: false,
                    maxHeight: 70,
                    hideOverlappingLabels: false,
                    style: { fontSize: '10px', colors: '#555555' }
                }
            },
            yaxis: this.yRange(points),
            legend: { show: false },
            grid: { show: false, padding: { left: 10, right: 10 } },
            tooltip: {
                custom: ({ dataPointIndex }) => {
                    const p = points[dataPointIndex];
                    return `<div class="px-2 py-1"><strong>${p.x}</strong>: ${fmt(p.value)}</div>`;
                }
            }
        });
        this.charts[id].render();
    }

    // Var (+) naik dari 0, Var (-) turun dari puncak Var (+)
    renderPosNeg(id, positive, negative, fmt) {
        const el = document.getElementById(id);
        if (!el) return;

        this.destroyChart(id);
        el.innerHTML = '';

        const points = [
            { x: 'Var (+)', y: [0, positive], fillColor: THEME.positive, value: positive },
            {
                x: 'Var (-)',
                y: [Math.min(positive, positive + negative), Math.max(positive, positive + negative)],
                fillColor: THEME.negative,
                value: negative
            }
        ];

        this.charts[id] = new ApexCharts(el, {
            series: [{ name: 'Variance', data: points }],
            chart: {
                type: 'rangeBar',
                height: POSNEG_HEIGHT,
                fontFamily: 'inherit',
                toolbar: { show: false },
                zoom: { enabled: false },
                animations: { enabled: true, speed: 500 }
            },
            plotOptions: {
                bar: { horizontal: false, columnWidth: '60%', borderRadius: 0 }
            },
            dataLabels: {
                enabled: true,
                formatter: (val, opts) => fmt(points[opts.dataPointIndex].value),
                style: { fontSize: '10px', fontWeight: 600, colors: [THEME.labelGrey] },
                background: {
                    enabled: true,
                    foreColor: '#FFFFFF',
                    borderRadius: 3,
                    borderWidth: 0,
                    padding: 3,
                    opacity: 1,
                    dropShadow: { enabled: false }
                }
            },
            xaxis: {
                type: 'category',
                axisBorder: { show: false },
                axisTicks: { show: false },
                tooltip: { enabled: false },
                labels: { style: { fontSize: '10px', colors: '#555555' } }
            },
            yaxis: this.yRange(points),
            legend: { show: false },
            grid: { show: false, padding: { left: 0, right: 0 } },
            tooltip: {
                custom: ({ dataPointIndex }) => {
                    const p = points[dataPointIndex];
                    return `<div class="px-2 py-1"><strong>${p.x}</strong>: ${fmt(p.value)}</div>`;
                }
            }
        });
        this.charts[id].render();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new DashboardVariance().init();
});
