<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Creates a new user.'
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'The email of the user.')
            ->addArgument('password', InputArgument::REQUIRED, 'The password of the user.')
            ->addArgument(name: 'role', mode: InputArgument::OPTIONAL, description: 'Role of new user.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        // Перевірка, чи існує користувач з таким email
        $existingUser = $this->userRepository->findOneBy(['email' => $email]);

        if ($existingUser) {
            $output->writeln('<error>User with this email already exists!</error>');

            return Command::FAILURE;
        }

        // Створення нового користувача
        $user = new User();
        $user->setEmail($email);

        if ('admin' === $input->getArgument('role')) {
            $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        } else {
            $user->setRoles(['ROLE_USER']);
        }

        // Хешування пароля
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Збереження користувача в базі даних
        $this->em->persist($user);
        $this->em->flush();

        $output->writeln('<info>User successfully created!</info>');

        return Command::SUCCESS;
    }
}
