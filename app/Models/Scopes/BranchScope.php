<?php

namespace App\Models\Scopes;

use App\Support\Branches\CurrentBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains queries on branch-scoped models to the current branch.
 *
 * With one branch this is effectively a no-op that still future-proofs the
 * app: when the clinic goes online with multiple branches, every list, report
 * and lookup is already isolated per branch without touching call sites.
 *
 * Resolution is skipped when no branch is resolvable yet (fresh install /
 * migrations) so bootstrapping never fails.
 */
class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $branchId = CurrentBranch::id();

        if ($branchId === null) {
            return;
        }

        $builder->where($model->getTable() . '.branch_id', $branchId);
    }
}
