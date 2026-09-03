<?php

namespace App\Models;

use App\Models\Concerns\RecordsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Assessor master — a global, shared list of people who conduct assessments.
 * No login is required; an assessor may exist purely as a directory record.
 */
class Assessor extends Model
{
    use RecordsHistory;

    protected $fillable = ['name', 'email', 'phone', 'designation', 'is_active', 'note', 'user_id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function schedules(): BelongsToMany
    {
        return $this->belongsToMany(AssessmentSchedule::class);
    }
}
