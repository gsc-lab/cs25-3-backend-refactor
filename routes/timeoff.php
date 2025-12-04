<?php
declare(strict_types=1);

// 회원 가입 
function registerTimeoff(AltoRouter $router): void {


    // =======================================
    // designer 휴무 정보 보기 (login) (manager)
    // =======================================  
    $router->map('GET', "/timeoff",[
                'controller' => 'TimeoffController',
                'action'     => 'index',
                'middleware' => []
            ]); 


    // =======================================
    // designer 휴무 작성 (login) (manager)
    // =======================================        
    $router->map('POST', "/timeoff/create",[
                'controller' => 'TimeoffController',
                'action'     => 'create',
                'middleware' => ['login', 'manager']
            ]); 


    // =======================================
     // designer 휴무 수정 (login) (manager)
    // ======================================= 
    $router->map('PUT', "/timeoff/update/[a:to_id]",[
                'controller' => 'TimeoffController',
                'action'     => 'update',
                'middleware' => ['login', 'manager']
            ]);
            
            
    // ===================================
    // designer 휴무 삭제 (login) (manager)
    //====================================
    $router->map('DELETE', "/timeoff/delete/[a:to_id]",[
                'controller' => 'TimeoffController',
                'action'     => 'delete',
                'middleware' => ['login', 'manager']
    ]);
}

?>