<?php

namespace App\Controller;

use App\Service\MentionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/mentions')]
#[IsGranted('ROLE_USER')]
class MentionController extends AbstractController
{
    #[Route('', name: 'api_mention_suggestions', methods: ['GET'])]
    public function suggestions(Request $request, MentionService $mentionService): JsonResponse
    {
        $query = trim($request->query->get('q', ''));

        if (strlen($query) < 1) {
            return new JsonResponse([]);
        }

        $users = $mentionService->getSuggestions($query, 8);

        $data = array_map(static fn($user) => [
            'id'       => $user->getId(),
            'username' => $user->getUsername(),
            'name'     => $user->getFullName(),
            'initials' => mb_strtoupper(mb_substr($user->getFirstName(), 0, 1) . mb_substr($user->getLastName(), 0, 1)),
        ], $users);

        return new JsonResponse($data);
    }
}
