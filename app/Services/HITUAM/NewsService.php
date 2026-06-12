<?php

namespace App\Services\HITUAM;

use App\Models\HITUAM01\HITUAM_MSHNEWS;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class NewsService
{
    public function uploadFile($file, $type)
    {
        $fileName = time() . '_' . $file->getClientOriginalName();
        $file->storeAs('news/' . ($type === 'file' ? 'files' : 'images'), $fileName, 'public');
        return $fileName;
    }

    public function deleteFile($fileName, $type)
    {
        $filePath = 'news/' . ($type === 'file' ? 'files/' : 'images/') . $fileName;
        if (Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }
    }

    public function mappingNewsData($data)
    {
        $mapped = [
            'VTITLE' => $data['title'],
            'VSUBJECT' => $this->generateSlug($data['title']),
            // 'AVIEWERS' => $data['publish_to_vendor'],
            'VFILE_PATH' => $data['upload_file'],
            'VIMAGE_PATH' => $data['upload_foto'],
            'VCONTENT' => $data['content'],
            'BSTATUS' => $data['publish'] == 'true' ? true : false,
            'DPUBLISHED_AT' => $data['publish'] == 'true' ? now() : null,
            // 'VCREA' => Auth::user()->VUSERNAME ?? 'SYSTEM',
            // 'DCREA' => now(),
        ];

        return $mapped;
    }

    public function generateSlug($title)
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        return $slug;
    }

    /**
     * Get published news filtered by vendor access
     *
     * @param int|null $vendorId Optional vendor ID, if null will use logged in user's vendor_id
     * @param bool $isAdmin Optional flag to bypass vendor filter
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPublishedNews($vendorId = null, $isAdmin = false)
    {
        $query = HITUAM_MSHNEWS::where('BSTATUS', true)
            ->orderBy('DPUBLISHED_AT', 'desc');

        // if (!$isAdmin) {
        //     $vendorId = $vendorId ?? Auth::user()->vendor_id;

        //     $query->where(function ($q) use ($vendorId) {
        //         $intFormat = json_encode([$vendorId]);
        //         $strFormat = json_encode([strval($vendorId)]);


        //         $q->whereRaw('"AVIEWERS"::jsonb @> ?::jsonb', [$intFormat])
        //             ->orWhereRaw('"AVIEWERS"::jsonb @> ?::jsonb', [$strFormat]);
        //     });
        // }

        return $query->get();
    }

    /**
     * Get single published news by slug
     *
     * @param string $slug
     * @param int|null $vendorId
     * @param bool $isAdmin
     * @return \App\Models\HITUAM01\HITUAM_MSHNEWS|null
     */
    public function getNewsBySlug($slug, $vendorId = null, $isAdmin = false)
    {
        $query = HITUAM_MSHNEWS::where('BSTATUS', true)
            ->where('VSUBJECT', $slug);

        if (!$isAdmin) {
            $vendorId = $vendorId ?? Auth::user()->vendor_id;

            $query->where(function ($q) use ($vendorId) {
                $q->whereRaw("CAST(\"AVIEWERS\" AS jsonb) ? ?", [$vendorId])
                    ->orWhereRaw("CAST(\"AVIEWERS\" AS jsonb) @> ?", [json_encode([$vendorId])]);
            });
        }

        return $query->first();
    }

    /**
     * Increment total view count
     *
     * @param int $newsId
     * @return bool
     */
    public function incrementViewCount($newsId)
    {
        $news = HITUAM_MSHNEWS::find($newsId);

        if ($news) {
            $news->ITOTALVIEW = ($news->ITOTALVIEW ?? 0) + 1;
            return $news->save();
        }

        return false;
    }

    /**
     * Get paginated published news
     *
     * @param int $perPage
     * @param int|null $vendorId
     * @param bool $isAdmin
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getPaginatedNews($perPage = 10, $vendorId = null, $isAdmin = false)
    {
        $query = HITUAM_MSHNEWS::where('BSTATUS', true)
            ->orderBy('DPUBLISHED_AT', 'desc');

        if (!$isAdmin) {
            $vendorId = $vendorId ?? Auth::user()->vendor_id;

            $query->where(function ($q) use ($vendorId) {
                $q->whereRaw("CAST(\"AVIEWERS\" AS jsonb) ? ?", [$vendorId])
                    ->orWhereRaw("CAST(\"AVIEWERS\" AS jsonb) @> ?", [json_encode([$vendorId])]);
            });
        }

        return $query->paginate($perPage);
    }
}
