<div class="modal modal-lg fade" id="menu-modal" tabindex="-1" aria-labelledby="menu-title" aria-modal="true" role="dialog">

    <div class="modal-dialog" tabindex="-1">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title w-100 pb-5 text-white" id="menu-title">
                    Menu Form
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close modal"></button>
            </div>

            <div class="modal-body">
                <form id="menu-form">
                    @csrf
                    <div class="row mb-3">
                        <div class="col-12 col-md-6">
                            <label for="app_id" class="form-label">App ID</label>
                            <input type="text" class="form-control" id="app_id" name="app_id" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12 col-md-6">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="description" name="description" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="flag" class="form-label">Flag</label>
                            <select name="flag" id="flag" class="form-select">
                                <option value="Basic Data">Basic Data</option>
                                <option value="Transactions">Transactions</option>
                                <option value="Report">Report</option>
                                <option value="Dashboard">Dashboard</option>
                                {{-- <option value="Settings">Settings</option> --}}
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12 col-md-6">
                            <label for="url" class="form-label">Url</label>
                            <input type="text" class="form-control" id="url" name="url" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="icon" class="form-label">Icon</label>
                            <input type="text" class="form-control" id="icon" name="icon" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12 col-md-6">
                            <label for="order" class="form-label">Order</label>
                            <input type="text" class="form-control" id="order" name="order" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="parent" class="form-label">Parent</label>
                            <select name="parent" id="parent" class="form-select"></select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12 col-md-6">
                            <label for="application" class="form-label">Application</label>
                            <select name="application" id="application" class="form-select" required></select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="type" class="form-label">Type</label>
                            <input type="text" class="form-control" id="type" name="type" required>
                        </div>
                    </div>
                    <div class="row-mb-3">
                        <div class="col-12 col-md-6">
                            <label for="env_app" class="form-label">Env App</label>
                            <input type="text" class="form-control" id="env_app" name="env_app">
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer border-top pt-8 pb-8 px-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                    aria-label="Cancel">Cancel</button>

                <button type="submit" form="menu-form" class="btn btn-primary" id="btn-save">Submit</button>
            </div>

        </div>
    </div>
</div>
