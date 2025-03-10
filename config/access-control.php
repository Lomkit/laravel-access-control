<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Access Control Queries
    |--------------------------------------------------------------------------
    |
    */
    'queries' => [
        'enabled_by_default' => false,
        'isolated' => true // Isolate the control's logic by applying a parent where on the query
    ],
];
