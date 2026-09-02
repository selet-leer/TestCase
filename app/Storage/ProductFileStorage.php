<?php

namespace App\Storage;

use RuntimeException;

class ProductFileStorage
{
    private const SCHEMA_VERSION = 1;

    private readonly string $path;

    /**
     * @var resource|null
     */
    private $lockHandle = null;

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
        $this->ensureDirectory();

        $payload = json_encode([
            '_schema_version' => self::SCHEMA_VERSION,
            'products' => $products,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // Write to a temp file first, then swap it in with an atomic rename so
        // a concurrent reader always sees a complete file, never a half-written one.
        $tmp = $this->path.'.tmp';
        file_put_contents($tmp, $payload);
        rename($tmp, $this->path);
    }

    private function ensureDirectory(): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    /**
     * @throws RuntimeException
     */
    public function lock(): void
    {
        $this->ensureDirectory();

        $handle = fopen($this->path.'.lock', 'c');

        if ($handle === false) {
            throw new RuntimeException('Unable to open lock file.');
        }

        flock($handle, LOCK_EX);

        $this->lockHandle = $handle;
    }

    public function unlock(): void
    {
        if ($this->lockHandle === null) {
            return;
        }

        flock($this->lockHandle, LOCK_UN);
        fclose($this->lockHandle);
        $this->lockHandle = null;
    }
}
