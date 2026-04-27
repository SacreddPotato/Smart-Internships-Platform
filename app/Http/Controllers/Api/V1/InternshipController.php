<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InternshipStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\InternshipCollection;
use App\Http\Resources\InternshipResource;
use App\Models\Internship;
use Illuminate\Http\Request;

class InternshipController extends Controller
{
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

    public function show(Internship $internship): InternshipResource
    {
        return new InternshipResource($internship->load(['company', 'skills']));
    }
}
