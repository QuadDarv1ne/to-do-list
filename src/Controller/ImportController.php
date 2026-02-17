<?php

namespace App\Controller;

use App\Service\TaskImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/import')]
#[IsGranted('ROLE_USER')]
class ImportController extends AbstractController
{
    public function __construct(
        private TaskImportService $importService
    ) {}
    
    /**
     * Import page
     */
    #[Route('', name: 'app_import_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('import/index.html.twig');
    }
    
    /**
     * Upload and import CSV file
     */
    #[Route('/upload', name: 'app_import_upload', methods: ['POST'])]
    public function upload(Request $request): Response
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('import_file');
        
        if (!$file) {
            $this->addFlash('error', 'Пожалуйста, выберите файл для импорта');
            return $this->redirectToRoute('app_import_index');
        }
        
        // Validate file type
        $allowedExtensions = ['csv', 'txt'];
        $extension = $file->getClientOriginalExtension();
        
        if (!in_array(strtolower($extension), $allowedExtensions)) {
            $this->addFlash('error', 'Неверный формат файла. Разрешены только CSV файлы');
            return $this->redirectToRoute('app_import_index');
        }
        
        // Validate file size (max 5MB)
        if ($file->getSize() > 5 * 1024 * 1024) {
            $this->addFlash('error', 'Файл слишком большой. Максимальный размер: 5MB');
            return $this->redirectToRoute('app_import_index');
        }
        
        try {
            // Move file to temp directory
            $uploadDir = $this->getParameter('kernel.project_dir') . '/var/uploads';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $filename = uniqid() . '.' . $extension;
            $file->move($uploadDir, $filename);
            $filePath = $uploadDir . '/' . $filename;
            
            // Validate CSV
            $validation = $this->importService->validateCSV($filePath);
            
            if (!$validation['valid']) {
                unlink($filePath);
                $this->addFlash('error', 'Ошибка валидации: ' . $validation['error']);
                return $this->redirectToRoute('app_import_index');
            }
            
            // Import tasks
            $results = $this->importService->importFromCSV($filePath, $this->getUser());
            
            // Delete temp file
            unlink($filePath);
            
            // Show results
            if ($results['success'] > 0) {
                $this->addFlash('success', sprintf(
                    'Успешно импортировано задач: %d',
                    $results['success']
                ));
            }
            
            if ($results['failed'] > 0) {
                $this->addFlash('warning', sprintf(
                    'Не удалось импортировать: %d задач',
                    $results['failed']
                ));
                
                // Show first 5 errors
                $errorCount = min(5, count($results['errors']));
                for ($i = 0; $i < $errorCount; $i++) {
                    $this->addFlash('error', $results['errors'][$i]);
                }
                
                if (count($results['errors']) > 5) {
                    $this->addFlash('info', sprintf(
                        'И еще %d ошибок...',
                        count($results['errors']) - 5
                    ));
                }
            }
            
            return $this->redirectToRoute('app_task_index');
            
        } catch (\Exception $e) {
            $this->addFlash('error', 'Ошибка при импорте: ' . $e->getMessage());
            return $this->redirectToRoute('app_import_index');
        }
    }
    
    /**
     * Download CSV template
     */
    #[Route('/template', name: 'app_import_template', methods: ['GET'])]
    public function downloadTemplate(): Response
    {
        $template = $this->importService->generateTemplate();
        
        $response = new Response($template);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="tasks_import_template.csv"');
        
        return $response;
    }
    
    /**
     * Import instructions
     */
    #[Route('/instructions', name: 'app_import_instructions', methods: ['GET'])]
    public function instructions(): Response
    {
        return $this->render('import/instructions.html.twig');
    }
}
