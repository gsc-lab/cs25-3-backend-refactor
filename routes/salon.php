<?php

declare(strict_types=1);

// salon
function registerSalon(AltoRouter $router): void {

    // ======================
    // salon 정보 보기 (누구나)
    // ======================
    $router->map(
        'GET',
        '/salon',
        [
            'controller' => 'SalonController',
            'action'     => 'index',
            'middleware' => []
        ]
    );

    // ================================
    // salon TEXT 정보 수정 (login + manager)
    // ================================
    $router->map(
        'PUT',
        '/salon/update',
        [
            'controller' => 'SalonController',
            'action'     => 'update',
            'middleware' => ['login', 'manager']
        ]
    );


    // image정보 수정 (login + manager)
    $router->map(
        'POST',
        '/salon/image',
        [
            'controller' => 'SalonController',
            'action'     => 'updateImage',
            'middleware' => ['login','manager']
        ]
    );
}
