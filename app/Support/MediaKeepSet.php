<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MediaKeepSet
{
    /**
     * `existing_image_ids[]` sent zero times has no multipart representation,
     * so a client cannot say "keep none" — the key is simply absent, which
     * this class already reads as "keep everything". A scalar sentinel gives
     * clients a way to say "keep none" over the same transport: normalise it
     * to an empty array before anything validates the key as an array.
     */
    public static function normalizeEmptySignal(Request $request): void
    {
        $value = $request->input('existing_image_ids');

        if (is_string($value) && in_array(trim($value), ['', 'none'], true)) {
            $request->merge(['existing_image_ids' => []]);
        }
    }

    public static function validate(Request $request, $parent): ?array
    {
        if (! $request->exists('existing_image_ids')) {
            return null;
        }

        self::normalizeEmptySignal($request);

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
