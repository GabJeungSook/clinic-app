<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Generates human-friendly sequential document numbers per branch, e.g.
 * INV-000001. Sufficient for single-user offline use; the online version can
 * swap in a locking/sequence strategy without changing call sites.
 */
class DocumentNumber
{
    public static function next(Builder $query, string $prefix, string $column, int $pad = 6): string
    {
        $count = $query->count();

        do {
            $count++;
            $candidate = $prefix . '-' . str_pad((string) $count, $pad, '0', STR_PAD_LEFT);
            $exists = (clone $query)->where($column, $candidate)->exists();
        } while ($exists);

        return $candidate;
    }
}
