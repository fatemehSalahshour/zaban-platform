<?php

use Illuminate\Support\Str;

return [ 
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,127.0.0.1')),

];
