<?php

namespace App\Services;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class ImageService
{
    private S3Client $s3;
    private string $bucket;
    private string $publicBase;

    public function __construct()
    {
        $this->bucket     = $_ENV['R2_BUCKET']      ?? '';
        $this->publicBase = $_ENV['R2_PUBLIC_BASE'] ?? '';
        $endpoint         = $_ENV['R2_ENDPOINT']    ?? '';
        $accessKey        = $_ENV['R2_ACCESS_KEY']  ?? '';
        $secretKey        = $_ENV['R2_SECRET_KEY']  ?? '';

        if ($this->bucket === '' || $endpoint === '' || $accessKey === '' || $secretKey === '') {
            throw new \RuntimeException('R2 환경변수가 제대로 설정되어 있지 않습니다.');
        }

        $this->s3 = new S3Client([
            'version'                 => 'latest',
            'region'                  => 'auto',
            'endpoint'                => $endpoint,
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key'    => $accessKey,
                'secret' => $secretKey,
            ],
        ]);
    }

    /**
     * 파일 업로드 후 [key, url] 반환
     */
    public function upload(array $file, string $prefix): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('파일 업로드 실패: error=' . ($file['error'] ?? 'unknown'));
        }

        $tmpPath = $file['tmp_name'] ?? null;
        if (!$tmpPath || !is_file($tmpPath)) {
            throw new \RuntimeException('임시 파일이 존재하지 않습니다.');
        }

        $ext = pathinfo($file['name'] ?? '', PATHINFO_EXTENSION) ?: 'jpg';

        // 🔹 prefix = 'hairstyle' or 'designer' or 'news' etc.
        $prefix = trim($prefix, '/'); 

        // 랜덤 파일명
        $random = bin2hex(random_bytes(16));

        // prefix로 폴더 분리 + 날짜 없음
        $key = sprintf('%s/%s.%s', $prefix, $random, $ext);

        try {
            $this->s3->putObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
                'Body'   => file_get_contents($tmpPath),
            ]);
        } catch (AwsException $e) {
            error_log('[ImageService] R2 putObject error: ' . $e->getMessage());
            throw new \RuntimeException('R2 업로드 중 오류가 발생했습니다.');
        }

        $url = rtrim($this->publicBase, '/') . '/' . $key;

        return [
            'key' => $key,
            'url' => $url,
        ];
    }

    // 삭제
    public function delete(string $key): void
    {
        if ($key === '') {
            return; // 비어 있으면 그냥 무시
        }

        try {
            $this->s3->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
            ]);
        } catch (AwsException $e) {
            // 삭제 실패해도 앱이 터지진 않게, 로그만 남기고 넘기는 패턴도 많이 씀
            error_log('[ImageService] R2 deleteObject error: ' . $e->getMessage());
            // 필요하면 예외를 다시 던져도 됨
            // throw new \RuntimeException('R2 삭제 중 오류가 발생했습니다.');
        }
    }

    // 수정
    /**
     * 기존 이미지를 새 파일로 교체
     * - 새 파일 업로드 성공 시, 이전 key가 있으면 삭제 시도
     * - 반환: 새 [key, url]
     */
    public function replace(array $file, string $prefix, ?string $oldKey = null): array
    {
        // 1) 새 파일 업로드
        $new = $this->upload($file, $prefix);

        // 2) 이전 키가 있으면 삭제 시도 (에러는 앱 죽이지 않고 로그만)
        if ($oldKey) {
            try {
                $this->delete($oldKey);
            } catch (\Throwable $e) {
                error_log('[ImageService] old image delete fail: ' . $e->getMessage());
            }
        }

        return $new; // ['key' => ..., 'url' => ...]
    }


}
