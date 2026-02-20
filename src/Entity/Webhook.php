<?php

namespace App\Entity;

use App\Repository\WebhookRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WebhookRepository::class)]
#[ORM\Table(name: 'webhooks')]
#[ORM\HasLifecycleCallbacks]
class Webhook
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Название webhook обязательно')]
    #[Assert\Length(max: 255, maxMessage: 'Название не может быть длиннее {{ limit }} символов')]
    private ?string $name = null;

    #[ORM\Column(length: 2048)]
    #[Assert\NotBlank(message: 'URL webhook обязателен')]
    #[Assert\Url(message: 'Некорректный URL формат')]
    #[Assert\Length(max: 2048, maxMessage: 'URL не может быть длиннее {{ limit }} символов')]
    private ?string $url = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $secret = null;

    #[ORM\Column(type: Types::JSON)]
    private array $events = [];

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastTriggeredAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'webhooks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'webhook', targetEntity: WebhookLog::class, orphanRemoval: true, cascade: ['persist'])]
    private Collection $logs;

    public function __construct()
    {
        $this->logs = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(?string $secret): static
    {
        $this->secret = $secret;
        return $this;
    }

    /**
     * @return list<string>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * @param list<string> $events
     */
    public function setEvents(array $events): static
    {
        $this->events = $events;
        return $this;
    }

    public function addEvent(string $event): static
    {
        if (!in_array($event, $this->events, true)) {
            $this->events[] = $event;
        }
        return $this;
    }

    public function removeEvent(string $event): static
    {
        $key = array_search($event, $this->events, true);
        if ($key !== false) {
            unset($this->events[$key]);
            $this->events = array_values($this->events);
        }
        return $this;
    }

    public function hasEvent(string $event): bool
    {
        return in_array($event, $this->events, true) || in_array('*', $this->events, true);
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getLastTriggeredAt(): ?\DateTimeInterface
    {
        return $this->lastTriggeredAt;
    }

    public function setLastTriggeredAt(?\DateTimeInterface $lastTriggeredAt): static
    {
        $this->lastTriggeredAt = $lastTriggeredAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return Collection<int, WebhookLog>
     */
    public function getLogs(): Collection
    {
        return $this->logs;
    }

    public function addLog(WebhookLog $log): static
    {
        if (!$this->logs->contains($log)) {
            $this->logs->add($log);
            $log->setWebhook($this);
        }
        return $this;
    }

    public function removeLog(WebhookLog $log): static
    {
        if ($this->logs->removeElement($log) && $log->getWebhook() === $this) {
            $log->setWebhook(null);
        }
        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    /**
     * Generate a random secret key
     */
    public function generateSecret(): string
    {
        $this->secret = bin2hex(random_bytes(32));
        return $this->secret;
    }

    /**
     * Get available event types
     */
    public static function getAvailableEvents(): array
    {
        return [
            'task.created' => 'Создание задачи',
            'task.updated' => 'Обновление задачи',
            'task.completed' => 'Завершение задачи',
            'task.deleted' => 'Удаление задачи',
            'deal.created' => 'Создание сделки',
            'deal.won' => 'Сделка выиграна',
            'deal.lost' => 'Сделка проиграна',
            'client.created' => 'Создание клиента',
            'comment.added' => 'Добавление комментария',
        ];
    }
}
