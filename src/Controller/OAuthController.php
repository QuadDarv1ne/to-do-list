<?php

namespace App\Controller;

use App\Entity\SocialAccount;
use App\Entity\User;
use App\Service\OAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/oauth')]
class OAuthController extends AbstractController
{
    public function __construct(
        private readonly OAuthService $oauthService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/google', name: 'app_oauth_google', methods: ['GET'])]
    public function googleRedirect(): Response
    {
        return $this->redirect($this->oauthService->getGoogleAuthUrl());
    }

    #[Route('/google/callback', name: 'app_oauth_google_callback', methods: ['GET'])]
    public function googleCallback(): Response
    {
        try {
            $googleUser = $this->oauthService->getGoogleUser();
            
            return $this->handleOAuthCallback('google', $googleUser);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Ошибка входа через Google: ' . $e->getMessage());
            
            return $this->redirectToRoute('app_login');
        }
    }

    #[Route('/github', name: 'app_oauth_github', methods: ['GET'])]
    public function githubRedirect(): Response
    {
        return $this->redirect($this->oauthService->getGitHubAuthUrl());
    }

    #[Route('/github/callback', name: 'app_oauth_github_callback', methods: ['GET'])]
    public function githubCallback(): Response
    {
        try {
            $githubUser = $this->oauthService->getGitHubUser();
            
            return $this->handleOAuthCallback('github', $githubUser);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Ошибка входа через GitHub: ' . $e->getMessage());
            
            return $this->redirectToRoute('app_login');
        }
    }

    private function handleOAuthCallback(string $provider, array $providerUser): Response
    {
        $socialAccount = $this->entityManager->getRepository(SocialAccount::class)
            ->findOneBy([
                'provider' => $provider,
                'providerId' => (string) $providerUser['id'],
            ]);

        if ($socialAccount) {
            $user = $socialAccount->getUser();
            $socialAccount->setLastLoginAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        } else {
            $user = $this->entityManager->getRepository(User::class)
                ->findOneBy(['email' => $providerUser['email']]);

            if (!$user) {
                $user = (new User())
                    ->setUsername($providerUser['email'])
                    ->setEmail($providerUser['email'])
                    ->setFirstName($providerUser['name'] ?? null)
                    ->setRoles(['ROLE_USER']);
                
                $this->entityManager->persist($user);
            }

            $socialAccount = (new SocialAccount())
                ->setProvider($provider)
                ->setProviderId((string) $providerUser['id'])
                ->setProviderEmail($providerUser['email'])
                ->setProviderName($providerUser['name'] ?? null)
                ->setProviderAvatar($providerUser['avatar'] ?? null)
                ->setProviderData($providerUser)
                ->setUser($user);

            $user->addSocialAccount($socialAccount);
            $this->entityManager->persist($socialAccount);
        }

        $this->entityManager->flush();

        return $this->redirectToRoute('app_dashboard');
    }
}
