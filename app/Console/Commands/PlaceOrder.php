<?php

namespace App\Console\Commands;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\ProductNotFoundException;
use App\Interfaces\ProductRepository;
use Illuminate\Console\Command;

class PlaceOrder extends Command
{
    public const EXIT_SOLD_OUT = 9;

    public const EXIT_NOT_FOUND = 10;

    protected $signature = 'order:place {id} {quantity}';

    protected $description = "Decrement a product's stock for the unit test.";

    public function handle(ProductRepository $products): int
    {
        try {
            $products->decrementStock($this->argument('id'), (int) $this->argument('quantity'));
        } catch (InsufficientStockException) {
            return self::EXIT_SOLD_OUT;
        } catch (ProductNotFoundException) {
            return self::EXIT_NOT_FOUND;
        }

        return self::SUCCESS;
    }
}
