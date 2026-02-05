<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return self::LOGIN_ROUTE === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $credentials = [
            'email' => $request->request->get('email', ''),
            'password' => $request->request->get('password', ''),
            'csrf_token' => $request->request->get('_csrf_token'),
        ];

        $request->getSession()->set(Security::LAST_USERNAME, $credentials['email']);

        return new Passport(
            new UserBadge($credentials['email'], function ($userIdentifier) {
                $user = $this->entityManager->getRepository(User::class)
                    ->findOneBy(['email' => $userIdentifier]);

                if (!$user) {
                    throw new UserNotFoundException('Пользователь не найден');
                }

                if (!$user->isActive()) {
                    throw new CustomUserMessageAuthenticationException('Аккаунт деактивирован');
                }

                // Обновляем время последнего входа
                $user->setLastLoginAt(new \DateTimeImmutable());
                $this->entityManager->flush();

                return $user;
            }),
            new PasswordCredentials($credentials['password']),
            [
                new CsrfTokenBadge('authenticate', $credentials['csrf_token']),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        // Перенаправляем на дашборд
        return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
        
        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE));
    }
}