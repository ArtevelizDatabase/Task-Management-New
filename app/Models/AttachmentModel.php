<?php

namespace App\Models;

use CodeIgniter\Model;

class AttachmentModel extends Model
{
    protected $table      = 'tb_attachments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $useTimestamps = false;
    protected $allowedFields = ['task_id', 'user_id', 'filename', 'original', 'mime_type', 'size', 'created_at'];

    public const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip', 'application/x-rar-compressed',
        'text/plain', 'text/csv',
        'video/mp4', 'video/quicktime',
    ];

    public const MAX_SIZE_MB = 20;

    /** Maksimal jumlah file per task (kebijakan penyimpanan). */
    public const MAX_ATTACHMENTS_PER_TASK = 20;

    /** Total ukuran semua lampiran per task (MB). */
    public const MAX_TOTAL_STORAGE_MB_PER_TASK = 200;

    public function uploadForTask(int $taskId, int $userId, \CodeIgniter\HTTP\Files\UploadedFile $file): array
    {
        if (! $file->isValid() || $file->hasMoved()) {
            throw new \RuntimeException('File tidak valid.');
        }

        if ($file->getSizeByUnit('mb') > self::MAX_SIZE_MB) {
            throw new \RuntimeException('Ukuran file melebihi ' . self::MAX_SIZE_MB . ' MB.');
        }

        // Sebelum move(): finfo memakai file temp. Setelah move_uploaded_file() temp hilang —
        // jangan panggil getMimeType()/getSize() lagi (akan error "Failed to open stream").
        $mimeType   = $file->getMimeType();
        $sizeBytes  = (int) $file->getSizeByUnit('b');
        $clientName = $file->getClientName();

        if (! in_array($mimeType, self::ALLOWED_MIMES, true)) {
            throw new \RuntimeException('Tipe file tidak diizinkan.');
        }

        $existingCount = $this->where('task_id', $taskId)->countAllResults();
        if ($existingCount >= self::MAX_ATTACHMENTS_PER_TASK) {
            throw new \RuntimeException(
                'Jumlah lampiran untuk task ini sudah mencapai batas maksimal (' . self::MAX_ATTACHMENTS_PER_TASK . ' file). Hapus lampiran lama terlebih dahulu.'
            );
        }

        $sumRow = $this->db->table('tb_attachments')
            ->selectSum('size', 'total_bytes')
            ->where('task_id', $taskId)
            ->get()
            ->getRowArray();
        $totalBytes = (int) ($sumRow['total_bytes'] ?? 0);
        $newBytes   = $sizeBytes;
        $maxBytes   = self::MAX_TOTAL_STORAGE_MB_PER_TASK * 1048576;
        if ($totalBytes + $newBytes > $maxBytes) {
            throw new \RuntimeException(
                'Total ukuran lampiran untuk task ini akan melebihi ' . self::MAX_TOTAL_STORAGE_MB_PER_TASK . ' MB. Hapus file besar atau lampiran tidak perlu.'
            );
        }

        $uploadPath = WRITEPATH . "uploads/attachments/{$taskId}/";
        if (! is_dir($uploadPath) && ! mkdir($uploadPath, 0755, true) && ! is_dir($uploadPath)) {
            throw new \RuntimeException("Gagal membuat direktori upload: {$uploadPath}");
        }

        $newName = $file->getRandomName();
        $file->move($uploadPath, $newName);

        $data = [
            'task_id'    => $taskId,
            'user_id'    => $userId,
            'filename'   => $newName,
            'original'   => $clientName,
            'mime_type'  => $mimeType,
            'size'       => $sizeBytes,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $id = $this->insert($data);
        $data['id'] = $id;

        return $data;
    }

    public function getForTask(int $taskId): array
    {
        return $this->db->table('tb_attachments a')
            ->select('a.*, u.username, u.nickname')
            ->join('tb_users u', 'u.id = a.user_id', 'left')
            ->where('a.task_id', $taskId)
            ->orderBy('a.created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    public function deleteAttachment(int $id, int $taskId): bool
    {
        $att = $this->where('id', $id)->where('task_id', $taskId)->first();
        if (! $att) {
            return false;
        }

        $path = WRITEPATH . "uploads/attachments/{$taskId}/{$att['filename']}";
        if (file_exists($path)) {
            unlink($path);
        }

        return $this->delete($id);
    }

    public static function publicUrl(int $taskId, string $filename): string
    {
        return base_url("tasks/{$taskId}/attachments/" . rawurlencode($filename) . '/serve');
    }

    public static function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 1) . ' MB';
    }
}
