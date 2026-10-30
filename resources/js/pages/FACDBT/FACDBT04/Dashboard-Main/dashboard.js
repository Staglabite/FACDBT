import axios from 'axios';
import ApexCharts from 'apexcharts';
import { autoRefresh, track, onEventType } from '../shared/dashboardStatus';
import { TimeRangeFilter } from '../shared/timeRangeFilter';
import { renderProgressByAreaChart } from '../shared/progressByAreaChart';
import { dimCategories, clickedIndex } from '../shared/chartDim';

// Konfigurasi Chart Atas
const LABEL_HEIGHT  = 18;
const LABEL_PAD_X   = 5;
const ITEM_GAP      = 8;
const QTY_GAP       = 4;
const SVG_NS        = 'http://www.w3.org/2000/svg';



// Tinggi chart Counting Progress, Counting Progress Oil, dan Counting Oil Area
const TOP_CHART_HEIGHT = 260;

// Batas tinggi chart Counting Progress (by Part) saat menyesuaikan tinggi kolom kanan
const PROGRESS_CHART_MIN_HEIGHT = 360;
const PROGRESS_CHART_MAX_HEIGHT = 700;
const TWO_COLUMN_MIN_WIDTH      = 1200; // breakpoint xl Bootstrap
const APEX_CONTAINER_EXTRA      = 15;   // ApexCharts membuat container chart 15px lebih tinggi dari SVG-nya

const THEMES = {
    nonOil: {
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
        column: '#666666',
        line: '#EAE095',
        itemBg: '#EAE095',
        itemColor: '#333333',
        // Kotak % Qty tetap terlihat walaupun bar pendek / kosong
        qtyBg: '#333333',
        qtyColor: '#FFFFFF',
        columnMax: 115,
        lineMax: 115
    },
    // Tampilan TWH: semua part
    twh: {
        column: '#118DFF',
        line: '#14229B',
        itemBg: '#3D3D3D',
        itemColor: '#FFFFFF',
        qtyBg: '#118DFF',
        qtyColor: '#FFFFFF',
        columnMax: 108,
        lineMax: 115,
        itemName: '% Part',
        height: 290
    }
};

// Tampilan Main Dashboard (lihat FACDBTD001::index): 'twh' saat event Taking TWH/Indirect
const TWH_LAYOUT = document.getElementById('dashboard-layout')?.dataset.layout === 'twh';
const TWH_EVENT  = 'TWH';

const TWH_THEME = {
    trendPositive: '#1E9E1E',
    trendNegative: '#E8713A',
    increase: '#1E9E1E',
    decrease: '#D9434F',
    labelBg: '#14229B'
};

// Counting Progress (by Part) tampilan TWH: % Achieve dari jumlah part, % Remaining dari qty
const TWH_PROGRESS_SERIES = [
    { name: '% Achieve (Part)', key: 'pct_item' },
    { name: '% Remaining (Qty)', key: 'pct_remaining' }
];

const OIL_AREA_THEME = {
    progress: '#CCCCCC',
    remaining: '#666666'
};

const VARIANCE_THEME = {
    negative: '#FF0000',
    positive: '#2E9E2E',
    labelBg: '#7F7F7F',
    absolute: '#118DFF',
    trendPositive: '#14229B',
    trendNegative: '#E63946'
};

// Part group yang ditampilkan di chart variance, sesuai urutan (group lain seperti OTHERS tidak ditampilkan)
const PART_GROUP_ORDER = ['4W', '2W'];

class Dashboard {
    constructor() {
        this.charts = {};
        // Filter waktu manual menggantikan filter klik jam
        // Tampilan TWH: satu baris di tengah halaman (mode bawaan TimeRangeFilter); tampilan biasa: sebaris di kolom kiri
        this.timeFilter = new TimeRangeFilter(document.getElementById('time-slots-container'), () => {
            this.filters.slot = null;
            this.applyDims();
            this.renderFilterBar();
            this.loadBottomCharts();
        }, TWH_LAYOUT ? {} : { inline: true });
        // Filter interaktif dari klik chart (null = tidak difilter):
        // slot = jam slot (kumulatif s/d jam itu), area = label area chart progress, seg = 4W / 2W, acc = RM / WIP / FG
        this.filters = { slot: null, area: null, seg: null, acc: null };
        this.topData = {};
        this.trendItems = [];
        this.oilItems = [];
        this.progressChart = null;
        this.progressItems = [];
        this.oilChart = null;
        this.nettVarianceChart = null;
        this.absoluteVarianceCharts = [];
        this.varianceTrendChart = null;
    }

    init() {
        this.bindVarianceCards();
        this.loadTopCharts();
        this.loadTimeRange();
        this.loadBottomCharts();
        this.loadVarianceTrend();
        this.watchColumnHeights();

        // Event periode berganti dari / ke Taking TWH: muat ulang halaman supaya tampilannya ikut berganti
        onEventType(type => {
            if ((type === TWH_EVENT) !== TWH_LAYOUT) window.location.reload();
        });

        // Perbarui semua chart secara berkala (rentang waktu yang diterapkan tetap dipakai)
        autoRefresh(() => {
            this.loadTopCharts();
            this.loadBottomCharts();
            this.loadVarianceTrend();
        });
    }

    // -------------------------------------------------------------
    // Filter interaktif (klik chart)
    // -------------------------------------------------------------

    // Nomor request per jenis data: klik filter beruntun hanya memakai hasil request terakhir
    nextSeq(name) {
        this.seq = this.seq || {};
        this.seq[name] = (this.seq[name] || 0) + 1;
        return this.seq[name];
    }

    isLatest(name, seq) {
        return this.seq[name] === seq;
    }

    // Klik nilai yang sama lagi = batal
    toggleFilter(key, value) {
        this.filters[key] = this.filters[key] === value ? null : value;
        this.applyDims();
        this.renderFilterBar();

        if (key === 'slot') {
            this.loadBottomCharts();
        } else {
            this.loadVariance();
            this.loadVarianceTrend();
        }
    }

    clearFilters() {
        const slotChanged = this.filters.slot !== null;
        this.filters = { slot: null, area: null, seg: null, acc: null };
        this.applyDims();
        this.renderFilterBar();

        if (slotChanged) this.loadBottomCharts();
        else this.loadVariance();
        this.loadVarianceTrend();
    }

    // Rentang waktu chart bawah & variance: klik jam = kumulatif dari awal taking s/d jam itu
    bottomParams() {
        return this.filters.slot
            ? { to: this.filters.slot.slice(0, 16) }
            : this.timeFilter.params();
    }

    varianceFilterParams() {
        const params = {};
        ['area', 'seg', 'acc'].forEach(key => {
            if (this.filters[key]) params[key] = this.filters[key];
        });
        return params;
    }

    // Pudarkan kategori yang tidak dipilih di semua chart yang sudah tergambar
    applyDims() {
        const slotIndex = items => items.findIndex(d => d.time === this.filters.slot);

        ['non_oil', 'oil', 'all'].forEach(key => {
            const data = this.topData[key] || [];
            if (this.charts[key]) dimCategories(this.charts[key], data.length, slotIndex(data), { seriesIndex: 1 });
        });
        if (this.varianceTrendChart) {
            dimCategories(this.varianceTrendChart, this.trendItems.length, slotIndex(this.trendItems));
        }
        if (this.progressChart) {
            dimCategories(this.progressChart, this.progressItems.length, this.progressItems.findIndex(d => d.area === this.filters.area));
        }
        if (this.oilChart) {
            dimCategories(this.oilChart, this.oilItems.length, this.oilItems.findIndex(d => d.area === this.filters.area), { horizontal: true });
        }
        this.dimVarianceCards();
    }

    // Penanda filter aktif di atas dashboard, tiap filter bisa dihapus satu per satu
    renderFilterBar() {
        const bar = document.getElementById('chart-filter-bar');
        if (!bar) return;

        const labels = {
            slot: v => `Jam ${this.formatTime(v)}`,
            area: v => `Area ${v}`,
            seg: v => v,
            acc: v => v
        };
        const active = Object.keys(labels).filter(key => this.filters[key]);

        bar.classList.toggle('d-none', active.length === 0);
        bar.innerHTML = '';
        if (active.length === 0) return;

        const title = document.createElement('span');
        title.className = 'small fw-semibold text-dark';
        title.textContent = 'Filter:';
        bar.appendChild(title);

        active.forEach(key => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'btn btn-sm btn-dark py-0 px-2 d-flex align-items-center gap-1';
            chip.innerHTML = `<span></span><i class="icon-base ti tabler-x"></i>`;
            chip.querySelector('span').textContent = labels[key](this.filters[key]);
            chip.title = 'Hapus filter';
            chip.addEventListener('click', () => this.toggleFilter(key, this.filters[key]));
            bar.appendChild(chip);
        });

        const reset = document.createElement('button');
        reset.type = 'button';
        reset.className = 'btn btn-sm btn-outline-secondary py-0 px-2';
        reset.textContent = 'Reset semua';
        reset.addEventListener('click', () => this.clearFilters());
        bar.appendChild(reset);
    }

    // Kotak RM / WIP / FG yang tidak dipilih dipudarkan
    dimVarianceCards() {
        document.querySelectorAll('#variance-accounting-groups .variance-value').forEach(el => {
            el.closest('.card').classList.toggle('fd-dim', Boolean(this.filters.acc) && this.filters.acc !== el.dataset.group);
        });
    }

    // Kotak RM / WIP / FG bisa diklik sebagai filter accounting group
    bindVarianceCards() {
        document.querySelectorAll('#variance-accounting-groups .variance-value').forEach(el => {
            const card = el.closest('.card');
            card.classList.add('fd-clickable');
            card.addEventListener('click', () => this.toggleFilter('acc', el.dataset.group));
        });
    }

    // Tinggi chart Counting Progress (by Part) disesuaikan supaya bagian bawah kolom kiri
    // sejajar dengan kolom kanan (tidak ada ruang kosong / card memanjang ke bawah).
    progressChartHeight() {
        const left  = document.getElementById('dashboard-left');
        const right = document.getElementById('dashboard-right');
        const chart = document.getElementById('chart-counting-progress');

        if (!left || !right || !chart || window.innerWidth < TWO_COLUMN_MIN_WIDTH) {
            return PROGRESS_CHART_MIN_HEIGHT + 40;
        }

        // Tinggi kolom kiri tanpa chart = left - (svg + extra); bila chart belum tergambar, container kosong (0px)
        const svg = chart.querySelector('svg.apexcharts-svg');
        const currentSvgHeight = svg ? Number(svg.getAttribute('height')) || 0 : -APEX_CONTAINER_EXTRA;
        const target = right.offsetHeight - left.offsetHeight + currentSvgHeight;

        return Math.round(Math.min(PROGRESS_CHART_MAX_HEIGHT, Math.max(PROGRESS_CHART_MIN_HEIGHT, target)));
    }

    // Kolom kanan berisi chart yang dimuat terpisah, jadi tingginya baru final setelah semua selesai
    watchColumnHeights() {
        const right = document.getElementById('dashboard-right');
        if (!right || typeof ResizeObserver === 'undefined') return;

        let frame = null;
        const fit = () => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(() => {
                if (!this.progressChart) return;

                // updateOptions tidak mengubah tinggi chart yang sudah tergambar, jadi digambar ulang
                const height = this.progressChartHeight();
                if (Math.abs(height - this.progressChart.w.config.chart.height) > 4) {
                    this.renderProgressChart(this.progressItems, false);
                }
            });
        };

        new ResizeObserver(fit).observe(right);
        window.addEventListener('resize', fit);
    }

    // -------------------------------------------------------------
    // 1. Chart Atas (Tidak terpengaruh filter waktu tombol)
    // -------------------------------------------------------------
    loadTopCharts() {
        track(axios.get('FACDBT/ds/main-dashboard/data'))
            .then(response => {
                const result = response.data.data || response.data;
                if (TWH_LAYOUT) {
                    this.renderTopChart('#chart-twh-progress', result.all || [], THEMES.twh, 'all');
                    return;
                }
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

        // Posisi X/Y diambil dari titik line yang sudah digambar ApexCharts supaya label
        // selalu tepat di atas titik / bar (fallback ke pembagian rata bila belum tersedia).
        const lineX = (g.seriesXvalues && g.seriesXvalues[1]) || [];
        const lineY = (g.seriesYvalues && g.seriesYvalues[1]) || [];
        const step  = g.gridWidth / categories.length;

        const addLabel = (cx, bottomY, text, bg, color) => {
            const t = document.createElementNS(SVG_NS, 'text');
            t.setAttribute('text-anchor', 'middle');
            t.setAttribute('dominant-baseline', 'central');
            t.setAttribute('font-size', '12');
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
            rect.setAttribute('rx', 4);
            rect.setAttribute('fill', bg);
            layer.insertBefore(rect, t);

            t.setAttribute('x', cx);
            t.setAttribute('y', bottom - LABEL_HEIGHT / 2);
        };

        categories.forEach((_, i) => {
            // Jam slot yang belum tiba sudah bernilai 0 dari server (tampil 0%)
            if (itemData[i] === null || qtyData[i] === null) return;

            const cx   = Number.isFinite(lineX[i]) ? lineX[i] : step * i + step / 2;
            const qty  = Number(qtyData[i]);
            const item = Number(itemData[i]);

            const pointY = Number.isFinite(lineY[i]) ? lineY[i] : g.gridHeight * (1 - item / theme.lineMax);
            addLabel(cx, pointY - ITEM_GAP, this.fmtPct(item), theme.itemBg, theme.itemColor);

            // % Qty selalu ditampilkan, termasuk saat nilainya kecil
            addLabel(cx, g.gridHeight - QTY_GAP, this.fmtPct(qty), theme.qtyBg, theme.qtyColor);
        });
    }

    getTopChartOptions(categories, qtyData, itemData, theme, key) {
        const redraw = ctx => {
            this.drawLabels(ctx, categories, qtyData, itemData, theme);
            const data = this.topData[key] || [];
            dimCategories(ctx, data.length, data.findIndex(d => d.time === this.filters.slot), { seriesIndex: 1 });
        };
        // Klik jam = filter chart lain kumulatif s/d jam itu (jam yang belum tiba tidak bisa dipilih)
        const select = (ctx, config) => {
            const slot = (this.topData[key] || [])[clickedIndex(ctx, config)];
            if (slot && !slot.future) this.toggleFilter('slot', slot.time);
        };

        return {
            series: [
                { name: '% Qty',  type: 'column', data: qtyData },
                { name: theme.itemName || '% Item', type: 'line',   data: itemData }
            ],
            chart: {
                height: theme.height || TOP_CHART_HEIGHT,
                type: 'line',
                toolbar: { show: false },
                zoom: { enabled: false },
                fontFamily: 'inherit',
                animations: { enabled: false },
                events: {
                    mounted: redraw,
                    updated: redraw,
                    click: (event, ctx, config) => select(ctx, config),
                    xAxisLabelClick: (event, ctx, config) => select(ctx, { dataPointIndex: config.labelIndex })
                }
            },
            colors: [theme.column, theme.line],
            plotOptions: {
                bar: { columnWidth: '72%', borderRadius: 0 }
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
                // Kolom + garis: tanpa 'between' area hover bergeser setengah kolom (tooltip menunjuk jam sebelumnya)
                tickPlacement: 'between',
                axisBorder: { show: false },
                axisTicks: { show: false },
                tooltip: { enabled: false },
                labels: { style: { colors: '#555555', fontSize: '12px' } }
            },
            yaxis: [
                // show: false supaya sumbu Y tidak memakan ruang lebar chart
                { show: false, min: 0, max: theme.columnMax },
                { show: false, opposite: true, min: 0, max: theme.lineMax }
            ],
            legend: {
                position: 'top',
                horizontalAlign: 'left',
                fontSize: '12px',
                labels: { colors: '#555555' },
                markers: { size: 6, shape: 'circle', strokeWidth: 0 },
                itemMargin: { horizontal: 6 }
            },
            grid: { show: false, padding: { left: 10, right: 10 } },
            tooltip: {
                shared: true,
                intersect: false,
                y: { formatter: val => (val === null || val === undefined ? '-' : this.fmtPct(val)) }
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
        el.classList.add('fd-clickable');
        this.topData[key] = data;
        const categories = data.map(d => this.formatTime(d.time));
        // Jam slot yang belum tiba bernilai 0 (tidak bisa diklik sebagai filter)
        const toValue    = v => (v === null || v === undefined ? null : Number(v));
        const qtyData    = data.map(d => toValue(d.pct_qty));
        const itemData   = data.map(d => toValue(d.pct_item));

        const options = this.getTopChartOptions(categories, qtyData, itemData, theme, key);
        this.charts[key] = new ApexCharts(el, options);
        this.charts[key].render();
    }

    // -------------------------------------------------------------
    // 2. Filter rentang waktu Dari - Sampai & Chart Bawah (default: seluruh periode taking)
    // -------------------------------------------------------------
    loadTimeRange() {
        axios.get('FACDBT/ds/main-dashboard/time-range')
            .then(res => this.timeFilter.setDays(res.data?.days))
            .catch(err => console.error('Error load time range:', err));
    }

    loadBottomCharts() {
        const seq = this.nextSeq('area');
        track(axios.get('FACDBT/ds/main-dashboard/progress-by-area', { params: this.bottomParams() }))
        .then(res => {
            if (!this.isLatest('area', seq)) return;
            const data = res.data || { parts: [], oil: [] };
            if (TWH_LAYOUT) {
                // Area oil ikut di chart yang sama, urut dari % Achieve (Part) tertinggi
                const areas = [...(data.parts || []), ...(data.oil || [])]
                    .sort((a, b) => Number(b.pct_item || 0) - Number(a.pct_item || 0));
                this.renderProgressChart(areas);
                return;
            }
            this.renderProgressChart(data.parts || []);
            this.renderOilAreaChart(data.oil || []);
        })
        .catch(err => console.error('Error load area progress:', err));

        this.loadVariance();
    }

    // -------------------------------------------------------------
    // 3. Variance (mengikuti filter rentang waktu)
    // -------------------------------------------------------------
    loadVariance() {
        const seq = this.nextSeq('variance');
        track(axios.get('FACDBT/ds/main-dashboard/variance', { params: { ...this.bottomParams(), ...this.varianceFilterParams() } }))
            .then(res => {
                if (!this.isLatest('variance', seq)) return;
                const data = res.data || {};
                if (TWH_LAYOUT) {
                    this.renderVarianceSummary(data);
                    this.renderTwhNettVarianceChart(data.by_accounting_group || []);
                    return;
                }
                const partGroups = this.sortPartGroups(data.by_part_group || []);

                this.renderVarianceSummary(data);
                this.renderNettVarianceChart(partGroups);
                this.renderAbsoluteVarianceCharts(partGroups);
            })
            .catch(err => console.error('Error load variance:', err));
    }

    // Chart Absolute Variance per slot waktu (tidak terpengaruh filter tombol waktu)
    loadVarianceTrend() {
        const seq = this.nextSeq('trend');
        track(axios.get('FACDBT/ds/main-dashboard/variance-trend', { params: this.varianceFilterParams() }))
            .then(res => {
                if (this.isLatest('trend', seq)) this.renderVarianceTrendChart(Array.isArray(res.data) ? res.data : []);
            })
            .catch(err => console.error('Error load variance trend:', err));
    }

    renderVarianceTrendChart(items) {
        const el = document.querySelector('#chart-variance-trend');
        if (!el) return;

        if (this.varianceTrendChart) {
            this.varianceTrendChart.destroy();
            this.varianceTrendChart = null;
        }

        if (items.length === 0) {
            el.innerHTML = '<p class="text-center text-muted my-5">No Data Available</p>';
            return;
        }

        el.innerHTML = '';
        el.classList.add('fd-clickable');
        this.trendItems = items;
        const colors     = TWH_LAYOUT
            ? [TWH_THEME.trendPositive, TWH_THEME.trendNegative]
            : [VARIANCE_THEME.trendPositive, VARIANCE_THEME.trendNegative];
        const dim = ctx => dimCategories(ctx, items.length, items.findIndex(d => d.time === this.filters.slot));
        const select = index => {
            const slot = items[index];
            if (slot && !slot.future) this.toggleFilter('slot', slot.time);
        };
        const categories = items.map(d => this.formatTime(d.time));
        // Jam slot yang belum tiba bernilai 0 (tidak bisa diklik sebagai filter)
        const positive   = items.map(d => (d.abs_positive === null ? null : Number(d.abs_positive || 0)));
        const negative   = items.map(d => (d.abs_negative === null ? null : Number(d.abs_negative || 0)));

        // Label lewat annotation supaya tiap series bisa punya posisi sendiri: label garis yang lebih
        // tinggi di atas titik, label garis yang lebih rendah di bawah titik, sehingga tidak bertumpuk
        // (dataLabels ApexCharts hanya punya satu offset untuk semua series).
        const pointLabel = (x, y, color, below) => ({
            x,
            y,
            marker: { size: 0 },
            label: {
                text: this.fmtMillion(y),
                borderWidth: 0,
                borderRadius: 4,
                offsetY: below ? 30 : -4,
                style: {
                    background: color,
                    color: '#FFFFFF',
                    fontSize: '11px',
                    fontWeight: 600,
                    padding: { left: 5, right: 5, top: 2, bottom: 2 }
                }
            }
        });
        const labels = categories.flatMap((x, i) => {
            if (positive[i] === null || negative[i] === null) return [];

            const positiveOnTop = positive[i] >= negative[i];

            return [
                pointLabel(x, positive[i], colors[0], !positiveOnTop),
                pointLabel(x, negative[i], colors[1], positiveOnTop)
            ];
        });

        const options = {
            series: [
                { name: 'Abs Var [+]', data: positive },
                { name: 'Abs Var [-]', data: negative }
            ],
            chart: {
                type: 'area',
                height: TWH_LAYOUT ? 280 : 190,
                fontFamily: 'inherit',
                toolbar: { show: false },
                zoom: { enabled: false },
                animations: { enabled: true, speed: 500 },
                events: {
                    mounted: dim,
                    updated: dim,
                    click: (event, ctx, config) => select(clickedIndex(ctx, config)),
                    xAxisLabelClick: (event, ctx, config) => select(config.labelIndex)
                }
            },
            colors,
            // Tampilan TWH: garis bertangga (nilai bertahan sampai jam slot berikutnya)
            stroke: { curve: TWH_LAYOUT ? 'stepline' : 'straight', width: 3 },
            fill: { type: 'solid', opacity: TWH_LAYOUT ? [0.2, 0.4] : [0.15, 0.35] },
            markers: { size: 5, strokeWidth: 0, hover: { sizeOffset: 2 } },
            dataLabels: { enabled: false },
            annotations: { points: labels },
            xaxis: {
                categories,
                axisBorder: { show: false },
                axisTicks: { show: false },
                tooltip: { enabled: false },
                // digeser ke bawah supaya tidak tertutup label [-] yang ada di bawah titik
                labels: { offsetY: 20, style: { fontSize: '11px', colors: '#333333' } }
            },
            yaxis: {
                min: 0,
                // ruang di atas titik tertinggi untuk label
                max: max => (max > 0 ? max * 1.25 : 1),
                labels: { show: false }
            },
            legend: {
                show: true,
                position: 'top',
                horizontalAlign: 'right',
                fontSize: '12px',
                labels: { colors: '#555555' },
                markers: { size: 6, shape: 'circle', strokeWidth: 0 },
                itemMargin: { horizontal: 6 }
            },
            grid: { show: false, padding: { top: -10, left: 40, right: 40, bottom: 40 } },
            tooltip: {
                shared: true,
                intersect: false,
                y: { formatter: v => (v === null || v === undefined ? '-' : this.fmtMillion(v)) }
            }
        };

        this.varianceTrendChart = new ApexCharts(el, options);
        this.varianceTrendChart.render();
    }

    // Format nilai rupiah dalam juta, contoh: -32389123456 -> "-32,389M"
    fmtMillion(v) {
        const million = Math.round(Number(v || 0) / 1e6);
        return `${million.toLocaleString('en-US')}M`;
    }

    // Angka penuh dengan pemisah ribuan, contoh: -45842574869 -> "-45,842,574,869"
    fmtFull(v) {
        return Math.round(Number(v || 0)).toLocaleString('en-US');
    }

    // Tampilan TWH: waterfall Nett Variance per accounting_group part TW1, urut dari nett terbesar.
    // Klik accounting group = filter chart lain (group lain dipudarkan).
    renderTwhNettVarianceChart(groups) {
        const el = document.querySelector('#chart-nett-variance');
        if (!el) return;

        if (this.nettVarianceChart) {
            this.nettVarianceChart.destroy();
            this.nettVarianceChart = null;
        }

        const sorted = groups
            .filter(g => Number(g.nett || 0) !== 0)
            .sort((a, b) => Number(b.nett || 0) - Number(a.nett || 0));

        if (sorted.length === 0) {
            el.innerHTML = '<p class="text-center text-muted my-5">No Data Available</p>';
            return;
        }

        el.innerHTML = '';
        el.classList.add('fd-clickable');
        let running = 0;
        const points = sorted.map(g => {
            const start = running;
            running += Number(g.nett || 0);

            const color  = g.nett < 0 ? TWH_THEME.decrease : TWH_THEME.increase;
            const dimmed = this.filters.acc && this.filters.acc !== g.group;

            return {
                x: g.group,
                y: [Math.min(start, running), Math.max(start, running)],
                fillColor: dimmed ? `${color}40` : color,
                nett: Number(g.nett || 0)
            };
        });
        const select = index => {
            if (points[index]) this.toggleFilter('acc', points[index].x);
        };

        this.nettVarianceChart = new ApexCharts(el, {
            series: [{ name: 'Nett Variance', data: points }],
            chart: {
                type: 'rangeBar',
                height: 280,
                fontFamily: 'inherit',
                toolbar: { show: false },
                zoom: { enabled: false },
                animations: { enabled: true, speed: 500 },
                events: {
                    click: (event, ctx, config) => select(config.dataPointIndex),
                    xAxisLabelClick: (event, ctx, config) => select(config.labelIndex)
                }
            },
            plotOptions: {
                bar: { horizontal: false, columnWidth: '75%', dataLabels: { position: 'bottom' } }
            },
            dataLabels: {
                enabled: true,
                formatter: (val, opts) => this.fmtFull(points[opts.dataPointIndex].nett),
                // Saat background aktif, style.colors dipakai sebagai warna kotak label
                style: { fontSize: '12px', fontWeight: 600, colors: [TWH_THEME.labelBg] },
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
                labels: { style: { fontSize: '13px', fontWeight: 600, colors: '#333333' } }
            },
            yaxis: { show: false },
            legend: {
                show: true,
                showForSingleSeries: true,
                position: 'top',
                horizontalAlign: 'center',
                fontSize: '12px',
                customLegendItems: ['Increase', 'Decrease'],
                markers: { size: 6, shape: 'circle', strokeWidth: 0, fillColors: [TWH_THEME.increase, TWH_THEME.decrease] },
                labels: { colors: '#333333' },
                itemMargin: { horizontal: 8, vertical: 0 }
            },
            grid: { show: false, padding: { left: 10, right: 10 } },
            tooltip: {
                custom: ({ dataPointIndex }) => {
                    const p = points[dataPointIndex];
                    return `<div class="px-2 py-1"><strong>${p.x}</strong>: ${this.fmtFull(p.nett)}</div>`;
                }
            }
        });
        this.nettVarianceChart.render();
    }

    sortPartGroups(groups) {
        const rank = g => PART_GROUP_ORDER.indexOf(String(g.group).toUpperCase());

        return groups
            .filter(g => rank(g) !== -1)
            .sort((a, b) => rank(a) - rank(b));
    }

    renderVarianceSummary(data) {
        const byAccounting = {};
        (data.by_accounting_group || []).forEach(g => {
            byAccounting[String(g.group).toUpperCase()] = g.nett;
        });

        document.querySelectorAll('#variance-accounting-groups .variance-value').forEach(el => {
            el.textContent = this.fmtMillion(byAccounting[el.dataset.group] || 0);
        });
        this.dimVarianceCards();

        // Tampilan TWH: angka penuh
        const fmt    = v => (TWH_LAYOUT ? this.fmtFull(v) : this.fmtMillion(v));
        const nettEl = document.getElementById('variance-nett-total');
        const absEl  = document.getElementById('variance-absolute-total');
        if (nettEl) nettEl.textContent = fmt(data.nett);
        if (absEl) absEl.textContent = fmt(data.absolute);
    }

    // Waterfall: tiap part group berangkat dari total kumulatif group sebelumnya
    renderNettVarianceChart(groups) {
        const el = document.querySelector('#chart-nett-variance');
        if (!el) return;

        if (this.nettVarianceChart) {
            this.nettVarianceChart.destroy();
            this.nettVarianceChart = null;
        }

        if (groups.length === 0) {
            el.innerHTML = '<p class="text-center text-muted my-5">No Data Available</p>';
            return;
        }

        el.innerHTML = '';
        let running = 0;
        const points = groups.map(g => {
            const start = running;
            running += Number(g.nett || 0);

            // Part group yang tidak dipilih dipudarkan (warna transparan)
            const color = g.nett < 0 ? VARIANCE_THEME.negative : VARIANCE_THEME.positive;
            const dimmed = this.filters.seg && this.filters.seg !== g.group;

            return {
                x: g.group,
                y: [Math.min(start, running), Math.max(start, running)],
                fillColor: dimmed ? `${color}40` : color,
                nett: Number(g.nett || 0)
            };
        });
        el.classList.add('fd-clickable');

        const options = {
            series: [{ name: 'Nett Variance', data: points }],
            chart: {
                type: 'rangeBar',
                height: 160,
                fontFamily: 'inherit',
                toolbar: { show: false },
                zoom: { enabled: false },
                animations: { enabled: true, speed: 500 },
                events: {
                    // Klik 4W / 2W = filter part group
                    click: (event, ctx, config) => {
                        const point = points[config.dataPointIndex];
                        if (point) this.toggleFilter('seg', point.x);
                    },
                    xAxisLabelClick: (event, ctx, config) => {
                        const point = points[config.labelIndex];
                        if (point) this.toggleFilter('seg', point.x);
                    }
                }
            },
            plotOptions: {
                bar: { horizontal: false, columnWidth: '55%' }
            },
            dataLabels: {
                enabled: true,
                formatter: (val, opts) => this.fmtMillion(points[opts.dataPointIndex].nett),
                // Saat background aktif, style.colors dipakai sebagai warna kotak label
                style: { fontSize: '14px', fontWeight: 700, colors: [VARIANCE_THEME.labelBg] },
                background: {
                    enabled: true,
                    foreColor: '#FFFFFF',
                    borderRadius: 6,
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
                labels: { style: { fontSize: '14px', fontWeight: 600, colors: '#333333' } }
            },
            yaxis: { show: false },
            // Keterangan warna: hijau = Var [+], merah = Var [-]
            legend: {
                show: true,
                showForSingleSeries: true,
                position: 'top',
                horizontalAlign: 'center',
                fontSize: '12px',
                customLegendItems: ['Var [+]', 'Var [-]'],
                markers: { size: 6, shape: 'square', strokeWidth: 0, fillColors: [VARIANCE_THEME.positive, VARIANCE_THEME.negative] },
                labels: { colors: '#333333' },
                itemMargin: { horizontal: 8, vertical: 0 }
            },
            grid: { show: false, padding: { left: 10, right: 10 } },
            tooltip: {
                custom: ({ dataPointIndex }) => {
                    const p = points[dataPointIndex];
                    return `<div class="px-2 py-1"><strong>${p.x}</strong>: ${this.fmtMillion(p.nett)}</div>`;
                }
            }
        };

        this.nettVarianceChart = new ApexCharts(el, options);
        this.nettVarianceChart.render();
    }

    // 1 card per part group, skala bar disamakan supaya bisa dibandingkan
    renderAbsoluteVarianceCharts(groups) {
        const container = document.querySelector('#chart-absolute-variance');
        if (!container) return;

        this.absoluteVarianceCharts.forEach(chart => chart.destroy());
        this.absoluteVarianceCharts = [];
        container.innerHTML = '';

        if (groups.length === 0) {
            container.innerHTML = `
                <div class="card shadow-none border border-dark rounded-3 flex-fill">
                    <div class="card-body"><p class="text-center text-muted my-4">No Data Available</p></div>
                </div>`;
            return;
        }

        const maxAbs = Math.max(...groups.map(g => Number(g.absolute || 0)), 1);

        // Urutan card: part group terakhir di atas (2W lalu 4W), seperti layout referensi
        [...groups].reverse().forEach(g => {
            const card = document.createElement('div');
            card.className = 'card shadow-none border border-dark rounded-3 flex-fill fd-clickable';
            // Klik card = filter part group; part group lain dipudarkan
            card.classList.toggle('fd-dim', Boolean(this.filters.seg) && this.filters.seg !== g.group);
            card.addEventListener('click', () => this.toggleFilter('seg', g.group));
            card.innerHTML = `
                <div class="card-header text-center pt-1 pb-0">
                    <div class="small fw-semibold text-dark">Absolute Variance</div>
                </div>
                <div class="card-body px-2 py-0"><div class="absolute-variance-chart"></div></div>`;
            container.appendChild(card);

            const chart = new ApexCharts(card.querySelector('.absolute-variance-chart'), {
                series: [{ name: 'Absolute Variance', data: [Number(g.absolute || 0)] }],
                chart: {
                    type: 'bar',
                    height: 60,
                    fontFamily: 'inherit',
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    animations: { enabled: true, speed: 500 }
                },
                // Label di ujung kanan bar dengan teks gelap supaya tetap terbaca walaupun bar pendek
                plotOptions: {
                    bar: { horizontal: true, barHeight: '70%', dataLabels: { position: 'top' } }
                },
                colors: [VARIANCE_THEME.absolute],
                dataLabels: {
                    enabled: true,
                    formatter: val => this.fmtMillion(val),
                    offsetX: 40,
                    style: { fontSize: '14px', fontWeight: 700, colors: ['#1F1F1F'] },
                    dropShadow: { enabled: false }
                },
                xaxis: {
                    categories: [g.group],
                    min: 0,
                    // Ruang di kanan bar terpanjang untuk label
                    max: maxAbs * 1.45,
                    labels: { show: false },
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                },
                yaxis: {
                    labels: { style: { fontSize: '14px', fontWeight: 600, colors: '#333333' } }
                },
                legend: { show: false },
                grid: { show: false, padding: { top: -15, bottom: -15, right: 10 } },
                tooltip: {
                    y: { formatter: v => this.fmtMillion(v) }
                }
            });

            chart.render();
            this.absoluteVarianceCharts.push(chart);
        });
    }

    fmtPct2(v) {
        return `${Number(v || 0).toFixed(2)}%`;
    }

    renderProgressChart(items, animate = true) {
        const el = document.querySelector('#chart-counting-progress');
        if (!el) return;

        this.progressItems = items;
        el.style.minHeight = ''; // sisa min-height dari chart sebelumnya ikut mempengaruhi perhitungan tinggi

        if (this.progressChart) {
            this.progressChart.destroy();
            this.progressChart = null;
        }

        this.progressChart = renderProgressByAreaChart(el, items, {
            height: this.progressChartHeight(),
            animate,
            ...(TWH_LAYOUT ? { series: TWH_PROGRESS_SERIES } : {}),
            selected: () => this.filters.area,
            onSelect: area => this.toggleFilter('area', area)
        });
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
        el.classList.add('fd-clickable');
        this.oilItems = items;
        const categories    = items.map(d => d.area);
        const progressData  = items.map(d => Number(d.pct_progress));
        const remainingData = items.map(d => Number(d.pct_remaining));
        const dim = ctx => dimCategories(ctx, items.length, items.findIndex(d => d.area === this.filters.area), { horizontal: true });
        const select = index => {
            if (items[index]) this.toggleFilter('area', items[index].area);
        };

        const options = {
            series: [
                { name: 'Progress', data: progressData },
                { name: 'Remaining', data: remainingData }
            ],
            chart: {
                type: 'bar',
                // Tinggi tetap (sama dengan chart Counting Progress Oil) supaya card tidak memanjang
                // ke bawah saat area oil banyak; ketebalan bar yang menyesuaikan.
                height: TOP_CHART_HEIGHT,
                fontFamily: 'inherit',
                stacked: true,
                stackType: '100%',
                toolbar: { show: false },
                events: {
                    mounted: dim,
                    updated: dim,
                    // Klik area = filter chart variance
                    click: (event, ctx, config) => select(clickedIndex(ctx, config)),
                    xAxisLabelClick: (event, ctx, config) => select(config.labelIndex)
                },
                animations: { enabled: true, speed: 500 }
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    barHeight: items.length > 4 ? '65%' : '75%',
                    dataLabels: { position: 'center' }
                }
            },
            colors: [OIL_AREA_THEME.progress, OIL_AREA_THEME.remaining],
            dataLabels: {
                enabled: true,
                formatter: val => (val > 0 ? this.fmtPct2(val) : ''),
                // Teks gelap di bar Progress (abu-abu muda), teks putih di bar Remaining (abu-abu tua)
                style: { fontSize: items.length > 4 ? '13px' : '16px', fontWeight: 700, colors: ['#1F1F1F', '#FFFFFF'] },
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
                    style: { fontSize: '12px', fontWeight: 600, colors: '#333333' }
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