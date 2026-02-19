<?php

namespace App\Entity;

use App\Repository\KnowledgeBaseArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: KnowledgeBaseArticleRepository::class)]
#[ORM\Table(name: 'knowledge_base_articles')]
#[ORM\Index(columns: ['author_id'], name: 'idx_author')]
#[ORM\Index(columns: ['status'], name: 'idx_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_created')]
#[ORM\Index(columns: ['slug'], name: 'idx_slug')]
class KnowledgeBaseArticle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    #[ORM\ManyToOne(inversedBy: 'articles')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'childArticles')]
    #[ORM\JoinColumn(name: 'parent_article_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?self $parentArticle = null;

    #[ORM\OneToMany(mappedBy: 'parentArticle', targetEntity: self::class)]
    private Collection $childArticles;

    #[ORM\ManyToMany(targetEntity: KnowledgeBaseCategory::class, inversedBy: 'articles')]
    private Collection $categories;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'knowledgeBaseArticles')]
    private Collection $tags;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $viewCount = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $likeCount = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $dislikeCount = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $slug = null;

    public function __construct()
    {
        $this->childArticles = new ArrayCollection();
        $this->categories = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->status = 'published';
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

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): static
    {
        $this->summary = $summary;

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

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

    public function getParentArticle(): ?self
    {
        return $this->parentArticle;
    }

    public function setParentArticle(?self $parentArticle): static
    {
        $this->parentArticle = $parentArticle;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildArticles(): Collection
    {
        return $this->childArticles;
    }

    public function addChildArticle(self $childArticle): static
    {
        if (!$this->childArticles->contains($childArticle)) {
            $this->childArticles->add($childArticle);
            $childArticle->setParentArticle($this);
        }

        return $this;
    }

    public function removeChildArticle(self $childArticle): static
    {
        if ($this->childArticles->removeElement($childArticle)) {
            // set the owning side to null (unless already changed)
            if ($childArticle->getParentArticle() === $this) {
                $childArticle->setParentArticle(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, KnowledgeBaseCategory>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(KnowledgeBaseCategory $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(KnowledgeBaseCategory $category): static
    {
        $this->categories->removeElement($category);

        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    public function getViewCount(): ?int
    {
        return $this->viewCount;
    }

    public function setViewCount(int $viewCount): static
    {
        $this->viewCount = $viewCount;

        return $this;
    }

    public function incrementViewCount(): static
    {
        $this->viewCount++;

        return $this;
    }

    public function getLikeCount(): ?int
    {
        return $this->likeCount;
    }

    public function setLikeCount(?int $likeCount): static
    {
        $this->likeCount = $likeCount;

        return $this;
    }

    public function incrementLikeCount(): static
    {
        $this->likeCount = ($this->likeCount ?? 0) + 1;

        return $this;
    }

    public function getDislikeCount(): ?int
    {
        return $this->dislikeCount;
    }

    public function setDislikeCount(?int $dislikeCount): static
    {
        $this->dislikeCount = $dislikeCount;

        return $this;
    }

    public function incrementDislikeCount(): static
    {
        $this->dislikeCount = ($this->dislikeCount ?? 0) + 1;

        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): static
    {
        $this->metaDescription = $metaDescription;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }
}