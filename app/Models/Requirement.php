<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Requirement extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'description', 'semester_id', 'department_id', 'is_required', 'deadline'];

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    /** Null means the requirement applies to every department. */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /** Requirements that apply to members of the given department: workspace-wide ones plus its own. */
    public function scopeForDepartment(Builder $query, ?int $departmentId): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('department_id')
            ->when($departmentId, fn ($q) => $q->orWhere('department_id', $departmentId)));
    }

    public function appliesTo(?int $departmentId): bool
    {
        return $this->department_id === null || $this->department_id === $departmentId;
    }
}
