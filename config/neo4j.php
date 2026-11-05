<?php

return [
    'uri' => env('NEO4J_URI', 'bolt://localhost:7687'),
    'user' => env('NEO4J_USER', 'neo4j'),
    'password' => env('NEO4J_PASSWORD', 'familytree123'),
    'database' => env('NEO4J_DATABASE', 'neo4j'),
    'socket_timeout' => 3.0,
    'query_timeout' => 10.0,
    'write_lock_wait_seconds' => 5,
];
