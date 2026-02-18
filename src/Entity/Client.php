<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\Table(name: 'clients')]
#[ORM\Index(columns: ['segment'], name: 'idx_clients_segment')]
#[ORM\Index(columns: ['category'], name: 'idx_clients_category')]
#[ORM\Index(columns: ['created_at'], name: 'idx_clients_created_at')]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Название организации обязательно')]
    #[Assert\Length(max: 255)]
    private ?string $companyName = null;

    #[ORM\Column(length: 12, nullable: true)]
    #[Assert\Regex(pattern: '/^\d{10}$|^\d{12}$/', message: 'ИНН должен содержать 10 или 12 цифр')]
    private ?string $inn = null;

    #[ORM\Column(length: 9, nullable: true)]
    #[Assert\Regex(pattern: '/^\d{9}$/', message: 'КПП должен содержать 9 цифр')]
    private ?string $kpp = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $contactPerson = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(pattern: '/^\+7\d{10}$/', message: 'Телефон должен быть в формате +79993332211')]
    private ?string $phone = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email(message: 'Некорректный email')]
    private ?string $email = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['retail', 'wholesale'], message: 'Выберите корректный сегмент')]
    private string $segment = 'retail'; // retail, wholesale

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['new', 'regular', 'vip', 'potential'], message: 'Выберите корректную категорию')]
    private string $category = 'new'; // new, regular, vip, potential

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $manager = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastContactAt = null;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Deal::class)]
    private Collection $deals;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: ClientInteraction::class, cascade: ['persist', 'remove'])]
    private Collection $interactions;

    public function __construct()
    {
        $this->deals = new ArrayCollection();
        $this->interactions = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(string $companyName): static
    {
        $this->companyName = $companyName;
        return $this;
    }

    public function getInn(): ?string
    {
        return $this->inn;
    }

    public function setInn(?string $inn): static
    {
        $this->inn = $inn;
        return $this;
    }

    public function getKpp(): ?string
    {
        return $this->kpp;
    }

    public function setKpp(?string $kpp): static
    {
        $this->kpp = $kpp;
        return $this;
    }

    public function getContactPerson(): ?string
    {
        return $this->contactPerson;
    }

    public function setContactPerson(?string $contactPerson): static
    {
        $this->contactPerson = $contactPerson;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getSegment(): string
    {
        return $this->segment;
    }

    public function setSegment(string $segment): static
    {
        $this->segment = $segment;
        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
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

    public function getLastContactAt(): ?\DateTimeInterface
    {
        return $this->lastContactAt;
    }

    public function setLastContactAt(?\DateTimeInterface $lastContactAt): static
    {
        $this->lastContactAt = $lastContactAt;
        return $this;
    }

    /**
     * @return Collection<int, Deal>
     */
    public function getDeals(): Collection
    {
        return $this->deals;
    }

    public function addDeal(Deal $deal): static
    {
        if (!$this->deals->contains($deal)) {
            $this->deals->add($deal);
            $deal->setClient($this);
        }
        return $this;
    }

    public function removeDeal(Deal $deal): static
    {
        if ($this->deals->removeElement($deal)) {
            if ($deal->getClient() === $this) {
                $deal->setClient(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ClientInteraction>
     */
    public function getInteractions(): Collection
    {
        return $this->interactions;
    }

    public function addInteraction(ClientInteraction $interaction): static
    {
        if (!$this->interactions->contains($interaction)) {
            $this->interactions->add($interaction);
            $interaction->setClient($this);
        }
        return $this;
    }

    public function removeInteraction(ClientInteraction $interaction): static
    {
        if ($this->interactions->removeElement($interaction)) {
            if ($interaction->getClient() === $this) {
                $interaction->setClient(null);
            }
        }
        return $this;
    }

    /**
     * Get total revenue from all deals
     */
    public function getTotalRevenue(): float
    {
        $total = 0;
        foreach ($this->deals as $deal) {
            if ($deal->getStatus() === 'won') {
                $total += $deal->getAmount();
            }
        }
        return $total;
    }

    /**
     * Get average deal amount
     */
    public function getAverageCheck(): float
    {
        $wonDeals = $this->deals->filter(fn($deal) => $deal->getStatus() === 'won');
        $count = $wonDeals->count();
        
        if ($count === 0) {
            return 0;
        }
        
        return $this->getTotalRevenue() / $count;
    }

    /**
     * Get number of completed deals
     */
    public function getCompletedDealsCount(): int
    {
        return $this->deals->filter(fn($deal) => $deal->getStatus() === 'won')->count();
    }

    public function __toString(): string
    {
        return $this->companyName ?? '';
    }
}
