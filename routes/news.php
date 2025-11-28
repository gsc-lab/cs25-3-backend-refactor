<?php

declare(strict_types=1);

function registerNews(AltoRouter $router) :void {

    // ======================
    // news 전체 보기 (누구나)
    // ======================  
    $router->map("GET", '/news',[
                'controller' => 'NewsController',
                'action'     => 'index',
                'middleware' => []
            ]);
            
    // ======================
    // news 상세 보기 (누구나)
    // ======================          
    $router->map("GET", '/news/[a:news_id]',[
                'controller' => 'NewsController',
                'action'     => 'show',
                'middleware' => []
            ]); 

    // ===========================
    // news작성 (login필수)(manager)
    // ===========================              
    $router->map("POST", '/news/create', [
                'controller' => 'NewsController',
                'action'     => 'create',
                'middleware' => ['login', 'manager']
            ]); 


    // ===========================
    // news수정  (login필수)(manager)
    // ===========================         
    $router->map("PUT", '/news/update/[a:news_id]', [
                'controller' => 'NewsController',
                'action'     => 'update',
                'middleware' => ['login', 'manager']
            ]); 


    // ================================
    // news 삭게하기 (login필수)(manager)
    // ================================ 
    $router->map("DELETE", '/news/delete/[a:news_id]',[
                'controller' => 'NewsController',
                'action'     => 'delete',
                'middleware' => ['login', 'manager']
            ]);   

    // ================================
    // news 이미지 업데이트 (login필수)(manager)
    // ================================ 
    $router->map("POST", '/news/image/[a:news_id]',[
                'controller' => 'NewsController',
                'action'     => 'updateImage',
                'middleware' => ['login', 'manager']
            ]);  
}

?>