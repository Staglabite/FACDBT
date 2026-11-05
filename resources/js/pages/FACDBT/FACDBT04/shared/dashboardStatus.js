import axios from 'axios';

// Status data dashboard FACDBT04: indikator loading, label "Terakhir diperbarui", dan auto refresh.

// Cek "ada scan baru?" (request sangat ringan). Server menyimpan versi data 5 detik.
export const CHECK_INTERVAL = 5 * 1000;

// Cadangan: muat ulang penuh berkala supaya perubahan master (unit cost, area, dll.) tetap terbaca
export const REFRESH_INTERVAL = 2 * 60 * 1000;

const VERSION_URL = '/FACDBT/ds/data-version';

let pending = 0;
let lastUpdated = null;
let statusEl = null;
// Nama event periode berjalan (Master Event), dari endpoint versi data
let eventName = null;
// Peringatan data event (mis. Pre-Taking tanpa daftar part)
let eventWarning = null;
let warningEl = null;
// Dipanggil dengan kode event (PRE / TWH / DIRECT / null) setiap kali versi data dicek
const eventTypeListeners = [];

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

    if (eventName) {
        const badge = document.createElement('span');
        badge.className = 'badge bg-label-primary';
        badge.textContent = `Event: ${eventName}`;
        el.prepend(badge);
    }
}

// Kotak peringatan di bawah baris status; disembunyikan bila tidak ada peringatan
function renderWarning() {
    if (!warningEl) {
        if (!eventWarning) return;
        warningEl = document.createElement('div');
        warningEl.className = 'alert alert-warning d-flex align-items-center gap-2 py-2 mb-3';
        warningEl.setAttribute('role', 'alert');
        ensureStatusElement().insertAdjacentElement('afterend', warningEl);
    }

    warningEl.classList.toggle('d-none', !eventWarning);
    warningEl.innerHTML = '<i class="icon-base ti tabler-alert-triangle"></i><span></span>';
    warningEl.querySelector('span').textContent = eventWarning || '';
}

function setEvent(data) {
    const warning = data?.warning || null;
    if (warning !== eventWarning) {
        eventWarning = warning;
        renderWarning();
    }

    const name = data?.event || null;
    if (name !== eventName) {
        eventName = name;
        render();
    }
    eventTypeListeners.forEach(listener => listener(data?.event_type || null));
}

/**
 * Pantau kode event periode berjalan (dicek bersama versi data oleh autoRefresh), mis. supaya Main Dashboard
 * berpindah ke tampilan TWH saat event diganti.
 */
export function onEventType(listener) {
    eventTypeListeners.push(listener);
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
            setEvent(res.data);

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
    axios.get(VERSION_URL).then(res => {
        version = res.data?.version ?? null;
        setEvent(res.data);
    }).catch(() => {});

    setInterval(check, CHECK_INTERVAL);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) check();
    });
}
