<?php

declare(strict_types=1);

function registerHairstyle(AltoRouter $router) :void {
    
    // =========================
    // hairstyle 전체 보기(누구나)
    // =========================
    $router->map("GET", '/hairstyle',[
                    'controller'  => 'HairstyleController',
                    'action'     => 'index',
                    'middleware' => []
                ]); 

    
    // =========================
    // hairstyle 세부 보기(누구나)
    // =========================
    $router->map("GET", '/hairstyle/[a:hairstyle_id]',[
                'controller' => 'HairstyleController',
                'action'     => 'show',
                'middleware' => []
            ]);
            
    // =================================
    // hairstyle 작성 (login필수)(manager)
    // =================================        
    $router->map("POST", '/hairstyle/create', [
                'controller'  => 'HairstyleController',
                'action'     => 'create',
                'middleware' => ['login', 'manager']
            ]); 
            
            
    // ========================================
    // hairstyle 이미지 수정 (login필수)(manager)
    // ========================================          
    $router->map("POST", '/hairstyle/image/[a:hairstyle_id]',[
                'controller' => 'HairstyleController',
                'action'     => 'updateImage',
                'middleware' => ['login', 'manager']
            ]);  

    // ================================================
    // 특정 hairstyle title, description 수정 (login필수)(manager)
    // ================================================
    $router->map("PUT", '/hairstyle/update/[a:hairstyle_id]',[
                'controller' => 'HairstyleController',
                'action'     => 'update',
                'middleware' => ['login', 'manager']
            ]); 


    // ==========================================
    // 특정 hairstyle 내용 삭제 (login필수)(manager)
    // ==========================================         
    $router->map("DELETE", '/hairstyle/delete/[a:hairstyle_id]', [
                'controller' => 'HairstyleController',
                'action'     => 'delete',
                'middleware' => ['login', 'manager']
            ]);    
}

?>