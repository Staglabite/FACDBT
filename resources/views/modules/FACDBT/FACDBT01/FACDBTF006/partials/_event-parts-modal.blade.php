<div class="modal modal-lg fade" id="event-parts-modal" tabindex="-1" aria-labelledby="event-parts-title"
    aria-modal="true" role="dialog">
    <div class="modal-dialog" tabindex="-1">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title w-100 pb-5 text-white" id="event-parts-title">Pre-Taking Part List</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-4">
                    Taking Date <b id="event-parts-date">-</b>: <b id="event-parts-count">0</b> part.
                    <a href="javascript:void(0)" id="event-parts-export" class="ms-2">
                        <i class="icon-base ti tabler-download"></i> Export part list
                    </a>
                </p>
                <form id="event-parts-form" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="event-parts-file" class="form-label">Select File (CSV / Excel, column "Part No")</label>
                        <input type="file" class="form-control" id="event-parts-file" name="file"
                            accept=".xlsx, .xls, .csv" required>
                        <div class="form-text">Upload akan <b>mengganti</b> seluruh daftar part event ini.</div>
                    </div>
                </form>
                <ul class="text-danger small d-none" id="event-parts-errors"></ul>
                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-primary" id="btn-submit-event-parts">
                        <i class="icon-base ti tabler-upload"></i> Upload
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
