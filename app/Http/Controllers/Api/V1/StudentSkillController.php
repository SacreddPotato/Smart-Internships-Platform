<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentProfileResource;
use App\Services\StudentProfileService;
use Illuminate\Http\Request;

class StudentSkillController extends Controller
{
    public function sync(Request $request, StudentProfileService $service) {
        $data = $request->validate([
            'skills' => ['array'],
            'skills.*' => ['integer', 'exists:skills,id']
        ]);

        $profile = $request->user()->studentProfile()->firstOrCreate([]);

        return new StudentProfileResource($service->syncSkills($profile, $data['skills'] ?? []));
    }
}
