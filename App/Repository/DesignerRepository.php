<?php

namespace App\Repository;

use mysqli;

class DesignerRepository {

    private mysqli $db;

    // 생성자: DB 연결 객체를 받아 Repository 내부에서 사용
    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }


    // ===============================
    // 'GET' -> Designer정보 전체 보기
    // ===============================
    public function index():array {

        // Designer + Users JOIN해서 전체 정보 조회
        $stmt = $this->db->prepare("SELECT 
                                    d.designer_id,
                                    d.user_id,
                                    u.user_name,
                                    d.image,
                                    d.image_key,
                                    d.experience,
                                    d.good_at,
                                    d.personality,
                                    d.message
                                    FROM Designer AS d
                                    JOIN Users AS u
                                        ON d.user_id = u.user_id
                                    ORDER BY designer_id DESC");
        $stmt->execute();

        // 전체 목록을 연관배열로 반환
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }


    // ===============================
    // 'GET' -> 해당 Designer정보 보기
    // =============================== 
    public function show(
        int $designerId
    ):array {

        $stmt = $this->db->prepare("SELECT
                                d.designer_id,
                                u.user_name,
                                d.image,
                                d.image_key,
                                d.experience,
                                d.good_at,
                                d.personality,
                                d.message
                                FROM Designer AS d
                                JOIN Users AS u
                                    ON d.user_id = u.user_id
                                WHERE d.designer_id=?");
        $stmt->bind_param('i',$designerId);
        $stmt->execute();
        
        // 행 하나만 반환
        return $stmt->get_result()->fetch_assoc();
    }


    // ===============================
    // 'POST' -> Designer정보 작성
    // ===============================
    public function create(
        int    $userId, 
        string $imageUrl,
        string $imageKey,
        int    $experience, 
        string $good_at,
        string $personality,
        string $message
    ):bool {

        $stmt = $this->db->prepare("INSERT INTO Designer
                                (user_id, image, image_key, experience, good_at, personality, message)
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ississs', 
                    $userId, $imageUrl, $imageKey, $experience, $good_at, $personality, $message);
        $stmt->execute();

        // INSERT 성공 여부 반환
        return $stmt->affected_rows > 0;
    }


    
    // ====================================================================
    // 텍스트 정보만 수정 ('experience', 'good_at', 'personality', 'message')
    // ====================================================================
    public function updateTextOnly(
        int    $designerId,
        int    $experience,
        string $goodAt,
        string $personality,
        string $message
    ):bool {

        $stmt = $this->db->prepare("UPDATE Designer 
                                    SET experience =?, good_at =?, personality =?, message =?
                                    WHERE designer_id = ?");
        $stmt->bind_param('isssi', $experience, $goodAt, $personality, $message, $designerId);
        $stmt->execute();

        // 수정된 row 수(1이면 성공)
        return $stmt->affected_rows > 0;
    }


    // ===========================================
    // 추가: 이미지만 교체하는 엔드포인트(원하면 사용)
    // ===========================================
    public function updateImageOnly(
        int    $designerId,
        string $newUrl,
        string $newKey,  
    ):bool {

        $stmt = $this->db->prepare("UPDATE Designer SET 
                                    image =?, image_key =? WHERE designer_id =?");
        $stmt->bind_param('ssi', $newUrl, $newKey, $designerId);
        
        // 실행 성공 여부 반환
        return $stmt->execute();
    }


    // ===========================================
    // 파일 키 조회 — 삭제 전에 R2 파일 삭제용으로 사용
    // ===========================================
    public function findFileKey(
        int $designerId 
    ): ?string { // 존재하지 않으면 null 반환

        $stmt = $this->db->prepare("SELECT image_key 
                                    FROM Designer WHERE designer_id =?");
        $stmt->bind_param('i', $designerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        // file_key가 null일 수도 있으니 null-safe 처리
        return $row['file_key'] ?? null;
    }


    // =======================
    // DELETE — Designer 삭제
    // =======================
    public function delete(
        int $designerId
    ): int {
        
        $stmt = $this->db->prepare("DELETE FROM Designer WHERE designer_id =?");
        $stmt->bind_param('i', $designerId);
        $stmt->execute();

        // 1이면 삭제 성공, 0이면 해당 row 없음
        return $stmt->affected_rows;
    }
}