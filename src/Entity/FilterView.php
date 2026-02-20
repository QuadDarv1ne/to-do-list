<?php

namespace App\Entity;

use App\Repository\FilterViewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FilterViewRepository::class)]
#[ORM\Table(name: 'filter_views')]
#[ORM\Index(columns: ['user_id', 'is_default'], name: 'idx_filter_view_user_default')]
#[ORM\Index(columns: ['is_shared'], name: 'idx_filter_view_shared')]
class FilterView
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(type: Types::JSON)]
    private array $filters = [];

    #[ORM\Column(type: Types::JSON)]
    private array $columns = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $sort = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $groupBy = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDefault = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $isShared = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'filterViews')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'filter_view_shared_users')]
    private \Doctrine\Common\Collections\Collection $sharedWithUsers;

    public function __construct()
    {
        $this->sharedWithUsers = new \Doctrine\Common\Collections\ArrayCollection();
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

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function setFilters(array $filters): self
    {
        $this->filters = $filters;
        return $this;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function setColumns(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    public function getSort(): ?array
    {
        return $this->sort;
    }

    public function setSort(?array $sort): self
    {
        $this->sort = $sort;
        return $this;
    }

    public function getGroupBy(): ?string
    {
        return $this->groupBy;
    }

    public function setGroupBy(?string $groupBy): self
    {
        $this->groupBy = $groupBy;
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    public function isShared(): bool
    {
        return $this->isShared;
    }

    public function setIsShared(bool $isShared): self
    {
        $this->isShared = $isShared;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getSharedWithUsers(): \Doctrine\Common\Collections\Collection
    {
        return $this->sharedWithUsers;
    }

    public function addSharedUser(User $user): self
    {
        if (!$this->sharedWithUsers->contains($user)) {
            $this->sharedWithUsers->add($user);
            $this->isShared = true;
        }
        return $this;
    }

    public function removeSharedUser(User $user): self
    {
        $this->sharedWithUsers->removeElement($user);
        if ($this->sharedWithUsers->isEmpty()) {
            $this->isShared = false;
        }
        return $this;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
