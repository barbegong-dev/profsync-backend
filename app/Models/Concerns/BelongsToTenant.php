<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Scopes every query to the signed-in user's tenant and stamps tenant_id on create.
 * The platform super admin (tenant_id = null) is not scoped.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = Auth::user()?->tenant_id;
            if ($tenantId) {
                $builder->where($builder->qualifyColumn('tenant_id'), $tenantId);
            }
        });

        static::creating(function ($model) {
            if (!$model->tenant_id) {
                $model->tenant_id = Auth::user()?->tenant_id;
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
