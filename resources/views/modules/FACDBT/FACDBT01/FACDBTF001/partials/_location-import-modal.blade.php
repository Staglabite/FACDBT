<div class="modal modal-lg fade" id="location-import-modal" tabindex="-1" aria-labelledby="location-import-title"
    aria-modal="true" role="dialog">
    <div class="modal-dialog" tabindex="-1">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title w-100 pb-5 text-white" id="location-import-title">
                    Location Import
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close modal"></button>
            </div>
            <div class="modal-body">
                <!-- ID diubah menjadi location-import -->
                <form id="location-import" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="location-import-file" class="form-label">Select Excel File</label>
                        <input type="file" class="form-control" id="location-import-file" name="file"
                            accept=".xlsx, .xls, .csv" required>
                        <i class="invalid-feedback"></i>
                    </div>
                </form>
                <div class="d-flex justify-content-end">
                    <!-- ID diubah menjadi btn-submit-location-import -->
                    <button type="submit" class="btn btn-primary" id="btn-submit-location-import">
                        <i class="icon-base ti tabler-upload"></i> Submit
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>