<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\TaskDependency;
use App\Entity\TaskNotification;
use App\Repository\TaskNotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class TaskDependencyNotificationService
{
    private EntityManagerInterface $entityManager;
    private MailerInterface $mailer;
    private UrlGeneratorInterface $urlGenerator;
    private Environment $twig;

    public function __construct(
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        UrlGeneratorInterface $urlGenerator,
        Environment $twig
    ) {
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        $this->urlGenerator = $urlGenerator;
        $this->twig = $twig;
    }

    /**
     * Notify when a task dependency is created
     */
    public function notifyOnDependencyCreated(TaskDependency $dependency): void
    {
        $dependentTask = $dependency->getDependentTask();
        $dependencyTask = $dependency->getDependencyTask();
        
        // Notify the assigned user of the dependent task
        $assignedUser = $dependentTask->getAssignedUser();
        if ($assignedUser) {
            $notification = new TaskNotification();
            $notification->setTask($dependentTask);
            $notification->setRecipient($assignedUser);
            $notification->setSender($dependentTask->getUser() ?: $assignedUser); // Use task creator as sender, fallback to assignee
            $notification->setType('info');
            $notification->setSubject("Новая зависимость для задачи {$dependentTask->getTitle()}");
            $notification->setMessage(
                "Задача '{$dependentTask->getTitle()}' теперь зависит от задачи '{$dependencyTask->getTitle()}'. " .
                "Зависимая задача должна быть завершена перед началом этой задачи."
            );
            $notification->setIsRead(false);
            $notification->setIsSent(false);

            $this->entityManager->persist($notification);
            $this->entityManager->flush();

            // Also send email notification
            $this->sendEmailNotification(
                $assignedUser,
                "Новая зависимость для задачи {$dependentTask->getTitle()}",
                $this->twig->render('emails/task_dependency_created.html.twig', [
                    'user' => $assignedUser,
                    'dependentTask' => $dependentTask,
                    'dependencyTask' => $dependencyTask,
                    'dependency' => $dependency
                ])
            );
        }
    }

    /**
     * Notify when a task dependency is removed
     */
    public function notifyOnDependencyRemoved(TaskDependency $dependency): void
    {
        $dependentTask = $dependency->getDependentTask();
        $dependencyTask = $dependency->getDependencyTask();
        
        // Notify the assigned user of the dependent task
        $assignedUser = $dependentTask->getAssignedUser();
        if ($assignedUser) {
            $notification = new TaskNotification();
            $notification->setTask($dependentTask);
            $notification->setRecipient($assignedUser);
            $notification->setSender($dependentTask->getUser() ?: $assignedUser); // Use task creator as sender, fallback to assignee
            $notification->setType('warning');
            $notification->setSubject("Удалена зависимость для задачи {$dependentTask->getTitle()}");
            $notification->setMessage(
                "Зависимость задачи '{$dependentTask->getTitle()}' от задачи '{$dependencyTask->getTitle()}' была удалена."
            );
            $notification->setIsRead(false);
            $notification->setIsSent(false);

            $this->entityManager->persist($notification);
            $this->entityManager->flush();

            // Also send email notification
            $this->sendEmailNotification(
                $assignedUser,
                "Удалена зависимость для задачи {$dependentTask->getTitle()}",
                $this->twig->render('emails/task_dependency_removed.html.twig', [
                    'user' => $assignedUser,
                    'dependentTask' => $dependentTask,
                    'dependencyTask' => $dependencyTask,
                    'dependency' => $dependency
                ])
            );
        }
    }

    /**
     * Notify when a dependency task is completed (unblocking dependent tasks)
     */
    public function notifyOnDependencySatisfied(TaskDependency $dependency): void
    {
        $dependentTask = $dependency->getDependentTask();
        $dependencyTask = $dependency->getDependencyTask();
        
        // Check if dependent task can now be started
        if ($dependentTask->canStart()) {
            $assignedUser = $dependentTask->getAssignedUser();
            if ($assignedUser) {
                $notification = new TaskNotification();
                $notification->setTask($dependentTask);
                $notification->setRecipient($assignedUser);
                $notification->setSender($dependentTask->getUser() ?: $assignedUser); // Use task creator as sender, fallback to assignee
                $notification->setType('success');
                $notification->setSubject("Можно начать задачу {$dependentTask->getTitle()}");
                $notification->setMessage(
                    "Зависимая задача '{$dependencyTask->getTitle()}' завершена! Теперь можно начать работу над задачей '{$dependentTask->getTitle()}'."
                );
                $notification->setIsRead(false);
                $notification->setIsSent(false);

                $this->entityManager->persist($notification);
                $this->entityManager->flush();

                // Also send email notification
                $this->sendEmailNotification(
                    $assignedUser,
                    "Можно начать задачу {$dependentTask->getTitle()}",
                    $this->twig->render('emails/task_can_start.html.twig', [
                        'user' => $assignedUser,
                        'dependentTask' => $dependentTask,
                        'dependencyTask' => $dependencyTask,
                        'dependency' => $dependency
                    ])
                );
            }
        }
    }

    /**
     * Notify when a dependency task is reopened (blocking dependent tasks again)
     */
    public function notifyOnDependencyUnsatisfied(TaskDependency $dependency): void
    {
        $dependentTask = $dependency->getDependentTask();
        $dependencyTask = $dependency->getDependencyTask();
        
        // Check if dependent task can no longer be started
        if (!$dependentTask->canStart()) {
            $assignedUser = $dependentTask->getAssignedUser();
            if ($assignedUser) {
                $notification = new TaskNotification();
                $notification->setTask($dependentTask);
                $notification->setRecipient($assignedUser);
                $notification->setSender($dependentTask->getUser() ?: $assignedUser); // Use task creator as sender, fallback to assignee
                $notification->setType('warning');
                $notification->setSubject("Задача {$dependentTask->getTitle()} заблокирована");
                $notification->setMessage(
                    "Зависимая задача '{$dependencyTask->getTitle()}' снова открыта. Задача '{$dependentTask->getTitle()}' заблокирована до завершения зависимости."
                );
                $notification->setIsRead(false);
                $notification->setIsSent(false);

                $this->entityManager->persist($notification);
                $this->entityManager->flush();

                // Also send email notification
                $this->sendEmailNotification(
                    $assignedUser,
                    "Задача {$dependentTask->getTitle()} заблокирована",
                    $this->twig->render('emails/task_blocked.html.twig', [
                        'user' => $assignedUser,
                        'dependentTask' => $dependentTask,
                        'dependencyTask' => $dependencyTask,
                        'dependency' => $dependency
                    ])
                );
            }
        }
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification($user, string $subject, string $htmlContent): void
    {
        $email = (new Email())
            ->from($_ENV['MAILER_FROM'] ?? 'noreply@example.com')
            ->to($user->getEmail())
            ->subject($subject)
            ->html($htmlContent);

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Log the error but don't interrupt the process
            error_log("Failed to send email notification: " . $e->getMessage());
        }
    }
}