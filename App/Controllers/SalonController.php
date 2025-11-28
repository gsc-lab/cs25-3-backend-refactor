<?php

namespace App\Controllers;

use App\Services\ImageService;
use RuntimeException;
use Throwable;

require_once __DIR__.'/../db.php';
require_once __DIR__.'/../http.php';

class SalonController
{
    // ==========================
    // 'GET' -> Salon 정보 가져오기
    // ==========================
    public function index(): void
    {
        try {
            $db = get_db();

            // Salon은 1개만 존재한다고 가정 (단일 정보)
            $stmt   = $db->prepare("SELECT * FROM Salon LIMIT 1");
            $stmt->execute();
            $result = $stmt->get_result();
            $row    = $result->fetch_assoc();

            // 데이터가 없을 수도 있으니 그대로 반환
            json_response([
                'success' => true,
                'data'    => ['salon' => $row],
            ]);
        } catch (Throwable $e) {
            error_log('[salon_index] ' . $e->getMessage());
            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'INTERNAL_SERVER_ERROR',
                    'message' => '서버 오류가 발생했습니다.',
                ],
            ], 500);
            return;
        }
    }

    // ==========================================
    // 'PUT' -> Salon 정보 + 이미지 수정 (통합 업데이트)
    // ==========================================
    //
    // - 텍스트 필드: introduction, information, map, traffic
    // - 이미지 필드: image(이미지 URL), image_key(R2 key)
    //
    // 요청은 multipart/form-data 로 온다고 가정:
    // - text: $_POST['introduction'], $_POST['information'], ...
    // - file: $_FILES['image']
    public function update(): void
    {
        // --- 1) 텍스트 필드 파싱 ---
        $data = $_POST;

        $imageFromBody = $data['image'] ?? ''; // 필요시 문자열 URL도 받을 수 있게 유지
        $intro         = isset($data['introduction']) ? trim((string)$data['introduction']) : '';
        $info          = isset($data['information'])  ? trim((string)$data['information'])  : '';
        $map           = isset($data['map'])          ? trim((string)$data['map'])          : '';
        $traffic       = isset($data['traffic'])      ? trim((string)$data['traffic'])      : '';

        // 필수 필드 검증 (필요에 따라 완화 가능)
        if ($intro === '' || $info === '' || $map === '' || $traffic === '') {
            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'INVALID_REQUEST',
                    'message' => '유효하지 않은 요청입니다.',
                ],
            ], 400);
            return;
        }

        // --- 2) 이미지 업로드 준비 ---
        $newImageUrl = null;
        $newImageKey = null;

        // 파일이 함께 전달된 경우만 업로드 처리
        $hasFile = isset($_FILES['image']) && is_array($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK;

        try {
            $db = get_db();

            // 현재 Salon 정보 조회 (기존 이미지 키 삭제용)
            $stmt = $db->prepare("SELECT * FROM Salon LIMIT 1");
            $stmt->execute();
            $result  = $stmt->get_result();
            $current = $result->fetch_assoc();
            $stmt->close();

            if (!$current) {
                // Salon 정보 자체가 없는 경우
                json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'RESOURCE_NOT_FOUND',
                        'message' => '수정할 Salon 정보를 찾을 수 없습니다.',
                    ],
                ], 404);
                return;
            }

            // --- 3) 이미지 파일 업로드 처리 (선택) ---
            if ($hasFile) {
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

                // 이미지 업로드 (폴더명 'salon' 으로 구분)
                $imageService = new ImageService();
                $uploadResult = $imageService->upload($file, 'salon');

                $newImageKey = $uploadResult['key'] ?? null;
                $newImageUrl = $uploadResult['url'] ?? null;

                // 기존 이미지가 있다면 삭제 시도 (image_key 기준)
                try {
                    if (!empty($current['image_key'])) {
                        $imageService->delete($current['image_key']);
                    }
                } catch (Throwable $e) {
                    // 이미지 삭제 실패해도 Salon 정보 수정은 계속 진행
                    error_log('[salon_update_delete_old_image] ' . $e->getMessage());
                }
            }

            // --- 4) UPDATE 쿼리 구성 ---
            // Salon은 1개만 존재한다고 가정 → WHERE 조건 없이 전체 업데이트
            // (만약 salon_id 컬럼이 있다면 WHERE salon_id = ? 로 바꾸면 됨)
            if ($newImageUrl !== null && $newImageKey !== null) {
                // 새 이미지도 함께 업데이트
                $stmt2 = $db->prepare("
                    UPDATE Salon SET
                        image      = ?,
                        image_key  = ?,
                        introduction = ?,
                        information  = ?,
                        map          = ?,
                        traffic      = ?
                ");
                $stmt2->bind_param(
                    'ssssss',
                    $newImageUrl,
                    $newImageKey,
                    $intro,
                    $info,
                    $map,
                    $traffic
                );
            } else {
                // 텍스트 정보만 수정 (기존 image, image_key 유지)
                // 필요 시 body에서 온 image URL로 덮어쓰고 싶다면, 여기 로직을 조정
                $stmt2 = $db->prepare("
                    UPDATE Salon SET
                        introduction = ?,
                        information  = ?,
                        map          = ?,
                        traffic      = ?
                ");
                $stmt2->bind_param(
                    'ssss',
                    $intro,
                    $info,
                    $map,
                    $traffic
                );
            }

            $stmt2->execute();

            if ($stmt2->affected_rows === 0) {
                json_response([
                    'success' => false,
                    'error'   => [
                        'code'    => 'NO_ROWS_UPDATED',
                        'message' => '수정된 내용이 없습니다.',
                    ],
                ], 409);
                return;
            }

            $stmt2->close();

            // --- 5) 수정된 Salon 정보 다시 조회해서 반환 (선택) ---
            $stmt3 = $db->prepare("SELECT * FROM Salon LIMIT 1");
            $stmt3->execute();
            $res2 = $stmt3->get_result();
            $updated = $res2->fetch_assoc();
            $stmt3->close();

            json_response([
                'success' => true,
                'data'    => ['salon' => $updated],
            ], 201);

        } catch (RuntimeException $e) {
            // 업로드 계열에서 RuntimeException 발생 시
            error_log('[salon_update_runtime] ' . $e->getMessage());
            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'UPLOAD_FAILED',
                    'message' => '이미지 업로드에 실패했습니다.',
                ],
            ], 400);
            return;
        } catch (Throwable $e) {
            error_log('[salon_update] ' . $e->getMessage());
            json_response([
                'success' => false,
                'error'   => [
                    'code'    => 'INTERNAL_SERVER_ERROR',
                    'message' => '서버 오류가 발생했습니다.',
                ],
            ], 500);
            return;
        }
    }
}

