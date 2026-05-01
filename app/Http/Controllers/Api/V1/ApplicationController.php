<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Applications\StoreApplicationRequest;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\Internship;
use App\Services\ApplicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ApplicationController extends Controller
{
    public function store(StoreApplicationRequest $request, Internship $internship, ApplicationService $service): ApplicationResource {
        $profile = $request->user()->studentProfile()->firstOrCreate([]);

        return new ApplicationResource($service->apply($profile, $internship, $request->validated()));
    }

    public function studentIndex(Request $request) {
        $profile = $request->user()->studentProfile()->firstOrCreate([]);

        return ApplicationResource::collection($profile->applications()->with(['internship.company', 'internship.skills'])->latest()->paginate(12));
    }

    public function companyIndex(Request $request) {
        $companyProfile = $request->user()->companyProfile;

        abort_if(!$companyProfile, 422, 'You must have a company profile to view applications.');

        return ApplicationResource::collection(Application::whereHas('internship', fn($query) => $query->where('company_profile_id', $companyProfile->id))
            ->with(['studentProfile.user', 'studentProfile.skills', 'internship.company', 'internship.skills'])
            ->latest()
            ->paginate(12)
        );
    }

    public function show(Application $application) {
        $application->load(['studentProfile.user', 'studentProfile.skills', 'internship.company', 'internship.skills']);

        // Only the student who applied or the company that posted the internship can view the application
        Gate::authorize('view', $application);

        return new ApplicationResource($application);
    }
}
