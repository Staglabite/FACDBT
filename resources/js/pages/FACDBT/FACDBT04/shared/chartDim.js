// Filter interaktif chart: kategori yang tidak dipilih dipudarkan (tidak disembunyikan).

const SVG_NS = 'http://www.w3.org/2000/svg';
const LAYER_CLASS = 'fd-dim-layer';

/**
 * Tutup kategori selain selectedIndex (satu index, atau array index) dengan lapisan putih transparan.
 * - horizontal : kategori tersusun ke bawah (bar horizontal)
 * - seriesIndex: series yang posisinya dipakai sebagai pusat kategori (chart vertikal)
 * selectedIndex < 0 / null = tidak ada pilihan (lapisan dihapus).
 */
export function dimCategories(chartCtx, count, selectedIndex, { horizontal = false, seriesIndex = 0 } = {}) {
    const g = chartCtx && chartCtx.w && chartCtx.w.globals;
    const host = g && g.dom && g.dom.elGraphical && g.dom.elGraphical.node;
    if (!host) return;

    host.querySelectorAll(`.${LAYER_CLASS}`).forEach(n => n.remove());
    const selected = new Set([].concat(selectedIndex ?? []).filter(i => Number.isInteger(i) && i >= 0));
    if (selected.size === 0 || count === 0) return;

    const layer = document.createElementNS(SVG_NS, 'g');
    layer.setAttribute('class', LAYER_CLASS);
    layer.style.pointerEvents = 'none';

    const addRect = (x, y, width, height) => {
        const rect = document.createElementNS(SVG_NS, 'rect');
        rect.setAttribute('x', x);
        rect.setAttribute('y', y);
        rect.setAttribute('width', Math.max(0, width));
        rect.setAttribute('height', Math.max(0, height));
        rect.setAttribute('fill', '#FFFFFF');
        rect.setAttribute('fill-opacity', '0.7');
        layer.appendChild(rect);
    };

    if (horizontal) {
        const band = g.gridHeight / count;
        for (let i = 0; i < count; i++) {
            if (!selected.has(i)) addRect(-g.translateX, band * i, g.gridWidth + g.translateX, band);
        }
    } else {
        const xs = (g.seriesXvalues && g.seriesXvalues[seriesIndex]) || [];
        const step = xs.length >= 2 && Number.isFinite(xs[1] - xs[0]) ? xs[1] - xs[0] : g.gridWidth / count;
        for (let i = 0; i < count; i++) {
            if (selected.has(i)) continue;
            const cx = Number.isFinite(xs[i]) ? xs[i] : step * i + step / 2;
            // Sampai ke bawah grid supaya label sumbu X ikut pudar
            addRect(cx - step / 2, 0, step, g.gridHeight + 60);
        }
    }

    host.appendChild(layer);
}

/**
 * Index kategori yang diklik (titik data, atau kolom yang sedang disorot tooltip); -1 bila tidak ada.
 */
export function clickedIndex(chartCtx, config) {
    if (config && config.dataPointIndex >= 0) return config.dataPointIndex;

    const captured = chartCtx && chartCtx.w && chartCtx.w.globals && chartCtx.w.globals.capturedDataPointIndex;
    return Number.isInteger(captured) && captured >= 0 ? captured : -1;
}
