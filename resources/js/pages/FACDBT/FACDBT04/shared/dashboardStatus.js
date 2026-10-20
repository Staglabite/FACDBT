import axios from 'axios';

// Status data dashboard FACDBT04: indikator loading, label "Terakhir diperbarui", dan auto refresh.

// Cek "ada scan baru?" (request sangat ringan). Server menyimpan versi data 15 detik.
export const CHECK_INTERVAL = 10 * 1000;

// Cadangan: muat ulang penuh berkala supaya perubahan master (unit cost, area, dll.) tetap terbaca
export const REFRESH_INTERVAL = 2 * 60 * 1000;

const VERSION_URL = '/FACDBT/ds/data-version';

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

    el.textContent = lastUpdated
        ? `Terakhir diperbarui ${lastUpdated.toLocaleTimeString('id-ID')} · otomatis diperbarui saat ada scan baru`
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
 * Muat ulang dashboard hampir real-time:
 * - tiap CHECK_INTERVAL browser menanyakan versi data ke server; callback dijalankan hanya bila versinya
 *   berubah (ada scan / book baru), jadi perhitungan berat tidak diulang tanpa perubahan data;
 * - tiap REFRESH_INTERVAL callback tetap dijalankan sebagai cadangan.
 * Dilewati saat tab tidak terlihat atau masih memuat; saat tab kembali terlihat langsung dicek.
 */
export function autoRefresh(callback, interval = REFRESH_INTERVAL) {
    let version = null;
    let checking = false;

    const isStale = () => !lastUpdated || Date.now() - lastUpdated.getTime() >= interval;

    const check = async () => {
        if (document.hidden || pending > 0 || checking) return;

        checking = true;
        try {
            const res = await axios.get(VERSION_URL, { timeout: 15000 });
            const latest = res.data?.version ?? null;

            if (version !== null && latest !== version) {
                callback();
            } else if (isStale()) {
                callback();
            }
            version = latest;
        } catch (e) {
            // Gagal cek versi (mis. jaringan): pakai pembaruan berkala biasa
            if (isStale()) callback();
        } finally {
            checking = false;
        }
    };

    // Versi awal (data yang baru dimuat saat halaman dibuka)
    axios.get(VERSION_URL).then(res => { version = res.data?.version ?? null; }).catch(() => {});

    setInterval(check, CHECK_INTERVAL);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) check();
    });
}
