<?php
declare(strict_types=1);

// 회원 가입 
function registerReservation(AltoRouter $router): void {
    
    // ===============================================
    // 자기 예약 정보 보기(login필수)(designer, client)
    // =============================================== 
    $router->map('GET', "/reservation", [
                'controller' => 'ReservationController',
                'action'     => 'show',
                'middleware' => ['login', 'designer_or_client']    
            ]); 


    // ===========================
    // 예약하기(login필수)( client)
    // ===========================        
    $router->map('POST', "/reservation",[
                'controller' => 'ReservationController',
                'action'     => 'create',
                'middleware' => ['login', 'client'] 
            ]); 


    // =====================================================
    // 예약 cancel , status 수정(login필수)(designer, client)
    // =====================================================         
    $router->map('PUT', "/reservation/update/[a:reservation_id]",[
                'controller' => 'ReservationController',
                'action'     => 'update',
                'middleware' => ['login', 'designer_or_client'] 
            ]); 


    $router->map('GET', "/reservation/designer", [
                    'controller' => 'ReservationController',
                    'action'     => 'designerDetail',
                    'middleware' => ['login', 'client']    
                ]); 
}
?>