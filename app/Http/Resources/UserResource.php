<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the user model into an API response.
     *
     * Password is never exposed. The `can_edit` flag and `orders_count`
     * are conditionally included when loaded by the query layer.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Included when the query eager-loads withCount('orders')
            'orders_count' => $this->whenCounted('orders'),

            // Computed by UserPolicy and attached by the service layer
            'can_edit' => $this->when(
                isset($this->resource->can_edit),
                fn () => $this->resource->can_edit,
            ),
        ];
    }
}
