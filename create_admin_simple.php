<?php
// Simple script to create an admin user

require_once __DIR__.'/vendor/autoload.php';

use App\Kernel;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

// Create the kernel
$kernel = new Kernel($_ENV['APP_ENV'] ?? 'dev', (bool) ($_ENV['APP_DEBUG'] ?? true));
$kernel->boot();

// Get the service container
$container = $kernel->getContainer();

// Get the entity manager and password hasher
$entityManager = $container->get('doctrine.orm.entity_manager');
$passwordHasher = $container->get(UserPasswordHasherInterface::class);

// Create admin user
$user = new User();
$user->setEmail('admin@example.com');
$user->setUsername('admin');
$user->setFirstName('Admin');
$user->setLastName('Administrator');
$user->setIsActive(true);
$user->setIsVerified(true);

// Set password
$hashedPassword = $passwordHasher->hashPassword($user, 'admin123');
$user->setPassword($hashedPassword);

// Assign admin role
$user->setRoles(['ROLE_ADMIN']);

$entityManager->persist($user);
$entityManager->flush();

echo "Admin user created successfully!\n";
echo "Email: admin@example.com\n";
echo "Password: admin123\n";