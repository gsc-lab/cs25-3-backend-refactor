<?php

declare(strict_types=1);

function registerDesigner(AltoRouter $router):void{
    
    // ============================
    // Designer정보 전체 보기 (누구나)
    // ============================
    $router->map("GET", '/designer', [
                'controller' => 'DesignerController', 
                'action'     => 'index',
                'middleware' => []
            ]);
                
    // ===============================
    // 해당하는 Designer정보 보기 
    // ===============================
    $router->map("GET", '/designer/[a:designer_id]',[
                'controller' => 'DesignerController',
                'action'     => 'show',
                'middleware' => ['login','designer']
            ]);
            
    // =============================================
    // designer 프로필 작성하기 (login필수)(designer만)
    // =============================================     
    $router->map("POST", '/designer/create',[
                'controller' => 'DesignerController',
                'action'     => 'create',
                'middleware' => ['login', 'designer']
            ]);
           
    // ===============================================
    // 해당하는 Designer정보 수정 (login필수)(designer만)
    // ===============================================
    $router->map("PUT", '/designer/update/[a:designer_id]',[
                'controller' => 'DesignerController',
                'action'     => 'update',
                'middleware' => ['login', 'designer']
            ]); 


    // ===============================================
    // 해당하는 Designer정보 삭제 (login필수)(manager만)
    // ===============================================        
    $router->map("DELETE", '/designer/delete/[a:designer_id]',[
                'controller' => 'DesignerController',
                'action'     => 'delete',
                'middleware' => ['login', 'manager']
            ]); // 해당하는 Designer정보 삭제


    // ===============================================
    // 이미지 업데이트 (login필수)(designer만)
    // =============================================== 
    $router->map("POST", '/designer/image/[a:designer_id]', [
                'controller' => 'DesignerController',
                'action'     => 'updateImage',
                'middleware' => ['login', 'designer']
                ]);  // 이미지 올리기
}
?>