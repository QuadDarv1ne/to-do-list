<?php

namespace App\Service;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OAuthService
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getGoogleAuthUrl(): string
    {
        $client = $this->clientRegistry->getClient('google');
        
        return $client->getRedirectUrl();
    }

    public function getGitHubAuthUrl(): string
    {
        $client = $this->clientRegistry->getClient('github');
        
        return $client->getRedirectUrl();
    }

    public function getGoogleUser(): array
    {
        $client = $this->clientRegistry->getClient('google');
        $googleUser = $client->fetchUser();

        return [
            'id' => $googleUser->getId(),
            'email' => $googleUser->getEmail(),
            'name' => $googleUser->getName(),
            'avatar' => $googleUser->getAvatar(),
            'provider' => 'google',
        ];
    }

    public function getGitHubUser(): array
    {
        $client = $this->clientRegistry->getClient('github');
        $githubUser = $client->fetchUser();

        return [
            'id' => $githubUser->getId(),
            'email' => $githubUser->getEmail(),
            'name' => $githubUser->getName() ?? $githubUser->getNickname(),
            'avatar' => $githubUser->getAvatarUrl(),
            'provider' => 'github',
        ];
    }
}
