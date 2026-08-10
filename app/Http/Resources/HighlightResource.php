<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HighlightResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'current_page' => $this->current_page,

            'location' => $this->location,

            'selected_text' => $this->selected_text,

            'note' => $this->note,

            'color' => $this->color,

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}