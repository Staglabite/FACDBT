import axios from 'axios';
import ApexCharts from 'apexcharts';
import { autoRefresh, track } from '../shared/dashboardStatus';
import { TimeRangeFilter } from '../shared/timeRangeFilter';
import { renderProgressByAreaChart } from '../shared/progressByAreaChart';
import { dimCategories } from '../shared/chartDim';

const ENDPOINT = 'FACDBT/ds/progress-dashboard';

const THEME = {
    bar: '#118DFF',
    labelBg: '#1F4E79',
    guide: '#BBBBBB'
};

const ROW_HEIGHT = 48;

class DashboardProgress {
    constructor() {
        // Area yang dipilih dengan klik chart Counting Progress (by Part); '' = semua area
        this.selectedGroup = '';
        // Filter waktu berlaku untuk semua chart
        this.timeFilter = new TimeRangeFilter(document.getElementById('time-filter-container'), () => {
            this.loadData();
            this.loadProgressByArea();
        });
        this.progressByAreaChart = null;
        this.progressByAreaItems = [];
        this.progressByAreaRequest = null;
        this.charts = {};
        this.dataRequest = null;
    }

    init() {
        document.getElementById('selected-area')?.addEventListener('click', () => this.selectArea(this.selectedGroup));
        this.loadTimeRange();
        this.loadData();
        this.loadProgressByArea();

        // Perbarui chart secara berkala dengan filter yang sedang dipilih
        autoRefresh(() => {
            this.loadData();
            this.loadProgressByArea();
        });
    }

    // -------------------------------------------------------------
    // Filter area: klik area di chart Counting Progress (by Part), klik lagi untuk batal
    // -------------------------------------------------------------
    selectArea(area) {
        this.selectedGroup = this.selectedGroup === area ? '' : area;

        const items = this.progressByAreaItems;
        if (this.progressByAreaChart) {
            dimCategories(this.progressByAreaChart, items.length, items.findIndex(d => d.area === this.selectedGroup));
        }
        this.renderSelectedArea();
        this.loadData();
    }

    renderSelectedArea() {
        const label = document.getElementById('selected-area');
        if (!label) return;

        label.classList.toggle('d-none', !this.selectedGroup);
        label.querySelector('span').textContent = this.selectedGroup ? `Area ${this.selectedGroup}` : '';
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
    // Chart Counting Progress (by Part), sama dengan Main Dashboard (ikut filter waktu); klik area = filter area
    // -------------------------------------------------------------
    loadProgressByArea() {
        if (this.progressByAreaRequest) {
            this.progressByAreaRequest.abort();
        }
        const controller = new AbortController();
        this.progressByAreaRequest = controller;

        track(axios.get('FACDBT/ds/main-dashboard/progress-by-area', { params: this.timeFilter.params(), signal: controller.signal, timeout: 60000 }))
            .then(res => {
                const el = document.getElementById('chart-counting-progress');
                if (!el) return;

                if (this.progressByAreaChart) {
                    this.progressByAreaChart.destroy();
                    this.progressByAreaChart = null;
                }
                this.progressByAreaItems = res.data?.parts || [];
                this.progressByAreaChart = renderProgressByAreaChart(el, this.progressByAreaItems, {
                    height: 420,
                    selected: () => this.selectedGroup,
                    onSelect: area => this.selectArea(area)
                });
            })
            .catch(err => {
                if (axios.isCancel(err)) return;
                console.error('Error load counting progress by part:', err);
            })
            .finally(() => {
                if (this.progressByAreaRequest === controller) {
                    this.progressByAreaRequest = null;
                }
            });
    }

    // -------------------------------------------------------------
    // Chart Progress by Loc (% Item & % Qty)
    // -------------------------------------------------------------
    loadData() {
        const params = this.timeFilter.params();
        if (this.selectedGroup) {
            params.group = this.selectedGroup;
        }

        // Batalkan request sebelumnya supaya klik filter beruntun tidak menumpuk request
        if (this.dataRequest) {
            this.dataRequest.abort();
        }
        const controller = new AbortController();
        this.dataRequest = controller;

        track(axios.get(`${ENDPOINT}/data`, { params, signal: controller.signal, timeout: 60000 }))
            .then(res => {
                const data = res.data || {};
                this.renderChart('#chart-progress-item', 'item', data.item || []);
                this.renderChart('#chart-progress-qty', 'qty', data.qty || []);
            })
            .catch(err => {
                if (axios.isCancel(err)) return;
                console.error('Error load progress by location:', err);
            })
            .finally(() => {
                if (this.dataRequest === controller) {
                    this.dataRequest = null;
                }
            });
    }

    fmtPct(v) {
        return `${Number(v || 0).toFixed(2)}%`;
    }

    renderChart(selector, key, items) {
        const el = document.querySelector(selector);
        if (!el) return;

        if (this.charts[key]) {
            this.charts[key].destroy();
            delete this.charts[key];
        }

        if (items.length === 0) {
            el.innerHTML = '<p class="text-center text-muted my-5">No Data Available</p>';
            return;
        }

        el.innerHTML = '';
        const values = items.map(d => Number(d.pct || 0));
        const maxVal = Math.max(...values, 0);
        // Sumbu sampai 100%, diperlebar bila ada progress di atas 100% supaya bar tidak terpotong
        const xMax = maxVal > 100 ? Math.ceil(maxVal / 10) * 10 : 100;

        // Garis bantu putus-putus 0% / 50% / 100% dengan label di bawah chart
        const guides = [0, 50, 100].map(x => ({
            x,
            borderColor: THEME.guide,
            strokeDashArray: 3,
            label: {
                text: `${x}%`,
                orientation: 'horizontal',
                position: 'bottom',
                offsetY: 22,
                borderWidth: 0,
                style: { background: 'transparent', color: '#555555', fontSize: '12px' }
            }
        }));

        const options = {
            series: [{ name: key === 'item' ? '% Item' : '% Qty', data: values }],
            chart: {
                type: 'bar',
                height: Math.max(300, items.length * ROW_HEIGHT + 60),
                fontFamily: 'inherit',
                toolbar: { show: false },
                zoom: { enabled: false },
                animations: { enabled: true, speed: 500 }
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    barHeight: '78%',
                    dataLabels: { position: 'top' }
                }
            },
            colors: [THEME.bar],
            dataLabels: {
                enabled: true,
                textAnchor: 'end',
                offsetX: -6,
                formatter: val => this.fmtPct(val),
                // Saat background aktif, style.colors dipakai sebagai warna kotak label
                style: { fontSize: '12px', fontWeight: 500, colors: [THEME.labelBg] },
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
            annotations: { xaxis: guides },
            xaxis: {
                categories: items.map(d => d.warehouse),
                min: 0,
                max: xMax,
                labels: { show: false },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                labels: {
                    maxWidth: 160,
                    style: { fontSize: '12px', colors: '#555555' }
                }
            },
            legend: { show: false },
            grid: { show: false, padding: { left: 10, right: 20, bottom: 20 } },
            tooltip: {
                y: { formatter: v => this.fmtPct(v) }
            }
        };

        this.charts[key] = new ApexCharts(el, options);
        this.charts[key].render();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new DashboardProgress().init();
});
