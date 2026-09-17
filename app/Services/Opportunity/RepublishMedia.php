<?php

namespace App\Services\Opportunity;

use App\Support\MediaKeepSet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RepublishMedia
{
    public static function rules(): array
    {
        return [
            'opportunity_id' => ['sometimes', 'integer'],
            'existing_image_ids' => ['sometimes', 'array'],
            'existing_image_ids.*' => ['integer', 'distinct'],
            'license_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'license_image_removed' => ['sometimes', 'boolean'],
        ];
    }

    public static function source(Request $request, string $class): ?object
    {
        MediaKeepSet::normalizeEmptySignal($request);

        if (! $request->filled('opportunity_id')) {
            if ($request->input('existing_image_ids', []) !== []) {
                throw ValidationException::withMessages(['opportunity_id' => ['A source is required to copy images.']]);
            }

            return null;
        }
        $source = $class::query()->notDeleted()->where('created_by', $request->user()->id)
            ->findOrFail($request->input('opportunity_id'));

        // A republish is a new opportunity copied from the old one. Once the
        // source's registration deadline has passed, copying it would let an
        // organizer resurrect an expired opportunity by accident.
        if (method_exists($source, 'registrationClosesAt')) {
            $deadline = $source->registrationClosesAt();
            if ($deadline && now()->gt($deadline)) {
                throw ValidationException::withMessages([
                    'opportunity_id' => [
                        'This opportunity can no longer be republished after its deadline.',
                    ],
                ]);
            }
        }
        MediaKeepSet::validate($request, $source);

        return $source;
    }

    public static function apply(Request $request, object $target, ?object $source = null): void
    {
        if ($source) {
            $images = $source->images()->notDeleted();
            if ($request->exists('existing_image_ids')) {
                $images->whereIn('id', $request->input('existing_image_ids'));
            }
            foreach ($images->get() as $image) {
                $target->images()->create([
                    'image' => self::copy($image->image),
                    ...($image->getAttribute('is_after_completed') !== null ? ['is_after_completed' => false] : []),
                ]);
            }
        }
        if ($request->hasFile('license_image')) {
            $target->update(['license_image' => $request->file('license_image')->store('licenses', 'public')]);
        } elseif ($request->boolean('license_image_removed')) {
            $target->update(['license_image' => null]);
        } elseif ($source && $source->license_image) {
            $target->update(['license_image' => self::copy($source->license_image)]);
        }
    }

    private static function copy(string $path): string
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            throw ValidationException::withMessages(['existing_image_ids' => ['Source media is missing; upload a replacement.']]);
        }
        $copy = 'republished/'.Str::uuid().'.'.pathinfo($path, PATHINFO_EXTENSION);
        if (! $disk->copy($path, $copy)) {
            throw new \RuntimeException('Unable to copy media.');
        }

        return $copy;
    }
}
