import ApexCharts from 'apexcharts';
import { dimCategories, clickedIndex } from './chartDim';

// Chart Counting Progress (by Part): % Progress & % Remaining per area.
// Dipakai Main Dashboard dan Progress Dashboard supaya tampilannya sama.

const THEME = {
    remaining: '#14229B',
    progress: '#E63946'
};

const fmtPct2 = v => `${Number(v || 0).toFixed(2)}%`;

// Series bawaan: [{ name, key }] -> nilai items[key]; warna mengikuti urutan THEME (biru tua, merah)
const DEFAULT_SERIES = [
    { name: '% Remaining', key: 'pct_remaining' },
    { name: '% Progress', key: 'pct_progress' }
];

/**
 * Gambar chart di elemen el. items: [{ area, pct_progress, pct_remaining, ... }, ...].
 * Opsional filter interaktif: onSelect(area) dipanggil saat area diklik, selected() = area yang dipilih
 * (area lain dipudarkan). series mengganti nama / kolom nilai kedua series (lihat DEFAULT_SERIES).
 * Mengembalikan instance ApexCharts, atau null bila tidak ada data.
 */
export function renderProgressByAreaChart(el, items, { height = 400, animate = true, emptyText = 'No Data Available for selected time', onSelect = null, selected = null, series = DEFAULT_SERIES } = {}) {
    if (!items || items.length === 0) {
        el.innerHTML = `<p class="text-center text-muted my-5">${emptyText}</p>`;
        return null;
    }

    el.innerHTML = '';
    const categories    = items.map(d => d.area);
    const colors        = [THEME.remaining, THEME.progress];
    const dim = ctx => dimCategories(ctx, items.length, selected ? items.findIndex(d => d.area === selected()) : -1);
    const select = index => {
        if (items[index]) onSelect(items[index].area);
    };
    if (onSelect) el.classList.add('fd-clickable');

    const options = {
        series: series.map(({ name, key }) => ({ name, data: items.map(d => Number(d[key] || 0)) })),
        chart: {
            type: 'area',
            height,
            fontFamily: 'inherit',
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { enabled: animate, speed: 500 },
            events: onSelect ? {
                mounted: dim,
                updated: dim,
                click: (event, ctx, config) => select(clickedIndex(ctx, config)),
                xAxisLabelClick: (event, ctx, config) => select(config.labelIndex)
            } : {}
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
