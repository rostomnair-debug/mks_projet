<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Event;
use App\Entity\Reservation;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $categories = [];
        foreach (['Exposition', 'Concert', 'Festival', 'Spectacle', 'Atelier'] as $name) {
            $category = (new Category())
                ->setName($name)
                ->setSlug(strtolower(str_replace(' ', '-', $name)));
            $manager->persist($category);
            $categories[] = $category;
        }

        $admin = (new User())
            ->setEmail('admin@mks.local')
            ->setUsername('admin')
            ->setRoles(['ROLE_ADMIN'])
            ->setIsVerified(true);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin1234'));
        $manager->persist($admin);

        $user = (new User())
            ->setEmail('user@mks.local')
            ->setUsername('user')
            ->setRoles(['ROLE_USER'])
            ->setIsVerified(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'user1234'));
        $manager->persist($user);

        $eventsData = [
            ['Nuit au Mucem', '+5 days', 'Mucem', '1 esplanade J4', '2e', 120, 0],
            ['Concerts sur le Vieux-Port', '+10 days', 'Vieux-Port', 'Quai du Port', '1er', 300, 1500],
            ['Expo immersive La Joliette', '+15 days', 'La Joliette', 'Place de la Joliette', '2e', 80, 1200],
        ];

        foreach ($eventsData as $index => [$title, $offset, $venue, $address, $district, $capacity, $price]) {
            $event = new Event();
            $event
                ->setTitle($title)
                ->setSlug(strtolower(str_replace(' ', '-', $title)))
                ->setDescription('Description de demo pour ' . $title)
                ->setStartAt(new DateTimeImmutable($offset))
                ->setEndAt(new DateTimeImmutable($offset . ' +2 hours'))
                ->setVenueName($venue)
                ->setAddress($address)
                ->setDistrict($district)
                ->setCapacity($capacity)
                ->setPriceCents($price)
                ->setCategory($categories[$index % count($categories)])
                ->setOrganizer($admin)
                ->setIsPublished(true)
                ->setUpdatedAt(new DateTimeImmutable());

            $manager->persist($event);

            if ($index === 1) {
                $reservation = new Reservation();
                $reservation
                    ->setEvent($event)
                    ->setUser($user)
                    ->setQuantity(2);
                $event->incrementReservedCount(2);
                $manager->persist($reservation);
            }
        }

        $manager->flush();
    }
}
