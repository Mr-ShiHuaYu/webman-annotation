<?php

return [
    'cron-monitor' => [
        'handler' => \X2nx\WebmanAnnotation\Process\CronMonitor::class,
        'count' => 1,
        'reloadable' => false,
        'constructor' => [],
    ],
];

