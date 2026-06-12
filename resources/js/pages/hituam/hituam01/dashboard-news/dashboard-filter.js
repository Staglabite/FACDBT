import axios from "axios";

class dashboardFilter {
    #dashboardEndpoint = 'HITUAM/bd/news';

    constructor() {
        this.$date = $('#date-range-picker');
        this.$search = $('#search-news');
        this.$main = $('#main-dashboard');
        this.$second = $('#second-dashboard');
        this.$loader = $('#dashboard-loading');

        this.loadingTimeout = null;

        this.#initEvents();
    }

    #initEvents() {
        this.$date.on('change input', (e) => this.#handleFilterChange(e));
        this.$search.on('input', (e) => this.#handleFilterChange(e));
    }

    #handleFilterChange(e) {
        this.#showLoading();

        clearTimeout(this.loadingTimeout);

        // kasih delay sedikit biar loading terlihat
        this.loadingTimeout = setTimeout(() => {
            this.#toggleDashboard();
            this.#hideLoading();
        }, 300);

        const dateRange = (this.$date.val() || '').trim();
        const searchQuery = (this.$search.val() || '').trim();

        this.#goFilter(dateRange, searchQuery);
    }

    async #goFilter(dateRange, searchQuery) {
        try {
            const response = await axios.get(`/${this.#dashboardEndpoint}/filter`, {
                params: {
                    date: dateRange,
                    search: searchQuery
                }
            });

            const data = response.data.data;

            const stripHtml = (html) => {
                let doc = new DOMParser().parseFromString(html, "text/html");
                return doc.body.textContent || "";
            };

            const renderHtml = data.map(item => {
                const cleanText = stripHtml(item.VCONTENT || "");
                const content = cleanText.substring(0, 100) + (cleanText.length > 50 ? "..." : "");
                const hrefUrl = `/${this.#dashboardEndpoint}/0/${item.VSUBJECT}`;
                return `<div class="col-md mb-4">
                            <div class="card">
                                <div class="row g-0">
                                    <div class="col-md-4">
                                        <img class="card-img card-img-left" src="${item.VIMAGE_PATH}" alt="Card image">
                                    </div>
                                    <div class="col-md-8">
                                        <div class="card-body">
                                            <a href="${hrefUrl}"">
                                                <h5 class="card-title">${item.VTITLE}</h5>
                                            </a>
                                            <p class="card-text">${content}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
            }).join('');

            this.$second.html(renderHtml);

        } catch (error) {
            console.error('Error fetching filtered dashboard:', error);
            this.$second.html('<p class="text-danger">Failed to load data. Please try again.</p>');
        }
    }

    #showLoading() {
        this.$loader.removeClass('d-none').hide().fadeIn(150);
    }

    #hideLoading() {
        this.$loader.fadeOut(150, () => {
            this.$loader.addClass('d-none');
        });
    }

    #toggleDashboard() {
        const hasFilter =
            (this.$date.val() || '').trim() !== '' ||
            (this.$search.val() || '').trim() !== '';

        this.$main.toggleClass('d-none', hasFilter);
        this.$second.toggleClass('d-none', !hasFilter);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new dashboardFilter();
});
