/**
 * Simple File Uploader Component
 *
 * Author: Andri Ilham
 * email: andri@tech.kelola.biz
 *
 */

/**
 * @typedef {Object} UploaderOptions
 * @property {HTMLElement} element
 * @property {number} maxFiles
 * @property {string} allowedTypes
 * @property {Function} onFileAdd
 * @property {Function} onFileRemove
 * @property {Function} onAddError
 * @property {Function} onSeededRemove
 */
export default class Uploader {
    /** @type {UploaderOptions} */
    #options = {};

    #fileInput = null;
    #fileList = null;
    #seedFileList = null;

    #uploadedFiles = [];
    #seededFiles = [];

    /**
     * @param {UploaderOptions} options
     */
    constructor(options) {
        /** @type {UploaderOptions} */
        this.#options = {
            element: options.element,
            allowedTypes: options.allowedTypes || '*',
            maxFiles: options.maxFiles || 1,
            onFileAdd: options.onFileAdd || null,
            onFileRemove: options.onFileRemove || null,
            onAddError: options.onAddError || null,
            onSeededRemove: options.onSeededRemove || null
        };

        this.#init();
    }

    #init() {
        const options = this.#options;
        if (!options.element) {
            return;
        }

        this.#draw();

        this.#fileInput = document.getElementById('fileInput');
        this.#fileList = document.getElementById('fileList');
        this.#seedFileList = document.getElementById('seedFileList');

        this.#listeners();
    }

    #handleFiles(files) {
        if (files.length + this.#uploadedFiles.length > this.#options.maxFiles) {
            if (this.#options.onAddError) {
                this.#options.onAddError('Maximum file limit exceeded. Max : ' + this.#options.maxFiles);
                console.error('Maximum file limit exceeded');
            }

            return;
        }

        // Add new files to the selectedFiles array
        for (const file of files) {
            if (this.#options.allowedTypes !== '*' && !file.type.match(this.#options.allowedTypes)) {
                if (this.#options.onAddError) {
                    this.#options.onAddError(`File type not allowed: ${file.type}`);
                }
                console.error(`File type not allowed: ${file.type}`);
                continue;
            }
            this.#uploadedFiles.push(file);
        }

        if (this.#options.onFileAdd) {
            this.#options.onFileAdd(files, this.#uploadedFiles);
        }

        this.#renderFileList();
    }

    #listeners() {
        this.#fileInput.addEventListener('change', event => {
            this.#handleFiles(event.target.files);
        });

        const fileList = this.#fileList;

        fileList.addEventListener('click', e => {
            // Check if the clicked element or its parent is a remove button
            const removeBtn = e.target.closest('.remove-selected');
            if (removeBtn) {
                const fileItem = e.target.closest('.file-info-item');
                if (fileItem) {
                    const index = parseInt(fileItem.dataset.index);
                    if (this.#options.onFileRemove) {
                        this.#options.onFileRemove(this.#uploadedFiles[index], this.#uploadedFiles);
                    } else {
                        this.#uploadedFiles.splice(index, 1);
                    }
                    this.#renderFileList();
                }
            }
        });

        const seedFileList = this.#seedFileList;
        seedFileList.addEventListener('click', e => {
            const rmExisting = e.target.closest('.remove-existing');
            if (rmExisting) {
                const fileItem = e.target.closest('.file-info-item');
                if (fileItem) {
                    const index = parseInt(fileItem.dataset.index);
                    if (this.#options.onSeededRemove) {
                        this.#options.onSeededRemove(this.#seededFiles[index], this.#seededFiles);
                    } else {
                        this.#seededFiles.splice(index, 1);
                    }
                    this.#renderFileList();
                }
            }
        });

        const fileUploadBox = this.#options.element;

        // Drag events for the drop zone
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileUploadBox.addEventListener(
                eventName,
                e => {
                    e.preventDefault();
                    e.stopPropagation();
                },
                false
            );
        });

        fileUploadBox.addEventListener('dragenter', () => {
            fileUploadBox.classList.add('drag-over');
        });

        fileUploadBox.addEventListener('dragleave', () => {
            fileUploadBox.classList.remove('drag-over');
        });

        fileUploadBox.addEventListener('drop', e => {
            fileUploadBox.classList.remove('drag-over');
            const files = e.dataTransfer.files;
            this.#handleFiles(files);
        });
    }

    #draw() {
        const options = this.#options;
        const inputs = `
            <input type="file" id="fileInput" class="form-control-file" ${options.maxFiles > 1 ? 'multiple' : ''
            } accept="${options.allowedTypes}">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="#d9dee3" viewBox="0 0 24 24">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm0 14v4l-4-4H8l4-4 4 4zm-2-6V4.414L15.586 8H12z"/>
            </svg>
            <p class="mt-3 text-secondary">
                Drag and drop your files here or
                <a href="javascript:void(0)" class="text-decoration-none">browse</a>
            </p>
        `;

        const fileInfoContainer = `
            <div id="fileInfoContainer" class="file-info-container" style="display: none;">
                <h6 class="fw-semibold">Selected Files:</h6>
                <div id="fileList"></div>
            </div>

            <div id="seedFileInfoContainer" class="file-info-container" style="display: none;">
                <h6 class="fw-semibold">Exisiting Files:</h6>
                <div id="seedFileList"></div>
            </div>
        `;

        this.#options.element.innerHTML = inputs;
        this.#options.element.onclick = e => {
            e.stopPropagation();
            document.getElementById('fileInput').click();
        };

        this.#options.element.insertAdjacentHTML('afterend', fileInfoContainer);
    }

    #renderFileList(renderFor = 'uploaded') {
        const ids = {
            uploaded: {
                container: 'fileInfoContainer',
                list: 'fileList',
                selectedFiles: this.#uploadedFiles,
                fileList: this.#fileList
            },
            seeded: {
                container: 'seedFileInfoContainer',
                list: 'seedFileList',
                selectedFiles: this.#seededFiles,
                fileList: this.#seedFileList
            }
        }[renderFor];

        const fileListContainer = document.getElementById(ids.list);
        const fileInfoContainer = document.getElementById(ids.container);
        const fileList = ids.fileList;
        fileListContainer.innerHTML = '';
        if (ids.selectedFiles.length > 0) {
            fileInfoContainer.style.display = 'block';

            ids.selectedFiles.forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-info-item';
                fileItem.dataset.index = index; // Add a data attribute to identify the file

                const fileIcon = document.createElement('div');

                const fileInfoText = document.createElement('div');
                fileInfoText.className = 'file-info-text';
                fileInfoText.innerHTML = `
                            <p class="mb-0 fw-medium">${file.name}</p>
                            <small class="text-muted">${(file.size / 1024).toFixed(2)} KB</small>
                        `;

                // Add a preview for image files
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.alt = 'Preview';
                        fileIcon.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                } else {
                    // Placeholder icon for non-image files
                    fileIcon.innerHTML = `
                                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="#88909c" viewBox="0 0 24 24">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                </svg>
                            `;
                }

                // Add the remove button
                const removeBtn = document.createElement('button');
                removeBtn.className =
                    renderFor === 'uploaded' ? 'remove-btn remove-selected' : 'remove-btn remove-existing';
                removeBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                        </svg>`;

                fileItem.appendChild(fileIcon);
                fileItem.appendChild(fileInfoText);
                fileItem.appendChild(removeBtn);
                fileList.appendChild(fileItem);
            });
        } else {
            fileInfoContainer.style.display = 'none';
        }
    }

    /**
     * File that has been uploaded
     *
     * @returns {File[]} files
     */
    getFiles() {
        return this.#uploadedFiles;
    }

    clearFiles() {
        this.#uploadedFiles = [];
        this.#seededFiles = [];

        this.#renderFileList();
        this.#renderFileList('seeded');
    }

    setSeededFiles(files) {
        if (!files) {
            return;
        }

        // Ensure #seededFiles is initialized
        if (!this.#seededFiles) {
            this.#seededFiles = [];
        }

        if (Array.isArray(files)) {
            this.#seededFiles.push(...files);
        } else {
            this.#seededFiles.push(files);
        }

        this.#renderFileList('seeded');
    }

    setError(message) {
        let feedback = document.createElement('div');
        feedback.classList.add('invalid-feedback');
        feedback.innerHTML = message;
        this.#options.element.classList.add('is-invalid');
        this.#options.element.after(feedback);
    }
}
