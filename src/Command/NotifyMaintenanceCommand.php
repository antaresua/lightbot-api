<?php

namespace App\Command;

use App\Service\TelegramService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:notify:maintenance',
    description: 'Send a notification to Telegram about channel status.'
)]
class NotifyMaintenanceCommand extends Command
{
    public function __construct(
        private readonly TelegramService $telegramService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('status', InputArgument::REQUIRED, 'The status of the channel (off or on)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $status = $input->getArgument('status');
        $message = match ($status) {
            'off' => '⚠️ Канал зупинено на технічне обслуговування\.',
            'on' => '✅ Роботу каналу відновлено\.',
            default => null,
        };

        if ($message === null) {
            $output->writeln('Невідомий статус. Використовуйте "maintenance" або "restored".');
            return Command::FAILURE;
        }

        $this->telegramService->sendMessage($message);
        $output->writeln('Повідомлення відправлено: ' . $message);

        return Command::SUCCESS;
    }
}
