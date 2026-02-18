<?php

namespace App\Entity;

use App\Repository\DealRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DealRepository::class)]
#[ORM\Table(name: 'deals')]
#[ORM\Index(columns: ['status'], name: 'idx_deals_status')]
#[ORM\Index(columns: ['stage'], name: 'idx_deals_stage')]
#[ORM\Index(columns: ['created_at'], name: 'idx_deals_created_at')]
#[ORM\Index(columns: ['expected_close_date'], name: 'idx_deals_expected_close')]
class Deal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Название сделки обязательно')]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'deals')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Клиент обязателен')]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Менеджер обязателен')]
    private ?User $manager = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    #[Assert\NotBlank(message: 'Сумма сделки обязательна')]
    #[Assert\Positive(message: 'Сумма должна быть положительной')]
    private ?string $amount = '0.00';

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['lead', 'qualification', 'proposal', 'negotiation', 'closing'], message: 'Выберите корректный этап')]
    private string $stage = 'lead'; // lead, qualification, proposal, negotiation, closing

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['in_progress', 'won', 'lost', 'postponed'], message: 'Выберите корректный статус')]
    private string $status = 'in_progress'; // in_progress, won, lost, postponed

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expectedCloseDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $actualCloseDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lostReason = null;

    #[ORM\OneToMany(mappedBy: 'deal', targetEntity: DealHistory::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $history;

    public function __construct()
    {
        $this->history = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;
        return $this;
    }

    public function getManager(): ?User
    {
        return $this->manager;
    }

    public function setManager(?User $manager): static
    {
        $this->manager = $manager;
        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getStage(): string
    {
        return $this->stage;
    }

    public function setStage(string $stage): static
    {
        $oldStage = $this->stage;
        $this->stage = $stage;
        
        // Log stage change
        if ($oldStage !== $stage) {
            $this->addHistoryEntry('stage_changed', "Этап изменён с '{$oldStage}' на '{$stage}'");
        }
        
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $oldStatus = $this->status;
        $this->status = $status;
        
        // Set actual close date when deal is won or lost
        if (in_array($status, ['won', 'lost']) && $this->actualCloseDate === null) {
            $this->actualCloseDate = new \DateTime();
        }
        
        // Log status change
        if ($oldStatus !== $status) {
            $this->addHistoryEntry('status_changed', "Статус изменён с '{$oldStatus}' на '{$status}'");
        }
        
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

    public function getExpectedCloseDate(): ?\DateTimeInterface
    {
        return $this->expectedCloseDate;
    }

    public function setExpectedCloseDate(?\DateTimeInterface $expectedCloseDate): static
    {
        $this->expectedCloseDate = $expectedCloseDate;
        return $this;
    }

    public function getActualCloseDate(): ?\DateTimeInterface
    {
        return $this->actualCloseDate;
    }

    public function setActualCloseDate(?\DateTimeInterface $actualCloseDate): static
    {
        $this->actualCloseDate = $actualCloseDate;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getLostReason(): ?string
    {
        return $this->lostReason;
    }

    public function setLostReason(?string $lostReason): static
    {
        $this->lostReason = $lostReason;
        return $this;
    }

    /**
     * @return Collection<int, DealHistory>
     */
    public function getHistory(): Collection
    {
        return $this->history;
    }

    public function addHistory(DealHistory $history): static
    {
        if (!$this->history->contains($history)) {
            $this->history->add($history);
            $history->setDeal($this);
        }
        return $this;
    }

    public function removeHistory(DealHistory $history): static
    {
        if ($this->history->removeElement($history)) {
            if ($history->getDeal() === $this) {
                $history->setDeal(null);
            }
        }
        return $this;
    }

    /**
     * Add history entry
     */
    private function addHistoryEntry(string $action, string $description): void
    {
        $history = new DealHistory();
        $history->setDeal($this);
        $history->setAction($action);
        $history->setDescription($description);
        $history->setCreatedAt(new \DateTime());
        
        $this->history->add($history);
    }

    /**
     * Get stage display name
     */
    public function getStageDisplayName(): string
    {
        return match($this->stage) {
            'lead' => 'Лид',
            'qualification' => 'Квалификация',
            'proposal' => 'Предложение',
            'negotiation' => 'Переговоры',
            'closing' => 'Закрытие',
            default => $this->stage,
        };
    }

    /**
     * Get status display name
     */
    public function getStatusDisplayName(): string
    {
        return match($this->status) {
            'in_progress' => 'В работе',
            'won' => 'Успешно завершена',
            'lost' => 'Проиграна',
            'postponed' => 'Отложена',
            default => $this->status,
        };
    }

    /**
     * Get days until expected close
     */
    public function getDaysUntilClose(): ?int
    {
        if ($this->expectedCloseDate === null) {
            return null;
        }
        
        $now = new \DateTime();
        $diff = $now->diff($this->expectedCloseDate);
        
        return $diff->invert ? -$diff->days : $diff->days;
    }

    /**
     * Check if deal is overdue
     */
    public function isOverdue(): bool
    {
        if ($this->expectedCloseDate === null || $this->status !== 'in_progress') {
            return false;
        }
        
        return new \DateTime() > $this->expectedCloseDate;
    }

    public function __toString(): string
    {
        return $this->title ?? '';
    }
}
