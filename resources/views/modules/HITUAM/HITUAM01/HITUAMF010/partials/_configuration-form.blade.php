<div class="modal fade" id="configuration-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title w-100 pb-5 text-white" id="application-title">
                    Application Form
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close modal"></button>
            </div>
            <div class="modal-body">
                <form id="configuration-form">
                    @csrf
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="variable">Variable</label>
                            <input class="form-control" type="text" name="variable" id="variable" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="value">Value</label>
                            <input class="form-control" placeholder="Type and press Enter" type="text" name="value" id="value">
                            <div id="tags-error" class="text-danger d-none">Please add at least one item.</div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="configuration-form" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>
</div>
