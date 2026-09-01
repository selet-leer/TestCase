<?php

namespace App\Storage;

class ProductFileStorage
{
    private const SCHEMA_VERSION = 1;

    private readonly string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? storage_path('app/data/products.json');
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function read(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $decoded = json_decode(file_get_contents($this->path), true);

        return $decoded['products'] ?? [];
    }

    /**
     * @param  array<array<string, mixed>>  $products
     */
    public function write(array $products): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $payload = json_encode([
            '_schema_version' => self::SCHEMA_VERSION,
            'products' => $products,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents($this->path, $payload);
    }
}
