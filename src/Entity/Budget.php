<?php

namespace App\Entity;

use App\Repository\BudgetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BudgetRepository::class)]
#[ORM\Table(name: 'budgets')]
class Budget
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'name', length: 255)]
    private ?string $title = null;

    // Virtual property for backward compatibility (not mapped to database)
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'total_amount', type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $amount = null;

    // Virtual property for backward compatibility (not mapped to database)
    private ?string $totalAmount = null;

    #[ORM\Column(name: 'spent_amount', type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $usedAmount = null;

    // Virtual property for backward compatibility (not mapped to database)
    private ?string $spentAmount = null;

    #[ORM\Column(name: 'created_by')]
    private ?int $userId = null;

    // Virtual property for backward compatibility (not mapped to database)
    private ?int $createdBy = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\Column(length: 10, options: ['default' => 'USD'])]
    private ?string $currency = 'USD';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = 'active';
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

    /**
     * Alias for getTitle() for backward compatibility
     */
    public function getName(): ?string
    {
        return $this->title;
    }

    /**
     * Alias for setTitle() for backward compatibility
     */
    public function setName(string $name): static
    {
        $this->title = $name;
        $this->name = $name;

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

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;
        $this->totalAmount = $amount;

        return $this;
    }

    /**
     * Alias for getAmount() for backward compatibility
     */
    public function getTotalAmount(): ?string
    {
        return $this->amount;
    }

    /**
     * Alias for setAmount() for backward compatibility
     */
    public function setTotalAmount(string $totalAmount): static
    {
        $this->amount = $totalAmount;
        $this->totalAmount = $totalAmount;

        return $this;
    }

    public function getUsedAmount(): ?string
    {
        return $this->usedAmount;
    }

    public function setUsedAmount(string $usedAmount): static
    {
        $this->usedAmount = $usedAmount;
        $this->spentAmount = $usedAmount;

        return $this;
    }

    /**
     * Alias for getUsedAmount() for backward compatibility
     */
    public function getSpentAmount(): ?string
    {
        return $this->usedAmount;
    }

    /**
     * Alias for setUsedAmount() for backward compatibility
     */
    public function setSpentAmount(string $spentAmount): static
    {
        $this->usedAmount = $spentAmount;
        $this->spentAmount = $spentAmount;

        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): static
    {
        $this->userId = $userId;
        $this->createdBy = $userId;

        return $this;
    }

    /**
     * Alias for getUserId() for backward compatibility
     */
    public function getCreatedBy(): ?int
    {
        return $this->userId;
    }

    /**
     * Alias for setUserId() for backward compatibility
     */
    public function setCreatedBy(int $createdBy): static
    {
        $this->userId = $createdBy;
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getPercentageUsed(): float
    {
        if ($this->amount == 0) {
            return 0;
        }
        
        $percentage = (bcdiv($this->usedAmount ?? '0', $this->amount, 4) * 100);
        return round((float)$percentage, 2);
    }

    public function isOverBudget(): bool
    {
        return bccomp($this->usedAmount ?? '0', $this->amount, 2) > 0;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getRemainingAmount(): string
    {
        return bcsub($this->amount ?? '0', $this->usedAmount ?? '0', 2);
    }
}