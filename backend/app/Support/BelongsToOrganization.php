<?php

namespace App\Support;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applies tenant isolation to a model: every query is constrained to the
 * organization bound in TenantContext, and new records inherit that
 * organization automatically.
 *
 * @mixin Model
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder) {
            $tenant = app(TenantContext::class);

            if ($tenant->has()) {
                $builder->where($builder->getModel()->getTable().'.organization_id', $tenant->id());
            }
        });

        static::creating(function ($model) {
            if ($model->organization_id === null) {
                $model->organization_id = app(TenantContext::class)->id();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
