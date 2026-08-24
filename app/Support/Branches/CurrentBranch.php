<?php

namespace App\Support\Branches;

use App\Models\Branch;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves the "current" branch for the request.
 *
 * Offline / single-branch: there is exactly one branch, so this returns it.
 * Online / multi-branch later: this becomes the authenticated user's branch,
 * and nothing else in the app has to change — the BranchScope and the
 * BelongsToBranch trait both read through here.
 */
class CurrentBranch
{
    protected static ?string $cachedId = null;

    /**
     * The active branch id, or null very early in bootstrap before any branch
     * has been seeded (e.g. during the first migration run).
     */
    public static function id(): ?string
    {
        if (static::$cachedId !== null) {
            return static::$cachedId;
        }

        $user = Auth::user();
        if ($user !== null && ! empty($user->branch_id)) {
            return static::$cachedId = $user->branch_id;
        }

        // Single-branch fallback: the sole seeded branch.
        return static::$cachedId = Branch::query()->value('id');
    }

    public static function model(): ?Branch
    {
        $id = static::id();

        return $id ? Branch::find($id) : null;
    }

    /** Override the resolved branch (used in tests and console context). */
    public static function set(?string $branchId): void
    {
        static::$cachedId = $branchId;
    }

    /** Clear the memoized value (call between requests/tests). */
    public static function flush(): void
    {
        static::$cachedId = null;
    }
}
