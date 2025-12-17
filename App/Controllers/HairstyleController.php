<?php
namespace App\Controllers;

use App\Repository\HairstyleRepository;
use App\Errors\ErrorHandler;
use Throwable;
use App\Services\ImageService; // R2 업로드/삭제용 서비스

require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../http.php";

class HairstyleController
{

    // =====================
    // GET /hairstyle
    // 전체 목록
    // ====================
    public function index(): void
    {
        try {

            // 🔹 쿼리스트링 limit 파라미터 처리 (옵션)
            $limit = null;
            if (isset($_GET['limit'])) {
                $limit = filter_var($_GET['limit'], FILTER_VALIDATE_INT, [
                    'options' => [
                        'min_range' => 1,
                        'max_range' => 50, // 한 번에 최대 50개까지만
                    ],
                ]);

                // limit 값이 유효하지 않을 때
                if ($limit === false) {
                    json_response([
                        'success' => false,
                        'error'   => [
                            'code'    => 'INVALID_LIMIT',
                            'message' => 'limit 파라미터가 올바르지 않습니다.',
                        ],
                    ], 400);
                    return;
                }
            }

            $db = get_db();
            $repo = new HairstyleRepository($db);
            $hairstyle = $repo->index($limit);
            
            json_response([
                'success' => true,
                'data'    => ['hairstyle' => $hairstyle],
            ]);

        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[hairstyle_index]'),500);
        }
    }

    // ==========================
    // GET /hairstyle/{hair_id}
    // 개별 헤어스타일 조회
    // ==========================
    public function show(string $hairId): void
    {
        // hairId 유효성 검사
        $hairId = filter_var($hairId, FILTER_VALIDATE_INT);

        if ($hairId === false || $hairId <= 0) {
            json_response([
                'success' => false,
                'error' => [
                    'code'    => 'INVALID_REQUEST',
                    'message' => '유효하지 않은 요청입니다.',
                ],
            ], 400);
            return;
        }

        try {
            $db = get_db();
            $repo = new HairstyleRepository($db);

            // 대상 데이터 조회
            $row = $repo->show($hairId);

            // 존재하지 않는 경우
            if (!$row) {
                json_response([
                    'success' => false,
                    'error' => [
                        'code'    => 'RESOURCE_NOT_FOUND',
                        'message' => '데이터를 찾을 수 없습니다.',
                    ],
                ], 404);
                return;
            }

            json_response([
                'success' => true,
                'data'    => ['hairstyle' => $row],
            ]);

        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[hairstyle_show]'),500);
        }
    }


    // =====================================================
    // POST /hairstyle/create
    // 새 헤어스타일 등록 (이미지 업로드 포함)
    // - multipart/form-data (title, description, image)
    // =====================================================
    public function create(): void
    {
        try {
            // 1) 필수 필드 확인
            $title       = trim((string)($_POST['title'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));

            if ($title === '' || $description === '') {
                json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'VALIDATION_ERROR',
                        'message' => 'title / description 은 비울 수 없습니다.',
                    ],
                ], 400);
                return;
            }

            // 이미지 파일 존재 확인
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

            $file = $_FILES['image'];

            // MIME 타입 검사 (이미지 파일인지 검증)
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

            // 2) 이미지 R2 업로드
            $imageService = new ImageService();
            // → ['key' => '폴더/파일명.png', 'url' => 'https://...r2.dev/...']
            $uploadResult = $imageService->upload($file, 'hairstyle');
            
            $imageKey     = $uploadResult['key']; // R2 key
            $imageUrl     = $uploadResult['url']; // 공개 URL

            // 3) DB INSERT (image: URL, image_key: R2 object key)
            $db = get_db();
            $repo = new HairstyleRepository($db);
            $repo->create($title, $imageUrl, $imageKey, $description);
          
            json_response([
                'success' => true,
                'message' => '작성 성공했습니다' 
            ], 201);

        } catch (\RuntimeException $e) {
            error_log('[hairstyle_create_runtime] ' . $e->getMessage());
            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'UPLOAD_FAILED',
                    'message' => '이미지 업로드에 실패했습니다.',
                ],
            ], 400);
        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[hairstyle_create]'),500);
        }
    }

    // =====================================
    // PUT /hairstyle/update/{hair_id}
    // 텍스트 정보만 수정 (title, description)
    // ======================================
    public function update(string $hairId): void
    {
        $hairId = filter_var($hairId, FILTER_VALIDATE_INT);

        // ID 유효성 검사
        if ($hairId === false || $hairId <= 0) {
            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'INVALID_REQUEST',
                    'message' => '유효하지 않은 요청입니다.'
                ]
            ], 400);
            return;
        }

        try {

            $data = read_json_body();

            if (!is_array($data)) {
                json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'INVALID_REQUEST_BODY',
                        'message' => 'JSON 형식의 요청 본문이 필요합니다.',
                    ],
                ], 400);
                return;
            }

            $title       = isset($data['title']) ? (string)$data['title'] : '';
            $description = isset($data['description']) ? (string)$data['description'] : '';
 
            $db = get_db();
            $repo = new HairstyleRepository($db);

            // DB 업데이트
            $repo->updateTextOnly($hairId, $title, $description);

            // 새로 갱신된 데이터 조회 후 반환
            $row = $repo->show($hairId);

            json_response([
                'success' => true,
                'data'    => ['hairstyle' => $row],
            ]);

        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[hairstyle_update]'),500);
        }
    }


    // ====================================
    // POST /hairstyle/{hair_id}/image
    // 기존 이미지 삭제 후 새로운 이미지 업로드
    // ====================================
    public function updateImage(string $hairId): void
    {
        $hairId = filter_var($hairId, FILTER_VALIDATE_INT);

        if ($hairId === false || $hairId <= 0) {
            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'INVALID_REQUEST',
                    'message' => '유효하지 않은 요청입니다.'
                ]
            ], 400);
            return;
        }

        // 이미지 파일 존재 여부 확인
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
            $db = get_db();
            $repo = new HairstyleRepository($db);

            // 기존 데이터 조회
            $current = $repo->show($hairId);

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

            $imageService = new ImageService();

            // 1) 새 이미지 업로드
            $uploadResult = $imageService->upload($file, 'hairstyle');
            $newKey       = $uploadResult['key'];
            $newUrl       = $uploadResult['url'];

            // 2) 기존 이미지 삭제 (실패해도 업데이트는 계속 진행)
            try {
                if (!empty($current['image_key'])) {
                    $imageService->delete($current['image_key']);
                }
            } catch (Throwable $e) {
                error_log('[hairstyle_updateImage_delete_old] ' . $e->getMessage());
            }

            // 3) DB 업데이트
            $repo->updateImageOnly($hairId, $newUrl, $newKey);

            // 변경된 내용 재조회 후 반환
            $row = $repo->show($hairId);

            json_response([
                'success' => true,
                'data'    => ['hairstyle' => $row],
            ]);

        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[hairstyle_updateImage]'),500);
        }
    }

    // =============================
    // DELETE /hairstyle/{hair_id}
    // DB 삭제 + R2 이미지 삭제
    // =============================
    public function delete(string $hairId): void
    {
        $hairId = filter_var($hairId, FILTER_VALIDATE_INT);

        if ($hairId === false || $hairId <= 0) {
            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'INVALID_REQUEST',
                    'message' => '유효하지 않은 요청입니다.'
                ]
            ], 400);
            return;
        }

        try {
            $db = get_db();
            $repo = new HairstyleRepository($db);

            // 삭제 대상 존재 여부 확인
            $row = $repo->show($hairId);

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

            // 1) R2 이미지 삭제
            $imageKey = $row['image_key'] ?? null;

            // 1) R2 이미지 삭제
            if ($imageKey) {
                $imageService = new ImageService();
                try {
                    $imageService->delete($imageKey);
                } catch (Throwable $e) {
                    error_log('[hairstyle_delete_image] ' . $e->getMessage());
                    // R2 삭제 실패해도 DB 삭제는 계속 진행
                }
            }

            // 2) DB 삭제
            $result = $repo->delete($hairId);
            
            if ($result === 0) {
                json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'RESOURCE_NOT_FOUND',
                        'message' => '이미 삭제되었거나 대상을 찾을 수 없습니다.',
                    ],
                ], 404);
                return;
            }

            // 보통 삭제 성공 시 204 사용
            http_response_code(204);

        } catch (Throwable $e) {
            json_response(ErrorHandler::server($e, '[hairstyle_delete]'),500);
        }
    }
}
