<?php

namespace App\Models;

readonly class Product
{
    public function __construct(
        public string $id,
        public string $name,
        public int    $price,
        public int    $stock,
        public int    $version,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            $data['name'],
            (int) $data['price'],
            (int) $data['stock'],
            (int) $data['version'],
            $data['created_at'],
            $data['updated_at'],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'stock' => $this->stock,
            'version' => $this->version,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
