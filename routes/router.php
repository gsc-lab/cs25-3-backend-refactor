<?php
declare(strict_types=1);

require_once __DIR__.'/users.php';
require_once __DIR__.'/news.php';
require_once __DIR__.'/service.php';
require_once __DIR__.'/designer.php';
require_once __DIR__.'/hairstyle.php';
require_once __DIR__.'/salon.php';
require_once __DIR__.'/timeoff.php';
require_once __DIR__.'/reservation.php';


function registerAllRoutes(AltoRouter $router): void {
    $router->map('GET', '/', fn() => 'Home Page');
    registerUsers($router);
    registerNews($router);
    registerService($router);
    registerDesigner($router);
    registerHairstyle($router);
    registerTimeoff($router);
    registerSalon($router);
    registerReservation($router);
}
