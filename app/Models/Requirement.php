<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Requirement extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'description', 'semester_id', 'is_required', 'deadline'];

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }
}
