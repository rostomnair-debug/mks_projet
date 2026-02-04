<?php

namespace App\Entity;

use App\Repository\ReportRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReportRepository::class)]
#[ORM\Table(name: 'reports')]
class Report
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CLOSED = 'closed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Event $event = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\Column(type: Types::JSON)]
    #[Assert\Count(min: 1, minMessage: 'Sélectionnez au moins une raison.')]
    private array $reasons = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $eventTitle = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $eventSlug = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $actionTaken = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adminResponse = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $respondedAt = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): self
    {
        $this->event = $event;

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
     * @return string[]
     */
    public function getReasons(): array
    {
        return $this->reasons;
    }

    /**
     * @param string[] $reasons
     */
    public function setReasons(array $reasons): self
    {
        $this->reasons = $reasons;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getEventTitle(): ?string
    {
        return $this->eventTitle;
    }

    public function setEventTitle(?string $eventTitle): self
    {
        $this->eventTitle = $eventTitle;

        return $this;
    }

    public function getEventSlug(): ?string
    {
        return $this->eventSlug;
    }

    public function setEventSlug(?string $eventSlug): self
    {
        $this->eventSlug = $eventSlug;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getActionTaken(): ?string
    {
        return $this->actionTaken;
    }

    public function setActionTaken(?string $actionTaken): self
    {
        $this->actionTaken = $actionTaken;

        return $this;
    }

    public function getAdminResponse(): ?string
    {
        return $this->adminResponse;
    }

    public function setAdminResponse(?string $adminResponse): self
    {
        $this->adminResponse = $adminResponse;

        return $this;
    }

    public function getRespondedAt(): ?DateTimeImmutable
    {
        return $this->respondedAt;
    }

    public function setRespondedAt(?DateTimeImmutable $respondedAt): self
    {
        $this->respondedAt = $respondedAt;

        return $this;
    }
}
