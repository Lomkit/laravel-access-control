<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Access Control Queries
    |--------------------------------------------------------------------------
    |
    */
    'queries' => [
        'enabled_by_default'             => false,
        'isolate_parent_query'           => true, // Isolate the control's logic by applying a parent where on the query
        'isolate_perimeter_queries'      => true, // Isolate every perimeter query by applying a default "orWhere" to prevent Overlayed Perimeters collapsing
    ],
];
