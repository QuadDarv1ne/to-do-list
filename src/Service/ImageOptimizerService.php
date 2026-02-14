<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service for optimizing images
 */
class ImageOptimizerService
{
    private LoggerInterface $logger;
    private ContainerInterface $container;
    private string $projectDir;

    public function __construct(
        LoggerInterface $logger,
        ContainerInterface $container
    ) {
        $this->logger = $logger;
        $this->container = $container;
        $this->projectDir = $container->getParameter('kernel.project_dir');
    }

    /**
     * Optimize a single image file
     */
    public function optimizeImage(string $imagePath, array $options = []): array
    {
        $this->logger->info('Starting image optimization', [
            'image_path' => $imagePath
        ]);

        // Default options
        $defaultOptions = [
            'quality' => 80,
            'max_width' => null,
            'max_height' => null,
            'format' => null, // preserve original format or convert to 'webp', 'jpeg', 'png'
            'strip_metadata' => true,
        ];
        
        $options = array_merge($defaultOptions, $options);

        if (!file_exists($imagePath)) {
            throw new \InvalidArgumentException("Image file does not exist: {$imagePath}");
        }

        $originalSize = filesize($imagePath);
        $imageInfo = getimagesize($imagePath);

        if ($imageInfo === false) {
            throw new \InvalidArgumentException("File is not a valid image: {$imagePath}");
        }

        $mimeType = $imageInfo['mime'];
        $extension = $this->getExtensionFromMimeType($mimeType);

        // Determine output format
        $outputFormat = $options['format'] ?? $extension;
        if (!in_array($outputFormat, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $outputFormat = $extension; // Fallback to original format
        }

        // Create temporary output path
        $tempPath = sys_get_temp_dir() . '/optimized_' . uniqid() . '.' . $outputFormat;

        try {
            // Process the image based on its type
            $success = $this->processImage($imagePath, $tempPath, $outputFormat, $options);

            if (!$success) {
                throw new \RuntimeException("Failed to process image: {$imagePath}");
            }

            $optimizedSize = filesize($tempPath);
            $sizeReduction = $originalSize - $optimizedSize;
            $reductionPercentage = $originalSize > 0 ? ($sizeReduction / $originalSize) * 100 : 0;

            // Prepare result
            $result = [
                'success' => true,
                'original_path' => $imagePath,
                'optimized_path' => $tempPath,
                'original_size' => $originalSize,
                'original_size_formatted' => $this->formatBytes($originalSize),
                'optimized_size' => $optimizedSize,
                'optimized_size_formatted' => $this->formatBytes($optimizedSize),
                'size_reduction' => $sizeReduction,
                'size_reduction_formatted' => $this->formatBytes($sizeReduction),
                'reduction_percentage' => round($reductionPercentage, 2),
                'image_type' => $mimeType,
                'dimensions' => [
                    'width' => $imageInfo[0],
                    'height' => $imageInfo[1]
                ]
            ];

            $this->logger->info('Image optimization completed', [
                'image_path' => $imagePath,
                'size_reduction' => $this->formatBytes($sizeReduction),
                'reduction_percentage' => $result['reduction_percentage']
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Image optimization failed', [
                'image_path' => $imagePath,
                'error' => $e->getMessage()
            ]);

            // Clean up temp file if it exists
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'original_path' => $imagePath
            ];
        }
    }

    /**
     * Batch optimize images in a directory
     */
    public function optimizeImagesInDirectory(string $directory, array $options = [], bool $recursive = false): array
    {
        $this->logger->info('Starting batch image optimization', [
            'directory' => $directory,
            'recursive' => $recursive
        ]);

        $supportedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $results = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'total_original_size' => 0,
            'total_optimized_size' => 0,
            'total_size_reduction' => 0,
            'individual_results' => []
        ];

        $imageFiles = $this->getImageFiles($directory, $supportedExtensions, $recursive);

        foreach ($imageFiles as $imageFile) {
            $result = $this->optimizeImage($imageFile, $options);

            $results['processed']++;
            $results['individual_results'][] = $result;

            if ($result['success']) {
                $results['successful']++;
                $results['total_original_size'] += $result['original_size'];
                $results['total_optimized_size'] += $result['optimized_size'];
                $results['total_size_reduction'] += $result['size_reduction'];
            } else {
                $results['failed']++;
            }
        }

        $results['total_reduction_percentage'] = $results['total_original_size'] > 0 ? 
            ($results['total_size_reduction'] / $results['total_original_size']) * 100 : 0;

        $this->logger->info('Batch image optimization completed', [
            'directory' => $directory,
            'processed' => $results['processed'],
            'successful' => $results['successful'],
            'failed' => $results['failed'],
            'total_size_reduction' => $this->formatBytes($results['total_size_reduction'])
        ]);

        return $results;
    }

    /**
     * Process an image based on its format
     */
    private function processImage(string $inputPath, string $outputPath, string $format, array $options): bool
    {
        // Get image info
        $imageInfo = getimagesize($inputPath);
        if ($imageInfo === false) {
            return false;
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $mimeType = $imageInfo['mime'];

        // Calculate new dimensions if resizing is requested
        $newWidth = $width;
        $newHeight = $height;

        if ($options['max_width'] || $options['max_height']) {
            $newDimensions = $this->calculateNewDimensions($width, $height, $options['max_width'], $options['max_height']);
            $newWidth = $newDimensions['width'];
            $newHeight = $newDimensions['height'];
        }

        // Create image resource based on original format
        $imageResource = $this->createImageResource($inputPath, $mimeType);
        if (!$imageResource) {
            return false;
        }

        // Resize image if needed
        if ($newWidth !== $width || $newHeight !== $height) {
            $resizedResource = imagecreatetruecolor($newWidth, $newHeight);

            // Preserve transparency for PNG and GIF
            if (in_array($format, ['png', 'gif'])) {
                imagealphablending($resizedResource, false);
                imagesavealpha($resizedResource, true);
                $transparent = imagecolorallocatealpha($resizedResource, 255, 255, 255, 127);
                imagefilledrectangle($resizedResource, 0, 0, $newWidth, $newHeight, $transparent);
            }

            imagecopyresampled(
                $resizedResource,
                $imageResource,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $width, $height
            );

            imagedestroy($imageResource);
            $imageResource = $resizedResource;
        }

        // Save image in requested format
        $quality = max(1, min(100, $options['quality'])); // Ensure quality is between 1 and 100

        $success = false;
        switch ($format) {
            case 'jpg':
            case 'jpeg':
                $success = imagejpeg($imageResource, $outputPath, $quality);
                break;
            case 'png':
                // PNG compression level is from 0 (no compression) to 9 (max compression)
                $compression = intval((100 - $quality) / 10);
                $compression = max(0, min(9, $compression));
                $success = imagepng($imageResource, $outputPath, $compression);
                break;
            case 'webp':
                $success = imagewebp($imageResource, $outputPath, $quality);
                break;
            case 'gif':
                $success = imagegif($imageResource, $outputPath);
                break;
        }

        imagedestroy($imageResource);

        // Strip metadata if requested
        if ($options['strip_metadata'] && $success) {
            $success = $this->stripMetadata($outputPath, $format);
        }

        return $success;
    }

    /**
     * Create image resource based on MIME type
     */
    private function createImageResource(string $imagePath, string $mimeType)
    {
        switch ($mimeType) {
            case 'image/jpeg':
                return imagecreatefromjpeg($imagePath);
            case 'image/png':
                return imagecreatefrompng($imagePath);
            case 'image/gif':
                return imagecreatefromgif($imagePath);
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    return imagecreatefromwebp($imagePath);
                }
                return null;
            default:
                return null;
        }
    }

    /**
     * Calculate new dimensions preserving aspect ratio
     */
    private function calculateNewDimensions(int $width, int $height, ?int $maxWidth, ?int $maxHeight): array
    {
        $newWidth = $width;
        $newHeight = $height;

        if ($maxWidth && $newWidth > $maxWidth) {
            $ratio = $maxWidth / $newWidth;
            $newWidth = $maxWidth;
            $newHeight = $newHeight * $ratio;
        }

        if ($maxHeight && $newHeight > $maxHeight) {
            $ratio = $maxHeight / $newHeight;
            $newHeight = $maxHeight;
            $newWidth = $newWidth * $ratio;
        }

        return [
            'width' => (int)round($newWidth),
            'height' => (int)round($newHeight)
        ];
    }

    /**
     * Strip metadata from image file
     */
    private function stripMetadata(string $imagePath, string $format): bool
    {
        // For now, we'll just return true since PHP's GD library doesn't easily support metadata stripping
        // In a real implementation, you might use exiftool or similar external tools
        return true;
    }

    /**
     * Get image files from directory
     */
    private function getImageFiles(string $directory, array $extensions, bool $recursive): array
    {
        $files = [];
        
        if ($recursive) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory)
            );
        } else {
            $iterator = new \DirectoryIterator($directory);
        }

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), $extensions)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Get file extension from MIME type
     */
    private function getExtensionFromMimeType(string $mimeType): string
    {
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        return $mimeToExt[$mimeType] ?? 'jpg';
    }

    /**
     * Format bytes to human readable format
     */
    public function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Validate image file
     */
    public function validateImage(string $imagePath): array
    {
        $validationResult = [
            'is_valid' => false,
            'file_exists' => file_exists($imagePath),
            'is_readable' => is_readable($imagePath),
            'file_size' => null,
            'file_size_formatted' => null,
            'mime_type' => null,
            'dimensions' => null,
            'errors' => []
        ];

        if (!$validationResult['file_exists']) {
            $validationResult['errors'][] = 'File does not exist';
            return $validationResult;
        }

        if (!$validationResult['is_readable']) {
            $validationResult['errors'][] = 'File is not readable';
            return $validationResult;
        }

        $imageInfo = getimagesize($imagePath);
        if ($imageInfo === false) {
            $validationResult['errors'][] = 'File is not a valid image';
            return $validationResult;
        }

        $validationResult['is_valid'] = true;
        $validationResult['file_size'] = filesize($imagePath);
        $validationResult['file_size_formatted'] = $this->formatBytes($validationResult['file_size']);
        $validationResult['mime_type'] = $imageInfo['mime'];
        $validationResult['dimensions'] = [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1]
        ];

        return $validationResult;
    }
}