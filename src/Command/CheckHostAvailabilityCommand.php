<?php

namespace App\Command;

use App\Repository\StatusRepository;
use Exception;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class CheckHostAvailabilityCommand extends Command
{
    private const API = 'https://bot.bondarenkoid.dev/api/light/';
    private const CHECK_INTERVAL = 10;
    private const RETRIES_COUNT_OFF = 2;
    private const RETRIES_COUNT_MAX = 6;
    private const STATUS_ON = 'on';
    private const STATUS_OFF = 'off';

    protected static $defaultName = 'app:check-host-availability';

    private Client $client;
    private LoggerInterface $logger;

    public function __construct(Client $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Checks host availability and updates the status accordingly.')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'The host to check')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'The port to check');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $host = $input->getOption('host');
        $port = $input->getOption('port');
        $url = "http://{$host}:{$port}";

        // Отримання останнього запису статусу
        $lastStatusJson = $this->client->request('GET', self::API . 'status')->getBody()->getContents();
        $lastStatus = json_decode($lastStatusJson, true);

        $count = 1;
        while ($count <= self::RETRIES_COUNT_MAX) {
            $isAvailable = $this->isHostAvailable($url);

            if ($isAvailable) {
                if ($lastStatus && $lastStatus['status'] === self::STATUS_OFF) {
                    // Host is available and last status was OFF
                    $this->changeStatus('on');
                    return Command::SUCCESS;
                }
            } else {
                if ($lastStatus && $lastStatus['status'] === self::STATUS_ON) {
                    // Host is not available and last status was ON
                    for ($i = 0; $i < self::RETRIES_COUNT_OFF; $i++) {
                        sleep(self::CHECK_INTERVAL);
                        $isAvailable = $this->retryCheck($url);
                    }

                    if (!$isAvailable) {
                        $this->changeStatus('off');
                        return Command::SUCCESS;
                    }
                }
            }
            $count++;
            sleep(self::CHECK_INTERVAL);
        }

        return Command::SUCCESS;
    }

    private function isHostAvailable(string $url): bool
    {
        try {
            $response = $this->client->request('GET', $url, ['timeout' => 5]);

            return $response->getStatusCode() === 200;
        } catch (Exception $e) {
            $this->logger->info("Host is down: " . $e->getMessage());

            return false;
        }
    }

    private function retryCheck(string $url): bool
    {
        if ($this->isHostAvailable($url)) {
            return true;
        }

        return false;
    }

    private function changeStatus(string $status)
    {
        $endpoint = self::API . $status;
        try {
            $this->client->request('POST', $endpoint);
            $this->logger->info("Status changed to $status.");
        } catch (Exception $e) {
            $this->logger->error("Failed to change status to $status: " . $e->getMessage());
        }
    }
}