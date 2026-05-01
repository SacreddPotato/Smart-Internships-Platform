<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_profile_id', 'internship_id', 'status', 'match_score', 'message', 'reviewed_at'])]
class Application extends Model
{
    /** @use HasFactory<\Database\Factories\ApplicationFactory> */
    use HasFactory;

    protected function casts(): array {
        return [
            'status' => ApplicationStatus::class,
            'reviewed_at' => 'datetime',
            'match_score' => 'integer',
        ];
    }

    public function studentProfile(): BelongsTo {
        return $this->belongsTo(StudentProfile::class);
    }

    public function internship(): BelongsTo {
        return $this->belongsTo(Internship::class);
    }   
}
