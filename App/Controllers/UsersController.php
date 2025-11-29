<?php

namespace App\Controllers;

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
            // SQL문 (SELECT)
            $stmt = $db->prepare("SELECT user_name, gender, phone, birth, created_at
                                         FROM Users WHERE user_id=?");
            $stmt->bind_param('i',$user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if($result->num_rows === 0){ 
                json_response([
                    'success' => false,
                    'error' => ['code' => 'USER_NOT_FOUND', 
                                'message' => '해당 회원을 찾을 수 없습니다.']
                ], 404);
                return;
            }
            
            $row = $result->fetch_assoc();
            json_response([
                'success' => true,
                'data' => ['user' => $row]
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
            $stmtSelect = $db->prepare("SELECT 1 FROM Users WHERE account=? LIMIT 1");
            $stmtSelect->bind_param('s', $account);
            $stmtSelect->execute();
            $result = $stmtSelect->get_result();
            
            // 중복된 account 여부를 확인
            if ($result->num_rows > 0){
                echo json_response([
                    'success' => false,
                    'error' => ['code' => 'ACCOUNT_DUPLICATED',
                                'message' => '중복된 ID입니다.']
                ], 409);
                return;
            }
            
            // 없으면 password hash처리해 저장
            $password_hash = password_hash($password_raw, PASSWORD_DEFAULT);
            
            $stmtInsert = $db->prepare("INSERT INTO Users
                                        (account, password, user_name, role, gender, phone, birth)
                                        VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtInsert->bind_param('sssssss', $account, $password_hash, $user_name, $role, $gender, $phone, $birth);
            $stmtInsert->execute();
            

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

        // UPDATE하기 위한 배열
        $sets = [];
        try {
            // DB접속
            $db   = get_db();
            
            foreach ($data as $key => $value) {
                $value = "?";
                $v = $key ."=". $value;
                array_push($sets, $v);
            }

            $stmt = $db->prepare("UPDATE Users SET "
                                . implode(",", $sets).
                                " WHERE user_id = ?");
            $stmt->bind_param('ssssi', $account, $password_hash, $user_name, $phone, $user_id);
            $stmt->execute();

            if ($stmt->affected_rows === 0){
                json_response([
                    "success" => false,
                    "error" => ['code' => 'NO_CHANGES_APPLIED',
                                'message' => '수정된 내용이 없습니다.']
                ], 409);
                return;
            }

            json_response(['ok' => true]);

        
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
            $stmt = $db->prepare("DELETE FROM Users WHERE user_id=?");
            $stmt->bind_param('i',$user_id);
            $stmt->execute();

            http_response_code(204);
            return;
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
        
        
        // DB접속
        $db = get_db();
        // account를 불어오기
        $stmt = $db->prepare("SELECT 
                                    user_name, user_id, role, password, account 
                                    FROM Users 
                                    WHERE account=? AND role=?");
        $stmt->bind_param('ss',$account, $role);
        $stmt->execute();
        $result = $stmt->get_result();

        // 필수 필드가 비었습니다.
        if ($account === '' || $password === '' || $role === '') {
            echo json_response([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR',
                            'message' => '필수 필드가 비었습니다.']
            ], 401);
            return;
        }
        
        // account 일하는지 비겨
        if($result->num_rows === 0){
            echo json_response([
                'success' => false,
                'error' => ['code' => 'AUTHENTICATION_FAILED',
                            'message' => 'ID가 일치하지 않습니다.']
            ], 401);
            return;
        }

        $row = $result->fetch_assoc();

        // 비밀번호 비겨
        if (!password_verify($password , $row['password'])) {
            echo json_response([
                'success' => false,
                'error' => ['code' => 'WRONG_PASSWORD',
                            'message' => '비밀번호가 일치하지 않습니다.']
            ], 401);
            return;
        }

        // 성공하면 SESSION에 저장
        $_SESSION['user'] = [
            'user_id'   => (int)$row['user_id'],
            'account'   => $row['account'],
            'role'      => $row['role'],
            'user_name' => $row['user_name']
        ];

        json_response([
            'success' => true,
            'data' => [
                'user' => [
                    'user_id'   => (int)$row['user_id'],
                    'account'   => $row['account'],
                    'role'      => $row['role'],
                    'user_name' => $row['user_name'],
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