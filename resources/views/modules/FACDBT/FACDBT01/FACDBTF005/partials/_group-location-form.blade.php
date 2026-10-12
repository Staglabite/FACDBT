<div class="modal modal-xl fade" id="group-location-modal" tabindex="-1" aria-labelledby="group-location-title"
    aria-modal="true" role="dialog">

    <div class="modal-dialog" tabindex="-1">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title w-100 pb-5 text-white" id="group-location-title">
                    Group Location Form
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close modal"></button>
            </div>

            <div class="modal-body">
                <form id="group-location-form">
                    @csrf
                    <div class="row">

                        <div class="col-12 col-lg-6">

                            <!-- Location No -->
                            <div class="mb-3">
                                <label for="LOCATION_NO" class="form-label">Location No</label>
                                <input type="text" class="form-control" id="LOCATION_NO" name="LOCATION_NO" maxlength="50"
                                    required aria-required="true" autofocus>
                            </div>

                            <!-- ID Loc -->
                            <div class="mb-3">
                                <label for="ID_LOC" class="form-label">ID Loc</label>
                                <input type="text" class="form-control" id="ID_LOC" name="ID_LOC" maxlength="50"
                                    required aria-required="true">
                            </div>

                        </div>

                        <div class="col-12 col-lg-6">

                            <!-- Location Group -->
                            <div class="mb-3">
                                <label for="location_group" class="form-label">Location Group</label>
                                <input type="text" class="form-control" id="location_group" name="location_group"
                                    maxlength="50" required aria-required="true">
                            </div>

                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer border-top pt-8 pb-8 px-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                    aria-label="Cancel">Cancel</button>

                <button type="submit" form="group-location-form" class="btn btn-primary"
                    id="btn-save">Submit</button>
            </div>

        </div>
    </div>
</div>
