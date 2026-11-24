<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        // 1. Create Admin User
        $admin = new User();
        $admin->setEmail('admin@aag.com')
            ->setFirstName('Super')
            ->setLastName('Admin')
            ->setPhone('+33 6 00 00 00 01')
            ->setStatus('ACTIVE')
            ->setEmailVerifiedAt(new \DateTimeImmutable())
            ->setPassword($this->passwordHasher->hashPassword($admin, 'Admin123!'))
            ->setRole('ROLE_ADMIN');

        $manager->persist($admin);

        // 2. Create Client User
        $client = new User();
        $client->setEmail('client@aag.com')
            ->setFirstName('Jean')
            ->setLastName('Dupont')
            ->setPhone('+33 6 00 00 00 02')
            ->setStatus('ACTIVE')
            ->setEmailVerifiedAt(new \DateTimeImmutable())
            ->setPassword($this->passwordHasher->hashPassword($client, 'Client123!'))
            ->setRole('ROLE_CLIENT');

        $manager->persist($client);

        $manager->flush();
    }
}
