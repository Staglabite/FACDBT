import axios from 'axios';
import ApexCharts from 'apexcharts';

// Konfigurasi Chart Atas
const MIN_QTY_LABEL = 10;
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
        this.progressItems = [];
        this.oilChart = null;
        this.nettVarianceChart = null;
        this.absoluteVarianceCharts = [];
        this.varianceTrendChart = null;
    }

    init() {
        this.loadTopCharts();
        this.loadTimeSlots();
        this.loadVarianceTrend();
        this.watchColumnHeights();
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
            const cx   = Number.isFinite(lineX[i]) ? lineX[i] : step * i + step / 2;
            const qty  = Number(qtyData[i]);
            const item = Number(itemData[i]);

            const pointY = Number.isFinite(lineY[i]) ? lineY[i] : g.gridHeight * (1 - item / theme.lineMax);
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
                height: TOP_CHART_HEIGHT,
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
        // Jangan menyusut / terlipat; kalau tidak muat, daftar slot digeser dengan tombol panah
        btn.style.flexShrink = '0';
        btn.style.whiteSpace = 'nowrap';
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

        this.loadVariance();
    }

    // -------------------------------------------------------------
    // 3. Variance (mengikuti filter waktu tombol slot)
    // -------------------------------------------------------------
    loadVariance() {
        axios.get('FACDBT/ds/main-dashboard/variance', { params: { time: this.selectedTime } })
            .then(res => {
                const data = res.data || {};
                const partGroups = this.sortPartGroups(data.by_part_group || []);

                this.renderVarianceSummary(data);
                this.renderNettVarianceChart(partGroups);
                this.renderAbsoluteVarianceCharts(partGroups);
            })
            .catch(err => console.error('Error load variance:', err));
    }

    // Chart Absolute Variance per slot waktu (tidak terpengaruh filter tombol waktu)
    loadVarianceTrend() {
        axios.get('FACDBT/ds/main-dashboard/variance-trend')
            .then(res => this.renderVarianceTrendChart(Array.isArray(res.data) ? res.data : []))
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
        const colors     = [VARIANCE_THEME.trendPositive, VARIANCE_THEME.trendNegative];
        const categories = items.map(d => this.formatTime(d.time));
        const positive   = items.map(d => Number(d.abs_positive || 0));
        const negative   = items.map(d => Number(d.abs_negative || 0));

        // Label lewat annotation supaya label [+] bisa di atas titik dan label [-] di bawah titik
        // (dataLabels ApexCharts hanya punya satu offset untuk semua series, sehingga bisa bertumpuk).
        const pointLabel = (x, y, color, below) => ({
            x,
            y,
            marker: { size: 0 },
            label: {
                text: this.fmtMillion(y),
                borderWidth: 0,
                borderRadius: 4,
                offsetY: below ? 34 : -4,
                style: {
                    background: color,
                    color: '#FFFFFF',
                    fontSize: '12px',
                    fontWeight: 600,
                    padding: { left: 6, right: 6, top: 3, bottom: 3 }
                }
            }
        });
        const labels = categories.flatMap((x, i) => [
            pointLabel(x, positive[i], colors[0], false),
            pointLabel(x, negative[i], colors[1], true)
        ]);

        const options = {
            series: [
                { name: 'Abs Var [+]', data: positive },
                { name: 'Abs Var [-]', data: negative }
            ],
            chart: {
                type: 'area',
                height: 240,
                fontFamily: 'inherit',
                toolbar: { show: false },
                zoom: { enabled: false },
                animations: { enabled: true, speed: 500 }
            },
            colors,
            stroke: { curve: 'straight', width: 3 },
            fill: { type: 'solid', opacity: [0.15, 0.35] },
            markers: { size: 5, strokeWidth: 0, hover: { sizeOffset: 2 } },
            dataLabels: { enabled: false },
            annotations: { points: labels },
            xaxis: {
                categories,
                axisBorder: { show: false },
                axisTicks: { show: false },
                tooltip: { enabled: false },
                // digeser ke bawah supaya tidak tertutup label [-] yang ada di bawah titik
                labels: { offsetY: 22, style: { fontSize: '12px', colors: '#333333' } }
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
            grid: { show: false, padding: { left: 40, right: 40, bottom: 45 } },
            tooltip: {
                shared: true,
                intersect: false,
                y: { formatter: v => this.fmtMillion(v) }
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

        const nettEl = document.getElementById('variance-nett-total');
        const absEl  = document.getElementById('variance-absolute-total');
        if (nettEl) nettEl.textContent = this.fmtMillion(data.nett);
        if (absEl) absEl.textContent = this.fmtMillion(data.absolute);
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

            return {
                x: g.group,
                y: [Math.min(start, running), Math.max(start, running)],
                fillColor: g.nett < 0 ? VARIANCE_THEME.negative : VARIANCE_THEME.positive,
                nett: Number(g.nett || 0)
            };
        });

        const options = {
            series: [{ name: 'Nett Variance', data: points }],
            chart: {
                type: 'rangeBar',
                height: 200,
                fontFamily: 'inherit',
                toolbar: { show: false },
                zoom: { enabled: false },
                animations: { enabled: true, speed: 500 }
            },
            plotOptions: {
                bar: { horizontal: false, columnWidth: '55%' }
            },
            dataLabels: {
                enabled: true,
                formatter: (val, opts) => this.fmtMillion(points[opts.dataPointIndex].nett),
                // Saat background aktif, style.colors dipakai sebagai warna kotak label
                style: { fontSize: '12px', fontWeight: 600, colors: [VARIANCE_THEME.labelBg] },
                background: {
                    enabled: true,
                    foreColor: '#FFFFFF',
                    borderRadius: 6,
                    borderWidth: 0,
                    padding: 6,
                    opacity: 1,
                    dropShadow: { enabled: false }
                }
            },
            xaxis: {
                type: 'category',
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: { style: { fontSize: '12px', colors: '#555555' } }
            },
            yaxis: { show: false },
            legend: { show: false },
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
            card.className = 'card shadow-none border border-dark rounded-3 flex-fill';
            card.innerHTML = `
                <div class="card-header text-center pt-2 pb-0">
                    <h6 class="card-title mb-0 fw-semibold">Absolute Variance</h6>
                </div>
                <div class="card-body py-0"><div class="absolute-variance-chart"></div></div>`;
            container.appendChild(card);

            const chart = new ApexCharts(card.querySelector('.absolute-variance-chart'), {
                series: [{ name: 'Absolute Variance', data: [Number(g.absolute || 0)] }],
                chart: {
                    type: 'bar',
                    height: 80,
                    fontFamily: 'inherit',
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    animations: { enabled: true, speed: 500 }
                },
                plotOptions: {
                    bar: { horizontal: true, barHeight: '60%', dataLabels: { position: 'center' } }
                },
                colors: [VARIANCE_THEME.absolute],
                dataLabels: {
                    enabled: true,
                    formatter: val => this.fmtMillion(val),
                    style: { fontSize: '12px', fontWeight: 600, colors: ['#FFFFFF'] },
                    dropShadow: { enabled: false }
                },
                xaxis: {
                    categories: [g.group],
                    min: 0,
                    max: maxAbs,
                    labels: { show: false },
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                },
                yaxis: {
                    labels: { style: { fontSize: '12px', colors: '#555555' } }
                },
                legend: { show: false },
                grid: { show: false, padding: { top: -10, bottom: -10, right: 10 } },
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
                height: this.progressChartHeight(),
                fontFamily: 'inherit',
                toolbar: { show: false },
                zoom: { enabled: false },
                animations: { enabled: animate, speed: 500 }
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
                // Tinggi tetap (sama dengan chart Counting Progress Oil) supaya card tidak memanjang
                // ke bawah saat area oil banyak; ketebalan bar yang menyesuaikan.
                height: TOP_CHART_HEIGHT,
                fontFamily: 'inherit',
                stacked: true,
                stackType: '100%',
                toolbar: { show: false },
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
                style: { fontSize: items.length > 4 ? '12px' : '16px', fontWeight: 400, colors: ['#FFFFFF'] },
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