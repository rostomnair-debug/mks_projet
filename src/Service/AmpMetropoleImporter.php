<?php

namespace App\Service;

use App\Entity\Category;
use App\Entity\Event;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\EventRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\SluggerInterface;

class AmpMetropoleImporter
{
    private const SOURCE = 'ampmetropole';
    private array $categoryCache = [];
    private array $slugCache = [];

    public function __construct(
        private EventRepository $eventRepository,
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        #[Autowire('%app.amp_api_url%')] private string $apiUrl
    ) {
    }

    public function import(User $organizer, int $limit = 20): array
    {
        $data = $this->fetchData($this->apiUrl . '&limit=' . $limit);

        $results = $data['results'] ?? [];
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($results as $row) {
            $externalId = (string) ($row['uuid_poi'] ?? $row['objectid'] ?? '');
            if ($externalId === '') {
                $skipped++;
                continue;
            }

            $event = $this->eventRepository->findOneByExternal(self::SOURCE, $externalId);
            $isNew = false;
            if (!$event) {
                $event = new Event();
                $event->setExternalSource(self::SOURCE);
                $event->setExternalId($externalId);
                $event->setOrganizer($organizer);
                $event->setIsPublished(true);
                $event->setReservedCount(0);
                $event->setCapacity(100);
                $event->setPriceCents(0);
                $isNew = true;
            }

            $title = (string) ($row['nom_poi'] ?? 'Événement');
            $event->setTitle($title);

            $description = (string) ($row['description'] ?? '');
            if ($description === '') {
                $description = 'Événement culturel à Marseille.';
            }
            $event->setDescription($description);

            $startAt = $this->parseDate($row['date_deb'] ?? null);
            $endAt = $this->parseDate($row['date_f'] ?? null);
            if (!$startAt) {
                $startAt = new DateTimeImmutable('+30 days');
            }
            $event->setStartAt($startAt);
            $event->setEndAt($endAt);

            $event->setVenueName((string) ($row['commune'] ?? 'Marseille'));
            $event->setAddress((string) ($row['adresse_postale'] ?? ''));
            $district = (string) ($row['code_postal'] ?? $row['commune'] ?? 'Marseille');
            $event->setDistrict($district);

            $websiteUrl = (string) ($row['site_web'] ?? $row['url_poi'] ?? '');
            if ($websiteUrl !== '') {
                $event->setWebsiteUrl($websiteUrl);
            }

            $latitude = isset($row['latitude']) ? (float) $row['latitude'] : null;
            $longitude = isset($row['longitude']) ? (float) $row['longitude'] : null;
            if ((!$latitude || !$longitude) && isset($row['geo_point_2d'])) {
                $geo = $row['geo_point_2d'];
                if (is_array($geo) && isset($geo['lat'], $geo['lon'])) {
                    $latitude = (float) $geo['lat'];
                    $longitude = (float) $geo['lon'];
                }
            }
            if ($latitude && $longitude) {
                $event->setLatitude($latitude);
                $event->setLongitude($longitude);
            }

            $categoryName = (string) ($row['niv3_categorie'] ?? $row['niv2_categorie'] ?? 'Culture');
            $category = $this->getOrCreateCategory($categoryName);
            $event->setCategory($category);

            $event->setUpdatedAt(new DateTimeImmutable());
            $this->setUniqueSlug($event);

            if ($isNew) {
                $this->entityManager->persist($event);
                $created++;
            } else {
                $updated++;
            }
        }

        $this->entityManager->flush();

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => count($results),
        ];
    }

    private function fetchData(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
            ],
        ]);

        $payload = @file_get_contents($url, false, $context);
        if ($payload === false) {
            throw new \RuntimeException("Impossible de joindre l'API AMP.");
        }

        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        return is_array($data) ? $data : [];
    }

    private function parseDate(?string $value): ?DateTimeImmutable
    {
        if (!$value) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception $exception) {
            return null;
        }
    }

    private function getOrCreateCategory(string $name): Category
    {
        $name = trim($name);
        $key = mb_strtolower($name);
        if (isset($this->categoryCache[$key])) {
            return $this->categoryCache[$key];
        }

        $category = $this->categoryRepository->findOneBy(['name' => $name]);
        if ($category) {
            $this->categoryCache[$key] = $category;
            return $category;
        }

        $slug = $this->slugger->slug($name)->lower()->toString();
        $category = $this->categoryRepository->findOneBy(['slug' => $slug]);
        if ($category) {
            $this->categoryCache[$key] = $category;
            return $category;
        }

        $category = new Category();
        $category->setName($name);
        $category->setSlug($slug);
        $this->entityManager->persist($category);
        $this->categoryCache[$key] = $category;

        return $category;
    }

    private function setUniqueSlug(Event $event): void
    {
        $baseSlug = $this->slugger->slug($event->getTitle())->lower()->toString();
        $slug = $baseSlug;
        $suffix = 1;
        $eventKey = $event->getId() ?? spl_object_id($event);

        while (true) {
            $existing = $this->eventRepository->findOneBy(['slug' => $slug]);
            $isSame = $existing && $existing->getId() === $event->getId();
            $isTakenInBatch = isset($this->slugCache[$slug]) && $this->slugCache[$slug] !== $eventKey;

            if ((!$existing || $isSame) && !$isTakenInBatch) {
                break;
            }

            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        $event->setSlug($slug);
        $this->slugCache[$slug] = $eventKey;
    }
}
