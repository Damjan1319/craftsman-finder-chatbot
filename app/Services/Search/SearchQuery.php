<?php

namespace App\Services\Search;

use App\Models\Category;

class SearchQuery
{
    public function __construct(
        public readonly ?Category $category,
        public readonly ?string $city,
    ) {}

    public function isComplete(): bool
    {
        return $this->category !== null && filled($this->city);
    }
}
