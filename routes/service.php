<?php
    declare(strict_types=1);

    function registerService(AltoRouter $router) :void {
        
        // ==========================
        // service 정보 보기 (누구나 ok)
        // ==========================
        $router->map('GET', "/service",
            [   'controller' => 'ServiceController',
                        'action'     => 'index',
                        'middleware' => []
                    ]); 
        
        // =================================
        // service 정보 작성 (login, manager)
        // =================================
        $router->map('POST', "/service/create",
         [  'controller' => 'ServiceController',
                    'action'     => 'create',
                    'middleware' => ['login', 'manager']
                ]);

        // ==================================
        // service 정보 수성 (login, manager)
        // ==================================
        $router->map('PUT', "/service/update/[a:service_id]",
        [   'controller' => 'ServiceController',
                    'action'     => 'update',
                    'middleware' => ['login', 'manager']
                ]);

        // =================================
        // service 정보 삭제 (login, manager)
        // =================================
        $router->map('DELETE',"/service/delete/[a:service_id]",
                [   'controller' =>  'ServiceController',
                    'action'     => 'delete',
                    'middleware' => ['login', 'manager']
                ]);


        $router->map('GET',"/service/[a:service_id]",
                [   'controller' =>  'ServiceController',
                    'action'     => 'show',
                    'middleware' => []
                ]);
    }
?>