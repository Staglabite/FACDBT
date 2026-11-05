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
                                <label for="VPARTNO" class="form-label">Part No</label>
                                <input type="text" class="form-control" id="VPARTNO" name="VPARTNO" required
                                    aria-required="true" autofocus>
                            </div>

                            <!-- Inventory Part Description -->
                            <div class="mb-3">
                                <label for="VPARTDESC" class="form-label">Description</label>
                                <input type="text" class="form-control" id="VPARTDESC" name="VPARTDESC">
                            </div>

                            <!-- Inventory UOM -->
                            <div class="mb-3">
                                <label for="VUOM" class="form-label">UOM</label>
                                <input type="text" class="form-control" id="VUOM" name="VUOM">
                            </div>

                            <!-- Accounting Group -->
                            <div class="mb-3">
                                <label for="VACCGROUP" class="form-label">Accounting Group</label>
                                <input type="text" class="form-control" id="VACCGROUP" name="VACCGROUP">
                            </div>

                        </div>

                        <!-- Kanan -->
                        <div class="col-12 col-lg-6">

                            <!-- On Hand Qty -->
                            <div class="mb-3">
                                <label for="NONHANDQTY" class="form-label">On Hand Qty</label>
                                <!-- Menggunakan type number untuk kolom qty/angka -->
                                <input type="number" step="any" class="form-control" id="NONHANDQTY" name="NONHANDQTY">
                            </div>

                            <!-- Material Cost -->
                            <div class="mb-3">
                                <label for="MMATCOST" class="form-label">Material Cost</label>
                                <input type="number" step="any" class="form-control" id="MMATCOST" name="MMATCOST">
                            </div>

                            <!-- Labor Cost -->
                            <div class="mb-3">
                                <label for="MLABCOST" class="form-label">Labor Cost</label>
                                <input type="number" step="any" class="form-control" id="MLABCOST" name="MLABCOST">
                            </div>

                            <!-- FOH Cost -->
                            <div class="mb-3">
                                <label for="MFOHCOST" class="form-label">FOH Cost</label>
                                <input type="number" step="any" class="form-control" id="MFOHCOST" name="MFOHCOST">
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