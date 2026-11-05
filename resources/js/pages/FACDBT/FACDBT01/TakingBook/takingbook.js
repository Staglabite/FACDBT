// Taking Book: hanya lihat & export data book INV_LIST_NO terbaru
class TakingBook {
    #table = $('#facdbtf004-table');

    init() {
        // Pindahkan baris pencarian per kolom (tfoot) ke thead
        this.#table.on('init.dt', () => {
            this.#table.find('tfoot tr').appendTo(this.#table.find('thead'));
        });

        $(document).on('change', '#entries', e => {
            this.#table.DataTable().page.len($(e.target).val()).draw();
        });

        let searchTimeout;
        $(document).on('keyup', '#search-input', e => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => this.#search($(e.target).val()), 500);
        });

        // File dibuat di server (seluruh data INV_LIST_NO terbaru, bukan hanya halaman yang tampil)
        $(document).on('click', '#export-excel', () => {
            window.location.href = '/FACDBT/bd/master-takingbook/export';
        });
    }

    #search(keyword) {
        const url = new URL(window.location.href);
        if (keyword) url.searchParams.set('keyword', keyword);
        else url.searchParams.delete('keyword');
        this.#table.DataTable().ajax.url(`${url.pathname}?${url.searchParams.toString()}`).load();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    new TakingBook().init();
});
