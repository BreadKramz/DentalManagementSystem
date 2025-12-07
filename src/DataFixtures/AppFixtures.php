<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Create staff user
        $staff = new User();
        $staff->setEmail('staff@staff.com');
        $staff->setRoles(['ROLE_STAFF']);
        $staff->setPassword($this->passwordHasher->hashPassword($staff, 'staff123'));
        $manager->persist($staff);

        $manager->flush();
    }
}