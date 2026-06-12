<div class="modal fade" id="user-modal" tabindex="-1" aria-labelledby="user-title" aria-modal="true" role="dialog">

    <div class="modal-dialog" tabindex="-1">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title w-100 pb-5 text-white" id="user-title">
                    User Form
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close modal"></button>
            </div>

            <div class="modal-body">
                <form id="user-form">
                    @csrf

                    <div class="row mb-3">
                        <div class="col-12 col-md-6">
                            <input type="radio" class="form-check-input" name="user_type" value="internal" checked>
                            <label for="user_type">Internal</label>
                        </div>
                        {{-- <div class="col-12 col-md-6">
                            <input type="radio" class="form-check-input" name="user_type" value="external">
                            <label for="user_type">Eksternal</label>
                        </div> --}}
                    </div>

                    <div class="row mb-3 d-none" id="supplier-input-group">
                        <div class="col-12 col-md-6">
                            <label for="supplier" class="form-label">Supplier</label>
                            <select name="supplier" id="supplier" class="form-control"></select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="user_supplier" class="form-label">User Supplier</label>
                            <select name="user_supplier" id="user_supplier" class="form-control" disabled></select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <!-- <div class="col-12 col-md-6">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="nameId" name="name" required
                                aria-required="true" autofocus>
                        </div> -->
                        <div class="col-12 col-md-6">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required
                                aria-required="true">
                            <span class="form-text text-danger" id="message-error"></span>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="npk" class="form-label">NPK</label>
                            <input type="text" class="form-control" id="npk" name="npk" required
                                aria-required="true">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12 col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="text" class="form-control" id="email" name="email" required
                                aria-required="true" autofocus>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12 col-md-6">
                            <label for="role" class="form-label">Role</label>
                            <select name="role[]" id="role" class="form-select" multiple
                                aria-required="true"></select>
                        </div>
                    </div>

                </form>
            </div>

            <div class="modal-footer border-top pt-8 pb-8 px-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                    aria-label="Cancel">Cancel</button>

                <button type="submit" form="user-form" class="btn btn-primary" id="btn-save-user">Submit</button>
            </div>

        </div>
    </div>
</div>
