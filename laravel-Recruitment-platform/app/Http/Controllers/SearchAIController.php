<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AIService;
use App\Services\JobService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SearchAIController extends Controller
{
    public function __construct(
        private AIService $aiService,
        private JobService $jobService
    ) {}

    public function searchJobsAI(Request $request): JsonResponse
    {
        $q = $request->query('q');

        if (!$q || !is_string($q)) {
            return response()->json([
                'success' => false,
                'message' => 'Từ khóa tìm kiếm không hợp lệ'
            ], 400);
        }

        try {

            [$aiMatches, $keywordResults] = [
                $this->aiService->searchJobsByAI($q),
                $this->jobService->searchJobsByKeyword($q)
            ];
            // echo json_encode($aiMatches);
            $allJobIds = collect($aiMatches)
                ->pluck("id")
                ->merge(
                    collect($keywordResults)->pluck('JobID')
                )
                ->unique()
                ->values();
            if ($allJobIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }
            $fullJobs = $this->jobService->getJobsByIds(
                $allJobIds->toArray()
            );
            $topJobsToRerank = collect($fullJobs)
                ->take(15)
                ->values()
                ->toArray();

            $finalSortedJobs = $this->aiService->rerankWithAI(
                $q,
                $topJobsToRerank
            );

            return response()->json([
                'success' => true,
                'data' => $finalSortedJobs
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);

        }
    }
}