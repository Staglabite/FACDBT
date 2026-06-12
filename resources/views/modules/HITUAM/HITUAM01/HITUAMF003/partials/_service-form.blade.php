<div class="modal modal-lg fade" id="service-modal" tabindex="-1" aria-labelledby="service-title" aria-modal="true"
    role="dialog">

    <div class="modal-dialog" tabindex="-1">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title w-100 pb-5 text-white" id="service-title">
                    Service Form
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close modal"></button>
            </div>

            <div class="modal-body">
                <form id="service-form">
                    @csrf
                    <div class="row mb-3">
                        <div class="col-12 col-md-6">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <i class="invalid-feedback"></i>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="description" name="description" required>
                            <i class="invalid-feedback"></i>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12 col-md-6">
                            <label for="url" class="form-label">Url</label>
                            <input type="text" class="form-control" id="url" name="url" required>
                            <i class="invalid-feedback"></i>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="method" class="form-label">Method</label>
                            <input type="text" class="form-control" id="method" name="method" required>
                            <i class="invalid-feedback"></i>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12 col-md-6">
                            <label for="begin" class="form-label">Begin Eff <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="begin" name="begin" required>
                            <i class="invalid-feedback"></i>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="end" class="form-label">End Eff <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="end" name="end" required>
                            <i class="invalid-feedback"></i>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12 col-md-6">
                            <label for="application" class="form-label">Application</label>
                            <select name="application" id="application" class="form-select" required></select>
                            <i class="invalid-feedback"></i>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="menu" class="form-label">Menu</label>
                            <select name="menu" id="menu" class="form-select" required></select>
                            <i class="invalid-feedback"></i>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer border-top pt-8 pb-8 px-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                    aria-label="Cancel">Cancel</button>

                <button type="submit" form="service-form" class="btn btn-primary"
                    id="btn-save">Submit</button>
            </div>

        </div>
    </div>
</div>
