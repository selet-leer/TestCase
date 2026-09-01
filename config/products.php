<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Products Storage Path
    |--------------------------------------------------------------------------
    |
    | Path to the JSON file backing the product catalog. Overridable via
    | PRODUCTS_STORAGE_PATH so tests and other environments can point at a
    | different file without touching the repository or controller.
    |
    */

    'storage_path' => env('PRODUCTS_STORAGE_PATH', storage_path('app/data/products.json')),

];
