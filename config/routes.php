<?php
/**
 * Routes configuration
 *
 * In this file, you set up routes to your controllers and their actions.
 * Routes are very important mechanism that allows you to freely connect
 * different URLs to chosen controllers and their actions (functions).
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

use Cake\Core\Plugin;
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;
use Cake\Routing\Route\DashedRoute;

/**
 * The default class to use for all routes
 *
 * The following route classes are supplied with CakePHP and are appropriate
 * to set as the default:
 *
 * - Route
 * - InflectedRoute
 * - DashedRoute
 *
 * If no call is made to `Router::defaultRouteClass()`, the class used is
 * `Route` (`Cake\Routing\Route\Route`)
 *
 * Note that `Route` does not do any inflections on URLs which will result in
 * inconsistently cased URLs when used with `:plugin`, `:controller` and
 * `:action` markers.
 *
 */
Router::defaultRouteClass(DashedRoute::class);

Router::scope('/', function (RouteBuilder $routes) {
    $routes->connect('/', ['controller' => 'Main', 'action' => 'index']);
    $routes->connect('/address/*', ['controller' => 'Main', 'action' => 'address']);
    $routes->connect('/blocks/*', ['controller' => 'Main', 'action' => 'blocks']);
    $routes->connect('/claims/*', ['controller' => 'Main', 'action' => 'claims']);
    $routes->connect('/find', ['controller' => 'Main', 'action' => 'find']);
    $routes->connect('/realtime', ['controller' => 'Main', 'action' => 'realtime']);
    $routes->connect('/tx/*', ['controller' => 'Main', 'action' => 'tx']);
    $routes->connect('/qr/*', ['controller' => 'Main', 'action' => 'qr']);

    $routes->connect('/api/v1/address/:addr/tag', ['controller' => 'Main', 'action' => 'apiaddrtag'], ['addr' => '[A-Za-z0-9,]+', 'pass' => ['addr']]);
    $routes->connect('/api/v1/address/:addr/utxo', ['controller' => 'Main', 'action' => 'apiaddrutxo'], ['addr' => '[A-Za-z0-9,]+', 'pass' => ['addr']]);
    $routes->connect('/api/v1/address/:addr/transactions', ['controller' => 'Main', 'action' => 'apiaddrtx'], ['addr' => '[A-Za-z0-9,]+', 'pass' => ['addr']]);

    $routes->connect('/api/v1/realtime/blocks', ['controller' => 'Main', 'action' => 'apirealtimeblocks']);
    $routes->connect('/api/v1/realtime/tx', ['controller' => 'Main', 'action' => 'apirealtimetx']);
    $routes->connect('/api/v1/recentblocks', ['controller' => 'Main', 'action' => 'apirecentblocks']);
    $routes->connect('/api/v1/status', ['controller' => 'Main', 'action' => 'apistatus']);
    //$routes->connect('/api/v1/recenttxs', ['controller' => 'Main', 'action' => 'apirecenttxs']);

    //$routes->fallbacks(DashedRoute::class);
});

/**
 * Load all plugin routes. See the Plugin documentation on
 * how to customize the loading of plugin routes.
 */
Plugin::routes();
