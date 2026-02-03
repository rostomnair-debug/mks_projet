<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\PlaceImage;
use App\Repository\PlaceImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\SluggerInterface;

class PexelsImageService
{
    public function __construct(
        private PlaceImageRepository $placeImageRepository,
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        #[Autowire('%app.place_uploads_dir%')] private string $uploadsDir,
        #[Autowire('%env(string:PEXELS_API_KEY)%')] private string $apiKey
    ) {
    }

    public function attachImageForEvent(Event $event): void
    {
        if ($event->getImagePath()) {
            return;
        }

        $place = trim($event->getVenueName() . ' ' . $event->getDistrict() . ' Marseille');
        if ($place === '') {
            return;
        }

        $placeImage = $this->getOrFetchPlaceImage($place);
        if ($placeImage) {
            $event->setImagePath($placeImage->getImagePath());
        }
    }

    private function getOrFetchPlaceImage(string $place): ?PlaceImage
    {
        $existing = $this->placeImageRepository->findOneBy(['place' => $place]);
        if ($existing) {
            return $existing;
        }

        $photo = $this->fetchPexelsPhoto($place);
        if (!$photo) {
            return null;
        }

        $imagePath = $this->downloadPhoto($photo['src'] ?? [], $place);
        if (!$imagePath) {
            return null;
        }

        $placeImage = new PlaceImage();
        $placeImage->setPlace($place);
        $placeImage->setImagePath($imagePath);
        $placeImage->setPhotographer($photo['photographer'] ?? null);
        $placeImage->setPexelsUrl($photo['url'] ?? null);

        $this->entityManager->persist($placeImage);
        $this->entityManager->flush();

        return $placeImage;
    }

    private function fetchPexelsPhoto(string $place): ?array
    {
        $query = urlencode($place);
        $url = 'https://api.pexels.com/v1/search?per_page=1&query=' . $query;

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: ' . $this->apiKey],
            CURLOPT_TIMEOUT => 8,
        ]);

        $raw = curl_exec($ch);
        curl_close($ch);

        if (!$raw) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['photos'][0])) {
            return null;
        }

        return $data['photos'][0];
    }

    private function downloadPhoto(array $src, string $place): ?string
    {
        $photoUrl = $src['large'] ?? $src['medium'] ?? null;
        if (!$photoUrl) {
            return null;
        }

        if (!is_dir($this->uploadsDir)) {
            @mkdir($this->uploadsDir, 0775, true);
        }

        $filename = 'place-' . $this->slugger->slug($place)->lower()->toString() . '-' . uniqid() . '.jpg';
        $target = rtrim($this->uploadsDir, '/\\') . DIRECTORY_SEPARATOR . $filename;

        $ch = curl_init($photoUrl);
        if ($ch === false) {
            return null;
        }

        $fp = fopen($target, 'wb');
        if ($fp === false) {
            curl_close($ch);
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $ok = curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        if (!$ok) {
            @unlink($target);
            return null;
        }

        return 'uploads/places/' . $filename;
    }
}
