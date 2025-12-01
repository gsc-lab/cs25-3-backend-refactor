<?php

namespace App\Controllers;

use App\Repository\UsersRepository;
use App\Errors\ErrorHandler;
use Throwable;

// DB 로딩
require_once __DIR__."/../db.php";
// http.php 불러오기
require_once __DIR__."/../http.php";

class UsersController {


    // ======================
    // 'GET' -> 회원 정보 보기
    // ======================
    public function show() :void {
        
        $user_id = $_SESSION['user']['user_id'];

        try {
            // DB접속
            $db = get_db();
            $repo = new UsersRepository($db);
            $result = $repo->show($user_id);
            
            if($result === null){ 
                json_response([
                    'success' => false,
                    'error' => ['code' => 'USER_NOT_FOUND', 
                                'message' => '해당 회원을 찾을 수 없습니다.']
                ], 404);
                return;
            }
            
            json_response([
                'success' => true,
                'data' => ['user' => $result]
            ]);
        
            // 오류시 
        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e,'[users_show]'), 500);
        }     
    }

    // ====================
    // 'POST' -> 회원 가입
    // ====================  
    public function create() :void {
        try{
            // 값을 받기
            $data = read_json_body();

            // 입력 값 꺼내기
            $account      = isset($data['account']) ? trim((string)($data['account'])) : '';
            $password_raw = isset($data['password']) ? trim((string)($data['password'])) : '' ;
            $user_name    = isset($data['user_name']) ? trim((string)($data['user_name'])) : '';
            $role         = isset($data['role']) ? trim((string)($data['role'])) : '';
            $gender       = isset($data['gender']) ? trim((string)($data['gender'])) : '';
            $phone        = isset($data['phone']) ? trim((string)($data['phone'])) : '';
            $birth        = isset($data['birth']) ? trim((string)($data['birth'])) : '';

            // 유호성 확인
            if ($account === '' || $password_raw === '' || $user_name === '' ||
                $role === '' || $gender === '' || $birth === '') {
                // 유호하지 않으면 json_responce 반환
                echo json_response([
                    'success' => false,
                    'error' => ['code' => 'VALIDATION_ERROR',
                                'message' => '필수 필드가 비었습니다.']
                ], 400);
                return;
            }

            // DB접속
            $db = get_db();
            $repo = new UsersRepository($db);
            $result = $repo->accountCheck($account);

            // 중복된 account 여부를 확인
            if ($result){
                echo json_response([
                    'success' => false,
                    'error' => ['code' => 'ACCOUNT_DUPLICATED',
                                'message' => '중복된 ID입니다.']
                ], 409);
                return;
            }
            
            // 없으면 password hash처리해 저장
            $password_hash = password_hash($password_raw, PASSWORD_DEFAULT);
            $repo->create($account, $password_hash, 
                                    $user_name, $role, $gender, $phone, $birth);
        
            json_response([
                'success' => true
            ], 201);
            

        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e,'[users_create]'), 500);
        }
    } 


    // ======================
    // 'PUT' => 회원 정부 수정
    // ======================
    public function update() :void {

        $user_id = $_SESSION['user']['user_id'];

        // Reqest버디(JSON)를 배열으로 받는다
        $data = read_json_body();

        $account      = isset($data['account']) ? trim((string)($data['account'])) : '';
        $password_raw = isset($data['password']) ? trim((string)($data['password'])) : '' ;
        $user_name    = isset($data['user_name']) ? trim((string)($data['user_name'])) : '';
        $phone        = isset($data['phone']) ? trim((string)($data['phone'])) : '';
        
        // 유호성 확인
        if ($account === '' || $password_raw === '' || $user_name === '') {
            // 유호하지 않으면 json_responce 반환
            echo json_response([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR',
                            'message' => '필수 필드가 비었습니다.']
            ], 400);
            return;
        }

        $password_hash = password_hash($password_raw, PASSWORD_DEFAULT);

        try {
            // DB접속
            $db   = get_db();
            $repo = new UsersRepository($db);
            $result = $repo->update($user_id, $account, 
                $password_hash, $user_name, $phone);

            if ($result === 0){
                json_response([
                    "success" => false,
                    "error" => ['code' => 'NO_CHANGES_APPLIED',
                                'message' => '수정된 내용이 없습니다.']
                ], 409);
                return;
            }

            json_response([
                'success' => true,
                'message' => '수정 성공했습니다.'
            ]);

        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e,'[users_update]'), 500);
        }
    }

    // =================
    // 'DELETE' =>  탈퇴
    // =================
    public function delete() :void {

        $user_id = $_SESSION['user']['user_id'];

        try{
            // db 접속
            $db = get_db();
            $repo = new UsersRepository($db);
            $result = $repo->delete($user_id);

            if ($result <= 0) {
                json_response([
                     "success" => false,
                     "error" => [
                        'code'    => 'RESOURCE_NOT_FOUND',
                        'message' => '삭제할 데이터를 찾을 수 없습니다.'
                    ]
                ], 404);
                return;
            }

            http_response_code(204);
 
        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e,'[users_delete]'), 500);
        }      
    } 


    // ================
    // 'POST' => login
    // ================
    public function login(): void {

        // JSON데이터를 받는다
        $data = read_json_body();

        $account      = isset($data['account']) ? trim((string)($data['account'])) : '';
        $password     = isset($data['password']) ? trim((string)($data['password'])) : '' ;
        $role         = isset($data['role']) ? trim((string)($data['role'])) : '';
        
        // 필수 필드가 비었습니다.
        if ($account === '' || $password === '' || $role === '') {
            echo json_response([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR',
                            'message' => '필수 필드가 비었습니다.']
            ], 401);
            return;
        }

        // DB접속
        $db = get_db();
        $repo = new UsersRepository($db);
        $result = $repo->login($account, $role);

        if ($result === null) {
            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'AUTHENTICATION_FAILED',
                    'message' => 'ID가 일치하지 않습니다.'
                ]
            ], 401);
            return;
        }

        // 비밀번호 비겨
        if (!password_verify($password , $result['password'])) {
            echo json_response([
                'success' => false,
                'error' => ['code' => 'WRONG_PASSWORD',
                            'message' => '비밀번호가 일치하지 않습니다.']
            ], 401);
            return;
        }

        // 성공하면 SESSION에 저장
        $_SESSION['user'] = [
            'user_id'   => (int)$result['user_id'],
            'account'   => $result['account'],
            'role'      => $result['role'],
            'user_name' => $result['user_name']
        ];

        json_response([
            'success' => true,
            'data' => [
                'user' => [
                    'user_id'   => (int)$result['user_id'],
                    'account'   => $result['account'],
                    'role'      => $result['role'],
                    'user_name' => $result['user_name']
                ]
            ]
        ]);
    }


    // ==================
    // 'delete' => logout
    // ==================
    public function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // 세션 값 초기화
        $_SESSION = [];

        // 쿠키 제거
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $p['path'], $p['domain'],
                $p['secure'], $p['httponly']
            );
        }

        session_destroy();

        // 꼭 200 으로! 204 절대 금지!
        json_response([
            'success' => true,
            'message' => '로그아웃 완료'
        ], 200);
    }
}
?>