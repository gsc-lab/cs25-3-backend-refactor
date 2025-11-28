<?php
namespace App\Controllers;

use Throwable;
use App\Services\ImageService; // R2 업로드/삭제용 서비스

require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../http.php";

class HairstyleController
{
    /**
     * GET /hairstyle
     * 전체 목록
     */
    public function index(): void
    {
        try {
            $db = get_db();

            // 🔹 쿼리스트링 limit 파라미터 처리 (옵션)
            $limit = null;
            if (isset($_GET['limit'])) {
                $limit = filter_var($_GET['limit'], FILTER_VALIDATE_INT, [
                    'options' => [
                        'min_range' => 1,
                        'max_range' => 50, // 한 번에 최대 50개까지만
                    ],
                ]);

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

            // 기본 쿼리
            $sql = "SELECT * FROM HairStyle ORDER BY hair_id DESC";
            if ($limit !== null) {
                $sql .= " LIMIT ?";
            }

            $stmt = $db->prepare($sql);
            if ($limit !== null) {
                $stmt->bind_param('i', $limit);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            $hairstyle = [];
            while ($row = $result->fetch_assoc()) {
                $hairstyle[] = $row;
            }

            json_response([
                'success' => true,
                'data'    => ['hairstyle' => $hairstyle],
            ]);
        } catch (Throwable $e) {
            error_log('[hairstyle_index] ' . $e->getMessage());

            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'INTERNAL_SERVER_ERROR',
                    'message' => '서버 오류가 발생했습니다.',
                ],
            ], 500);
        }
    }

    // 'GET' => 특정 게시물 조회
    public function show(string $hair_id): void
    {
        $id = (int)$hair_id;

        if ($id <= 0) {
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

            $stmt = $db->prepare("SELECT * FROM HairStyle WHERE hair_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

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
        } catch (\Throwable $e) {
            error_log('[hairstyle_show] ' . $e->getMessage());

            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'INTERNAL_SERVER_ERROR',
                    'message' => '서버 오류가 발생했습니다.',
                ],
            ], 500);
        }
    }


    /**
     * POST /hairstyle/create
     * 새 헤어스타일 등록 (이미지 업로드 포함)
     * - body: multipart/form-data (title, description, image)
     */
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

            // (선택) MIME 검사
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
            $imageKey     = $uploadResult['key'];
            $imageUrl     = $uploadResult['url'];

            // 3) DB INSERT (image: URL, image_key: R2 object key)
            $db = get_db();
            $stmt = $db->prepare(
                "INSERT INTO HairStyle (title, image, image_key, description)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param('ssss', $title, $imageUrl, $imageKey, $description);
            $stmt->execute();

            if ($db->affected_rows === 0) {
                json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'NO_RECORD_INSERTED',
                        'message' => '삽입 처리가 수행되지 않았습니다.',
                    ],
                ], 400);
                return;
            }

            json_response([
                'success' => true,
                'data'    => [
                    'hairstyle' => [
                        'hair_id'     => $db->insert_id,
                        'title'       => $title,
                        'image'       => $imageUrl,
                        'image_key'   => $imageKey,
                        'description' => $description,
                    ],
                ],
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
        } catch (\Throwable $e) {
            error_log('[hairstyle_create] ' . $e->getMessage());

            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'INTERNAL_SERVER_ERROR',
                    'message' => '서버 오류가 발생했습니다.',
                ],
            ], 500);
        }
    }

    /**
     * PUT /hairstyle/update/{hair_id}
     * 텍스트 정보 수정 (title, description)
     *  - body: JSON
     *  - 이미지는 그대로 두고 싶을 때 사용
     *  ※ 이미지까지 변경하고 싶으면 아래 updateImage() 같은 별도 엔드포인트 쓰는 게 깔끔함
     */
    public function update(string $hair_id): void
    {
        $id = (int)$hair_id;

        if ($id <= 0) {
            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'INVALID_REQUEST',
                    'message' => '유효하지 않은 요청입니다.',
                ],
            ], 400);
            return;
        }

        try {
            $data = read_json_body(); // { "title": "...", "description": "..." }

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

            $fields = [];
            $params = [];
            $types  = '';

            // 수정 가능 필드만 허용
            $allowed = ['title', 'description'];

            foreach ($allowed as $field) {
                if (array_key_exists($field, $data)) {
                    $value = trim((string)$data[$field]);
                    if ($value === '') {
                        json_response([
                            'success' => false,
                            'error'   => [
                                'code'    => 'VALIDATION_ERROR',
                                'message' => '요청 데이터의 형식이 올바르지 않습니다.',
                            ],
                        ], 422);
                        return;
                    }
                    $fields[] = $field . ' = ?';
                    $params[] = $value;
                    $types   .= 's';
                }
            }

            if (empty($fields)) {
                json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'NO_FIELDS_TO_UPDATE',
                        'message' => '수정할 필드가 없습니다.',
                    ],
                ], 400);
                return;
            }

            $db = get_db();

            // 1) UPDATE
            $sql = "UPDATE HairStyle SET " . implode(', ', $fields) . " WHERE hair_id = ?";
            $stmt = $db->prepare($sql);

            // 타입 문자열 + id
            $types  .= 'i';
            $params[] = $id;

            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            if ($db->affected_rows === 0) {
                // 완전히 같은 값으로 보냈을 수도 있으니, 여기서는 그냥 404 대신 조회 한 번 더 해봄
                $stmtCheck = $db->prepare("SELECT * FROM HairStyle WHERE hair_id = ?");
                $stmtCheck->bind_param('i', $id);
                $stmtCheck->execute();
                $resCheck = $stmtCheck->get_result();
                $rowCheck = $resCheck->fetch_assoc();

                if (!$rowCheck) {
                    json_response([
                        'success' => false,
                        'error'   => [
                            'code'    => 'RESOURCE_NOT_FOUND',
                            'message' => '수정할 데이터를 찾을 수 없습니다.',
                        ],
                    ], 404);
                    return;
                }
                // row는 있는데 값이 동일해서 변경 없음 → 그냥 성공으로 응답
            }

            // 2) 수정된 데이터 다시 조회
            $stmt2 = $db->prepare("SELECT * FROM HairStyle WHERE hair_id = ?");
            $stmt2->bind_param('i', $id);
            $stmt2->execute();
            $result = $stmt2->get_result();
            $row    = $result->fetch_assoc();

            json_response([
                'success' => true,
                'data'    => ['hairstyle' => $row],
            ]);
        } catch (Throwable $e) {
            error_log('[hairstyle_update] ' . $e->getMessage());

            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'INTERNAL_SERVER_ERROR',
                    'message' => '서버 오류가 발생했습니다.',
                ],
            ], 500);
        }
    }

    /**
     * 추가: 이미지만 교체하는 엔드포인트(원하면 사용)
     * POST /hairstyle/{hair_id}/image
     *  - body: multipart/form-data (image)
     *  - 기존 R2 이미지 삭제 후 새 이미지 업로드
     */
    public function updateImage(string $hair_id): void
    {
        $id = (int)$hair_id;
        if ($id <= 0) {
            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'INVALID_REQUEST',
                    'message' => '유효하지 않은 요청입니다.',
                ],
            ], 400);
            return;
        }

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

            // 0) 기존 데이터 조회 (image_key 포함)
            $stmt = $db->prepare("SELECT * FROM HairStyle WHERE hair_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result  = $stmt->get_result();
            $current = $result->fetch_assoc();

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

            // 새로 업로드
            $uploadResult = $imageService->upload($file, 'hairstyle');
            $newKey       = $uploadResult['key'];
            $newUrl       = $uploadResult['url'];

            // 기존 이미지 삭제 (실패하더라도 서비스 자체는 계속)
            try {
                if (!empty($current['image_key'])) {
                    $imageService->delete($current['image_key']);
                }
            } catch (Throwable $e) {
                error_log('[hairstyle_updateImage_delete_old] ' . $e->getMessage());
            }

            // DB 수정
            $stmt2 = $db->prepare(
                "UPDATE HairStyle SET image = ?, image_key = ? WHERE hair_id = ?"
            );
            $stmt2->bind_param('ssi', $newUrl, $newKey, $id);
            $stmt2->execute();

            // 수정된 데이터 다시 조회
            $stmt3 = $db->prepare("SELECT * FROM HairStyle WHERE hair_id = ?");
            $stmt3->bind_param('i', $id);
            $stmt3->execute();
            $row = $stmt3->get_result()->fetch_assoc();

            json_response([
                'success' => true,
                'data'    => ['hairstyle' => $row],
            ]);
        } catch (Throwable $e) {
            error_log('[hairstyle_updateImage] ' . $e->getMessage());

            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'INTERNAL_SERVER_ERROR',
                    'message' => '서버 오류가 발생했습니다.',
                ],
            ], 500);
        }
    }

    /**
     * DELETE /hairstyle/delete/{hair_id}
     * DB 레코드 + R2 이미지 같이 삭제
     */
    public function delete(string $hair_id): void
    {
        $id = (int)$hair_id;

        if ($id <= 0) {
            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'INVALID_REQUEST',
                    'message' => '유효하지 않은 요청입니다.',
                ],
            ], 400);
            return;
        }

        try {
            $db = get_db();

            // 0) 먼저 image_key 조회
            $stmt = $db->prepare(
                "SELECT image_key FROM HairStyle WHERE hair_id = ?"
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row    = $result->fetch_assoc();

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

            $imageKey = $row['image_key'] ?? null;

            // 1) R2 이미지 삭제
            if ($imageKey) {
                $imageService = new ImageService();
                try {
                    $imageService->delete($imageKey);
                } catch (Throwable $e) {
                    error_log('[hairstyle_delete_image] ' . $e->getMessage());
                    // 정책에 따라 여기서 바로 500을 줄 수도 있고,
                    // 일단 레코드는 삭제하고 나중에 orphan 정리하는 식으로 갈 수도 있음
                }
            }

            // 2) DB 삭제
            $stmt2 = $db->prepare("DELETE FROM HairStyle WHERE hair_id = ?");
            $stmt2->bind_param('i', $id);
            $stmt2->execute();

            if ($db->affected_rows === 0) {
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
            error_log('[hairstyle_delete] ' . $e->getMessage());

            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'INTERNAL_SERVER_ERROR',
                    'message' => '서버 오류가 발생했습니다.',
                ],
            ], 500);
        }
    }
}
