<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;

/**
 * The location input was replaced by a map picker, so clients now send:
 *
 *     { "map_desc": "شارع الجمهورية، المنصورة", "lat": 31.0409, "lng": 31.3785 }
 *
 * The columns are still `latitude` / `longitude` / `map_desc`, and the legacy
 * `location_en` / `location_ar` stay populated so older clients and existing
 * records keep working. This trait bridges the two.
 */
trait HandlesMapLocation
{
    /**
     * Validation rules for the map fields. Merge into a controller's rule set.
     *
     * @return array<string, array<int, string>>
     */
    protected function mapLocationRules(bool $partial = false): array
    {
        $optional = $partial ? 'sometimes' : 'nullable';

        return [
            'map_desc' => [$optional, 'nullable', 'string', 'max:500'],
            'lat' => [$optional, 'nullable', 'numeric', 'between:-90,90'],
            'lng' => [$optional, 'nullable', 'numeric', 'between:-180,180'],
            // Long-form names remain accepted for backwards compatibility.
            'latitude' => [$optional, 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => [$optional, 'nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * Fold `lat` / `lng` onto the real column names before validation, so a
     * client sending either shape lands on the same fields.
     */
    protected function normalizeMapLocation(Request $request): void
    {
        if (! $request->has('latitude') && $request->has('lat')) {
            $request->merge(['latitude' => $request->input('lat')]);
        }

        if (! $request->has('longitude') && $request->has('lng')) {
            $request->merge(['longitude' => $request->input('lng')]);
        }
    }

    /**
     * Turn validated input into column values.
     *
     * `map_desc` is the single description the map picker produces, so it also
     * feeds `location_en` / `location_ar` — those columns still drive the
     * location search filter and older payloads.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mapLocationAttributes(array $data): array
    {
        $attributes = [];

        if (array_key_exists('latitude', $data)) {
            $attributes['latitude'] = $data['latitude'] === null ? null : (float) $data['latitude'];
        }

        if (array_key_exists('longitude', $data)) {
            $attributes['longitude'] = $data['longitude'] === null ? null : (float) $data['longitude'];
        }

        if (array_key_exists('map_desc', $data)) {
            $attributes['map_desc'] = $data['map_desc'];

            // Only mirror into the legacy columns when the client did not send
            // them explicitly — never clobber a value it deliberately set.
            if (! array_key_exists('location_en', $data)) {
                $attributes['location_en'] = $data['map_desc'];
            }
            if (! array_key_exists('location_ar', $data)) {
                $attributes['location_ar'] = $data['map_desc'];
            }
        }

        return $attributes;
    }

    /**
     * Map payload for API responses: short names the map picker expects, plus
     * the legacy fields.
     *
     * @return array<string, mixed>
     */
    protected function mapLocationPayload(object $model): array
    {
        $lat = $model->latitude === null ? null : (float) $model->latitude;
        $lng = $model->longitude === null ? null : (float) $model->longitude;

        return [
            'map_desc' => $model->map_desc ?? $model->location_ar ?? $model->location_en,
            'lat' => $lat,
            'lng' => $lng,
            'latitude' => $lat,
            'longitude' => $lng,
        ];
    }
}
