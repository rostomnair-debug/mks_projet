<?php

namespace App\Entity;

use App\Repository\PlaceImageRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlaceImageRepository::class)]
#[ORM\Table(name: 'place_images')]
class PlaceImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $place = '';

    #[ORM\Column(length: 255)]
    private string $imagePath = '';

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $photographer = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pexelsUrl = null;

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

    public function getPlace(): string
    {
        return $this->place;
    }

    public function setPlace(string $place): self
    {
        $this->place = $place;

        return $this;
    }

    public function getImagePath(): string
    {
        return $this->imagePath;
    }

    public function setImagePath(string $imagePath): self
    {
        $this->imagePath = $imagePath;

        return $this;
    }

    public function getPhotographer(): ?string
    {
        return $this->photographer;
    }

    public function setPhotographer(?string $photographer): self
    {
        $this->photographer = $photographer;

        return $this;
    }

    public function getPexelsUrl(): ?string
    {
        return $this->pexelsUrl;
    }

    public function setPexelsUrl(?string $pexelsUrl): self
    {
        $this->pexelsUrl = $pexelsUrl;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
