<?php

namespace App\Controller;

use App\Entity\TaskCategory;
use App\Form\TaskCategoryType;
use App\Repository\TaskCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/task-categories')]
class TaskCategoryController extends AbstractController
{
    #[Route('/', name: 'app_task_category_index', methods: ['GET'])]
    public function index(TaskCategoryRepository $taskCategoryRepository): Response
    {
        $user = $this->getUser();
        $categories = $taskCategoryRepository->findByOwner($user);

        return $this->render('task_category/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/new', name: 'app_task_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $taskCategory = new TaskCategory();
        $taskCategory->setOwner($this->getUser());
        
        $form = $this->createForm(TaskCategoryType::class, $taskCategory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($taskCategory);
            $entityManager->flush();

            $this->addFlash('success', 'Категория успешно создана!');

            return $this->redirectToRoute('app_task_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('task_category/form.html.twig', [
            'category' => $taskCategory,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_task_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TaskCategory $taskCategory, EntityManagerInterface $entityManager): Response
    {
        // Check if user owns this category
        if ($taskCategory->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException('У вас нет прав для редактирования этой категории.');
        }

        $form = $this->createForm(TaskCategoryType::class, $taskCategory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $taskCategory->setUpdatedAt();
            $entityManager->flush();

            $this->addFlash('success', 'Категория успешно обновлена!');

            return $this->redirectToRoute('app_task_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('task_category/form.html.twig', [
            'category' => $taskCategory,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_task_category_delete', methods: ['POST'])]
    public function delete(Request $request, TaskCategory $taskCategory, EntityManagerInterface $entityManager): Response
    {
        // Check if user owns this category
        if ($taskCategory->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException('У вас нет прав для удаления этой категории.');
        }

        if ($this->isCsrfTokenValid('delete'.$taskCategory->getId(), $request->request->get('_token'))) {
            $entityManager->remove($taskCategory);
            $entityManager->flush();

            $this->addFlash('success', 'Категория успешно удалена!');
        }

        return $this->redirectToRoute('app_task_category_index', [], Response::HTTP_SEE_OTHER);
    }
}