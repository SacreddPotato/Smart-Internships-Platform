<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\UpdateProfileRequest;
use App\Http\Requests\Student\UploadCvRequest;
use App\Http\Resources\StudentProfileResource;
use App\Services\StudentProfileService;
use Illuminate\Http\Request;

class StudentProfileController extends Controller
{
    public function show(Request $request): StudentProfileResource {
        $profile = $request->user()->studentProfile()->firstOrCreate([]);
        $profile->wasRecentlyCreated = false;

        return new StudentProfileResource($profile->load(['user', 'skills']));
    }

    public function update(UpdateProfileRequest $request, StudentProfileService $service): StudentProfileResource {
        $profile = $request->user()->studentProfile()->firstOrCreate([]);

        return new StudentProfileResource($service->updateProfile($profile, $request->validated()));
    }

    public function uploadCv(UploadCvRequest $request, StudentProfileService $service): StudentProfileResource {
        $profile = $request->user()->studentProfile()->firstOrCreate([]);

        return new StudentProfileResource($service->uploadCv($profile, $request->file('cv')));
    }
}
