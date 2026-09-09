<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MediaKeepSet
{
    public static function validate(Request $request, $parent): ?array
    {
        if (! $request->exists('existing_image_ids')) {
            return null;
        }
        $data = $request->validate([
            'existing_image_ids' => ['present', 'array'],
            'existing_image_ids.*' => ['integer', 'distinct'],
        ]);
        $ids = $data['existing_image_ids'];
        if ($parent->images()->notDeleted()->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['existing_image_ids' => ['Images must belong to this record.']]);
        }

        return $ids;
    }

    public static function apply($parent, ?array $ids): void
    {
        if ($ids !== null) {
            // Retain bytes with soft-deleted rows; republished/historical media
            // may still reference them. Purging needs a reference-aware policy.
            $parent->images()->notDeleted()->whereNotIn('id', $ids)
                ->update(['is_deleted' => true, 'deleted_at' => now()]);
        }
    }
}
