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

// Tema chart bawah & tombol slot waktu
const PROGRESS_THEME = {
    remaining: '#14229B',
    progress: '#E63946'
};

const OIL_AREA_THEME = {
    progress: '#CCCCCC',
    remaining: '#666666'
};

const SLOT_THEME = {
    border: '#C27D60',
    activeBg: '#333333',
    text: '#333333'
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
    styleSlotButton(btn, isActive) {
        btn.className = `btn btn-sm px-3 py-2 rounded-1 ${isActive ? 'fw-bold' : 'fw-normal'}`;
        btn.style.border = `1px solid ${SLOT_THEME.border}`;
        btn.style.fontStyle = 'italic';
        btn.style.minWidth = '72px';
        btn.style.backgroundColor = isActive ? SLOT_THEME.activeBg : '#FFFFFF';
        btn.style.color = isActive ? '#FFFFFF' : SLOT_THEME.text;
    }

    loadTimeSlots() {
        axios.get('FACDBT/ds/main-dashboard/time-slots')
            .then(res => {
                const slots = res.data || [];
                const container = document.getElementById('time-slots-container');
                if (!container || slots.length === 0) return;

                container.innerHTML = '';
                const buttons = [];

                slots.forEach((s, idx) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.innerText = s.label;

                    // Default aktif pada slot ke-2 (6-07.30)
                    const isActive = idx === 1;
                    if (isActive) {
                        this.selectedTime = s.full_time;
                    }
                    this.styleSlotButton(btn, isActive);

                    btn.addEventListener('click', () => {
                        buttons.forEach(b => this.styleSlotButton(b, b === btn));

                        // Filter waktu hanya memanggil ulang kedua chart bawah
                        this.selectedTime = s.full_time;
                        this.loadBottomCharts();
                    });

                    buttons.push(btn);
                    container.appendChild(btn);
                });

                this.addSlotScroller(container);
                this.loadBottomCharts();
            })
            .catch(err => console.error('Error load time slots:', err));
    }

    // Tombol panah ">" untuk menggeser daftar slot bila tidak muat di layar
    addSlotScroller(container) {
        container.parentElement.querySelectorAll('.slot-scroller').forEach(n => n.remove());

        const next = document.createElement('button');
        next.type = 'button';
        next.className = 'btn btn-sm btn-link text-dark px-1 slot-scroller';
        next.setAttribute('aria-label', 'Next time slots');
        next.innerHTML = '<i class="icon-base ti tabler-chevron-right"></i>';
        next.addEventListener('click', () => {
            const atEnd = container.scrollLeft + container.clientWidth >= container.scrollWidth - 2;
            container.scrollTo({ left: atEnd ? 0 : container.scrollLeft + container.clientWidth * 0.6, behavior: 'smooth' });
        });

        container.insertAdjacentElement('afterend', next);

        const toggle = () => {
            next.classList.toggle('d-none', container.scrollWidth <= container.clientWidth);
        };
        toggle();
        window.addEventListener('resize', toggle);
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

    fmtPct2(v) {
        return `${Number(v || 0).toFixed(2)}%`;
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
        const colors        = [PROGRESS_THEME.remaining, PROGRESS_THEME.progress];

        const options = {
            series: [
                { name: '% Remaining', data: remainingData },
                { name: '% Progress', data: progressData }
            ],
            chart: {
                type: 'area',
                height: 400,
                fontFamily: 'inherit',
                toolbar: { show: false },
                zoom: { enabled: false },
                animations: { enabled: true, speed: 500 }
            },
            colors,
            dataLabels: {
                enabled: true,
                offsetY: -8,
                formatter: val => this.fmtPct2(val),
                // Saat background aktif, style.colors dipakai sebagai warna kotak label
                style: { fontSize: '11px', fontWeight: 600, colors },
                background: {
                    enabled: true,
                    foreColor: '#FFFFFF',
                    borderRadius: 4,
                    borderWidth: 0,
                    padding: 5,
                    opacity: 1,
                    dropShadow: { enabled: false }
                }
            },
            stroke: { curve: 'smooth', width: 2 },
            fill: {
                type: 'solid',
                opacity: [0.6, 0.55]
            },
            markers: {
                size: 4,
                strokeWidth: 0,
                hover: { sizeOffset: 2 }
            },
            xaxis: {
                categories,
                axisBorder: { show: true, color: '#CCCCCC' },
                axisTicks: { show: false },
                tooltip: { enabled: false },
                labels: {
                    rotate: -45,
                    rotateAlways: true,
                    trim: false,
                    maxHeight: 120,
                    hideOverlappingLabels: false,
                    style: { fontSize: '11px', colors: '#555555' }
                }
            },
            yaxis: {
                min: 0,
                max: 115, // ruang di atas 100% untuk label
                labels: { show: false }
            },
            legend: {
                show: true,
                position: 'top',
                horizontalAlign: 'left',
                fontSize: '13px',
                labels: { colors: '#555555' },
                markers: { size: 6, shape: 'circle', strokeWidth: 0 },
                itemMargin: { horizontal: 8 }
            },
            grid: { show: false, padding: { left: 55, right: 30 } },
            tooltip: {
                shared: true,
                intersect: false,
                y: { formatter: v => this.fmtPct2(v) }
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
                // Tinggi menyesuaikan jumlah area supaya bar tidak terlalu tipis / tebal
                height: Math.max(400, items.length * 90),
                fontFamily: 'inherit',
                stacked: true,
                stackType: '100%',
                toolbar: { show: false },
                animations: { enabled: true, speed: 500 }
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    barHeight: '75%',
                    dataLabels: { position: 'center' }
                }
            },
            colors: [OIL_AREA_THEME.progress, OIL_AREA_THEME.remaining],
            dataLabels: {
                enabled: true,
                formatter: val => (val > 0 ? this.fmtPct2(val) : ''),
                style: { fontSize: '16px', fontWeight: 400, colors: ['#FFFFFF'] },
                dropShadow: { enabled: false }
            },
            stroke: { width: 0 },
            xaxis: {
                categories,
                labels: { show: false },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                labels: {
                    maxWidth: 110,
                    style: { fontSize: '11px', fontWeight: 400, colors: '#555555' }
                }
            },
            legend: { show: false },
            grid: { show: false, padding: { left: 0, right: 12 } },
            states: {
                hover: { filter: { type: 'darken', value: 0.9 } }
            },
            tooltip: {
                y: { formatter: v => this.fmtPct2(v) }
            }
        };

        this.oilChart = new ApexCharts(el, options);
        this.oilChart.render();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new Dashboard().init();
});