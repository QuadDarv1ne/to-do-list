<?php

namespace App\Entity;

use App\Repository\ResourceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResourceRepository::class)]
#[ORM\Table(name: 'resources')]
class Resource
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.00'])]
    private ?string $hourlyRate = '0.00';

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 40])]
    private ?int $capacityPerWeek = 40;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'available'])]
    private ?string $status = 'available';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToMany(targetEntity: Skill::class, inversedBy: 'resources')]
    private Collection $skills;

    #[ORM\OneToMany(mappedBy: 'resource', targetEntity: ResourceAllocation::class, cascade: ['persist', 'remove'])]
    private Collection $allocations;

    public function __construct()
    {
        $this->skills = new ArrayCollection();
        $this->allocations = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getHourlyRate(): ?string
    {
        return $this->hourlyRate;
    }

    public function setHourlyRate(string $hourlyRate): static
    {
        $this->hourlyRate = $hourlyRate;

        return $this;
    }

    public function getCapacityPerWeek(): ?int
    {
        return $this->capacityPerWeek;
    }

    public function setCapacityPerWeek(int $capacityPerWeek): static
    {
        $this->capacityPerWeek = $capacityPerWeek;

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

    /**
     * @return Collection<int, Skill>
     */
    public function getSkills(): Collection
    {
        return $this->skills;
    }

    public function addSkill(Skill $skill): static
    {
        if (!$this->skills->contains($skill)) {
            $this->skills->add($skill);
        }

        return $this;
    }

    public function removeSkill(Skill $skill): static
    {
        $this->skills->removeElement($skill);

        return $this;
    }

    /**
     * @return Collection<int, ResourceAllocation>
     */
    public function getAllocations(): Collection
    {
        return $this->allocations;
    }

    public function addAllocation(ResourceAllocation $allocation): static
    {
        if (!$this->allocations->contains($allocation)) {
            $this->allocations->add($allocation);
            $allocation->setResource($this);
        }

        return $this;
    }

    public function removeAllocation(ResourceAllocation $allocation): static
    {
        if ($this->allocations->removeElement($allocation)) {
            // set the owning side to null (unless already changed)
            if ($allocation->getResource() === $this) {
                $allocation->setResource(null);
            }
        }

        return $this;
    }

    public function getAvailableHours(\DateTime $date): float
    {
        $allocatedHours = 0;
        foreach ($this->allocations as $allocation) {
            if ($allocation->getDate() == $date && $allocation->getStatus() === 'confirmed') {
                $allocatedHours += $allocation->getHours();
            }
        }

        return $this->capacityPerWeek / 7 - $allocatedHours; // Assuming daily capacity
    }

    public function isAvailable(\DateTime $date, float $hours = 1): bool
    {
        return $this->getAvailableHours($date) >= $hours;
    }
}