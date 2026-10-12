import axios from 'axios';
import ApexCharts from 'apexcharts';

// Konfigurasi Chart Atas
const MIN_QTY_LABEL = 10;
const LABEL_HEIGHT  = 18;
const LABEL_PAD_X   = 5;
const ITEM_GAP      = 8;
const QTY_GAP       = 4;
const SVG_NS        = 'http://www.w3.org/2000/svg';



const THEMES = {
    nonOil: {
        title: 'Non-Oil Progress',
        column: '#B5DEB4',
        line: '#14229B',
        itemBg: '#3D3D3D',
        itemColor: '#FFFFFF',
        qtyBg: '#2E9E2E',
        qtyColor: '#FFFFFF',
        columnMax: 108,
        lineMax: 140
    },
    oil: {
        title: 'Oil Progress',
        column: '#666666',
        line: '#EAE095',
        itemBg: '#EAE095',
        itemColor: '#333333',
        qtyBg: 'transparent',
        qtyColor: '#FFFFFF',
        columnMax: 115,
        lineMax: 115
    }
};

class Dashboard {
    constructor() {
        this.charts = {};
        this.selectedTime = null;
        this.progressChart = null;
        this.oilChart = null;
    }

    init() {
        this.loadTopCharts();
        this.loadTimeSlots();
    }

    // -------------------------------------------------------------
    // 1. Chart Atas (Tidak terpengaruh filter waktu tombol)
    // -------------------------------------------------------------
    loadTopCharts() {
        axios.get('FACDBT/ds/main-dashboard/data')   
            .then(response => {
                const result = response.data.data || response.data;
                this.renderTopChart('#chart-non-oil', result.non_oil || [], THEMES.nonOil, 'non_oil');
                this.renderTopChart('#chart-oil', result.oil || [], THEMES.oil, 'oil');
            })
            .catch(error => console.error('Error loading top dashboard data:', error));
    }

    formatTime(value) {
        if (!value) return '';
        const str = String(value).replace(' ', 'T');
        const d = new Date(str);
        if (isNaN(d.getTime())) return String(value);

        const pad = n => String(n).padStart(2, '0');
        return `${d.getDate()}-${pad(d.getHours())}.${pad(d.getMinutes())}`;
    }

    fmtPct(v) {
        return `${Number(v || 0).toFixed(1)}%`;
    }

    drawLabels(chartCtx, categories, qtyData, itemData, theme) {
        const g = chartCtx && chartCtx.w && chartCtx.w.globals;
        const host = g && g.dom && g.dom.elGraphical && g.dom.elGraphical.node;
        if (!host) return;

        host.querySelectorAll('.custom-labels').forEach(n => n.remove());

        const layer = document.createElementNS(SVG_NS, 'g');
        layer.setAttribute('class', 'custom-labels');
        layer.style.pointerEvents = 'none';
        host.appendChild(layer);

        const step = g.gridWidth / categories.length;

        const addLabel = (cx, bottomY, text, bg, color) => {
            const t = document.createElementNS(SVG_NS, 'text');
            t.setAttribute('text-anchor', 'middle');
            t.setAttribute('dominant-baseline', 'central');
            t.setAttribute('font-size', '11');
            t.setAttribute('font-weight', '600');
            t.setAttribute('font-family', 'inherit');
            t.setAttribute('fill', color);
            t.textContent = text;
            layer.appendChild(t);

            const width = t.getBBox().width + LABEL_PAD_X * 2;
            const bottom = Math.max(bottomY, LABEL_HEIGHT);

            const rect = document.createElementNS(SVG_NS, 'rect');
            rect.setAttribute('x', cx - width / 2);
            rect.setAttribute('y', bottom - LABEL_HEIGHT);
            rect.setAttribute('width', width);
            rect.setAttribute('height', LABEL_HEIGHT);
            rect.setAttribute('rx', 3);
            rect.setAttribute('fill', bg);
            layer.insertBefore(rect, t);

            t.setAttribute('x', cx);
            t.setAttribute('y', bottom - LABEL_HEIGHT / 2);
        };

        categories.forEach((_, i) => {
            const cx   = step * i + step / 2;
            const qty  = Number(qtyData[i]);
            const item = Number(itemData[i]);

            const pointY = g.gridHeight * (1 - item / theme.lineMax);
            addLabel(cx, pointY - ITEM_GAP, this.fmtPct(item), theme.itemBg, theme.itemColor);

            if (qty >= MIN_QTY_LABEL) {
                addLabel(cx, g.gridHeight - QTY_GAP, this.fmtPct(qty), theme.qtyBg, theme.qtyColor);
            }
        });
    }

    getTopChartOptions(categories, qtyData, itemData, theme) {
        const redraw = ctx => this.drawLabels(ctx, categories, qtyData, itemData, theme);

        return {
            series: [
                { name: '% Qty',  type: 'column', data: qtyData },
                { name: '% Item', type: 'line',   data: itemData }
            ],
            chart: {
                height: 320,
                type: 'line',
                toolbar: { show: false },
                zoom: { enabled: false },
                fontFamily: 'inherit',
                animations: { enabled: false },
                events: {
                    mounted: redraw,
                    updated: redraw
                }
            },
            title: {
                text: theme.title || '',
                align: 'center',
                style: { fontSize: '16px', fontWeight: 600, color: '#333' }
            },
            colors: [theme.column, theme.line],
            plotOptions: {
                bar: { columnWidth: '60%', borderRadius: 2 }
            },
            stroke: { width: [0, 3], curve: 'straight' },
            fill: { opacity: 1 },
            dataLabels: { enabled: false },
            markers: {
                size: [0, 5],
                colors: [theme.line],
                strokeColors: theme.line,
                strokeWidth: 0,
                hover: { sizeOffset: 3 }
            },
            xaxis: {
                type: 'category',
                categories,
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: { style: { colors: '#666', fontSize: '12px' } }
            },
            yaxis: [
                { min: 0, max: theme.columnMax, labels: { show: false } },
                { opposite: true, min: 0, max: theme.lineMax, labels: { show: false } }
            ],
            legend: {
                position: 'top',
                horizontalAlign: 'left',
                fontSize: '12px',
                labels: { colors: '#666' },
                markers: { width: 10, height: 10, radius: 10 }
            },
            grid: { show: false },
            tooltip: {
                shared: true,
                intersect: false,
                y: { formatter: val => this.fmtPct(val) }
            }
        };
    }

    renderTopChart(selector, data, theme, key) {
        const el = document.querySelector(selector);
        if (!el) return;

        if (this.charts[key]) {
            this.charts[key].destroy();
            delete this.charts[key];
        }

        if (!Array.isArray(data) || data.length === 0) {
            el.innerHTML = '<p class="text-center text-muted my-5">No Data Available</p>';
            return;
        }

        el.innerHTML = '';
        const categories = data.map(d => this.formatTime(d.time));
        const qtyData    = data.map(d => Number(d.pct_qty));
        const itemData   = data.map(d => Number(d.pct_item));

        const options = this.getTopChartOptions(categories, qtyData, itemData, theme);
        this.charts[key] = new ApexCharts(el, options);
        this.charts[key].render();
    }

    // -------------------------------------------------------------
    // 2. Tombol Filter Slot Waktu & Chart Bawah
    // -------------------------------------------------------------
    loadTimeSlots() {
        axios.get('FACDBT/ds/main-dashboard/time-slots')
            .then(res => {
                const slots = res.data || [];
                const container = document.getElementById('time-slots-container');
                if (!container || slots.length === 0) return;

                container.innerHTML = '';
                slots.forEach((s, idx) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';

                    // Default aktif pada slot ke-2 (6-07.30)
                    const isActive = idx === 1;
                    if (isActive) {
                        this.selectedTime = s.full_time;
                    }

                    btn.className = `btn btn-sm px-3 py-2 border rounded-1 fw-bold ${
                        isActive ? 'btn-dark text-white' : 'btn-outline-secondary text-dark bg-white'
                    }`;
                    btn.style.borderColor = '#c27d60';
                    btn.style.fontStyle = 'italic';
                    btn.innerText = s.label;

                    btn.addEventListener('click', () => {
                        container.querySelectorAll('button').forEach(b => {
                            b.className = 'btn btn-sm px-3 py-2 border rounded-1 fw-bold btn-outline-secondary text-dark bg-white';
                            b.style.borderColor = '#c27d60';
                        });
                        btn.className = 'btn btn-sm px-3 py-2 border rounded-1 fw-bold btn-dark text-white';

                        // Filter waktu hanya memanggil ulang kedua chart bawah
                        this.selectedTime = s.full_time;
                        this.loadBottomCharts();
                    });

                    container.appendChild(btn);
                });

                this.loadBottomCharts();
            })
            .catch(err => console.error('Error load time slots:', err));
    }

    loadBottomCharts() {
        axios.get('FACDBT/ds/main-dashboard/progress-by-area', { params: { time: this.selectedTime } })
        .then(res => {
            const data = res.data || { parts: [], oil: [] };
            this.renderProgressChart(data.parts || []);
            this.renderOilAreaChart(data.oil || []);
        })
        .catch(err => console.error('Error load area progress:', err));
    }

    renderProgressChart(items) {
        const el = document.querySelector('#chart-counting-progress');
        if (!el) return;

        if (this.progressChart) {
            this.progressChart.destroy();
            this.progressChart = null;
        }

        if (items.length === 0) {
            el.innerHTML = '<p class="text-center text-muted my-5">No Data Available for selected time</p>';
            return;
        }

        el.innerHTML = '';
        const categories    = items.map(d => d.area);
        const progressData  = items.map(d => Number(d.pct_progress));
        const remainingData = items.map(d => Number(d.pct_remaining));

        const options = {
            series: [
                { name: '% Remaining', data: remainingData },
                { name: '% Progress', data: progressData }
            ],
            chart: {
                type: 'area',
                height: 380,
                toolbar: { show: false },
                zoom: { enabled: false },
                animations: { enabled: true }
            },
            colors: ['#14229B', '#E63946'],
            dataLabels: {
                enabled: true,
                formatter: val => `${Number(val).toFixed(2)}%`,
                style: { fontSize: '10px', fontWeight: 'bold', colors: ['#fff'] },
                background: {
                    enabled: true,
                    foreColor: '#fff',
                    borderRadius: 3,
                    padding: 3,
                    opacity: 0.95
                }
            },
            stroke: { curve: 'smooth', width: 3 },
            fill: {
                type: 'solid',
                opacity: 0.55
            },
            xaxis: {
                categories: categories,
                labels: {
                    rotate: -45,
                    rotateAlways: true,
                    style: { fontSize: '11px', colors: '#555' }
                }
            },
            yaxis: {
                min: 0,
                max: 100,
                labels: { show: false }
            },
            legend: { show: false },
            grid: { show: false },
            tooltip: {
                shared: true,
                y: { formatter: v => `${Number(v).toFixed(2)}%` }
            }
        };

        this.progressChart = new ApexCharts(el, options);
        this.progressChart.render();
    }

    renderOilAreaChart(items) {
        const el = document.querySelector('#chart-oil-area');
        if (!el) return;

        if (this.oilChart) {
            this.oilChart.destroy();
            this.oilChart = null;
        }

        if (items.length === 0) {
            el.innerHTML = '<p class="text-center text-muted my-5">No Data Available for selected time</p>';
            return;
        }

        el.innerHTML = '';
        const categories    = items.map(d => d.area);
        const progressData  = items.map(d => Number(d.pct_progress));
        const remainingData = items.map(d => Number(d.pct_remaining));

        const options = {
            series: [
                { name: 'Progress', data: progressData },
                { name: 'Remaining', data: remainingData }
            ],
            chart: {
                type: 'bar',
                height: 380,
                stacked: true,
                stackType: '100%',
                toolbar: { show: false },
                animations: { enabled: true }
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    barHeight: '45%'
                }
            },
            colors: ['#cccccc', '#555555'],
            dataLabels: {
                enabled: true,
                formatter: val => (val > 0 ? `${Number(val).toFixed(2)}%` : ''),
                style: { fontSize: '13px', fontWeight: 600, colors: ['#ffffff'] }
            },
            stroke: { width: 0 },
            xaxis: {
                categories: categories,
                labels: { show: false },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                labels: {
                    style: { fontSize: '12px', fontWeight: 600, colors: '#333' }
                }
            },
            legend: { show: false },
            grid: { show: false },
            tooltip: {
                y: { formatter: v => `${Number(v).toFixed(2)}%` }
            }
        };

        this.oilChart = new ApexCharts(el, options);
        this.oilChart.render();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new Dashboard().init();
});