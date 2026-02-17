<?php
// src/Command/TestAuthCommand.php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:test-auth',
    description: 'Test authentication system',
)]
class TestAuthCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Find test user
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => 'admin@test.local']);
        
        if (!$user) {
            $io->error('Test user not found');
            return Command::FAILURE;
        }
        
        $io->success('Found user: ' . $user->getUsername() . ' (' . $user->getEmail() . ')');
        $io->writeln('User roles: ' . implode(', ', $user->getRoles()));
        $io->writeln('User active: ' . ($user->isActive() ? 'Yes' : 'No'));
        
        // Test password verification
        $testPassword = 'admin123';
        $isPasswordValid = $this->passwordHasher->isPasswordValid($user, $testPassword);
        
        if ($isPasswordValid) {
            $io->success('Password verification successful!');
        } else {
            $io->error('Password verification failed!');
            
            // Try to update password
            $io->writeln('Updating password...');
            $user->setPassword($this->passwordHasher->hashPassword($user, $testPassword));
            $this->entityManager->flush();
            $io->success('Password updated successfully');
        }
        
        return Command::SUCCESS;
    }
}
