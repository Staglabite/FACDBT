<div class="modal fade modal-xl" id="role-modal" tabindex="-1" aria-labelledby="user-title" aria-modal="true" role="dialog">

    <div class="modal-dialog" tabindex="-1">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title w-100 pb-5 text-white" id="role-title">
                    Role Form
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close modal"></button>
            </div>

            <div class="modal-body">
                <form id="role-form">
                    @csrf

                    <div class="row mb-3">
                        <div class="col-12 col-md-6">
                            <label for="name" class="form-label">Role Name</label>
                            <input type="text" class="form-control" id="name" name="name" required
                                aria-required="true" autofocus>
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="status"
                                    name="status" checked>
                                <label class="form-check-label" for="status">Active</label>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="description" name="description" required
                                aria-required="true" autofocus>
                        </div>
                    </div>

                    <div class="mb-3" id="menu-list">
                        <h5>Role Permission</h5>
                        <table class="table">
                            <colgroup>
                                <col style="width: 30%">
                            </colgroup>
                            <tbody>
                                <tr>
                                    <td>Administrator Access</td>
                                    <td style="text-align: right">
                                        <input type="checkbox" class="form-check-input me-1" id="admin-access" name="admin-access">
                                        <label for="admin-access" class="form-check-label">Select All</label>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>

            <div class="modal-footer border-top pt-8 pb-8 px-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                    aria-label="Cancel">Cancel</button>

                <button type="submit" form="role-form" class="btn btn-primary" id="btn-save-role">Submit</button>
            </div>

        </div>
    </div>
</div>