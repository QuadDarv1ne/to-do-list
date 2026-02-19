<?php

namespace App\Controller;

use App\Entity\Habit;
use App\Repository\HabitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/habits')]
#[IsGranted('ROLE_USER')]
class HabitController extends AbstractController
{
    #[Route('/', name: 'app_habits_index', methods: ['GET'])]
    public function index(HabitRepository $habitRepository): Response
    {
        $user = $this->getUser();
        $habits = $habitRepository->findActiveByUser($user);

        return $this->render('habits/index.html.twig', [
            'habits' => $habits,
        ]);
    }

    #[Route('/{id}', name: 'app_habits_show', methods: ['GET'])]
    public function show(Habit $habit): Response
    {
        $this->denyAccessUnlessGranted('view', $habit);

        return $this->render('habits/show.html.twig', [
            'habit' => $habit,
        ]);
    }
}
