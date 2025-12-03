<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use X2nx\WebmanAnnotation\Container\InjectionContainer;

// Use extended container that auto-injects properties
// This extends Webman's Container and overrides make() to inject properties
// after instantiation, ensuring injection works even when Webman re-wraps
// controller calls in App::getCallback() when controller_reuse is false
$config = config('plugin.x2nx/webman-annotation.app', []);
if ($config['enable_value_injection'] ?? true) {
    return new InjectionContainer();
}

// Fallback to default container if injection is disabled
return new Webman\Container;