<?php

namespace App\Repository;

use mysqli;

class SalonRepository {

    private mysqli $db;

    // 생성자: DB 연결 객체를 받아 Repository 내부에서 사용
    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }


    // ============================
    // Salon 전체 정보 읽기 (SELECT)
    // ============================
    public function index():array {

        $stmt = $this->db->prepare("SELECT * FROM Salon");
        $stmt->execute();
        $result = $stmt->get_result();
        
        // 단일 행을 연관 배열로 반환
        return $result->fetch_assoc();
    }


    // ==========================================
    // 텍스트 정보만 업데이트 (이미지 변경 없음)
    // ==========================================
    public function updateTextOnly(
        string $intro,
        string $info,
        string $traffic
    ):bool {

        $stmt = $this->db->prepare("UPDATE Salon SET
                                    introduction = ?,
                                    information  = ?,
                                    traffic      = ?
                                    ");
        $stmt->bind_param('sss',
                    $intro,$info, $traffic);
        

        // execute()는 true/false 반환
        return $stmt->execute();
    }


    // =======================
    // 이미지 정보만 업데이트
    // =======================
    public function updateImage(
        string $newUrl,
        string $newKey
    ):bool {

        $stmt = $this->db->prepare("UPDATE Salon SET image = ?, image_key = ?");
        $stmt->bind_param('ss', $newUrl, $newKey);
        
        // execute()는 true/false 반환
        return $stmt->execute();
    }
}