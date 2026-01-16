<?php

namespace App\Services;

use App\Repository\NewsRepository;
use mysqli;

class NewsService {

    /**
    * NewsRepository 인스턴스
    */
    private NewsRepository $repo;

    /**
     * 생성자
     * @param mysqli $db 데이터베이스 연결 객체
     */
    public function __construct(mysqli $db)
    {
        // Repository 객체 생성 및 DB 연결 주입
        $this->repo = new NewsRepository($db);
    }



    /**
     * News 글 생성
     * @param array $params   바인딩할 실제 값 리스트
     * @param string $colum   INSERT할 컬럼 목록 (title, content, file ...)
     * @param string $values  VALUES (?, ?, ?) 형태의 place holders
     * @param string $types   bind_param용 타입 문자열 ('ssss')
     * @return bool
     */
    public function createNews(
        array  $params,
        string $colum,
        string $values, 
        string $types
    ):bool {

       $result = $this->repo->create($params, $colum, $values, $types);

       return $result;
    }


    /**
     * 전체 News 조회
     * @param mixed $limit null이면 전체, 정수면 제한된 개수만 조회
     * @return array
     */
    public function getAllNews(
        ?int $limit = null
    ):array {

        return $this->repo->index($limit);
    
    }


    /**
     * 단일 News 조회
     * @param int $newsId PRYMARY KEY
     * @return array
     */
    public function getNewsById(
        int $newsId
    ):array {

        return $this->repo->show($newsId);

    }


    /**
     * 제목과 내용만 수정
     * @param int $newsId     PRYMARY KEY
     * @param string $title   제목
     * @param string $content 내용
     * @return bool
     */
    public function updateNewsContent(
        int $newsId,
        string $title,
        string $content
    ):bool {

        return $this->repo->updateTextOnly($newsId, $title, $content);
    
    }


    /**
     * 이미지 파일(url/key)만 변경
     * @param int    $newsId   PRYMARY KEY
     * @param string $imageUrl 이미지 URL
     * @param string $imageKey 이미지 key
     * @return bool
     */
    public function updateNewsImage(
        int $newsId,
        string $imageUrl,
        string $imageKey
    ):bool {

        $result = $this->repo->updateImageOnly($newsId, $imageUrl, $imageKey);
    
        return $result;
    }



    /**
     * 파일 키 조회 
     * @param int $newsId   PRYMARY KEY
     * @return string|null  존재하지 않으면 null 반환
     */
    public function getNewsImageKey(
        int $newsId
    ): ?string { 

       $result = $this->repo->findFileKey($newsId);

       return $result ?? null;
    }


    /**
     * DELETE — 뉴스 글 삭제
     * @param int $newsId
     * @return int
     */
    public function deleteNews(
        int $newsId
    ): int {
        
        return $this->repo->delete($newsId);
    
    }


}