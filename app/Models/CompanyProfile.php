<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'company_name', 'industry', 'website', 'description'])]
class CompanyProfile extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyProfileFactory> */
    use HasFactory;

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function internships(): HasMany {
        return $this->hasMany(Internship::class);
    }
}
