<?php

return [

    //-- time to live (in days)
    'ttl' => 7,

    'date_format' => env('JOBX_DATE_FORMAT', 'Y-m-d H:i:s.v'),
    'timestamp_precision' => 3,
    
    'route' => [
        'prefix' => [
            'web' => env('JOBX_ROUTE_PREFIX_WEB', 'jobx'),
            'api' => env('JOBX_ROUTE_PREFIX_API', 'api/jobx'),
        ],
    ],
];
