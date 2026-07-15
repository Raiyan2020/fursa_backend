<?php

namespace App\Http\Traits;

use Illuminate\Http\UploadedFile;

trait UploadTrait
{
    /**
     * Return a public URL for the stored image path (Nafas-style).
     */
    public function getImageAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return getimg($value);
    }

    /**
     * Upload a file when assigned, otherwise store the raw path string.
     */
    public function setImageAttribute($value): void
    {
        if ($value instanceof UploadedFile) {
            $folder = property_exists($this, 'uploadFolder') ? $this->uploadFolder : 'uploads';
            $this->attributes['image'] = uploader($value, $folder);

            return;
        }

        $this->attributes['image'] = $value;
    }

    /**
     * Raw DB value without the URL accessor (for delete/replace logic).
     */
    public function getRawImagePath(): ?string
    {
        return $this->attributes['image'] ?? null;
    }
}
