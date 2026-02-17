<?php

namespace App\Command;

use App\Service\ImageOptimizerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:optimize-images',
    description: 'Optimize images in the application'
)]
class ImageOptimizerCommand extends Command
{
    private ImageOptimizerService $imageOptimizerService;

    public function __construct(ImageOptimizerService $imageOptimizerService)
    {
        $this->imageOptimizerService = $imageOptimizerService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Action to perform: optimize-single, optimize-dir, validate', 'optimize-single')
            ->addOption('image-path', 'i', InputOption::VALUE_REQUIRED, 'Path to image file')
            ->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'Directory to optimize images in')
            ->addOption('quality', 'q', InputOption::VALUE_REQUIRED, 'Quality for optimization (1-100)', '80')
            ->addOption('max-width', null, InputOption::VALUE_REQUIRED, 'Maximum width for resized images')
            ->addOption('max-height', null, InputOption::VALUE_REQUIRED, 'Maximum height for resized images')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: jpg, png, webp, gif')
            ->addOption('recursive', 'r', InputOption::VALUE_NONE, 'Process directory recursively')
            ->addOption('strip-metadata', 's', InputOption::VALUE_NONE, 'Strip metadata from images')
            ->addOption('format-output', 'o', InputOption::VALUE_REQUIRED, 'Output format: text, json', 'text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getOption('action');
        $formatOutput = $input->getOption('format-output');

        $io->title('Image Optimizer Tool');

        // Build options array
        $options = [
            'quality' => (int)$input->getOption('quality'),
            'max_width' => $input->getOption('max-width') ? (int)$input->getOption('max-width') : null,
            'max_height' => $input->getOption('max-height') ? (int)$input->getOption('max-height') : null,
            'format' => $input->getOption('format'),
            'strip_metadata' => $input->getOption('strip-metadata'),
        ];

        switch ($action) {
            case 'optimize-single':
                $imagePath = $input->getOption('image-path');
                
                if (!$imagePath) {
                    $io->error('Image path is required for optimize-single action');
                    return 1;
                }
                
                $io->writeln("Optimizing image: {$imagePath}");
                
                $result = $this->imageOptimizerService->optimizeImage($imagePath, $options);
                
                if ($formatOutput === 'json') {
                    $output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                } else {
                    if ($result['success']) {
                        $io->success('Image optimized successfully!');
                        $io->table(
                            ['Metric', 'Value'],
                            [
                                ['Original Size', $result['original_size_formatted']],
                                ['Optimized Size', $result['optimized_size_formatted']],
                                ['Size Reduction', $result['size_reduction_formatted']],
                                ['Reduction Percentage', $result['reduction_percentage'] . '%'],
                                ['Dimensions', $result['dimensions']['width'] . 'x' . $result['dimensions']['height']],
                                ['Image Type', $result['image_type']],
                            ]
                        );
                    } else {
                        $io->error('Image optimization failed: ' . $result['error']);
                        return 1;
                    }
                }
                break;

            case 'optimize-dir':
                $directory = $input->getOption('directory');
                $recursive = $input->getOption('recursive');
                
                if (!$directory) {
                    $io->error('Directory path is required for optimize-dir action');
                    return 1;
                }
                
                $io->writeln("Optimizing images in directory: {$directory}" . ($recursive ? ' (recursive)' : ''));
                
                $results = $this->imageOptimizerService->optimizeImagesInDirectory($directory, $options, $recursive);
                
                if ($formatOutput === 'json') {
                    $output->writeln(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                } else {
                    $io->success('Batch optimization completed!');
                    $io->table(
                        ['Metric', 'Value'],
                        [
                            ['Processed', $results['processed']],
                            ['Successful', $results['successful']],
                            ['Failed', $results['failed']],
                            ['Total Original Size', $this->imageOptimizerService->formatBytes($results['total_original_size'])],
                            ['Total Optimized Size', $this->imageOptimizerService->formatBytes($results['total_optimized_size'])],
                            ['Total Size Reduction', $this->imageOptimizerService->formatBytes($results['total_size_reduction'])],
                            ['Overall Reduction', round($results['total_reduction_percentage'], 2) . '%'],
                        ]
                    );
                    
                    if ($results['failed'] > 0) {
                        $io->warning($results['failed'] . ' images failed to optimize');
                    }
                }
                break;

            case 'validate':
                $imagePath = $input->getOption('image-path');
                
                if (!$imagePath) {
                    $io->error('Image path is required for validate action');
                    return 1;
                }
                
                $io->writeln("Validating image: {$imagePath}");
                
                $validation = $this->imageOptimizerService->validateImage($imagePath);
                
                if ($formatOutput === 'json') {
                    $output->writeln(json_encode($validation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                } else {
                    if ($validation['is_valid']) {
                        $io->success('Image is valid');
                        $io->table(
                            ['Property', 'Value'],
                            [
                                ['File Size', $validation['file_size_formatted']],
                                ['MIME Type', $validation['mime_type']],
                                ['Dimensions', $validation['dimensions']['width'] . 'x' . $validation['dimensions']['height']],
                            ]
                        );
                    } else {
                        $io->error('Image is invalid');
                        $io->listing($validation['errors']);
                        return 1;
                    }
                }
                break;

            default:
                $io->error("Unknown action: {$action}. Use optimize-single, optimize-dir, or validate.");
                return 1;
        }

        return 0;
    }
}
