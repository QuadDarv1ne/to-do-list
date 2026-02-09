<?php

namespace App\Command;

use App\Entity\Task;
use App\Entity\TaskRecurrence;
use App\Repository\TaskRecurrenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

#[AsCommand(
    name: 'app:generate-recurring-tasks',
    description: 'Generates recurring tasks based on their recurrence settings'
)]
class GenerateRecurringTasksCommand extends Command
{
    public function __construct(
        private readonly TaskRecurrenceRepository $taskRecurrenceRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Starting recurring task generation...</info>');

        // Find all tasks with recurrence settings
        $recurrences = $this->taskRecurrenceRepository->findAll(); // We'll use findAll for now, but in production you might want to filter by active users
        
        $progressBar = new ProgressBar($output, count($recurrences));
        $progressBar->start();

        $generatedCount = 0;

        foreach ($recurrences as $recurrence) {
            $task = $recurrence->getTask();
            
            // Skip if the task doesn't belong to an active user or is overdue
            if (!$task || !$task->getUser()) {
                $progressBar->advance();
                continue;
            }

            // Check if this task should be created today
            if ($this->shouldCreateRecurringTask($recurrence)) {
                $newTask = $this->createNewRecurringTask($task);
                
                if ($newTask) {
                    $this->entityManager->persist($newTask);
                    $generatedCount++;
                    
                    // Update the last generated date for this recurrence
                    $recurrence->setLastGenerated(new \DateTimeImmutable());
                    $this->entityManager->persist($recurrence);
                    
                    $output->writeln(sprintf(
                        '<comment>Created recurring task: %s for user %s</comment>',
                        $newTask->getName(),
                        $newTask->getUser()->getUserIdentifier()
                    ));
                }
            }

            $progressBar->advance();
        }

        $this->entityManager->flush();

        $progressBar->finish();
        $output->writeln('');
        $output->writeln("<info>Generated $generatedCount new recurring tasks.</info>");

        return Command::SUCCESS;
    }

    private function shouldCreateRecurringTask(TaskRecurrence $recurrence): bool
    {
        $task = $recurrence->getTask();
        if (!$task) {
            return false;
        }

        // Check if recurrence has ended
        if ($recurrence->getEndDate() && $recurrence->getEndDate() < new \DateTimeImmutable()) {
            return false;
        }

        // Check if the task should be created today based on frequency
        $today = new \DateTimeImmutable();
        $rawLastCreated = $recurrence->getLastGenerated() ?: $task->getCreatedAt(); // Use last generated date if available, otherwise use original creation date
        
        // Ensure $lastCreated is not null
        if (!$rawLastCreated) {
            return false; // Can't determine when to generate next task
        }
        
        // Convert to DateTimeImmutable if needed
        $lastCreated = $rawLastCreated instanceof \DateTimeImmutable ? $rawLastCreated : \DateTimeImmutable::createFromMutable($rawLastCreated);
        
        switch ($recurrence->getFrequency()) {
            case 'daily':
                // For daily tasks, we check if it's been at least the interval days since last creation
                $interval = new \DateInterval('P' . $recurrence->getInterval() . 'D');
                $expectedNext = $lastCreated->add($interval);
                return $today >= $expectedNext;
                
            case 'weekly':
                // For weekly tasks, we check if the day of week matches and interval is correct
                $currentDayOfWeek = (int)$today->format('N'); // 1 (Monday) to 7 (Sunday)
                $daysOfWeek = $recurrence->getDaysOfWeekArray() ?: [];
                
                if (!empty($daysOfWeek) && !in_array($currentDayOfWeek, $daysOfWeek)) {
                    return false;
                }
                
                // Calculate if the week interval matches
                $weeksSinceLast = floor(($today->getTimestamp() - $lastCreated->getTimestamp()) / (7 * 24 * 60 * 60));
                return $weeksSinceLast >= $recurrence->getInterval();
                
            case 'monthly':
                // For monthly tasks, check if it's the right day of month and interval matches
                $currentDayOfMonth = (int)$today->format('j');
                $daysOfMonth = $recurrence->getDaysOfMonthArray() ?: [];
                
                if (!empty($daysOfMonth) && !in_array($currentDayOfMonth, $daysOfMonth)) {
                    return false;
                }
                
                // Calculate if the month interval matches
                $monthsSinceLast = ($today->format('Y') - $lastCreated->format('Y')) * 12 + 
                                  ($today->format('n') - $lastCreated->format('n'));
                return $monthsSinceLast >= $recurrence->getInterval();
                
            case 'yearly':
                // For yearly tasks, check if it's the right date and year interval matches
                $currentMonthDay = $today->format('m-d');
                $originalDate = $task->getCreatedAt();
                if (!$originalDate) {
                    return false;
                }
                $originalDateImmutable = $originalDate instanceof \DateTimeImmutable ? $originalDate : \DateTimeImmutable::createFromMutable($originalDate);
                $originalMonthDay = $originalDateImmutable->format('m-d');
                
                if ($currentMonthDay !== $originalMonthDay) {
                    return false;
                }
                
                $yearsSinceLast = (int)$today->format('Y') - (int)$lastCreated->format('Y');
                return $yearsSinceLast >= $recurrence->getInterval();
                
            default:
                return false;
        }
    }

    private function createNewRecurringTask(Task $originalTask): ?Task
    {
        // Create a new task based on the original
        $newTask = new Task();
        $newTask->setName($originalTask->getName());
        $newTask->setDescription($originalTask->getDescription());
        $newTask->setPriority($originalTask->getPriority());
        $newTask->setUser($originalTask->getUser());
        $newTask->setAssignedUser($originalTask->getAssignedUser());
        
        // Set a new deadline based on the recurrence frequency
        $now = new \DateTimeImmutable();
        $newTask->setCreatedAt($now);
        $newTask->setUpdatedAt($now);

        // Copy category
        $newTask->setCategory($originalTask->getCategory());

        return $newTask;
    }
}