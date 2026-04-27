<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InternshipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Internships\StoreInternshipRequest;
use App\Http\Resources\InternshipCollection;
use App\Http\Resources\InternshipResource;
use App\Models\Internship;
use App\Services\InternshipService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class InternshipController extends Controller
{
    // Unlocks the authorization methods like $this->authorize() in the controller which are delegated to the defined policies
    use AuthorizesRequests;

    // Return a paginated list of internships with optional search and filtering
    public function index(Request $request): InternshipCollection
    {
        $query = Internship::query()->with(['company', 'skills'])->where('status', InternshipStatus::OPEN->value);
        $terms = collect($request->query('terms', []))
            ->map(fn ($term) => trim((string) $term))
            ->filter()
            ->values();
        $match = $request->query('match', 'any');


        if ($terms->isNotEmpty()) {
            if ($match === 'all') {
                foreach ($terms as $term) {
                    $query->where(function ($builder) use ($term) {
                        $builder->where('title', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhereHas('company', function ($company) use ($term) {
                            $company->where('company_name', 'like', "%{$term}%");
                            })
                            ->orWhereHas('skills', function ($skills) use ($term) {
                                $skills->where('name', 'like', "%{$term}%");
                                });
                                });
                                }
                                } else {
                $query->where(function ($builder) use ($terms) {
                    foreach ($terms as $term) {
                        $builder->orWhere('title', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhereHas('company', function ($company) use ($term) {
                            $company->where('company_name', 'like', "%{$term}%");
                            })
                            ->orWhereHas('skills', function ($skills) use ($term) {
                                $skills->where('name', 'like', "%{$term}%");
                                });
                                }
                                });
                                }
                                }

        $type = $request->query('type');
        if ($type) {
            $query->where('type', $type);
        }
        return new InternshipCollection($query->paginate(12)->withQueryString());
    }

    // Return a single internship
    public function show(Internship $internship): InternshipResource
    {
        return new InternshipResource($internship->load(['company', 'skills']));
    }

    // Return internships of the authenticated user's company
    public function companyIndex(Request $request): InternshipCollection {
        $companyProfile = $request->user()->companyProfile;

        abort_if(! $companyProfile, 422, 'Company profile is required to view company internships');

        return new InternshipCollection(
            $companyProfile->internships()->with(['company', 'skills'])
            ->where('status', '!=', InternshipStatus::ARCHIVED->value)
            ->latest()
            ->paginate(12)
        );
    }

    // Return archived internships of the authenticated user's company
    public function archived(Request $request): InternshipCollection {
        $companyProfile = $request->user()->companyProfile;

        abort_if(! $companyProfile, 422, 'Company profile is required to view archived internships');

        return new InternshipCollection(
            $companyProfile->internships()->with(['company', 'skills'])
            ->where('status', InternshipStatus::ARCHIVED->value)
            ->latest('archived_at')
            ->paginate(12)
        );
    }

    // Handles creation of a new internship by the authenticated user's company
    public function store(StoreInternshipRequest $request, InternshipService $service): InternshipResource{
        $this->authorize('create', Internship::class);

        return new InternshipResource($service->create($request->user(), $request->validated()));
    }

    // Handles updating an existing internship owned by the authenticated user's company
    public function update(StoreInternshipRequest $request, Internship $internship, InternshipService $service): InternshipResource {
        $this->authorize('update', $internship);

        return new InternshipResource($service->update($internship, $request->validated()));
    }

    public function archive (Internship $internship, InternshipService $service): InternshipResource {
        $this->authorize('archive', $internship);

        return new InternshipResource($service->archive($internship));
    }

    public function destroy(Internship $internship, InternshipService $service) {
        $this->authorize('delete', $internship);

        $service->delete($internship);

        return response()->noContent();
    }
}
