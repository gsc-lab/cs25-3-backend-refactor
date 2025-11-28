<?php
declare(strict_types=1);

// 회원 가입 
function registerUsers(AltoRouter $router): void {
    
    // ========================
    // 회원 정보 보기 (login필수)
    // ========================
    $router->map('GET', "/users",
     [  'controller' => 'UsersController',
                'action'     => 'show',
                'middleware' => []
            ]);
    
    // ================
    // 회원 가입 (guest)
    // ================
    $router->map('POST', "/users/create",
    [   'controller' => 'UsersController',
                'action'     => 'create',
                'middleware' => []
            ]); 
    
    // =============
    // 회원 정보 수정 
    // =============
    $router->map('PUT', "/users/update",
    [   'controller' => 'UsersController',
                'action'     => 'update',
                'middleware' => ['login']
            ]);

    // ============================
    // 회원 탈퇴 (login필수)(client 만)
    // ============================
    $router->map('DELETE', "/users/delete",
     [  'controller' => 'UsersController',
                'action'     => 'delete',
                'middleware' => ['login', 'client']
            ]); 
            
    // ==============
    // 로그인 (guest)
    // ==============
    $router->map('POST', "/users/login",
     [  'controller' => 'UsersController',
                'action'     => 'login',
                'middleware' => ['guest']
            ]);  
    
    // ==================
    // 로그아웃
    // ==================
    $router->map('POST', "/users/logout", 
    [   'controller' => 'UsersController',
                'action'     => 'logout',
                'middleware' => []]);
}

?>