<?php

use X2nx\WebmanAnnotation\Registrar\RouteRegistrar;

$config = config('plugin.x2nx.webman-annotation.app', []);
if (!($config['enable'] ?? true) || !($config['auto_register_routes'] ?? true)) {
    return;
}

RouteRegistrar::register();
