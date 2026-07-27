<?php

namespace App\Http\Resources\Website;

use App\Http\Resources\Concerns\ResolvesApiPayloads;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Website registration response payload. */
class WebsiteRegisterUserResource extends JsonResource
{
    use ResolvesApiPayloads;

    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'user_type' => $user->user_type?->value ?? $user->user_type,
        ];
    }
}
