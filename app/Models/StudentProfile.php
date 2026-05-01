<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'university', 'major', 'gpa', 'graduation_year', 'bio', 'cv_path'])]
class StudentProfile extends Model
{
    /** @use HasFactory<\Database\Factories\StudentProfileFactory> */
    use HasFactory;

    protected function casts(): array {
        return [
            'gpa' => 'decimal:2',
            'graduation_year' => 'integer',
        ];
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function skills(): BelongsToMany {
        return $this->belongsToMany(Skill::class, 'student_skill');
    }

    public function applications(): HasMany {
        return $this->hasMany(Application::class);
    }
}
