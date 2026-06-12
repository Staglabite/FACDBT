import axios from "axios";
import Quill from "quill";
import 'quill/dist/quill.snow.css';
import { _showInvalidError, toast } from "../../../../helpers";

export default class NewsForm {
    #formSelector = '#application-form';
    #newsEndpoint = 'HITUAM/bd/master-news';
    #quill;
    #news = window.APP_CONFIG?.news || null;

    // Track apakah existing file/foto dihapus oleh user
    #fileDeleted = false;
    #fotoDeleted = false;

    constructor(onSuccess) {
        this.onSuccess = onSuccess;
        this.#initQuill();
        this.#init();
    }

    #initQuill() {
        // Initialize Quill editor
        this.#quill = new Quill('#content-editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                    [{ 'align': [] }],
                    ['link', 'image'],
                    ['clean']
                ]
            },
            placeholder: 'Write your content here...'
        });
    }

    #init() {
        const self = this;

        $(document).on('submit', this.#formSelector, async function (e) {
            e.preventDefault();
            await self.#submitForm(this);
        });

        if (window.newsData) {
            this.#quill.root.innerHTML = window.newsData.VCONTENT ?? '';
        }

        this.#fetchVendorData();
        this.#selectTwoEvents();
        this.#initFilePreview();
        this.#initResetButton();
    }

    #initFilePreview() {
        const self = this;

        // Handle Upload File preview
        $('#upload_file').on('change', function (e) {
            const file = e.target.files[0];
            const previewDiv = $('#file_preview')[0];

            if (file) {
                // User memilih file baru → batalkan flag delete
                self.#fileDeleted = false;
                previewDiv.innerHTML = `
                    <div class="file-preview-item">
                        <i class="bx bx-file"></i>
                        <span class="file-name">${file.name}</span>
                        <button type="button" class="btn btn-sm btn-danger btn-delete"
                            data-input-id="upload_file" data-preview-id="file_preview" data-existing="false">
                            <i class="bx bx-trash"></i> Delete
                        </button>
                    </div>
                `;
            } else {
                previewDiv.innerHTML = '';
            }
        });

        // Handle Upload Foto preview
        $('#upload_foto').on('change', function (e) {
            const file = e.target.files[0];
            const previewDiv = $('#foto_preview')[0];

            if (file) {
                // User memilih foto baru → batalkan flag delete
                self.#fotoDeleted = false;
                previewDiv.innerHTML = `
                    <div class="file-preview-item">
                        <i class="bx bx-image"></i>
                        <span class="file-name">${file.name}</span>
                        <button type="button" class="btn btn-sm btn-danger btn-delete"
                            data-input-id="upload_foto" data-preview-id="foto_preview" data-existing="false">
                            <i class="bx bx-trash"></i> Delete
                        </button>
                    </div>
                `;
            } else {
                previewDiv.innerHTML = '';
            }
        });

        // Handle delete button clicks using event delegation
        $(document).on('click', '.file-preview-item .btn-delete', function (e) {
            if (e.target.closest('.btn-delete')) {
                const button = e.target.closest('.btn-delete');
                const inputId = button.getAttribute('data-input-id');
                const previewId = button.getAttribute('data-preview-id');
                const isExisting = button.getAttribute('data-existing') !== 'false';

                // Jika yang dihapus adalah file/foto existing dari server → tandai untuk dihapus
                if (isExisting) {
                    if (inputId === 'upload_file') {
                        self.#fileDeleted = true;
                    } else if (inputId === 'upload_foto') {
                        self.#fotoDeleted = true;
                    }
                }

                document.getElementById(inputId).value = '';
                document.getElementById(previewId).innerHTML = '';
            }
        });
    }

    #initResetButton() {
        const self = this;
        $('#btn-reset').on('click', function () {
            self.#resetForm();
        });
    }

    #selectTwoEvents() {
        $('#publish_to_vendor').on('select2:select', function (e) {
            const selectedValue = e.params.data.id;
            const currentValues = $(this).val() || [];

            // Jika "All" dipilih
            if (selectedValue === 'all') {
                // Clear semua pilihan lain dan hanya pilih "all"
                $(this).val(['all']).trigger('change');

                // Disable semua option kecuali "all"
                $(this).find('option').each(function () {
                    if ($(this).val() !== 'all') {
                        $(this).prop('disabled', true);
                    }
                });
            } else {
                // Jika pilihan lain dipilih, remove "all" jika ada
                if (currentValues.includes('all')) {
                    const newValues = currentValues.filter(val => val !== 'all');
                    $(this).val(newValues).trigger('change');
                }
            }
        });

        $('#publish_to_vendor').on('select2:unselect', function (e) {
            const unselectedValue = e.params.data.id;

            // Jika "all" di-unselect, enable semua option
            if (unselectedValue === 'all') {
                $(this).find('option').prop('disabled', false);
            }
        });

        $('#publish_to_vendor').on('select2:clear', function () {
            $(this).find('option').prop('disabled', false);
        });
    }

    async #submitForm(form) {
        const formData = new FormData(form);
        formData.set('content', this.#quill.root.innerHTML);

        const isPublish = document.querySelector('#publish').checked;
        formData.set('publish', isPublish);

        // Kirim flag apakah existing file/foto harus dihapus
        formData.set('delete_file', this.#fileDeleted ? '1' : '0');
        formData.set('delete_foto', this.#fotoDeleted ? '1' : '0');

        const parts = window.location.pathname.split('/').filter(Boolean);
        const id = parts[parts.length - 1];

        const url = this.#newsEndpoint + (id != 0 ? `/` + id : '');
        await axios({
            headers: { 'Content-Type': 'multipart/form-data' },
            method: 'post',
            url: `/${url}`,
            data: formData,
        }).then(response => {
            if (response.status === 200 || response.status === 201) {
                toast.success('News saved successfully!');
                setTimeout(() => {
                    window.location.href = '/' + this.#newsEndpoint;
                }, 1000);
            } else {
                toast.success(response.data.message || 'An error occurred while saving the News.');
            }
        }).catch(error => {
            const res = error.response;
            _showInvalidError(res.data.errors);
        });
    }

    #fetchVendorData() {
        $('#publish_to_vendor').each(function () {
            const $select = $(this);
            $select.select2({
                placeholder: 'Select Vendors',
                allowClear: true
            });
        });
    }

    #resetForm() {
        const self = this;
        const data = this.#news;

        // Reset deletion flags
        self.#fileDeleted = false;
        self.#fotoDeleted = false;

        // MODE EDIT → restore data
        if (data) {

            // Title
            $('#title').val(data.VTITLE ?? '');

            // Publish checkbox
            $('#publish').prop('checked', !!data.BSTATUS);

            // Select2 vendor
            let vendors = [];
            if (Array.isArray(data.AVIEWERS)) {
                vendors = data.AVIEWERS;
            } else if (typeof data.AVIEWERS === 'string') {
                vendors = data.AVIEWERS.split(',');
            }

            $('#publish_to_vendor')
                .val(vendors)
                .trigger('change');

            $('#publish_to_vendor option').prop('disabled', false);

            // File preview — restore existing file dengan data-existing="true"
            $('#file_preview').html(
                data.VFILE_PATH
                    ? `
                <div class="file-preview-item">
                    <i class="bx bx-file"></i>
                    <span class="file-name">${data.VFILE_PATH}</span>
                    <button type="button" class="btn btn-sm btn-danger btn-delete"
                        data-input-id="upload_file" data-preview-id="file_preview" data-existing="true">
                        <i class="bx bx-trash"></i> Delete
                    </button>
                </div>`
                    : ''
            );

            // Foto preview — restore existing foto dengan data-existing="true"
            $('#foto_preview').html(
                data.VIMAGE_PATH
                    ? `
                <div class="file-preview-item">
                    <i class="bx bx-file"></i>
                    <span class="file-name">${data.VIMAGE_PATH}</span>
                    <button type="button" class="btn btn-sm btn-danger btn-delete"
                        data-input-id="upload_foto" data-preview-id="foto_preview" data-existing="true">
                        <i class="bx bx-trash"></i> Delete
                    </button>
                </div>`
                    : ''
            );

            // Clear file input (security reason)
            $('#upload_file').val('');
            $('#upload_foto').val('');

            // Content (Quill)
            if (data.VCONTENT) {
                self.#quill.clipboard.dangerouslyPasteHTML(data.VCONTENT);
            } else {
                self.#quill.setContents([]);
            }

            return;
        }

        // Reset form
        $(self.#formSelector)[0].reset();

        // Clear file previews
        $('#file_preview').empty();
        $('#foto_preview').empty();

        // Clear Quill editor
        self.#quill.setText('');

        // Reset Select2
        $('#publish_to_vendor').val(null).trigger('change');
        $('#publish_to_vendor').find('option').prop('disabled', false);
    }
}

// Initialize NewsForm
document.addEventListener('DOMContentLoaded', function () {
    new NewsForm(() => {
        console.log('Form submitted successfully');
    });
});
