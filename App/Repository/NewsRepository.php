<?php

namespace App\Repository;

use mysqli;

class NewsRepository {

    // DB 커넥션을 저장하는 멤버 변수 (mysqli 인스턴스)
    private mysqli $db;

    // 생성자: 외부에서 주입한 mysqli 인스턴스를 받아 저장
    public function __construct($db)
    {
        $this->db = $db;
    }


    // ===========================
    // CREATE — News 글 생성
    // ===========================
    public function create(
       array  $params, // 바인딩할 실제 값 리스트
       string $colum,  // INSERT할 컬럼 목록 (title, content, file ...)
       string $values, // VALUES (?, ?, ?) 형태의 place holders
       string $types   // bind_param용 타입 문자열 (예: 'sss')
    ):bool {

        $stmt = $this->db->prepare("INSERT INTO News 
                                ($colum) 
                                VALUES ($values)");
        // 가변 인자 바인딩 (...$params)
        $stmt->bind_param($types,...$params);
        $stmt->execute();

        // INSERT 성공 여부 → affected_rows > 0이면 true
        return $stmt->affected_rows > 0;
    }


    // =======================
    // INDEX — 전체 News 조회
    // =======================
    public function index(
        ?int $limit = null // null이면 전체, 정수면 제한된 개수만 조회
    ):array {

        //  - 최신 글(news_id DESC) 기준 정렬
        $sql = "SELECT * FROM News ORDER BY news_id DESC";
        
        //  limit 파라미터가 있을 경우 LIMIT 추가
        if ($limit !== null) {
            $sql .= " LIMIT ?";
        }

        $stmt = $this->db->prepare($sql);
        
        // LIMIT 값 바인딩
        if ($limit !== null) {
            $stmt->bind_param('i', $limit);
        }

        $stmt->execute();

        // 전체 행을 연관 배열(MYSQLI_ASSOC)로 반환
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }


    // ==========================
    // SHOW — 단일 News 조회
    // ==========================
    public function show(
        int $newsId
    ):array {

        $stmt = $this->db->prepare("SELECT * FROM News WHERE news_id=?"); 
        $stmt->bind_param('i',$newsId);
        $stmt->execute();
        
        // 단일 레코드만 반환
        return $stmt->get_result()->fetch_assoc();
    }


    // ===============================
    //  UPDATE — 제목과 내용만 수정
    // ===============================
    public function updateTextOnly(
        int    $newsId, 
        string $title,
        string $content
    ):bool {

        $stmt = $this->db->prepare("UPDATE News 
                            SET title = ?, content = ? WHERE news_id=?");

        $stmt->bind_param('ssi', $title, $content, $newsId);
        $stmt->execute();

        // UPDATE가 실제 반영된 경우에만 true
        return $stmt->affected_rows > 0;
    }


    // =======================================
    // UPDATE IMAGE — 이미지 파일(url/key)만 변경
    // - 기존 이미지 삭제는 Controller에서 처리
    // =======================================
    public function updateImageOnly(
        int    $newsId,
        string $newUrl,
        string $newKey
    ):bool {

        $stmt2 = $this->db->prepare(
            "UPDATE News SET file = ?, file_key = ? WHERE news_id = ?"
        );
        $stmt2->bind_param('ssi', $newUrl, $newKey, $newsId);
        
        // 실행 성공 여부 반환
        return $stmt2->execute();
    }


    // ===========================================
    // 파일 키 조회 — 삭제 전에 R2 파일 삭제용으로 사용
    // ===========================================
    public function findFileKey(
        int $newsId 
    ): ?string { // 존재하지 않으면 null 반환

        $stmt = $this->db->prepare("SELECT file_key FROM News WHERE news_id = ?");
        $stmt->bind_param('i', $newsId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        // file_key가 null일 수도 있으니 null-safe 처리
        return $row['file_key'] ?? null;
    }


    // =======================
    // DELETE — 뉴스 글 삭제
    // =======================
    public function delete(
        int $newsId
    ): int {
        
        $stmt = $this->db->prepare("DELETE FROM News WHERE news_id =?");
        $stmt->bind_param('i', $newsId);
        $stmt->execute();

        // 1이면 삭제 성공, 0이면 해당 row 없음
        return $stmt->affected_rows;
    }
}