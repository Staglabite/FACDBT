<?php

namespace App\Http\Controllers\Original\HITUAM\HITUAM01;

use App\DataTables\Original\HITUAM01\HITUAMF012Datatable;
use App\Helpers\Response;
use App\Http\Controllers\Controller;
use App\Models\HITUAM01\HITUAM_MSHNEWS as News;
use App\Services\HITUAM\NewsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class HITUAMF012 extends Controller
{
    public function __construct(protected NewsService $newsService) {}

    public function index(HITUAMF012Datatable $datatable)
    {
        $roles = count(Auth::user()->roles) > 0 ? Auth::user()->roles->pluck('VROLENAME')->toArray()[0] : 'Gues';

        // $userSupplier = Auth::user()->load(['supplierUser'])->supplierUser;
        $username = Auth::user()->VUSERNAME;

        // $isAdmin = $roles === 'Admin' || $userSupplier === null;
        // $news = $this->newsService->getPublishedNews($userSupplier?->ISUPPLIER_ID ?? null, $isAdmin);

        $isAdmin = $roles === 'Admin' || $username === null;
        $news = $this->newsService->getPublishedNews($username ?? null, $isAdmin);

        $latestNews = $news->first();
        $olderNews = $news->slice(1, 2)->values();

        // $idViewers = $isAdmin ? 0 : ($userSupplier == null ? 0 : $userSupplier->ISUPPLIER_ID);
        $idViewers = $username;

        return $datatable->render('modules.HITUAM.HITUAM01.HITUAMF012.HITUAMF012', [
            'latestNews' => $latestNews,
            'olderNews' => $olderNews,
            'idViewers' => $idViewers
        ]);
    }

    public function showNewsByViewer($idViewers, $slug)
    {
        $news = News::where('VSUBJECT', $slug)->where('BSTATUS', true)->firstOrFail();

        // if (in_array($idViewers, $news->AVIEWERS) || $idViewers == 0) {
        if ($idViewers || $idViewers == 0) {

            $readers = $news->AVIEWERS ? explode(',', $news->AVIEWERS) : [];

            if (!in_array($idViewers, $readers)) {
                $readers[] = $idViewers;
                $news->ITOTALVIEW = $news->ITOTALVIEW ? $news->ITOTALVIEW + 1 : 1;
            }

            $news->AVIEWERS = implode(',', $readers);
            // Increment the total view count
            // $news->ITOTALVIEW = $addView;
            $news->save();
            // return Response::success(data: $news, message: 'News retrieved successfully');
            return view('modules.HITUAM.HITUAM01.HITUAMF012.partials._news-detail', compact('news'));
        } else {
            abort(404);
        }
    }

    public function filterNews(Request $request)
    {
        try {
            $dateRange = $request->query('date');
            $searchQuery = $request->query('search');

            $roles = Auth::user()->roles->pluck('VROLENAME')->toArray()[0];

            // $userSupplier = Auth::user()->load(['supplierUser'])->supplierUser;
            $username = Auth::user()->VUSERNAME;

            // $isAdmin = $roles === 'Admin' || $userSupplier === null;
            $isAdmin = $roles === 'Admin' || $username === null;
            // $allNews = $this->newsService->getPublishedNews($userSupplier?->ISUPPLIER_ID ?? null, $isAdmin);
            $allNews = $this->newsService->getPublishedNews($username ?? null, $isAdmin);

            // Filter by date range
            if ($dateRange) {
                $parts = explode(' to ', $dateRange);

                // Single date
                if (count($parts) === 1) {
                    $allNews = $allNews->filter(function ($news) use ($parts) {
                        return Carbon::parse($news->DPUBLISHED_AT)->toDateString() === $parts[0];
                    });
                }
                // Range date
                elseif (count($parts) === 2) {
                    // dd($parts);
                    [$startDate, $endDate] = $parts;
                    $startDate = Carbon::parse($startDate)->startOfDay();
                    $endDate   = Carbon::parse($endDate)->endOfDay();

                    $allNews = $allNews->filter(function ($news) use ($startDate, $endDate) {
                        return Carbon::parse($news->DPUBLISHED_AT)
                            ->between($startDate, $endDate);
                    });
                    // dd($startDate);
                    // $allNews = $allNews->filter(
                    //     fn($news) =>
                    //     $news->DPUBLISHED_AT >= $startDate && $news->DPUBLISHED_AT <= $endDate
                    // );
                    // dd($allNews);
                }
            }

            // Filter by search query
            if ($searchQuery) {
                $allNews = $allNews->filter(function ($news) use ($searchQuery) {
                    return stripos($news->VTITLE, $searchQuery) !== false || stripos($news->VCONTENT, $searchQuery) !== false;
                });
            }

            // Re-index the collection
            $allNews = $allNews->map(function ($news) {
                $news->VIMAGE_PATH = $news->VIMAGE_PATH
                    ? asset('storage/news/images/' . $news->VIMAGE_PATH)
                    : null;

                return $news;
            })->values();

            return response()->json([
                'status' => 'success',
                'data' => $allNews,
            ]);
        } catch (\Throwable $e) {

            // Log error untuk debugging
            Log::error('Filter News Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong while filtering news.',
                'error' => $e->getMessage(), // optional → hapus jika tidak ingin expose error
            ], 500);
        }
    }
}
