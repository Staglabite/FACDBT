import ApexCharts from 'apexcharts';

// Chart Counting Progress (by Part): % Progress & % Remaining per area.
// Dipakai Main Dashboard dan Progress Dashboard supaya tampilannya sama.

const THEME = {
    remaining: '#14229B',
    progress: '#E63946'
};

const fmtPct2 = v => `${Number(v || 0).toFixed(2)}%`;

/**
 * Gambar chart di elemen el. items: [{ area, pct_progress, pct_remaining }, ...].
 * Mengembalikan instance ApexCharts, atau null bila tidak ada data.
 */
export function renderProgressByAreaChart(el, items, { height = 400, animate = true, emptyText = 'No Data Available for selected time' } = {}) {
    if (!items || items.length === 0) {
        el.innerHTML = `<p class="text-center text-muted my-5">${emptyText}</p>`;
        return null;
    }

    el.innerHTML = '';
    const categories    = items.map(d => d.area);
    const progressData  = items.map(d => Number(d.pct_progress));
    const remainingData = items.map(d => Number(d.pct_remaining));
    const colors        = [THEME.remaining, THEME.progress];

    const options = {
        series: [
            { name: '% Remaining', data: remainingData },
            { name: '% Progress', data: progressData }
        ],
        chart: {
            type: 'area',
            height,
            fontFamily: 'inherit',
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { enabled: animate, speed: 500 }
        },
        colors,
        dataLabels: {
            enabled: true,
            offsetY: -8,
            formatter: val => fmtPct2(val),
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
            y: { formatter: v => fmtPct2(v) }
        }
    };


    const chart = new ApexCharts(el, options);
    chart.render();

    return chart;
}
