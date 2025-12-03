<?php

namespace App\Repository;

use mysqli;

class HairstyleRepository {

    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }


    // =====================
    // GET /hairstyle
    // 전체 목록
    // ====================
    public function index(
       ?int $limit = null
    ):array{

        $sql = "SELECT * FROM HairStyle ORDER BY hair_id DESC";

        if ($limit !== null) {
            $sql .= " LIMIT ?";
        }
            
        $stmt = $this->db->prepare($sql);
            
        if ($limit !== null) {
            $stmt->bind_param('i', $limit);
        }
        
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }



    // ============================
    // 'GET' => 특정 HairStyle 조회
    // ============================
    public function show(
        int $hairId
    ):array {

        $stmt = $this->db->prepare("SELECT * FROM HairStyle WHERE hair_id = ?");
        $stmt->bind_param('i', $hairId);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc();
    }


    // ======================================================
    // POST /hairstyle/create
    // 새 헤어스타일 등록 (이미지 업로드 포함)
    // - body: multipart/form-data (title, description, image)
    //=======================================================
    public function create(
        string $title, 
        string $imageUrl,
        string $imageKey,
        string $description
    ):int {

        $stmt = $this->db->prepare("INSERT INTO HairStyle 
                            (title, image, image_key, description)
                            VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $title, $imageUrl, $imageKey, $description);
        $stmt->execute();
        $insertId = $stmt->insert_id;

        return $insertId;
    }


    // ========================================
    // PUT /hairstyle/update/{hair_id}
    // 텍스트 정보만 수정 (title, description)
    // ========================================
    public function updateTextOnly(
        int    $hairId,
        string $title,
        string $description
    ):bool {

        $stmt = $this->db->prepare("UPDATE HairStyle 
                                    SET title =?, description =? WHERE hair_id = ?");
        $stmt->bind_param('ssi', $title, $description, $hairId);
        $stmt->execute();

        return $stmt->affected_rows > 0;
    }


    // ===========================================
    // 추가: 이미지만 교체하는 엔드포인트(원하면 사용)
    // ===========================================
    public function updateImageOnly(
        int    $hairId,
        string $newUrl,
        string $newKey, 
        
    ):bool {

        $stmt = $this->db->prepare("UPDATE HairStyle SET 
                                    image =?, image_key =? WHERE hair_id =?");
        $stmt->bind_param('ssi', $newUrl, $newKey, $hairId);
        
        // 실행 성공 여부 반환
        return $stmt->execute();
    }


    // ===========================================
    // 파일 키 조회 — 삭제 전에 R2 파일 삭제용으로 사용
    // ===========================================
    public function findFileKey(
        int $hairId 
    ): ?string { // 존재하지 않으면 null 반환

        $stmt = $this->db->prepare("SELECT image_key FROM HairStyle WHERE hairId =?");
        $stmt->bind_param('i', $hairId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        // file_key가 null일 수도 있으니 null-safe 처리
        return $row['file_key'] ?? null;
    }


    // =======================
    // DELETE — HairStyle 삭제
    // =======================
    public function delete(
        int $hairId
    ): int {
        
        $stmt = $this->db->prepare("DELETE FROM HairStyle WHERE hair_id =?");
        $stmt->bind_param('i', $hairId);
        $stmt->execute();

        // 1이면 삭제 성공, 0이면 해당 row 없음
        return $stmt->affected_rows;
    }

}