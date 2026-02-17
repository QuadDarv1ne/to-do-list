<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\TaskAttachment;
use App\Entity\User;
use App\Repository\TaskAttachmentRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileAttachmentService
{
    private const MAX_FILE_SIZE = 10485760; // 10MB
    private const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'gif'];

    public function __construct(
        private TaskAttachmentRepository $attachmentRepository,
        private SluggerInterface $slugger,
        private string $uploadsDirectory
    ) {}

    /**
     * Upload file and create attachment
     */
    public function uploadFile(UploadedFile $file, Task $task, User $user): TaskAttachment
    {
        // Validate file
        $this->validateFile($file);

        // Generate unique filename
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        // Move file to upload directory
        $file->move($this->uploadsDirectory, $newFilename);

        // Create attachment entity
        $attachment = new TaskAttachment();
        $attachment->setTask($task);
        $attachment->setFilename($newFilename);
        $attachment->setOriginalFilename($file->getClientOriginalName());
        $attachment->setMimeType($file->getMimeType());
        $attachment->setFileSize($file->getSize());
        $attachment->setFilePath($this->uploadsDirectory . '/' . $newFilename);
        $attachment->setUploadedBy($user);

        $this->attachmentRepository->save($attachment, true);

        return $attachment;
    }

    /**
     * Delete attachment
     */
    public function deleteAttachment(TaskAttachment $attachment): void
    {
        // Delete physical file
        $filePath = $attachment->getFilePath();
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Delete database record
        $this->attachmentRepository->remove($attachment, true);
    }

    /**
     * Get attachments for task
     */
    public function getTaskAttachments(Task $task): array
    {
        return $this->attachmentRepository->findByTask($task);
    }

    /**
     * Validate uploaded file
     */
    private function validateFile(UploadedFile $file): void
    {
        // Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \Exception('Файл слишком большой. Максимальный размер: 10MB');
        }

        // Check extension
        $extension = $file->guessExtension();
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            throw new \Exception('Недопустимый тип файла. Разрешены: ' . implode(', ', self::ALLOWED_EXTENSIONS));
        }
    }

    /**
     * Get total size of attachments for task
     */
    public function getTotalSize(Task $task): int
    {
        $attachments = $this->getTaskAttachments($task);
        return array_sum(array_map(fn($a) => $a->getFileSize(), $attachments));
    }

    /**
     * Get attachment statistics
     */
    public function getStatistics(): array
    {
        $qb = $this->attachmentRepository->createQueryBuilder('a');
        
        $totalCount = $qb->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $totalSize = $qb->select('SUM(a.fileSize)')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total_count' => (int)$totalCount,
            'total_size' => (int)$totalSize,
            'total_size_formatted' => $this->formatBytes((int)$totalSize)
        ];
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
