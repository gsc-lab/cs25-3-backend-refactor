<?php

namespace App\Controllers;

use App\Errors\ErrorHandler;
use App\Services\ImageService;
use RuntimeException;
use Throwable;

// DB 및 공통 HTTP 응답 함수 불러오기
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../http.php';

class NewsController {

    // ==========================
    // 'POST' -> news 글 작성하기
    // ==========================
    public function create():void{
         
        try {
            // POST로 전달된 title, content 값 가져오기 (없으면 '')
            $title   = isset($_POST['title']) ? (string)($_POST['title']) : '';
            $content = isset($_POST['content']) ? (string)($_POST['content']) : '';

            // 제목과 내용이 빈 문자열인지 검사
            if ($title === '' || $content === '') {
                json_response([
                    'success' => false,
                    'error' => ['code' => 'VALIDATION_ERROR', 
                                'message' => '필수 필드가 비었습니다.'
                ]], 400);
                return;
            }

            // 파일이 전달되었는지 확인
            if (!isset($_FILES['image'])) {
                json_response([
                    'success' => false,
                    'error' => [
                        'code' => 'NO_FILE',
                        'message' => 'image 파일이 전달되지 않았습니다.']
                ], 400);
                return;
            }

            // 업로드된 파일 정보
            $file = $_FILES['image'];

            // MIME 타입 체크 → 이미지인지 검사
            $mime = mime_content_type($file['tmp_name']) ?: '';
            if (strpos($mime, 'image/') !== 0) {
                json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'INVALID_MIME',
                        'message' => '이미지 파일만 업로드할 수 있습니다.',
                        ],
                ], 400);
                return;
            }

            // 이미지 업로드 서비스 호출
            $imageService = new ImageService();
            $uploadResult = $imageService->upload($file, 'news');
            
            // 업로드 후 반환된 파일의 key, url
            $fileKey     = $uploadResult['key'];
            $fileUrl     = $uploadResult['url'];
    
            // DB 접속
            $db = get_db();

            // sql문
            $stmt = $db->prepare("INSERT INTO News 
                                (title,  content, file, file_key) 
                                VALUES (?,?,?, ?)");
            $stmt->bind_param('ssss',$title, $content, $fileUrl, $fileKey);
            $stmt->execute();

            // 프론트엔드에 반환하는 값
            json_response([
                'success' => true
            ],201);

        // 예외 처리
        } catch (RuntimeException $e) {
            error_log('[news_create_runtime]'. $e->getMessage());
            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'UPLOAD_FAILED',
                    'message' => '이미지 업로드에 실패했습니다.'
                ]
            ], 400);
            return;
         } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[news_create]'),500);
        }

    }

    // ==========================
    // 'GET' -> News 글 전체 보기
    // ==========================
    public function index():void
    {
        try {
            $db = get_db();

            // 쿼리스트링 limit 파라미터 처리
            $limit = null;
            if (isset($_GET['limit'])) {
                $limit = filter_var($_GET['limit'], FILTER_VALIDATE_INT);
            }

            // 기본 쿼리
            $sql = "SELECT * FROM News ORDER BY news_id DESC";
            if ($limit !== null) {
                $sql .= " LIMIT ?";
            }

            $stmt = $db->prepare($sql);
            if ($limit !== null) {
                $stmt->bind_param('i', $limit);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            $news = [];
            while ($row = $result->fetch_assoc()) {
                $news[] = $row;
            }

            json_response([
                "success" => true,
                "data"    => ['news' => $news],
            ]);

         } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[news_index]'),500);
        }

    }


    // =========================
    // 'GET' => 해당 글 자세히 보기
    // =========================
    public function show(string $news_id):void {
        
        // news_id를 받는다
        $news_id = filter_var($news_id, FILTER_VALIDATE_INT);

        // 유호성 검중
        if ($news_id === false || $news_id <= 0) {
            json_response([
                 "success" => false,
                 "error" => ['code' => 'INVALID_ID',
                            'message' => '유효하지 않은 ID입니다.']
            ], 400);
            return;
        }
        
        try {
            // DB접속
            $db = get_db();

            // 해당 news 글을 가져오기
            // SQL문
            $stmt = $db->prepare("SELECT * FROM News WHERE news_id=?"); 
            $stmt->bind_param('i',$news_id);
            $stmt->execute();

            // 해당 데이터를 가져 오기
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            // 요청한 news글을 찾을 수 없었을 때
            if (!$row) {
                json_response([
                    "success" => false,
                    "error" => [
                        'code'    => 'RESOURCE_NOT_FOUND',
                        'message' => '요청한 리소스를 찾을 수 없습니다.']
                ], 404);
                return;
            }
            
            $stmt->close();

            // 값을 프런트에 보내기
            json_response([
                "success" => true,
                "data"    => ['news' => $row]
            ]);

         } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[news_show]'),500);
        }

    }


    // =========================
    // 'PUT' -> 해당 글을 Update
    // =========================
    public function update(string $news_id):void{
        
        // ID를 정수로 변환 및 유효성 검사
        $news_id = filter_var($news_id, FILTER_VALIDATE_INT);

        // 유호성 검중
        if ($news_id === false || $news_id <= 0) {
                json_response([
                 "success" => false,
                 "error" => ['code' => 'INVALID_ID',
                            'message' => '유효하지 않은 ID입니다.']
            ], 400);
            return;
        }

        // JSON body 파싱
        $data = read_json_body();

        // JSON 형태가 아니면 오류
        if (!is_array($data)) {
            json_response([
                'success'  => false,
                'error'    => [
                    'code'   => 'INVALID_REQUEST_BODY',
                    'message' => 'JSON 형식의 요청 본문이 필요합니다.',
                ],
            ], 400);
            return;
        }
            
        // 수정 가능한 필드 목록
        $fields = [];
        $params = [];
        $types = '';
        $allowed = ['title', 'content'];

        // allowed 배열에 있는 값만 업데이트
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $value = trim((string)$data[$field]);
                
                // 값이 비어 있으면 오류 반환
                if ($value === '') {
                    json_response([
                        "success" => false,
                        "error" => [
                                'code' => 'VALIDATION_ERROR', 
                                'message' => '필수 필드가 비었습니다.'
                        ]
                ], 400);
                return;
                }
                // SQL 세팅
                $fields[] = $field . ' = ?'; 
                $params[] = $value;
                $types   .= 's';
            }
        }
        
        try {

            // DB 접속
            $db = get_db();

            // update SQL문
            $stmt = $db->prepare("UPDATE News SET "
                                . implode(',', $fields). 
                                  " WHERE news_id=?");
            // 마지막에 news_id 추가
            $types .= 'i';
            $params[] = $news_id;
            // 가변 인자 바인딩
            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            $stmt->close();

            // update 정보 가져오기
            $stmt2 = $db->prepare("SELECT * FROM News WHERE news_id = ?");
            $stmt2->bind_param('i',$news_id);
            $stmt2->execute();
            $result = $stmt2->get_result();
            $row = $result->fetch_assoc(); 
            $stmt2->close();
            
            // update date데이터를 반환
            json_response([
                'success' => true,
                "data"    => ['news' => $row]
            ], 201);
        
         } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[news_update]'),500);
        }

    }



    // ==========================
    // PUT /news/image/:id — 이미지 변경
    // ==========================
    // --- updateImage 메서드는 뉴스 글의 '이미지만' 수정하는 기능을 담당합니다.
    // --- 클라이언트는 PUT /news/{id}/image 와 같은 요청을 보내며
    // --- 새로운 이미지를 업로드하면, 기존 이미지를 삭제하고 새 이미지로 교체합니다.
    public function updateImage (string $news_id):void {
        
        // 전달받은 news_id가 정수(Integer)인지 확인
        $news_id = filter_var($news_id, FILTER_VALIDATE_INT);

        // 유호성 검중
        if ($news_id === false || $news_id <= 0) {
                json_response([
                 "success" => false,
                 "error" => ['code' => 'INVALID_ID',
                            'message' => '유효하지 않은 ID입니다.']
            ], 400);
            return;
        }

        // 이미지 파일이 요청에 포함되지 않은 경우
        if (!isset($_FILES['image'])) {
            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'NO_FILE',
                    'message' => 'image 파일이 전달되지 않았습니다.',
                ],
            ], 400);
            return;
        }

        try {

            $db = get_db(); // DB 연결

            // 해당 글이 실제 DB에 존재하는지 조회
            $stmt = $db->prepare("SELECT * FROM News WHERE news_id = ?");
            $stmt->bind_param('i', $news_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $current = $result->fetch_assoc();


            // DB에 해당 글이 없을 경우 404 반환
            if (!$current) {
                json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'RESOURCE_NOT_FOUND',
                        'message' => '수정할 데이터를 찾을 수 없습니다.',
                    ],
                ], 404);
                return;
            }

            $file = $_FILES['image'];

            // 파일 MIME 타입 검사 → 이미지인지 확인
            $mime = mime_content_type($file['tmp_name']) ?: '';
            if (strpos($mime, 'image/') !== 0) {
                json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'INVALID_MIME',
                        'message' => '이미지 파일만 업로드할 수 있습니다.'
                ]], 400);
                return;
            }


            // 이미지 업로드 서비스 호출
            $imageService = new ImageService();
            $uploadResult = $imageService->upload($file, 'news');
            $newKey       = $uploadResult['key']; // R2 저장 키
            $newUrl       = $uploadResult['url']; // 새로운 이미지 URL

            // 기존 이미지가 있다면 삭제 시도
            try {
                if (!empty($current['file_key'])) {
                    $imageService->delete($current['file_key']);
                }
            } catch (Throwable $e){
                // 이미지 삭제 실패해도 글 수정은 계속
                error_log('[news_updateImage_delete_old]' . $e->getMessage());
            }

            // DB에 새로운 이미지 정보 업데이트
            $stmt2 = $db->prepare("UPDATE News SET 
                                    file = ?, file_key = ? 
                                WHERE news_id = ?");
            $stmt2->bind_param('ssi', $newUrl, $newKey, $news_id);
            $stmt2->execute();

            json_response([
                'success' => true
            ]);

         } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[news_updateImage]'),500);
        }
         
    }


    // =====================
    // 'DELETE' -> 글을 삭제
    // =====================
    // DELETE /news/{id} 요청으로 뉴스 글을 삭제하고
    // 해당 글에 연결된 이미지 파일도 R2에서 함께 삭제합니다.
    public function delete(string $news_id):void{
        
        // news_id 받기
        $news_id = filter_var($news_id, FILTER_VALIDATE_INT);
        
        // id 유호성 검중
        if ($news_id === false || $news_id <= 0) {
            json_response([
                 "success" => false,
                 "error" => ['code' => 'INVALID_ID',
                            'message' => '유효하지 않은 ID입니다.']
            ], 400);
            return;
        }

        try {
            // $db접속
            $db = get_db();

            // 삭제 전에 파일 키(file_key)를 조회
            $stmt = $db->prepare("SELECT file_key FROM News WHERE news_id = ?");
            $stmt->bind_param('i', $news_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            // 삭제할 글이 존재하지 않는 경우
            if (!$row) {
                json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'RESOURCE_NOT_FOUND',
                        'message' => '삭제할 데이터를 찾을 수 없습니다.',
                    ],
                ], 404);
                return;
            }

            $fileKey = $row['file_key'] ?? null;

            // 이미지 파일이 있을 경우 삭제 시도
            if ($fileKey) {
                $imageService = new ImageService();
                try {
                    $imageService->delete($fileKey);
                } catch (Throwable $e) {
                    error_log('[news_delete_image] ' . $e->getMessage());
                }
            }

            // news 테이블에서 해당 글을 삭제 하기
            $stmt2 = $db->prepare("DELETE FROM News WHERE news_id =?");
            $stmt2->bind_param('i', $news_id);
            $stmt2->execute();
            
            // DELETE SQL문의 영향을 받는 행이 없으면 삭제할 데이터 없음
            if ($db->affected_rows === 0) {
                json_response([
                     "success" => false,
                     "error" => ['code' => 'RESOURCE_NOT_FOUND',
                                'message' => '삭제할 데이터를 찾을 수 없습니다.']
                ], 404);
                return;
            }

            json_response([
                     "success" => true
                ],204);

            $stmt2->close();
         } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[news_delete]'),500);
        }

    }  

}

?>