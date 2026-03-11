<?php

namespace App\Entity;

use App\Repository\SocialAccountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SocialAccountRepository::class)]
#[ORM\Table(name: 'social_accounts')]
#[ORM\UniqueConstraint(name: 'UNIQ_SOCIAL_ACCOUNT', fields: ['provider', 'providerId'])]
class SocialAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $provider = null;

    #[ORM\Column(length: 255)]
    private ?string $providerId = null;

    #[ORM\Column(length: 255)]
    private ?string $providerEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $providerName = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $providerAvatar = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $providerData = null;

    #[ORM\ManyToOne(inversedBy: 'socialAccounts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getProviderId(): ?string
    {
        return $this->providerId;
    }

    public function setProviderId(string $providerId): static
    {
        $this->providerId = $providerId;

        return $this;
    }

    public function getProviderEmail(): ?string
    {
        return $this->providerEmail;
    }

    public function setProviderEmail(string $providerEmail): static
    {
        $this->providerEmail = $providerEmail;

        return $this;
    }

    public function getProviderName(): ?string
    {
        return $this->providerName;
    }

    public function setProviderName(?string $providerName): static
    {
        $this->providerName = $providerName;

        return $this;
    }

    public function getProviderAvatar(): ?string
    {
        return $this->providerAvatar;
    }

    public function setProviderAvatar(?string $providerAvatar): static
    {
        $this->providerAvatar = $providerAvatar;

        return $this;
    }

    public function getProviderData(): ?array
    {
        return $this->providerData;
    }

    public function setProviderData(?array $providerData): static
    {
        $this->providerData = $providerData;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    public function getProviderDisplayName(): string
    {
        return ucfirst($this->provider);
    }
}
