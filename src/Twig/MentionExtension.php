<?php

namespace App\Twig;

use App\Repository\UserRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MentionExtension extends AbstractExtension
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('mention_links', [$this, 'mentionLinks']),
        ];
    }

    /**
     * Replaces @username mentions with styled <span> tags and applies nl2br.
     * Content is expected to be pre-sanitized (stored after strip_tags).
     */
    public function mentionLinks(string $text): string
    {
        $processed = preg_replace_callback('/@([a-zA-Z0-9_]+)/', function (array $matches): string {
            $username = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            $user = $this->userRepository->findOneBy(['username' => $matches[1]]);

            if ($user) {
                return sprintf(
                    '<span class="mention-tag" data-user-id="%d" title="%s">@%s</span>',
                    $user->getId(),
                    htmlspecialchars($user->getFullName(), ENT_QUOTES, 'UTF-8'),
                    $username
                );
            }

            return '@' . $username;
        }, $text);

        return nl2br((string) $processed);
    }
}
