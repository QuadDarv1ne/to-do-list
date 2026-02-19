<?php

namespace App\Entity;

use App\Repository\ClientInteractionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ClientInteractionRepository::class)]
#[ORM\Table(name: 'client_interactions')]
#[ORM\Index(columns: ['interaction_type'], name: 'idx_client_interactions_type')]
#[ORM\Index(columns: ['interaction_date'], name: 'idx_client_interactions_date')]
class ClientInteraction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'interactions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['call', 'meeting', 'email', 'other'], message: 'Выберите корректный тип взаимодействия')]
    private string $interactionType = 'call'; // call, meeting, email, other

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'Дата взаимодействия обязательна')]
    private ?\DateTimeInterface $interactionDate = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Описание обязательно')]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->interactionDate = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getInteractionType(): string
    {
        return $this->interactionType;
    }

    public function setInteractionType(string $interactionType): static
    {
        $this->interactionType = $interactionType;

        return $this;
    }

    public function getInteractionDate(): ?\DateTimeInterface
    {
        return $this->interactionDate;
    }

    public function setInteractionDate(\DateTimeInterface $interactionDate): static
    {
        $this->interactionDate = $interactionDate;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

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

    /**
     * Get interaction type display name
     */
    public function getInteractionTypeDisplayName(): string
    {
        return match($this->interactionType) {
            'call' => 'Звонок',
            'meeting' => 'Встреча',
            'email' => 'Email',
            'other' => 'Другое',
            default => $this->interactionType,
        };
    }
}
