<div class="card">
    <div class="card-header">
        <h5 class="card-title">Users List</h5>
    </div>
    <div class="card-datatable">
        <div class="d-flex justify-content-between align-items-center my-5 flex-column flex-xl-row px-6">
            <div class="d-flex align-items-center gap-1 justify-content-center">
                <div class="d-flex align-items-center gap-2 justify-content-center">
                    @serve('HITUAMF005-Create')
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary d-flex align-items-center gap-2 px-2 py-2"
                                id="btn-create-user">
                                <i class="icon-base ti tabler-plus"></i>
                                Add
                            </button>
                        </div>
                    @endserve
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary d-flex align-items-center gap-2 py-2 px-3"
                            id="export-excel-user">
                            <i class="icon-base ti tabler-upload"></i> Export
                        </button>
                        <button type="button" class="btn btn-primary d-flex align-items-center gap-2 py-2 px-3"
                            id="import-excel-user">
                            <i class="icon-base ti tabler-download"></i> Import
                        </button>
                        <button type="button" class="btn btn-primary d-flex align-items-center gap-2 py-2 px-3"
                            id="download-template-user">
                            <i class="icon-base ti tabler-upload"></i> Download Template
                        </button>
                    </div>
                    @serve('HITUAMF005-Delete')
                        <button type="button" class="btn btn-danger waves-effect waves-effect d-none"
                            id="btn-delete-user-selected">
                            <i class="icon-base ti tabler-trash"></i>
                            <span>Delete</span>
                        </button>
                    @endserve
                </div>
            </div>
            <div class="d-flex gap-2 align-items-center flex-column flex-lg-row mt-2 mt-xxl-0">
                <select class="form-select form-select-sm mx-2" id="user-entries"
                    style="width: 70px; max-width: 70px; font-size: 1rem; padding: 0.25rem 0.5rem;">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="-1">All</option>
                </select>
                <div class="input-group" style="width: 100%; min-width: 200px;">
                    <span class="input-group-text border-end-0">
                        <i class="icon-base ti tabler-search"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" placeholder="Search"
                        aria-label="Search User Data" id="user-search-input">
                </div>
            </div>
        </div>

        {{ $userTable->table(['class' => 'table'], true) }}
    </div>
</div>
