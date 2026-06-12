<?php

namespace App\Http\Controllers\Original\HITUAM\HITUAM01;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\DataTables\Original\HITUAM01\HITUAMF011DataTable as NewsTable;
use App\Helpers\Response;
use App\Http\Requests\HITUAM\HITUAM01\NewsRequest;
use App\Models\HITUAM01\HITUAM_MSHNEWS as News;
// use App\Services\FACTWM\SupplierService;
use App\Services\HITUAM\SupplierService;
use App\Services\HITUAM\NewsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HITUAMF011 extends Controller
{
    // public function __construct(private SupplierService $supplierService, private NewsService $newsService) {}
    public function __construct(private NewsService $newsService) {}
    /**
     * Display a listing of the resource.
     */
    public function index(NewsTable $dataTable)
    {
        return $dataTable->render('modules.HITUAM.HITUAM01.HITUAMF011.HITUAMF011');
    }

    public function storeForm($news = 0)
    {
        $newsData = $news ? News::findOrFail($news) : null;
        // $vendorData = $this->supplierService->getAllSuppliers(request: request());

        // // Mapping: Convert AVIEWERS jika sama dengan semua vendor
        // if ($newsData && $newsData->AVIEWERS) {
        //     $selectedVendors = is_array($newsData->AVIEWERS)
        //         ? $newsData->AVIEWERS
        //         : explode(',', $newsData->AVIEWERS);

        //     // Ambil semua ID vendor untuk perbandingan
        //     $allVendorIds = $vendorData->pluck('IID')->toArray();

        //     // Cek apakah jumlah selected vendors sama dengan total vendors
        //     // DAN semua ID vendor ada di selected vendors
        //     if (count($selectedVendors) === count($allVendorIds)) {
        //         // Sort untuk memastikan perbandingan akurat
        //         sort($selectedVendors);
        //         sort($allVendorIds);

        //         // Jika sama persis, replace dengan "all"
        //         if ($selectedVendors === $allVendorIds) {
        //             $newsData->AVIEWERS = ['all'];
        //         }
        //     }
        // }

        // return view('modules.HITUAM.HITUAM01.HITUAMF011.partials._news-form', compact('newsData', 'vendorData'));
        return view('modules.HITUAM.HITUAM01.HITUAMF011.partials._news-form', compact('newsData'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(NewsRequest $request)
    {
        $data = $request->validated();
        DB::transaction(function () use ($data, $request) {
            //vendor handling
            // $publishToVendor = $data['publish_to_vendor'] ?? [];
            // if (!in_array('all', $publishToVendor)) {
            //     $data['publish_to_vendor'] = $publishToVendor;
            // } else {
            //     $data['publish_to_vendor'] = $this->supplierService->getAllSuppliers(request: request())->pluck('IID')->toArray();
            // }
            //file handling
            if ($request->hasFile('upload_file')) {
                $data['upload_file'] = $this->newsService->uploadFile($request->file('upload_file'), 'file');
            } else {
                $data['upload_file'] = null;
            }
            //image handling
            if ($request->hasFile('upload_foto')) {
                $data['upload_foto'] = $this->newsService->uploadFile($request->file('upload_foto'), 'image');
            } else {
                $data['upload_foto'] = null;
            }

            $mappingData = $this->newsService->mappingNewsData($data);
            $mappingData['VCREA'] = Auth::user()->VUSERNAME ?? 'SYSTEM';
            $mappingData['DCREA'] = now();
            News::create($mappingData);
            return Response::success(message: 'News Create successfully');
        });
    }

    /**
     * Display the specified resource.
     */
    public function show($slug)
    {
        $news = News::where('VSUBJECT', $slug)->firstOrFail();
        return view('modules.HITUAM.HITUAM01.HITUAMF011.partials._news-detail', compact('news'));
    }

    /**
     * Show the news By Id
     */
    public function showNewsById($id)
    {
        return News::where('IID', $id)->firstOrFail();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(NewsRequest $request, string $id)
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request, $id) {
            $updateData = News::where('IID', $id);
            $existingNews = $updateData->first();

            //vendor handling
            // $publishToVendor = $data['publish_to_vendor'] ?? [];
            // if (!in_array('all', $publishToVendor)) {
            //     $data['publish_to_vendor'] = $publishToVendor;
            // } else {
            //     $data['publish_to_vendor'] = $this->supplierService->getAllSuppliers(request: request())->pluck('IID')->toArray();
            // }

            // -------------------------------------------------------
            // File handling
            // -------------------------------------------------------
            if ($request->hasFile('upload_file')) {
                // User upload file baru → hapus file lama (jika ada), simpan file baru
                if ($existingNews->VFILE_PATH) {
                    $this->newsService->deleteFile($existingNews->VFILE_PATH, 'file');
                }
                $data['upload_file'] = $this->newsService->uploadFile($request->file('upload_file'), 'file');
            } elseif (($data['delete_file'] ?? '0') === '1') {
                // User menghapus file existing tanpa upload baru → hapus file dari storage
                if ($existingNews->VFILE_PATH) {
                    $this->newsService->deleteFile($existingNews->VFILE_PATH, 'file');
                }
                $data['upload_file'] = null;
            } else {
                // Tidak ada perubahan → pertahankan file lama
                $data['upload_file'] = $existingNews->VFILE_PATH;
            }

            // -------------------------------------------------------
            // Image handling
            // -------------------------------------------------------
            if ($request->hasFile('upload_foto')) {
                // User upload foto baru → hapus foto lama (jika ada), simpan foto baru
                if ($existingNews->VIMAGE_PATH) {
                    $this->newsService->deleteFile($existingNews->VIMAGE_PATH, 'image');
                }
                $data['upload_foto'] = $this->newsService->uploadFile($request->file('upload_foto'), 'image');
            } elseif (($data['delete_foto'] ?? '0') === '1') {
                // User menghapus foto existing tanpa upload baru → hapus foto dari storage
                if ($existingNews->VIMAGE_PATH) {
                    $this->newsService->deleteFile($existingNews->VIMAGE_PATH, 'image');
                }
                $data['upload_foto'] = null;
            } else {
                // Tidak ada perubahan → pertahankan foto lama
                $data['upload_foto'] = $existingNews->VIMAGE_PATH;
            }

            $mappingData = $this->newsService->mappingNewsData($data);
            $mappingData['VMODI'] = Auth::user()->VUSERNAME ?? 'SYSTEM';
            $mappingData['DMODI'] = now();
            $updateData->update($mappingData);
            return Response::success(message: 'News Update successfully');
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        DB::transaction(function () use ($id) {
            $news = News::where('IID', $id)->first();
            //delete file
            if ($news->VFILE_PATH) {
                $this->newsService->deleteFile($news->VFILE_PATH, 'file');
            }
            //delete image
            if ($news->VIMAGE_PATH) {
                $this->newsService->deleteFile($news->VIMAGE_PATH, 'image');
            }
            //delete record
            $news->delete();
            return Response::success(message: 'News Delete successfully');
        });
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);

        DB::transaction(function () use ($ids) {
            $newsItems = News::whereIn('IID', $ids)->get();
            foreach ($newsItems as $news) {
                //delete file
                if ($news->VFILE_PATH) {
                    $this->newsService->deleteFile($news->VFILE_PATH, 'file');
                }
                //delete image
                if ($news->VIMAGE_PATH) {
                    $this->newsService->deleteFile($news->VIMAGE_PATH, 'image');
                }
                //delete record
                $news->delete();
            }
            return Response::success(message: 'Selected News deleted successfully');
        });
    }
}
