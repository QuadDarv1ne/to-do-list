<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AIAssistantService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Suggest title based on description
     */
    public function suggestTitle(string $description, ?string $context = null): array
    {
        // Пробуем использовать AI если доступен
        if ($this->isAIAvailable()) {
            $aiSuggestions = $this->getAISuggestions($description, 'title');
            if (!empty($aiSuggestions)) {
                return [
                    'suggestions' => $aiSuggestions,
                    'confidence' => 0.9,
                    'source' => 'ai',
                ];
            }
        }

        // Fallback на keyword-based подход
        $keywords = $this->extractKeywords($description);

        return [
            'suggestions' => [
                $this->generateTitle($keywords, 'action'),
                $this->generateTitle($keywords, 'feature'),
                $this->generateTitle($keywords, 'fix'),
            ],
            'confidence' => 0.7,
            'source' => 'keywords',
        ];
    }

    /**
     * Check if AI API is available
     */
    private function isAIAvailable(): bool
    {
        // Проверяем наличие API ключа в .env
        return !empty($_ENV['OPENAI_API_KEY'] ?? false);
    }

    /**
     * Get suggestions from AI API
     */
    private function getAISuggestions(string $input, string $type): array
    {
        if (!$this->isAIAvailable()) {
            return [];
        }

        try {
            $prompt = match($type) {
                'title' => "Generate 3 concise task titles (max 5 words each) based on: $input",
                'description' => "Generate a structured task description for: $input",
                default => "Analyze: $input",
            };

            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $_ENV['OPENAI_API_KEY'],
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens' => 100,
                ],
                'timeout' => 5,
            ]);

            $data = $response->toArray();
            
            return explode("\n", $data['choices'][0]['message']['content'] ?? '');
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Auto-complete task description
     */
    public function autoCompleteDescription(string $partial): array
    {
        $suggestions = [];

        // Common patterns
        if (str_contains(strtolower($partial), 'исправить')) {
            $suggestions[] = "**Описание проблемы:**\n\n**Шаги воспроизведения:**\n1. \n2. \n\n**Ожидаемое поведение:**\n\n**Фактическое поведение:**";
        }

        if (str_contains(strtolower($partial), 'добавить')) {
            $suggestions[] = "**Описание функции:**\n\n**Зачем это нужно:**\n\n**Критерии приемки:**\n- [ ] \n- [ ] ";
        }

        return [
            'suggestions' => $suggestions,
            'type' => 'description',
        ];
    }

    /**
     * Suggest priority based on task content
     */
    public function suggestPriority(Task $task): string
    {
        $score = 0;
        $title = strtolower($task->getTitle() ?? '');
        $description = strtolower($task->getDescription() ?? '');

        // Urgent keywords
        $urgentKeywords = ['срочно', 'критично', 'баг', 'ошибка', 'не работает', 'сломано', 'production'];
        foreach ($urgentKeywords as $keyword) {
            if (str_contains($title, $keyword) || str_contains($description, $keyword)) {
                $score += 30;
            }
        }

        // High priority keywords
        $highKeywords = ['важно', 'приоритет', 'клиент', 'deadline'];
        foreach ($highKeywords as $keyword) {
            if (str_contains($title, $keyword) || str_contains($description, $keyword)) {
                $score += 20;
            }
        }

        // Check deadline
        if ($task->getDeadline()) {
            $daysUntil = (new \DateTime())->diff($task->getDeadline())->days;
            if ($daysUntil <= 1) {
                $score += 25;
            } elseif ($daysUntil <= 3) {
                $score += 15;
            } elseif ($daysUntil <= 7) {
                $score += 10;
            }
        }

        return match(true) {
            $score >= 50 => 'urgent',
            $score >= 30 => 'high',
            $score >= 15 => 'medium',
            default => 'low'
        };
    }

    /**
     * Suggest assignee based on task content and workload
     */
    public function suggestAssignee(Task $task): array
    {
        // Получаем ключевые слова из задачи
        $keywords = $this->extractKeywords($task->getTitle() . ' ' . $task->getDescription());

        if (empty($keywords)) {
            return [
                'user_id' => null,
                'confidence' => 0.0,
                'reason' => 'Недостаточно данных для анализа',
            ];
        }

        // Анализируем загрузку пользователей
        $users = $this->userRepository->findAll();
        $userScores = [];

        foreach ($users as $user) {
            $score = 0;
            
            // Получаем статистику пользователя
            $userStats = $this->getUserStats($user);
            
            // Учитываем опыт выполнения похожих задач
            $similarTasks = $this->getSimilarTasksCount($user, $keywords);
            $score += $similarTasks * 10;
            
            // Учитываем текущую загрузку (меньше задач = выше приоритет)
            $activeTasks = $userStats['active_tasks'] ?? 0;
            $score += max(0, 20 - $activeTasks * 2);
            
            // Учитываем успешность выполнения
            $completionRate = $userStats['completion_rate'] ?? 50;
            $score += $completionRate / 10;

            $userScores[$user->getId()] = [
                'user' => $user,
                'score' => $score,
            ];
        }

        // Сортируем по убыванию scores
        usort($userScores, fn ($a, $b) => $b['score'] <=> $a['score']);

        if (!empty($userScores) && $userScores[0]['score'] > 0) {
            $bestUser = $userScores[0]['user'];
            return [
                'user_id' => $bestUser->getId(),
                'confidence' => min(0.9, $userScores[0]['score'] / 50),
                'reason' => sprintf(
                    'Лучший кандидат: %d похожих задач, %d активных, %.1f%% завершений',
                    $similarTasks ?? 0,
                    $activeTasks ?? 0,
                    $completionRate ?? 0
                ),
            ];
        }

        return [
            'user_id' => null,
            'confidence' => 0.0,
            'reason' => 'Нет подходящих кандидатов',
        ];
    }

    /**
     * Get user statistics
     */
    private function getUserStats(User $user): array
    {
        $qb = $this->em->createQueryBuilder();
        
        // Активные задачи
        $active = (int) $qb->select('COUNT(t.id)')
            ->from(\App\Entity\Task::class, 't')
            ->where('t.assignedUser = :user')
            ->andWhere('t.status != :completed')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        // Завершённые задачи
        $completed = (int) $qb->select('COUNT(t.id)')
            ->from(\App\Entity\Task::class, 't')
            ->where('t.assignedUser = :user')
            ->andWhere('t.status = :completed')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        $total = $active + $completed;
        $completionRate = $total > 0 ? ($completed / $total) * 100 : 0;

        return [
            'active_tasks' => $active,
            'completed_tasks' => $completed,
            'completion_rate' => $completionRate,
        ];
    }

    /**
     * Get count of similar tasks for user
     */
    private function getSimilarTasksCount(User $user, array $keywords): int
    {
        if (empty($keywords)) {
            return 0;
        }

        $qb = $this->em->createQueryBuilder();
        $pattern = '%' . implode('%', array_slice($keywords, 0, 2)) . '%';
        
        return (int) $qb->select('COUNT(t.id)')
            ->from(\App\Entity\Task::class, 't')
            ->where('t.assignedUser = :user')
            ->andWhere('t.status = :completed')
            ->andWhere('(t.title LIKE :pattern OR t.description LIKE :pattern)')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed')
            ->setParameter('pattern', $pattern)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Suggest deadline based on task complexity
     */
    public function suggestDeadline(Task $task): \DateTime
    {
        $complexity = $this->estimateComplexity($task);

        $days = match($complexity) {
            'simple' => 1,
            'medium' => 3,
            'complex' => 7,
            'very_complex' => 14,
            default => 3
        };

        return (new \DateTime())->modify("+$days days");
    }

    /**
     * Estimate task complexity
     */
    public function estimateComplexity(Task $task): string
    {
        $description = $task->getDescription() ?? '';
        $wordCount = str_word_count($description);

        // Simple heuristic based on description length
        return match(true) {
            $wordCount < 20 => 'simple',
            $wordCount < 50 => 'medium',
            $wordCount < 100 => 'complex',
            default => 'very_complex'
        };
    }

    /**
     * Suggest related tasks
     */
    public function suggestRelatedTasks(Task $task, int $limit = 5): array
    {
        $keywords = $this->extractKeywords($task->getTitle() . ' ' . $task->getDescription());

        if (empty($keywords)) {
            return [];
        }

        // Build search pattern from keywords
        $searchPattern = '%' . implode('%', \array_slice($keywords, 0, 3)) . '%';

        $qb = $this->taskRepository->createQueryBuilder('t')
            ->where('t.id != :currentId')
            ->setParameter('currentId', $task->getId())
            ->andWhere('(t.title LIKE :pattern OR t.description LIKE :pattern)')
            ->setParameter('pattern', $searchPattern)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Suggest tags based on content
     */
    public function suggestTags(Task $task): array
    {
        $content = strtolower($task->getTitle() . ' ' . $task->getDescription());
        $suggestedTags = [];

        $tagPatterns = [
            'bug' => ['баг', 'ошибка', 'не работает', 'сломано'],
            'feature' => ['функция', 'добавить', 'новый', 'feature'],
            'improvement' => ['улучшить', 'оптимизировать', 'рефакторинг'],
            'documentation' => ['документация', 'readme', 'docs'],
            'testing' => ['тест', 'testing', 'qa'],
            'security' => ['безопасность', 'security', 'уязвимость'],
            'performance' => ['производительность', 'медленно', 'оптимизация'],
        ];

        foreach ($tagPatterns as $tag => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($content, $keyword)) {
                    $suggestedTags[] = $tag;

                    break;
                }
            }
        }

        return array_unique($suggestedTags);
    }

    /**
     * Generate smart checklist
     */
    public function generateChecklist(Task $task): array
    {
        $type = $this->detectTaskType($task);

        return match($type) {
            'bug' => [
                'Воспроизвести ошибку',
                'Найти причину',
                'Написать тест',
                'Исправить код',
                'Проверить исправление',
                'Обновить документацию',
            ],
            'feature' => [
                'Проанализировать требования',
                'Создать дизайн',
                'Написать код',
                'Написать тесты',
                'Code review',
                'Обновить документацию',
            ],
            'deployment' => [
                'Проверить тесты',
                'Создать бэкап',
                'Подготовить миграции',
                'Деплой на staging',
                'Тестирование на staging',
                'Деплой на production',
                'Мониторинг',
            ],
            default => [
                'Начать работу',
                'Выполнить задачу',
                'Проверить результат',
                'Завершить',
            ]
        };
    }

    /**
     * Detect task type
     */
    private function detectTaskType(Task $task): string
    {
        $content = strtolower($task->getTitle() . ' ' . $task->getDescription());

        if (preg_match('/баг|ошибка|bug|fix/i', $content)) {
            return 'bug';
        }
        if (preg_match('/функция|feature|добавить/i', $content)) {
            return 'feature';
        }
        if (preg_match('/деплой|deploy|релиз/i', $content)) {
            return 'deployment';
        }
        if (preg_match('/тест|test|qa/i', $content)) {
            return 'testing';
        }

        return 'general';
    }

    /**
     * Extract keywords from text
     */
    private function extractKeywords(string $text): array
    {
        $text = strtolower($text);
        $words = preg_split('/\\s+/', $text);

        // Remove common words
        $stopWords = ['и', 'в', 'на', 'с', 'для', 'по', 'из', 'к', 'о', 'от', 'the', 'a', 'an', 'in', 'on', 'at'];
        $keywords = array_diff($words, $stopWords);

        return \array_slice(array_values($keywords), 0, 5);
    }

    /**
     * Generate title from keywords
     */
    private function generateTitle(array $keywords, string $type): string
    {
        $prefix = match($type) {
            'action' => 'Выполнить',
            'feature' => 'Добавить',
            'fix' => 'Исправить',
            default => 'Задача'
        };

        return $prefix . ': ' . implode(' ', \array_slice($keywords, 0, 3));
    }

    /**
     * Analyze task sentiment
     */
    public function analyzeSentiment(Task $task): array
    {
        $content = strtolower($task->getTitle() . ' ' . $task->getDescription());

        $positiveWords = ['отлично', 'хорошо', 'успешно', 'готово'];
        $negativeWords = ['проблема', 'ошибка', 'не работает', 'срочно', 'критично'];

        $positiveCount = 0;
        $negativeCount = 0;

        foreach ($positiveWords as $word) {
            $positiveCount += substr_count($content, $word);
        }

        foreach ($negativeWords as $word) {
            $negativeCount += substr_count($content, $word);
        }

        $sentiment = match(true) {
            $negativeCount > $positiveCount => 'negative',
            $positiveCount > $negativeCount => 'positive',
            default => 'neutral'
        };

        return [
            'sentiment' => $sentiment,
            'score' => $positiveCount - $negativeCount,
            'confidence' => 0.7,
        ];
    }

    /**
     * Predict completion time
     */
    public function predictCompletionTime(Task $task): array
    {
        $complexity = $this->estimateComplexity($task);
        
        // Получаем среднее время выполнения похожих задач
        $avgTime = $this->getAverageCompletionTime($task);
        
        // Учитываем приоритет (срочные задачи делаются быстрее)
        $priorityFactor = match($task->getPriority()) {
            'urgent' => 0.7,
            'high' => 0.85,
            'medium' => 1.0,
            'low' => 1.2,
            default => 1.0
        };

        // Учитываем загрузку исполнителя
        $workloadFactor = 1.0;
        if ($task->getAssignedUser()) {
            $stats = $this->getUserStats($task->getAssignedUser());
            $activeTasks = $stats['active_tasks'] ?? 0;
            $workloadFactor = 1.0 + ($activeTasks * 0.1); // +10% за каждую активную задачу
        }

        $baseHours = match($complexity) {
            'simple' => 2,
            'medium' => 8,
            'complex' => 24,
            'very_complex' => 80,
            default => 8
        };

        // Используем среднее время если оно есть
        if ($avgTime > 0) {
            $baseHours = $avgTime;
        }

        $estimatedHours = $baseHours * $priorityFactor * $workloadFactor;

        return [
            'estimated_hours' => round($estimatedHours, 1),
            'estimated_days' => ceil($estimatedHours / 8),
            'confidence' => $avgTime > 0 ? 0.8 : 0.6,
            'factors' => [
                'complexity' => $complexity,
                'priority_factor' => $priorityFactor,
                'workload_factor' => round($workloadFactor, 2),
                'historical_avg' => round($avgTime, 1),
            ],
        ];
    }

    /**
     * Get average completion time for similar tasks
     */
    private function getAverageCompletionTime(Task $task): float
    {
        $qb = $this->em->createQueryBuilder();
        
        $qb->select('AVG(t.completedAt - t.createdAt) as avg_time')
            ->from(\App\Entity\Task::class, 't')
            ->where('t.status = :completed')
            ->andWhere('t.category = :category')
            ->setParameter('completed', 'completed')
            ->setParameter('category', $task->getCategory());
        
        $result = $qb->getQuery()->getOneOrNullResult();
        
        if ($result && $result['avg_time']) {
            // Конвертируем секунды в часы
            return (float) ($result['avg_time'] / 3600);
        }
        
        return 0;
    }
}
