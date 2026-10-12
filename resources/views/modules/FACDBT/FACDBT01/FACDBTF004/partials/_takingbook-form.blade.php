<div class="modal modal-xl fade" id="takingbook-modal" tabindex="-1" aria-labelledby="takingbook-title" aria-modal="true"
    role="dialog">

    <div class="modal-dialog modal-dialog-scrollable" tabindex="-1">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title w-100 pb-5 text-white" id="takingbook-title">
                    Taking Book Form
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close modal"></button>
            </div>

            <div class="modal-body">
                <form id="takingbook-form">
                    @csrf
                    <div class="row">

                        <!-- Kolom Kiri: Primary Keys & Data Penting -->
                        <div class="col-12 col-lg-6">
                            
                            <h6 class="fw-bold mb-3 border-bottom pb-2">Primary Information</h6>

                            <div class="mb-3">
                                <label for="INV_LIST_NO" class="form-label">Inv List No</label>
                                <input type="text" class="form-control" id="INV_LIST_NO" name="INV_LIST_NO" required autofocus>
                            </div>

                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label for="SEQ" class="form-label">Seq</label>
                                    <input type="number" class="form-control" id="SEQ" name="SEQ" required>
                                </div>
                                <div class="col-3 mb-3">
                                    <label for="MONTH" class="form-label">Month</label>
                                    <input type="number" class="form-control" id="MONTH" name="MONTH" min="1" max="12" required>
                                </div>
                                <div class="col-3 mb-3">
                                    <label for="YEAR" class="form-label">Year</label>
                                    <input type="number" class="form-control" id="YEAR" name="YEAR" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="CONTRACT" class="form-label">Contract</label>
                                <input type="text" class="form-control" id="CONTRACT" name="CONTRACT" required>
                            </div>

                            <div class="mb-3">
                                <label for="PART_NO" class="form-label">Part No</label>
                                <input type="text" class="form-control" id="PART_NO" name="PART_NO" required>
                            </div>

                            <div class="mb-3">
                                <label for="LOCATION_NO" class="form-label">Location No</label>
                                <input type="text" class="form-control" id="LOCATION_NO" name="LOCATION_NO" required>
                            </div>

                            <div class="mb-3">
                                <label for="LOT_BATCH_NO" class="form-label">Lot/Batch No</label>
                                <input type="text" class="form-control" id="LOT_BATCH_NO" name="LOT_BATCH_NO" required>
                            </div>

                            <h6 class="fw-bold mb-3 mt-4 border-bottom pb-2">Quantity & Cost</h6>

                            <div class="row">
                                <div class="col-4 mb-3">
                                    <label for="QUANTITY" class="form-label">Quantity</label>
                                    <input type="number" step="any" class="form-control" id="QUANTITY" name="QUANTITY">
                                </div>
                                <div class="col-4 mb-3">
                                    <label for="UNIT_COST" class="form-label">Unit Cost</label>
                                    <input type="number" step="any" class="form-control" id="UNIT_COST" name="UNIT_COST">
                                </div>
                                <div class="col-4 mb-3">
                                    <label for="UNIT_MEAS" class="form-label">UoM</label>
                                    <input type="text" class="form-control" id="UNIT_MEAS" name="UNIT_MEAS">
                                </div>
                            </div>

                        </div>

                        <!-- Kolom Kanan: Detail Lainnya -->
                        <div class="col-12 col-lg-6">
                            
                            <h6 class="fw-bold mb-3 border-bottom pb-2">Location Details</h6>

                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label for="WAREHOUSE" class="form-label">Warehouse</label>
                                    <input type="text" class="form-control" id="WAREHOUSE" name="WAREHOUSE">
                                </div>
                                <div class="col-6 mb-3">
                                    <label for="BAY_NO" class="form-label">Bay No</label>
                                    <input type="text" class="form-control" id="BAY_NO" name="BAY_NO">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-4 mb-3">
                                    <label for="ROW_NO" class="form-label">Row No</label>
                                    <input type="text" class="form-control" id="ROW_NO" name="ROW_NO">
                                </div>
                                <div class="col-4 mb-3">
                                    <label for="TIER_NO" class="form-label">Tier No</label>
                                    <input type="text" class="form-control" id="TIER_NO" name="TIER_NO">
                                </div>
                                <div class="col-4 mb-3">
                                    <label for="BIN_NO" class="form-label">Bin No</label>
                                    <input type="text" class="form-control" id="BIN_NO" name="BIN_NO">
                                </div>
                            </div>

                            <h6 class="fw-bold mb-3 mt-4 border-bottom pb-2">Handling Unit & Status</h6>

                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label for="HANDLING_UNIT_ID" class="form-label">Handling Unit ID</label>
                                    <input type="number" class="form-control" id="HANDLING_UNIT_ID" name="HANDLING_UNIT_ID">
                                </div>
                                <div class="col-6 mb-3">
                                    <label for="HANDLING_UNIT_TYPE_ID" class="form-label">Handling Unit Type</label>
                                    <input type="text" class="form-control" id="HANDLING_UNIT_TYPE_ID" name="HANDLING_UNIT_TYPE_ID">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label for="NPK" class="form-label">NPK</label>
                                    <input type="text" class="form-control" id="NPK" name="NPK">
                                </div>
                                <div class="col-6 mb-3">
                                    <label for="VERIFIED" class="form-label">Verified</label>
                                    <select class="form-select" id="VERIFIED" name="VERIFIED">
                                        <option value="">-- Select --</option>
                                        <option value="TRUE">TRUE</option>
                                        <option value="FALSE">FALSE</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="WDR" class="form-label">WDR</label>
                                <input type="text" class="form-control" id="WDR" name="WDR">
                            </div>

                            <div class="mb-3">
                                <label for="AVAILABILITY_CONTROL_ID" class="form-label">Availability Control ID</label>
                                <input type="text" class="form-control" id="AVAILABILITY_CONTROL_ID" name="AVAILABILITY_CONTROL_ID">
                            </div>

                            <div class="mb-3">
                                <label for="AVAILABILITY_CONTROL_DESC" class="form-label">Availability Control Desc</label>
                                <input type="text" class="form-control" id="AVAILABILITY_CONTROL_DESC" name="AVAILABILITY_CONTROL_DESC">
                            </div>

                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer border-top pt-8 pb-8 px-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Cancel">Cancel</button>
                <button type="submit" form="takingbook-form" class="btn btn-primary" id="btn-save">Submit</button>
            </div>

        </div>
    </div>
</div>