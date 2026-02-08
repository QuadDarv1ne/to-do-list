<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class UserRegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request, 
        UserPasswordHasherInterface $passwordHasher, 
        EntityManagerInterface $entityManager,
        AuthenticationUtils $authenticationUtils
    ): Response {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash the password
            $hashedPassword = $passwordHasher->hashPassword($user, $user->getPassword());
            $user->setPassword($hashedPassword);
            
            // Set default role
            if (empty($user->getRoles())) {
                $user->setRoles(['ROLE_USER']);
            }
            
            $entityManager->persist($user);
            $entityManager->flush();

            // Automatically log in the user after registration
            return $this->redirectToRoute('app_login');
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        return $this->render('registration/register.html.twig', [
            'registration_form' => $form->createView(),
            'error' => $error,
        ]);
    }
}