<div class="modal modal-xl fade" id="location-modal" tabindex="-1" aria-labelledby="location-title" aria-modal="true"
    role="dialog">

    <div class="modal-dialog" tabindex="-1">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title w-100 pb-5 text-white" id="location-title">
                    Location Form
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close modal"></button>
            </div>

            <div class="modal-body">
                <form id="location-form">
                    @csrf
                    <div class="row">

                        <div class="col-12 col-lg-6">

                            <!-- Location No -->
                            <div class="mb-3">
                                <label for="VLOCATIONNO" class="form-label">Location No</label>
                                <input type="text" class="form-control" id="VLOCATIONNO" name="VLOCATIONNO" required
                                    aria-required="true" autofocus>
                            </div>

                            <!-- Location Group -->
                            <div class="mb-3">
                                <label for="VLOCGROUP" class="form-label">Location Group</label>
                                <input type="text" class="form-control" id="VLOCGROUP" name="VLOCGROUP">
                            </div>

                            <!-- Site -->
                            <div class="mb-3">
                                <label for="VSITE" class="form-label">Site</label>
                                <input type="text" class="form-control" id="VSITE" name="VSITE">
                            </div>

                        </div>

                        <div class="col-12 col-lg-6">

                            <!-- Warehouse -->
                            <div class="mb-3">
                                <label for="VWAREHOUSE" class="form-label">Warehouse</label>
                                <input type="text" class="form-control" id="VWAREHOUSE" name="VWAREHOUSE">
                            </div>

                            <!-- Location Description -->
                            <div class="mb-3">
                                <label for="VLOCDESC" class="form-label">Location Description</label>
                                <input type="text" class="form-control" id="VLOCDESC" name="VLOCDESC">
                            </div>

                            <!-- Location Group Description -->
                            <div class="mb-3">
                                <label for="VLOCGROUPDESC" class="form-label">Location Group Description</label>
                                <input type="text" class="form-control" id="VLOCGROUPDESC" name="VLOCGROUPDESC">
                            </div>

                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer border-top pt-8 pb-8 px-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                    aria-label="Cancel">Cancel</button>

                <button type="submit" form="location-form" class="btn btn-primary"
                    id="btn-save">Submit</button>
            </div>

        </div>
    </div>
</div>