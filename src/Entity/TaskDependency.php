<?php

namespace App\Entity;

use App\Repository\TaskDependencyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaskDependencyRepository::class)]
#[ORM\Table(name: 'task_dependencies')]
#[ORM\UniqueConstraint(name: 'unique_dependency', columns: ['dependent_task_id', 'dependency_task_id'])]
class TaskDependency
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Task::class, inversedBy: 'dependencies')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Task $dependentTask;

    #[ORM\ManyToOne(targetEntity: Task::class, inversedBy: 'dependents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Task $dependencyTask;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'blocking'])]
    private string $type = 'blocking';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDependentTask(): Task
    {
        return $this->dependentTask;
    }

    public function setDependentTask(Task $dependentTask): self
    {
        $this->dependentTask = $dependentTask;

        return $this;
    }

    public function getDependencyTask(): Task
    {
        return $this->dependencyTask;
    }

    public function setDependencyTask(Task $dependencyTask): self
    {
        $this->dependencyTask = $dependencyTask;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Check if the dependency is satisfied (dependency task is completed)
     */
    public function isSatisfied(): bool
    {
        return $this->dependencyTask->getStatus() === 'completed';
    }

    /**
     * Check if the dependent task can be started (all blocking dependencies satisfied)
     */
    public function canStartDependentTask(): bool
    {
        if ($this->type !== 'blocking') {
            return true;
        }

        return $this->isSatisfied();
    }
}