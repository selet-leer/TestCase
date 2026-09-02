<?php

namespace Tests\Feature;

use App\Console\Commands\PlaceOrder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class OrderConcurrencyTest extends TestCase
{
    private string $dataFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dataFile = storage_path('framework/testing/products.json');
        config(['products.storage_path' => $this->dataFile]);
        $this->cleanFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanFiles();

        parent::tearDown();
    }

    private function cleanFiles(): void
    {
        @unlink($this->dataFile);
        @unlink($this->dataFile.'.lock');
        @unlink($this->dataFile.'.tmp');
    }

    public function test_50_concurrent_orders_never_oversell(): void
    {
        $id = $this->postJson('/api/products', [
            'name' => 'Chair',
            'price' => 100,
            'stock' => 10,
        ])->json('data.id');


        /**
         * @var array<Process> $processes
         */
        $processes = [];

        // 50 processes contending on the same file lock.
        for ($i = 0; $i < 50; $i++) {
            $process = new Process(
                [PHP_BINARY, 'artisan', 'order:place', $id, '1'],
                base_path(),
                ['PRODUCTS_STORAGE_PATH' => $this->dataFile],
            );
            $process->start();
            $processes[] = $process;
        }

        $succeeded = 0;
        $rejected = 0;

        foreach ($processes as $process) {
            $process->wait();

            if ($process->getExitCode() === 0) {
                $succeeded++;
            } elseif ($process->getExitCode() === PlaceOrder::EXIT_SOLD_OUT) {
                $rejected++;
            }
        }

        $this->assertSame(50, $succeeded + $rejected, 'every process returned a known status');
        $this->assertSame(10, $succeeded, 'exactly 10 orders succeed');
        $this->assertSame(40, $rejected, 'the other 40 are rejected');

        $data = json_decode(file_get_contents($this->dataFile), true);

        $this->assertIsArray($data, 'products.json is still valid');
        $this->assertSame(0, $data['products'][0]['stock'], 'stock never goes negative');
        $this->assertSame(11, $data['products'][0]['version'], 'version went up by 10');
    }
}
