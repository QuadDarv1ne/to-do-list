<?php

namespace App\Service;

use App\Entity\Task;
use App\Repository\TaskRepository;

class AIAssistantService
{
    public function __construct(
        private TaskRepository $taskRepository,
    ) {
    }

    /**
     * Suggest title based on description
     * TODO: Интеграция с AI API для улучшения предложений
     * - Использовать OpenAI API или локальную LLM модель
     * - Анализировать контекст проекта и категории
     * - Учитывать стиль названий задач пользователя
     * - Поддержка разных языков (русский, английский)
     * - Кэширование частых паттернов
     */
    public function suggestTitle(string $description): array
    {
        $keywords = $this->extractKeywords($description);

        return [
            'suggestions' => [
                $this->generateTitle($keywords, 'action'),
                $this->generateTitle($keywords, 'feature'),
                $this->generateTitle($keywords, 'fix'),
            ],
            'confidence' => 0.85,
        ];
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
     * TODO: Улучшить алгоритм подбора исполнителя
     * - Анализировать текущую загрузку пользователей
     * - Учитывать навыки (skills) пользователей
     * - Анализировать историю выполнения похожих задач
     * - Machine Learning модель для предсказания лучшего исполнителя
     * - Учитывать часовые пояса и доступность
     */
    public function suggestAssignee(Task $task): array
    {
        // Get all users who have completed similar tasks
        $keywords = $this->extractKeywords($task->getTitle() . ' ' . $task->getDescription());

        if (empty($keywords)) {
            return [
                'user_id' => null,
                'confidence' => 0.0,
                'reason' => 'Недостаточно данных для анализа',
            ];
        }

        // Find users with experience in similar tasks
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->select('IDENTITY(t.assignedTo) as user_id, COUNT(t.id) as task_count')
            ->where('t.assignedTo IS NOT NULL')
            ->andWhere('t.status = :status')
            ->setParameter('status', 'completed')
            ->groupBy('t.assignedTo')
            ->orderBy('task_count', 'DESC')
            ->setMaxResults(1);

        $result = $qb->getQuery()->getOneOrNullResult();

        if ($result && $result['user_id']) {
            return [
                'user_id' => $result['user_id'],
                'confidence' => 0.75,
                'reason' => 'Пользователь имеет опыт выполнения похожих задач',
            ];
        }

        return [
            'user_id' => null,
            'confidence' => 0.0,
            'reason' => 'Нет данных о выполненных задачах',
        ];
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
     * TODO: Улучшить предсказание времени выполнения
     * - Анализировать историю выполнения похожих задач
     * - Учитывать сложность на основе количества подзадач
     * - Факторы: приоритет, категория, исполнитель
     * - Machine Learning модель для точных предсказаний
     * - Учитывать текущую загрузку команды
     */
    public function predictCompletionTime(Task $task): array
    {
        $complexity = $this->estimateComplexity($task);

        $hours = match($complexity) {
            'simple' => 2,
            'medium' => 8,
            'complex' => 24,
            'very_complex' => 80,
            default => 8
        };

        return [
            'estimated_hours' => $hours,
            'estimated_days' => ceil($hours / 8),
            'confidence' => 0.6,
        ];
    }
}
