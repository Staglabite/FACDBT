<div class="modal modal-xl fade" id="unitcost-modal" tabindex="-1" aria-labelledby="unitcost-title" aria-modal="true"
    role="dialog">

    <div class="modal-dialog" tabindex="-1">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title w-100 pb-5 text-white" id="unitcost-title">
                    Unit Cost Form
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close modal"></button>
            </div>

            <div class="modal-body">
                <form id="unitcost-form">
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
                                <label for="inventory_part_description" class="form-label">Description</label>
                                <input type="text" class="form-control" id="inventory_part_description" name="inventory_part_description">
                            </div>

                            <!-- Inventory UOM -->
                            <div class="mb-3">
                                <label for="inventory_uom" class="form-label">UOM</label>
                                <input type="text" class="form-control" id="inventory_uom" name="inventory_uom">
                            </div>

                            <!-- Accounting Group -->
                            <div class="mb-3">
                                <label for="accounting_group" class="form-label">Accounting Group</label>
                                <input type="text" class="form-control" id="accounting_group" name="accounting_group">
                            </div>

                        </div>

                        <!-- Kanan -->
                        <div class="col-12 col-lg-6">

                            <!-- On Hand Qty -->
                            <div class="mb-3">
                                <label for="on_hand_qty" class="form-label">On Hand Qty</label>
                                <!-- Menggunakan type number untuk kolom qty/angka -->
                                <input type="number" step="any" class="form-control" id="on_hand_qty" name="on_hand_qty">
                            </div>

                            <!-- Material Cost -->
                            <div class="mb-3">
                                <label for="mat_cost" class="form-label">Material Cost</label>
                                <input type="number" step="any" class="form-control" id="mat_cost" name="mat_cost">
                            </div>

                            <!-- Labor Cost -->
                            <div class="mb-3">
                                <label for="lab_cost" class="form-label">Labor Cost</label>
                                <input type="number" step="any" class="form-control" id="lab_cost" name="lab_cost">
                            </div>

                            <!-- FOH Cost -->
                            <div class="mb-3">
                                <label for="foh_cost" class="form-label">FOH Cost</label>
                                <input type="number" step="any" class="form-control" id="foh_cost" name="foh_cost">
                            </div>

                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer border-top pt-8 pb-8 px-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                    aria-label="Cancel">Cancel</button>

                <button type="submit" form="unitcost-form" class="btn btn-primary"
                    id="btn-save">Submit</button>
            </div>

        </div>
    </div>
</div>