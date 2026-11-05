import axios from "axios";

class Dashboard {

    dashboardInformationEndpoint = 'HITUAM/ds/informations'
    informationModal = new bootstrap.Modal(document.getElementById('infoModal'));
    imageBaseUrl = window.STORAGE_URL;

    // Antrian informasi yang akan ditampilkan
    informationQueue = [];
    currentInformation = null;
    isClosing = false;

    constructor() {
        this.init();
    }

    init() {
        this.fetchInformation();
        this.bindEvents();
    }

    bindEvents() {
        document
            .getElementById('closeInfoModal')
            ?.addEventListener('click', () => {
                this.informationModal.hide();
            });

        document
            .getElementById('infoModal')
            ?.addEventListener('hidden.bs.modal', () => {
                if (!this.isClosing) {
                    this.isClosing = true;
                    this.closeInformation();
                }
            });
    }

    async fetchInformation() {
        try {
            const response = await axios.get(`/${this.dashboardInformationEndpoint}`);
            const data = response.data.data;

            // data adalah array, cek apakah ada informasi yang perlu ditampilkan
            if (!data || data.length === 0) {
                console.log('No information to show today');
                return;
            }

            // Simpan semua informasi ke dalam antrian
            this.informationQueue = [...data];

            // Tampilkan informasi pertama
            this.showNextInformation();

        } catch (error) {
            console.error('Error fetching information:', error);
        }
    }

    showNextInformation() {
        // Jika antrian kosong, tidak ada lagi yang ditampilkan
        if (this.informationQueue.length === 0) {
            console.log('All information have been shown');
            return;
        }

        // Ambil informasi pertama dari antrian
        this.currentInformation = this.informationQueue.shift();

        // Isi konten modal
        $('.info-text').html(this.currentInformation.VNOTES);

        if (this.currentInformation.VUPDLOAD_FOTO_ASSET) {
            $('.info-image').attr('src', this.imageBaseUrl + '/' + this.currentInformation.VUPDLOAD_FOTO_ASSET);
            $('.info-image').show();
        } else {
            $('.info-image').hide();
        }

        if (this.currentInformation.VFILE_INFORMATION) {
            const pdfUrl = this.imageBaseUrl + '/' + this.currentInformation.VFILE_INFORMATION;
            $('.info-pdf-link').attr('href', pdfUrl);
            $('.info-pdf-wrapper').show();
        } else {
            $('.info-pdf-wrapper').hide();
        }

        // Reset flag dan tampilkan modal
        this.isClosing = false;
        this.informationModal.show();
    }

    async closeInformation() {
        if (!this.currentInformation) return;

        try {
            await axios.post(`/${this.dashboardInformationEndpoint}/close`, {
                information_id: this.currentInformation.IID
            });
        } catch (error) {
            console.error('Error closing info modal:', error);
        } finally {
            this.currentInformation = null;

            // Tampilkan informasi berikutnya jika ada di antrian
            // Beri sedikit jeda agar animasi modal selesai sebelum modal berikutnya muncul
            if (this.informationQueue.length > 0) {
                setTimeout(() => {
                    this.showNextInformation();
                }, 400);
            }
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    new Dashboard();
});
