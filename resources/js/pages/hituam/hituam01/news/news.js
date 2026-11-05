import axios from "axios";
import Swal from "sweetalert2";

class news {
    #newsTable = $('#hituamf011-table');
    #newsEndpoint = 'HITUAM/bd/master-news';

    init() {
        const self = this;

        this.#newsTable.on('init.dt', function () {
            var tfoot = self.#newsTable.find('tfoot tr');
            var thead = self.#newsTable.find('thead');

            // Move the tfoot row into thead (after the header row)
            tfoot.appendTo(thead);
        });

        this.#events();
    }

    #events() {
        const self = this;

        $('#btn-create').on('click', function (e) {
            e.preventDefault();
            window.location.href = `/${self.#newsEndpoint}/form/0`;
        });

        $(document).on('click', '.edit-news', e => {
            const newsId = $(e.currentTarget).data('id');
            window.location.href = `/${self.#newsEndpoint}/form/${newsId}`;
        });

        $(document).on('click', '.delete-news', e => {
            const newsId = $(e.currentTarget).data('id');
            self.#deleteNews(newsId);
        });

        $(document).on('click', '.view-news', e => {
            const newsId = $(e.currentTarget).data('id');
            axios.get(`/${self.#newsEndpoint}/id/${newsId}`).then(response => {
                const slugs = response.data.VSUBJECT;
                window.open(`/${self.#newsEndpoint}/slug/${slugs}`, '_blank');
            });
        });

        $(document).on('click', '#btn-delete-selected-service', event => {
            event.preventDefault();
            this.#deleteSelected();
        });

        $(document).on('change', '#entries', e => {
            const perPage = $(e.target).val();
            this.#newsTable.DataTable().page.len(perPage).draw();
        });
    }

    async #deleteNews(newsId) {
        Swal.fire({
            title: 'Are you sure?',
            text: 'You won\'t be able to revert this!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const response = await axios.delete(`/${this.#newsEndpoint}/${newsId}`);
                    if (response.status === 200) {
                        Swal.fire(
                            'Deleted!',
                            'News has been deleted.',
                            'success'
                        );
                        this.#newsTable.DataTable().ajax.reload();
                    }
                } catch (error) {
                    Swal.fire(
                        'Error!',
                        'There was an error deleting the news.',
                        'error'
                    );
                }
            }
        });
    }

    async #deleteSelected() {
        const selectedIds = [];
        $('input[name="selected-service[]"]:checked').each(function () {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            Swal.fire('No Selection', 'Please select at least one news to delete.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete ${selectedIds.length} news item(s). This action cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete them!'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const response = await axios.post(`/${this.#newsEndpoint}/bulk-delete`, { ids: selectedIds });
                    if (response.status === 200) {
                        Swal.fire(
                            'Deleted!',
                            `${selectedIds.length} news item(s) have been deleted.`,
                            'success'
                        );
                        this.#newsTable.DataTable().ajax.reload();
                    }
                } catch (error) {
                    Swal.fire(
                        'Error!',
                        'There was an error deleting the selected news items.',
                        'error'
                    );
                }
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    new news().init();
});
