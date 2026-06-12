import flatpickr from "flatpickr";
import "flatpickr/dist/flatpickr.min.css";
import axios from "axios";

class Dashboard {
    #dashboardEndpoint = 'HITUAM/bd/news';

    constructor() {
        this.$main = $('#main-dashboard');
        this.$forList = $('#for-list-news');
        this.$second = $('#second-dashboard');
        this.$loader = $('#dashboard-loading');
        this.$dateRange = $('.date-range-picker'); // Support multiple instances

        this.flatpickrInstances = [];
        this.#init();
    }

    #init() {
        this.#filterFlatpickr();
        this.#clickEvent();
    }

    #filterFlatpickr() {
        // Initialize all date range pickers
        document.querySelectorAll('#date-range-picker').forEach((element) => {
            const instance = flatpickr(element, {
                mode: "range",
                dateFormat: "Y-m-d",
                allowInput: true,
                onChange: (selectedDates, dateStr) => {
                    // Sync all date pickers
                    this.#syncDatePickers(dateStr);

                    if (dateStr) {
                        this.#handleDateFilter(dateStr);
                    } else {
                        this.#resetToDefault();
                    }
                },
                onClose: (selectedDates, dateStr) => {
                    if (!dateStr) {
                        this.#resetToDefault();
                    }
                }
            });

            this.flatpickrInstances.push(instance);
        });
    }

    #syncDatePickers(dateStr) {
        // Update all date picker instances with the same value
        this.flatpickrInstances.forEach(instance => {
            if (instance.input.value !== dateStr) {
                instance.setDate(dateStr);
            }
        });
    }

    #clickEvent() {
        $('#more-news').on('click', (e) => {
            e.preventDefault();
            this.#loadMoreNews();
        });
    }

    #loadMoreNews() {
        this.#showLoading();

        // Fetch all news without filters
        this.#fetchNews('').then(() => {
            this.#showListView();
            this.#hideLoading();
        });
    }

    #handleDateFilter(dateRange) {
        this.#showLoading();

        this.#fetchNews(dateRange).then(() => {
            this.#showListView();
            this.#hideLoading();
        });
    }

    async #fetchNews(dateRange = '') {
        try {
            const response = await axios.get(`/${this.#dashboardEndpoint}/filter`, {
                params: {
                    date: dateRange
                }
            });

            const data = response.data.data;

            this.#renderNewsList(data);
        } catch (error) {
            console.error('Error fetching news:', error);
            this.$second.html('<div class="alert alert-danger">Failed to load data. Please try again.</div>');
        }
    }

    #renderNewsList(data) {
        const stripHtml = (html) => {
            let doc = new DOMParser().parseFromString(html, "text/html");
            return doc.body.textContent || "";
        };

        if (data.length === 0) {
            this.$second.html(`
                <div class="text-center py-5">
                    <i class="icon-base ti tabler-file-x" style="font-size: 48px; color: #999;"></i>
                    <p class="text-muted mt-3">No news found for the selected date range.</p>
                </div>
            `);
            return;
        }

        const renderHtml = data.map(item => {
            const cleanText = stripHtml(item.VCONTENT || "");
            const content = cleanText.substring(0, 100) + (cleanText.length > 100 ? "..." : "");
            const hrefUrl = `/${this.#dashboardEndpoint}/0/${item.VSUBJECT}`;
            const imagePath = item.VIMAGE_PATH?.startsWith('http')
                ? item.VIMAGE_PATH
                : (item.VIMAGE_PATH
                    ? `/storage/news/images/${item.VIMAGE_PATH}`
                    : '/storage/default-astemo.png');

            return `
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="row g-0">
                            <div class="col-md-4">
                                <img class="card-img card-img-left" src="${imagePath}" alt="Card image" style="height: 100%; object-fit: cover;">
                            </div>
                            <div class="col-md-8">
                                <div class="card-body">
                                    <a href="${hrefUrl}" class="text-decoration-none">
                                        <h5 class="card-title">${item.VTITLE}</h5>
                                    </a>
                                    <p class="card-text text-muted">${content}</p>
                                    <a href="${hrefUrl}" class="btn btn-outline-secondary btn-sm gap-2 px-2 py-2">
                                        <i class="icon-base ti tabler-book"></i> Read More
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        this.$second.html(renderHtml);
    }

    #showListView() {
        this.$main.addClass('d-none');
        this.$forList.removeClass('d-none');
    }

    #resetToDefault() {
        this.$main.removeClass('d-none');
        this.$forList.addClass('d-none');
        this.$loader.addClass('d-none');

        // Clear all date picker instances
        this.flatpickrInstances.forEach(instance => {
            instance.clear();
        });
    }

    #showLoading() {
        this.$main.addClass('d-none');
        this.$forList.addClass('d-none');
        this.$loader.removeClass('d-none').hide().fadeIn(150);
    }

    #hideLoading() {
        this.$loader.fadeOut(150, () => {
            this.$loader.addClass('d-none');
        });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    new Dashboard();
});
