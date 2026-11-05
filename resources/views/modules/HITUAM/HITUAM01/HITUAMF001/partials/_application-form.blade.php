<div class="modal modal-xl fade" id="application-modal" tabindex="-1" aria-labelledby="application-title" aria-modal="true"
    role="dialog">

    <div class="modal-dialog" tabindex="-1">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title w-100 pb-5 text-white" id="application-title">
                    Application Form
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close modal"></button>
            </div>

            <div class="modal-body">
                <form id="application-form">
                    @csrf
                    <div class="row">

                        <div class="col-12 col-lg-6">

                            <!-- Project Code -->
                            <div class="mb-3">
                                <label for="code" class="form-label">Project Code</label>
                                <input type="text" class="form-control" id="code" name="code" required
                                    aria-required="true" autofocus>
                            </div>

                            <!-- Project Description -->
                            <div class="mb-3">
                                <label for="desc" class="form-label">Project Description</label>
                                <input type="text" class="form-control" id="desc" name="desc" required
                                    aria-required="true">
                            </div>

                            <div class="mb-3">
                                <label for="prefix" class="form-label">Project Prefix</label>
                                <input type="text" class="form-control" id="prefix" name="prefix">
                            </div>

                            <div class="mb-3">
                                <label for="pic" class="form-label">PIC</label>
                                <input type="text" class="form-control" id="pic" name="pic">
                            </div>

                            <div class="mb-3">
                                <label for="portal" class="form-label">Portal Name</label>
                                <input type="text" class="form-control" id="portal" name="portal">
                            </div>

                            <div class="mb-3">
                                <label for="operational" class="form-label">Operational</label>
                                <input type="text" class="form-control" id="operational" name="operational">
                            </div>

                            <div class="mb-3">
                                <label for="std" class="form-label">Standardization</label>
                                <input type="text" class="form-control" id="std" name="std">
                            </div>

                            <div class="mb-3">
                                <label for="portal_access" class="form-label">Portal Access</label>
                                <input type="text" class="form-control" id="portal_access" name="portal_access">
                            </div>

                        </div>

                        <div class="col-12 col-lg-6">

                            <div class="mb-3">
                                <label for="host" class="form-label">Host</label>
                                <input type="text" class="form-control" id="host" name="host">
                            </div>

                            <div class="mb-3">
                                <label for="publish" class="form-label">Publish</label>
                                <input type="text" class="form-control" id="publish" name="publish">
                            </div>

                            <div class="mb-3">
                                <label for="database" class="form-label">Database</label>
                                <input type="text" class="form-control" id="database" name="database">
                            </div>

                            <div class="mb-3">
                                <label for="order" class="form-label">Order</label>
                                <input type="number" min="0" class="form-control" id="order"
                                    name="order">
                            </div>

                            <div class="mb-3">
                                <label for="icon" class="form-label">Icon</label>
                                <input type="text" class="form-control" id="icon" name="icon"
                                    artia-describedby="icon-control-help" required>
                                <div id="icon-control-help" class="form-text">Copy icon name from <a
                                        href="https://tabler.io/icons" target="_blnk">tabler Icons</a></div>
                            </div>

                            <!-- Radio Group with Fieldset -->
                            <fieldset class="mt-3 d-none" aria-describedby="embedded-desc" aria-hidden="true">
                                <legend class="form-label">Application Type</legend>
                                <div class="row">

                                    <div class="col-md mb-md-0 mb-5">
                                        <div class="form-check custom-option custom-option-basic">
                                            <label class="form-check-label custom-option-content" for="isEmbedded">
                                                <input name="is_embedded" class="form-check-input" type="radio"
                                                    value="true" id="isEmbedded" checked />
                                                <span class="custom-option-header">
                                                    <span class="h6 mb-0">Embedded</span>
                                                </span>
                                                <span class="custom-option-body" id="embedded-desc">
                                                    <small>Same project as User Access Management</small>
                                                </span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-md">
                                        <div class="form-check custom-option custom-option-basic">
                                            <label class="form-check-label custom-option-content" for="isNotEmbedded">
                                                <input name="is_embedded" class="form-check-input" type="radio"
                                                    value="false" id="isNotEmbedded" />
                                                <span class="custom-option-header">
                                                    <span class="h6 mb-0">Separate</span>
                                                </span>
                                                <span class="custom-option-body">
                                                    <small>Separate application entirely or another system</small>
                                                </span>
                                            </label>
                                        </div>
                                    </div>

                                </div>
                            </fieldset>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer border-top pt-8 pb-8 px-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                    aria-label="Cancel">Cancel</button>

                <button type="submit" form="application-form" class="btn btn-primary"
                    id="btn-save">Submit</button>
            </div>

        </div>
    </div>
</div>
