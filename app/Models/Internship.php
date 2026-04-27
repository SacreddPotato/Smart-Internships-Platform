<?php

namespace App\Models;

use App\Enums\InternshipStatus;
use App\Enums\InternshipType;
use Database\Factories\InternshipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_profile_id',
    'title',
    'description',
    'requirements',
    'location',
    'type',
    'status',
    'starts_at',
    'ends_at',
    'archived_at',
])]
class Internship extends Model
{
    /** @use HasFactory<InternshipFactory> */
    use HasFactory, SoftDeletes;

    public function casts(): array {
        return [
            'type' => InternshipType::class,
            'status' => InternshipStatus::class,
            'starts_at' => 'date',
            'ends_at' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo {
        return $this->belongsTo(CompanyProfile::class, 'company_profile_id');
    }

    public function skills(): BelongsToMany {
        return $this->belongsToMany(Skill::class)->withTimestamps();
    }
}
