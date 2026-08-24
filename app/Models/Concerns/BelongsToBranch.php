<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use App\Support\Branches\CurrentBranch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applied to every branch-scoped business model. It:
 *  - registers the global BranchScope so reads are isolated per branch, and
 *  - auto-stamps branch_id on create from the current branch.
 *
 * A model can opt out of the auto-scope for a single query via
 * Model::withoutBranchScope() when a cross-branch operation is genuinely needed.
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope);

        static::creating(function ($model) {
            if (empty($model->branch_id)) {
                $model->branch_id = CurrentBranch::id();
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<static> */
    public static function withoutBranchScope()
    {
        return static::withoutGlobalScope(BranchScope::class);
    }
}
