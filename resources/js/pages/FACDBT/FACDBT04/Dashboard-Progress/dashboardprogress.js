import axios from 'axios';
import ApexCharts from 'apexcharts';
import { autoRefresh, track } from '../shared/dashboardStatus';

const ENDPOINT = 'FACDBT/ds/progress-dashboard';

const THEME = {
    bar: '#118DFF',
    labelBg: '#1F4E79',
    guide: '#BBBBBB',
    groupBg: '#E9E9E9',
    groupBorder: '#8C8C8C',
    timeBg: '#E8703A',
    activeBg: '#333333'
};

const ROW_HEIGHT = 48;

class DashboardProgress {
    constructor() {
        this.selectedGroup = '';
        this.selectedTime = null;
        this.charts = {};
        this.dataRequest = null;
    }

    init() {
        this.bindScrollers();
        this.loadGroups();
        this.loadTimeSlots();

        // Perbarui chart secara berkala dengan filter yang sedang dipilih
        autoRefresh(() => this.loadData());
    }

    // -------------------------------------------------------------
    // Filter Group Location
    // -------------------------------------------------------------
    loadGroups() {
        axios.get(`${ENDPOINT}/groups`)
            .then(res => {
                const container = document.getElementById('group-filter-container');
                if (!container) return;

                container.innerHTML = '';
                const buttons = [];

                (res.data || []).forEach(group => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.innerText = group;
                    this.styleGroupButton(btn, false);

                    // Klik group yang sama sekali lagi untuk menghapus filter
                    btn.addEventListener('click', () => {
                        this.selectedGroup = this.selectedGroup === group ? '' : group;
                        buttons.forEach(b => this.styleGroupButton(b, b.innerText === this.selectedGroup));
                        this.loadData();
                    });

                    buttons.push(btn);
                    container.appendChild(btn);
                });
            })
            .catch(err => console.error('Error load location groups:', err));
    }

    styleGroupButton(btn, isActive) {
        btn.className = 'btn btn-sm rounded-0 px-3 py-2';
        btn.style.minWidth = '140px';
        btn.style.flexShrink = '0';
        btn.style.whiteSpace = 'nowrap';
        btn.style.border = `1px solid ${THEME.groupBorder}`;
        btn.style.backgroundColor = isActive ? THEME.activeBg : THEME.groupBg;
        btn.style.color = isActive ? '#FFFFFF' : '#333333';
    }

    bindScrollers() {
        document.querySelectorAll('.group-scroller').forEach(btn => {
            btn.addEventListener('click', () => {
                const container = document.getElementById('group-filter-container');
                if (!container) return;

                const step = container.clientWidth * 0.6 * Number(btn.dataset.direction || 1);
                container.scrollBy({ left: step, behavior: 'smooth' });
            });
        });
    }

    // -------------------------------------------------------------
    // Filter Waktu (default: tidak dipilih = semua data taking)
    // -------------------------------------------------------------
    loadTimeSlots() {
        axios.get(`${ENDPOINT}/time-slots`)
            .then(res => {
                const slots = res.data || [];
                const container = document.getElementById('time-filter-container');
                if (!container) return;

                container.innerHTML = '';
                const buttons = [];

                slots.forEach(slot => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.innerText = slot.label;
                    btn.dataset.time = slot.full_time;
                    this.styleTimeButton(btn, false);

                    // Klik slot yang sama sekali lagi untuk menghapus filter waktu
                    btn.addEventListener('click', () => {
                        this.selectedTime = this.selectedTime === slot.full_time ? null : slot.full_time;
                        buttons.forEach(b => this.styleTimeButton(b, b.dataset.time === this.selectedTime));
                        this.loadData();
                    });

                    buttons.push(btn);
                    container.appendChild(btn);
                });

                this.loadData();
            })
            .catch(err => console.error('Error load time slots:', err));
    }

    styleTimeButton(btn, isActive) {
        btn.className = 'btn btn-sm rounded-0 px-2 py-2 fs-6';
        // Lebar sama rata mengisi kotak filter, dibatasi supaya tidak terlalu lebar di layar besar
        btn.style.flex = '1 1 0';
        btn.style.minWidth = '84px';
        btn.style.maxWidth = '140px';
        btn.style.whiteSpace = 'nowrap';
        btn.style.border = `1px solid ${THEME.groupBorder}`;
        btn.style.backgroundColor = isActive ? THEME.activeBg : THEME.timeBg;
        btn.style.color = '#FFFFFF';
    }

    // -------------------------------------------------------------
    // Chart Progress by Loc (% Item & % Qty)
    // -------------------------------------------------------------
    loadData() {
        const params = {};
        if (this.selectedTime) {
            params.time = this.selectedTime;
        }
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
