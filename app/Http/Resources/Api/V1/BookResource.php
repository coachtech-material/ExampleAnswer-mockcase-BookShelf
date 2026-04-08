<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,
            'published_date' => $this->published_date,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'genres' => GenreResource::collection($this->whenLoaded('genres')),
            'average_rating' => $this->reviews_avg_rating !== null
                ? round((float) $this->reviews_avg_rating, 1)
                : null,
            'review_count' => (int) ($this->reviews_count ?? 0),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
        ];
    }
}
