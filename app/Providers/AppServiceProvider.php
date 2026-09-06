<?php

namespace App\Providers;

use App\Interfaces\ProductRepository;
use App\Repositories\JsonProductRepository;
use App\Storage\ProductFileStorage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ProductFileStorage::class, fn () => new ProductFileStorage(
            config('products.storage_path'),
        ));

        $this->app->bind(ProductRepository::class, JsonProductRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
