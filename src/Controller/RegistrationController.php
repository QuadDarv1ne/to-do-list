<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\PerformanceMonitorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/register')]
class RegistrationController extends AbstractController
{
    #[Route('/', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, ?PerformanceMonitorService $performanceMonitor = null): Response
    {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('registration_controller_register');
        }
        
        // Rate limiting: Check if too many registrations from same IP recently
        $session = $request->getSession();
        $lastRegistration = $session->get('last_registration_attempt', 0);
        $currentTime = time();
        
        // Prevent registration attempts within 30 seconds of last attempt
        if ($currentTime - $lastRegistration < 30) {
            $this->addFlash('error', 'Пожалуйста, подождите перед повторной попыткой регистрации.');
            
            try {
                return $this->redirectToRoute('app_register');
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTimer('registration_controller_register');
                }
            }
        }
        
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // Update last registration attempt time
            $session->set('last_registration_attempt', $currentTime);
            
            // Encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Регистрация прошла успешно!');

            try {
                return $this->redirectToRoute('app_login');
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTimer('registration_controller_register');
                }
            }
        }

        try {
            return $this->render('registration/register.html.twig', [
                'registration_form' => $form->createView(),
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('registration_controller_register');
            }
        }
    }
}