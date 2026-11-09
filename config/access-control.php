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
    ],

    /*
    |--------------------------------------------------------------------------
    | Access Control Methods
    |--------------------------------------------------------------------------
    |
    */
    'methods' => [
        'viewAny' => 'view',
        'view' => 'view',
        'create' => 'create',
        'update' => 'update',
        'delete' => 'delete',
        'restore' => 'restore',
        'forceDelete' => 'forceDelete',
    ],
];
