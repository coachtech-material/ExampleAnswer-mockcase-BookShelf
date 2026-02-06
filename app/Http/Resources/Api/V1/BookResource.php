<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
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
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,
            'published_date' => $this->published_date,
            'description' => $this->when($this->relationLoaded('reviews'), $this->description),
            'image_url' => $this->when($this->relationLoaded('reviews'), $this->image_url),
            'genres' => GenreResource::collection($this->whenLoaded('genres')),
            'average_rating' => round($this->whenAggregated('reviews', 'rating', 'avg'), 1),
            'review_count' => (int) $this->whenCounted('reviews'),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
        ];
    }
}
