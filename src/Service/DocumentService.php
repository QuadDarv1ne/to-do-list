<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\User;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class DocumentService
{
    private string $uploadDirectory;
    
    public function __construct(
        private DocumentRepository $documentRepository,
        private EntityManagerInterface $entityManager,
        string $kernelProjectDir
    ) {
        $this->uploadDirectory = $kernelProjectDir . '/public/uploads/documents';
        
        // Create upload directory if it doesn't exist
        if (!is_dir($this->uploadDirectory)) {
            mkdir($this->uploadDirectory, 0755, true);
        }
    }

    /**
     * Create a new document
     */
    public function createDocument(array $data, User $user): Document
    {
        $document = new Document();
        $document->setTitle($data['title']);
        $document->setContent($data['content'] ?? '');
        $document->setDescription($data['description'] ?? '');
        $document->setCreatedBy($user->getId());
        $document->setStatus($data['status'] ?? 'draft');
        $document->setContentType($data['content_type'] ?? 'text/markdown');
        
        if (isset($data['parent_id'])) {
            $document->setParentId($data['parent_id']);
        }
        
        if (isset($data['tags'])) {
            $document->setTags(implode(',', $data['tags']));
        }

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $document;
    }

    /**
     * Update an existing document
     */
    public function updateDocument(Document $document, array $data): Document
    {
        if (isset($data['title'])) {
            $document->setTitle($data['title']);
        }
        
        if (isset($data['content'])) {
            $document->setContent($data['content']);
            $document->incrementVersion(); // Increment version when content changes
        }
        
        if (isset($data['description'])) {
            $document->setDescription($data['description']);
        }
        
        if (isset($data['status'])) {
            $document->setStatus($data['status']);
        }
        
        if (isset($data['content_type'])) {
            $document->setContentType($data['content_type']);
        }
        
        if (isset($data['parent_id'])) {
            $document->setParentId($data['parent_id']);
        }
        
        if (isset($data['tags'])) {
            $document->setTags(implode(',', $data['tags']));
        }

        $document->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $document;
    }

    /**
     * Delete a document
     */
    public function deleteDocument(Document $document): void
    {
        $this->entityManager->remove($document);
        $this->entityManager->flush();
    }

    /**
     * Upload a file and create a document
     */
    public function uploadDocument(UploadedFile $file, User $user, array $metadata = []): Document
    {
        // Validate file
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
        $fileName = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        // Create directory if needed
        $uploadDir = $this->uploadDirectory;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        try {
            $file->move($uploadDir, $fileName);
        } catch (FileException $e) {
            throw new \Exception('Failed to upload file: ' . $e->getMessage());
        }

        // Create document record
        $document = new Document();
        $document->setTitle($metadata['title'] ?? $originalFilename);
        $document->setContent(''); // Content will be the file path or empty for binary files
        $document->setFileName($fileName);
        $document->setContentType($file->getMimeType() ?? 'application/octet-stream');
        $document->setCreatedBy($user->getId());
        $document->setStatus($metadata['status'] ?? 'published');
        $document->setDescription($metadata['description'] ?? '');
        
        if (isset($metadata['parent_id'])) {
            $document->setParentId($metadata['parent_id']);
        }
        
        if (isset($metadata['tags'])) {
            $document->setTags(implode(',', $metadata['tags']));
        }

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $document;
    }

    /**
     * Get file path for a document
     */
    public function getFilePath(Document $document): string
    {
        if (!$document->getFileName()) {
            throw new \Exception('Document does not have a file associated with it');
        }

        return $this->uploadDirectory . '/' . $document->getFileName();
    }

    /**
     * Get documents by user
     */
    public function getDocumentsByUser(User $user, int $limit = null, int $offset = 0): array
    {
        $qb = $this->documentRepository->createQueryBuilder('d')
            ->where('d.createdBy = :userId')
            ->setParameter('userId', $user->getId())
            ->orderBy('d.createdAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }
        
        if ($offset) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get documents by status
     */
    public function getDocumentsByStatus(string $status, int $limit = null, int $offset = 0): array
    {
        $qb = $this->documentRepository->createQueryBuilder('d')
            ->where('d.status = :status')
            ->setParameter('status', $status)
            ->orderBy('d.createdAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }
        
        if ($offset) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Search documents
     */
    public function searchDocuments(string $query, User $user = null, int $limit = 10, int $offset = 0): array
    {
        $qb = $this->documentRepository->createQueryBuilder('d')
            ->where('d.title LIKE :query OR d.content LIKE :query OR d.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('d.createdAt', 'DESC');

        if ($user) {
            $qb->andWhere('d.createdBy = :userId')
               ->setParameter('userId', $user->getId());
        }

        $qb->setMaxResults($limit)
           ->setFirstResult($offset);

        return $qb->getQuery()->getResult();
    }

    /**
     * Get document statistics
     */
    public function getDocumentStatistics(?User $user = null): array
    {
        $qb = $this->documentRepository->createQueryBuilder('d');
        
        if ($user) {
            $qb->where('d.createdBy = :userId')
               ->setParameter('userId', $user->getId());
        }

        $totalDocs = $qb->select('COUNT(d.id)')->getQuery()->getSingleScalarResult();
        
        $statusCounts = $qb->select('d.status, COUNT(d.id) as count')
                          ->groupBy('d.status')
                          ->getQuery()
                          ->getResult();

        $monthlyStats = $qb->select('MONTH(d.createdAt) as month, YEAR(d.createdAt) as year, COUNT(d.id) as count')
                          ->groupBy('year, month')
                          ->orderBy('year', 'DESC')
                          ->addOrderBy('month', 'DESC')
                          ->setMaxResults(12)
                          ->getQuery()
                          ->getResult();

        return [
            'total_documents' => (int)$totalDocs,
            'by_status' => $statusCounts,
            'monthly_creation' => $monthlyStats
        ];
    }

    /**
     * Move document to a different parent
     */
    public function moveDocument(Document $document, ?int $newParentId): Document
    {
        $document->setParentId($newParentId);
        $this->entityManager->flush();

        return $document;
    }

    /**
     * Publish a document
     */
    public function publishDocument(Document $document): Document
    {
        $document->setStatus('published');
        $this->entityManager->flush();

        return $document;
    }

    /**
     * Unpublish a document
     */
    public function unpublishDocument(Document $document): Document
    {
        $document->setStatus('draft');
        $this->entityManager->flush();

        return $document;
    }

    /**
     * Get child documents
     */
    public function getChildDocuments(int $parentId): array
    {
        return $this->documentRepository->findBy(['parentId' => $parentId]);
    }

    /**
     * Get documents by tags
     */
    public function getDocumentsByTags(array $tags, User $user = null): array
    {
        $tagString = '%' . implode('%', $tags) . '%';
        
        $qb = $this->documentRepository->createQueryBuilder('d')
            ->where('d.tags LIKE :tags')
            ->setParameter('tags', $tagString);

        if ($user) {
            $qb->andWhere('d.createdBy = :userId')
               ->setParameter('userId', $user->getId());
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Generate document preview
     */
    public function generatePreview(Document $document, int $length = 200): string
    {
        $content = $document->getContent();
        
        if (strlen($content) <= $length) {
            return $content;
        }
        
        return substr($content, 0, $length) . '...';
    }

    /**
     * Get recent documents
     */
    public function getRecentDocuments(int $limit = 10, User $user = null): array
    {
        $qb = $this->documentRepository->createQueryBuilder('d')
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($user) {
            $qb->andWhere('d.createdBy = :userId')
               ->setParameter('userId', $user->getId());
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Duplicate a document
     */
    public function duplicateDocument(Document $document, User $user, string $newTitle = null): Document
    {
        $newDocument = new Document();
        $newDocument->setTitle($newTitle ?: $document->getTitle() . ' (Copy)');
        $newDocument->setContent($document->getContent());
        $newDocument->setDescription($document->getDescription());
        $newDocument->setCreatedBy($user->getId());
        $newDocument->setStatus('draft'); // New copies start as drafts
        $newDocument->setContentType($document->getContentType());
        $newDocument->setParentId($document->getParentId());
        $newDocument->setTags($document->getTags());

        $this->entityManager->persist($newDocument);
        $this->entityManager->flush();

        return $newDocument;
    }
}