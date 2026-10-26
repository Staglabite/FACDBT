<div class="modal modal-lg fade" id="event-modal" tabindex="-1" aria-labelledby="event-title" aria-modal="true"
    role="dialog">
    <div class="modal-dialog" tabindex="-1">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title w-100 pb-5 text-white" id="event-title">Event Form</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close modal"></button>
            </div>

            <div class="modal-body">
                <form id="event-form">
                    @csrf
                    <div class="row">
                        <div class="col-12 col-lg-6">
                            <!-- Periode taking (SIM_INV_TAKING_CONCLUDING_TAB) -->
                            <div class="mb-3">
                                <label for="TAKING_DATE" class="form-label">Taking Period</label>
                                <select class="form-select" id="TAKING_DATE" name="TAKING_DATE" required
                                    aria-required="true"></select>
                            </div>
                        </div>

                        <div class="col-12 col-lg-6">
                            <div class="mb-3">
                                <label for="EVENT_TYPE" class="form-label">Event</label>
                                <select class="form-select" id="EVENT_TYPE" name="EVENT_TYPE" required aria-required="true">
                                    <option value="">- Select Event -</option>
                                    @foreach ($types as $code => $name)
                                        <option value="{{ $code }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="mb-3">
                                <label for="DESCRIPTION" class="form-label">Description</label>
                                <input type="text" class="form-control" id="DESCRIPTION" name="DESCRIPTION" maxlength="255">
                            </div>
                            <div class="small text-muted d-none" id="event-pre-hint">
                                Setelah disimpan, upload daftar part Pre-Taking lewat tombol
                                <i class="icon-base ti tabler-list-details"></i> pada tabel.
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer border-top pt-8 pb-8 px-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Cancel">Cancel</button>
                <button type="submit" form="event-form" class="btn btn-primary" id="btn-save">Submit</button>
            </div>
        </div>
    </div>
</div>
