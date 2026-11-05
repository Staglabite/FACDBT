class RoleAccess {
    #roleAccessTable = $('#hituamf007-table');

    init() {
        const self = this;
        this.#filterEvents();
    }

    #filterEvents() {
        $(document).on('change', '#entries', e => {
            const perPage = $(e.target).val();
            const table = this.#roleAccessTable.DataTable();

            table.page.len(perPage).draw();
        });

        let searchTimeout;
        $(document).on('keyup', '#search-input', e => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const keyword = $(e.target).val();
                this.#updateQuery({ keyword });
            }, 500);
        });
    }

    #updateQuery(params) {
        const table = this.#roleAccessTable.DataTable();
        const currentUrl = new URL(window.location.href);
        const searchParams = new URLSearchParams(currentUrl.search);

        // Update parameters
        for (const key in params) {
            // delete key parameters if its empty for clearance
            if (params[key] === '' || params[key] === null || params[key] === undefined) {
                searchParams.delete(key);
            } else {
                searchParams.set(key, params[key]);
            }
        }

        searchParams.set('page', 1);

        // Update the AJAX URL and reload
        const newUrl = `${currentUrl.pathname}?${searchParams.toString()}`;
        table.ajax.url(newUrl).load();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    new RoleAccess().init();
});
