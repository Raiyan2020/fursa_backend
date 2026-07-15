<?php

namespace App\Http\Resources\Organization;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** Matches Django OrganizationDocumentSerializer. */
class OrganizationDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document' => $this->document
                ? Storage::disk('public')->url($this->document)
                : null,
            'uploaded_at' => optional($this->uploaded_at)?->toIso8601String(),
        ];
    }
}
