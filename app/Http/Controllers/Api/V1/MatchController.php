<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InternshipStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\InternshipResource;
use App\Models\Internship;
use App\Services\MatchScoreService;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function score(Request $request, Internship $internship) {
        $profile = $request->user()->studentProfile()->firstOrCreate([]);

        return response()->json([
            'score' => MatchScoreService::calculate(
                $profile->load('skills'),
                $internship->load('skills')
            )
        ]);
    }

    public function recommendations(Request $request) {
        $profile = $request->user()->studentProfile()->firstOrCreate([])->load('skills');

        $internships = Internship::with(['company', 'skills'])
            ->where('status', InternshipStatus::OPEN)
            ->latest()
            ->get()
            ->map(function (Internship $internship) use ($profile) {
                $internship->match_score = MatchScoreService::calculate($profile, $internship);
                return $internship;
            })
            ->sortByDesc('match_score')
            ->values()
            ->take(12);

            return InternshipResource::collection($internships);
    }
}
