<?php

return [
    'enable' => true,
    'enable_cache' => false,
    
    'scan_dirs' => [
        app_path(),
    ],
    
    'exclude_dirs' => [
        'vendor',
        'runtime',
        'config',
        'public',
    ],
    
    'auto_register_routes' => true,
    'auto_register_middleware' => true,
    'auto_register_beans' => true,
    'auto_register_crons' => true,
    'auto_register_events' => true,
    'enable_value_injection' => true,
    
    'annotations' => [
    ],
    
    'blacklist' => [
        'annotations' => [
        ],
        'classes' => [
        ],
        'namespaces' => [
        ],
    ],
    
    'cache_store' => '',
    'cache_prefix' => 'annotation:',
    'cache_ttl' => 86400,
    
    'log_channel' => 'default',
    
    'cron_monitor' => [
        'enable' => true,
        'check_interval' => 60,
        'auto_recovery' => true,
        'max_failures' => 3,
    ],
    
    'channel' => [
        'host' => '127.0.0.1',
        'port' => 2206,
    ],
];
