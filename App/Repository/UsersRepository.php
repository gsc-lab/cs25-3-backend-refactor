<?php

namespace App\Repository;

use mysqli;

class UsersRepository {

    private mysqli $db;

    // 생성자: DB 연결 객체를 받아 Repository 내부에서 사용
    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }


    // ===========================
    // 회원 정보 보기 (단일 조회)
    // ===========================
    // user_id를 기준으로 회원 한 명의 정보를 가져옴
    // 결과가 있으면 연관배열(array)을 반환하고,
    // 없으면 null을 반환한다.
    public function show(
        int $user_id
    ):?array {
        
        $stmt = $this->db->prepare("SELECT user_name, gender, phone, birth, created_at
                                         FROM Users WHERE user_id=?");
        $stmt->bind_param('i',$user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc() ?: null;
    }
    

    // =========================================
    // account 중복 검사  
    // =========================================
    // account 값이 이미 DB에 존재하면 true,
    // 존재하지 않으면 false를 반환
    public function accountCheck(
        string $account
        ): bool{
            
        $stmt = $this->db->prepare("SELECT 1 FROM Users WHERE account=? LIMIT 1");
        $stmt->bind_param('s', $account);
        $stmt->execute();
        $result = $stmt->get_result();

        // num_rows === 1 → 존재함(true)
        return $result->num_rows === 1;
    } 


    // ===========================
    // 회원 가입 (INSERT)
    // ===========================
    // INSERT 실행 후 실제로 추가된 행이 있으면 true 반환
    public function create(
        string $account,
        string $password_hash,
        string $user_name,
        string $role,
        string $gender,
        string $phone,
        string $birth
    ):bool {

        $stmt = $this->db->prepare("INSERT INTO Users
                                        (account, password, user_name, role, gender, phone, birth)
                                        VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssssss', 
                        $account, $password_hash, $user_name, $role, $gender, $phone, $birth);
        $stmt->execute();

        // affected_rows > 0 → 성공적으로 삽입됨
        return $stmt->affected_rows > 0;
    }


    // ===========================
    // 회원 정보 수정 (UPDATE)
    // ===========================
    // 업데이트된 행 수(0 또는 1)를 반환
    // 0 → 수정 대상이 없거나, 값이 동일함 (변동 없음)
    // 1 → 정상적으로 수정됨
    public function update(
        int $user_id,
        string $account,
        string $password_hash,
        string $user_name,
        string $phone
    ):int {

        $stmt = $this->db->prepare("UPDATE Users SET 
                                account = ?, password = ?, user_name = ?, phone = ?
                             WHERE user_id = ?");
        $stmt->bind_param('ssssi', 
                        $account, $password_hash, $user_name, $phone, $user_id);
        $stmt->execute();

        // 변경된 행 수 반환
        return $stmt->affected_rows;
    }


    // ===========================
    // 회원 탈퇴 (DELETE)
    // ===========================
    // 삭제된 행 수 반환 (0 또는 1)
    public function delete(
        int $user_id
    ):int {
        
        $stmt = $this->db->prepare("DELETE FROM Users WHERE user_id=?");
        $stmt->bind_param('i',$user_id);
        $stmt->execute();

        return $stmt->affected_rows;
    }


    // ===========================
    // 로그인 (SELECT)
    // ===========================
    // account + role 로 사용자 조회
    // 존재하지않음 -> null , 존재함 -> 배열 반환 
    public function login(
        string $account,
        string $role
    ):?array{

        $stmt = $this->db->prepare("SELECT 
                                    user_name, user_id, role, password, account 
                                    FROM Users 
                                    WHERE account=? AND role=?");
        $stmt->bind_param('ss',$account, $role);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc() ?: null;
    }


    /**
     * 현재 password chack
     * @param string $account accountID
     * @return string         등록된 password 반환 
     */
    public function currentPasswordChack(
        string $account
    ): string {

        $stmt = $this->db->prepare("SELECT password FROM Users WHERE account=?");
        $stmt->bind_param('s', $account);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        return $result["password"];

    }

}