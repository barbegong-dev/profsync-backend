<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name'];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
