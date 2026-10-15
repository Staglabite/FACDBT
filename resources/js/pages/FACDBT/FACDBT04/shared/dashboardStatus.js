// Status data dashboard FACDBT04: indikator loading, label "Terakhir diperbarui", dan auto refresh.

// Interval auto refresh. Cache server segar 60 detik, jadi lebih cepat dari ini tidak menambah data baru.
export const REFRESH_INTERVAL = 2 * 60 * 1000;

let pending = 0;
let lastUpdated = null;
let statusEl = null;

function ensureStatusElement() {
    if (statusEl) return statusEl;

    if (!document.getElementById('fd-dashboard-status-style')) {
        const style = document.createElement('style');
        style.id = 'fd-dashboard-status-style';
        // Saat memuat, card diredupkan supaya terlihat data sedang diperbarui (filter tetap bisa diklik)
        style.textContent = `
            body.fd-loading .card { opacity: .55; transition: opacity .2s ease; }
            body:not(.fd-loading) .card { transition: opacity .2s ease; }
        `;
        document.head.appendChild(style);
    }

    statusEl = document.createElement('div');
    statusEl.className = 'd-flex justify-content-end align-items-center gap-2 small text-muted mb-2';
    statusEl.setAttribute('role', 'status');
    statusEl.setAttribute('aria-live', 'polite');

    const breadcrumb = document.querySelector('nav[aria-label="breadcrumb"]');
    if (breadcrumb) {
        breadcrumb.insertAdjacentElement('afterend', statusEl);
    } else {
        document.body.prepend(statusEl);
    }

    return statusEl;
}

function render() {
    const el = ensureStatusElement();
    document.body.classList.toggle('fd-loading', pending > 0);

    if (pending > 0) {
        el.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Memuat data...</span>';
        return;
    }

    const minutes = REFRESH_INTERVAL / 60000;
    el.textContent = lastUpdated
        ? `Terakhir diperbarui ${lastUpdated.toLocaleTimeString('id-ID')} · otomatis diperbarui tiap ${minutes} menit`
        : '';
}

/**
 * Tandai request data dashboard: tampilkan indikator loading selama request berjalan dan
 * perbarui label "Terakhir diperbarui" bila berhasil. Promise asli diteruskan apa adanya.
 */
export function track(promise) {
    pending++;
    render();

    return promise
        .then(result => {
            lastUpdated = new Date();
            return result;
        })
        .finally(() => {
            pending = Math.max(0, pending - 1);
            render();
        });
}

/**
 * Jalankan callback setiap REFRESH_INTERVAL. Dilewati saat tab tidak terlihat atau masih memuat;
 * saat tab kembali terlihat dan data sudah lewat interval, langsung diperbarui.
 */
export function autoRefresh(callback, interval = REFRESH_INTERVAL) {
    const isStale = () => !lastUpdated || Date.now() - lastUpdated.getTime() >= interval;

    setInterval(() => {
        if (!document.hidden && pending === 0) {
            callback();
        }
    }, interval);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && pending === 0 && isStale()) {
            callback();
        }
    });
}
