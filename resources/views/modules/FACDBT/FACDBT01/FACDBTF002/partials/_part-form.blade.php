<div class="modal modal-xl fade" id="part-modal" tabindex="-1" aria-labelledby="part-title" aria-modal="true"
    role="dialog">

    <div class="modal-dialog" tabindex="-1">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title w-100 pb-5 text-white" id="part-title">
                    Part Form
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close modal"></button>
            </div>

            <div class="modal-body">
                <form id="part-form">
                    @csrf
                    <div class="row">

                        <!-- Kiri -->
                        <div class="col-12 col-lg-6">

                            <!-- Part No -->
                            <div class="mb-3">
                                <label for="part_no" class="form-label">Part No</label>
                                <input type="text" class="form-control" id="part_no" name="part_no" required
                                    aria-required="true" autofocus>
                            </div>

                            <!-- Inventory Part Description -->
                            <div class="mb-3">
                                <label for="inventory_part_description" class="form-label">Inventory Part Description</label>
                                <input type="text" class="form-control" id="inventory_part_description" name="inventory_part_description">
                            </div>

                            <!-- Accounting Group -->
                            <div class="mb-3">
                                <label for="accounting_group" class="form-label">Accounting Group</label>
                                <input type="text" class="form-control" id="accounting_group" name="accounting_group">
                            </div>

                            <!-- Stream -->
                            <div class="mb-3">
                                <label for="stream" class="form-label">Stream</label>
                                <input type="text" class="form-control" id="stream" name="stream">
                            </div>

                        </div>

                        <!-- Kanan -->
                        <div class="col-12 col-lg-6">

                            <!-- Seg -->
                            <div class="mb-3">
                                <label for="seg" class="form-label">Seg</label>
                                <input type="text" class="form-control" id="seg" name="seg">
                            </div>

                            <!-- Prod Group -->
                            <div class="mb-3">
                                <label for="prod_group" class="form-label">Prod Group</label>
                                <input type="text" class="form-control" id="prod_group" name="prod_group">
                            </div>

                            <!-- Part Group -->
                            <div class="mb-3">
                                <label for="part_group" class="form-label">Part Group</label>
                                <input type="text" class="form-control" id="part_group" name="part_group">
                            </div>

                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer border-top pt-8 pb-8 px-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                    aria-label="Cancel">Cancel</button>

                <button type="submit" form="part-form" class="btn btn-primary"
                    id="btn-save">Submit</button>
            </div>

        </div>
    </div>
</div>