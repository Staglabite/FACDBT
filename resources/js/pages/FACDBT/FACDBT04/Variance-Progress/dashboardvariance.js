import axios from 'axios';
import { autoRefresh, track } from '../shared/dashboardStatus';
import { TimeRangeFilter } from '../shared/timeRangeFilter';
import { VarianceAreaCharts } from '../shared/varianceAreaCharts';

const ENDPOINT = 'FACDBT/ds/variance-dashboard';

class DashboardVariance {
    constructor() {
        this.timeFilter = new TimeRangeFilter(document.getElementById('time-filter-container'), () => this.loadData());
        this.charts = new VarianceAreaCharts();
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
            .then(res => this.charts.render(res.data || {}))
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
}

document.addEventListener('DOMContentLoaded', () => {
    new DashboardVariance().init();
});
