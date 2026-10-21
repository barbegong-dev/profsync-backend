<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'faculty_id', 'requirement_id', 'file_path', 'status', 'remarks'];

    public function faculty()
    {
        return $this->belongsTo(User::class, 'faculty_id');
    }

    public function requirement()
    {
        return $this->belongsTo(Requirement::class);
    }
}
