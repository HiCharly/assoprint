<?php

namespace App\Http\Resources;

use App\Models\PrintJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PrintJob
 */
class PrintJobResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_filename' => $this->original_filename,
            'copies' => $this->copies,
            'duplex' => $this->duplex->value,
            'duplex_label' => $this->duplex->label(),
            'color_mode' => $this->color_mode->value,
            'color_mode_label' => $this->color_mode->label(),
            'page_count' => $this->page_count,
            'pages_printed' => $this->pages_printed,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'error_message' => $this->error_message,
            'is_duplicate' => $this->duplicated_from_id !== null,
            'counts_pages' => $this->counts_pages,
            'file_exists' => $this->fileExists(),
            'created_at' => $this->created_at?->toIso8601String(),
            'printed_at' => $this->printed_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
        ];
    }
}
