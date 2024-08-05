<?php

namespace App\Command;

use Exception;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

class CheckHostAvailabilityCommand extends Command
{
    private const API = 'https://bot.bondarenkoid.dev/api/light/';
    private const CHECK_INTERVAL = 10;
    private const RETRIES_COUNT_OFF = 2;
    private const STATUS_ON = 'on';
    private const STATUS_OFF = 'off';

    protected static $defaultName = 'app:check-host-availability';

    private Client $client;
    private LoggerInterface $logger;
    private LockFactory $lockFactory;

    public function __construct(Client $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;

        $store = new FlockStore();
        $this->lockFactory = new LockFactory($store);

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

        $lock = $this->lockFactory->createLock('check-host-availability');

        if (!$lock->acquire()) {
            $this->logger->info('Another instance of the command is already running.');
            return Command::SUCCESS;
        }

        try {
            $this->checkHostEveryTenSeconds($url);
        } finally {
            $lock->release();
        }

        return Command::SUCCESS;
    }

    private function checkHostEveryTenSeconds(string $url): void
    {
        $startTime = time();

        while (time() - $startTime < 60) {
            $this->checkHost($url);
            sleep(self::CHECK_INTERVAL);
        }
    }

    private function checkHost(string $url): void
    {
        $lastStatus = $this->getLastStatus();

        $isAvailable = $this->isHostAvailable($url);

        if ($isAvailable) {
            if ($lastStatus && $lastStatus['status'] === self::STATUS_OFF) {
                // Host is available and last status was OFF
                $this->changeStatus(self::STATUS_ON);
            }
        } else {
            if ($lastStatus && $lastStatus['status'] === self::STATUS_ON) {
                // Host is not available and last status was ON
                for ($i = 0; $i < self::RETRIES_COUNT_OFF; $i++) {
                    sleep(self::CHECK_INTERVAL);
                    $isAvailable = $this->retryCheck($url);

                    if ($isAvailable) {
                        break;
                    }
                }

                if (!$isAvailable) {
                    $this->changeStatus(self::STATUS_OFF);
                }
            }
        }
    }

    private function getLastStatus(): ?array
    {
        try {
            $lastStatusJson = $this->client->request('GET', self::API . 'status')->getBody()->getContents();
            return json_decode($lastStatusJson, true);
        } catch (Exception $e) {
            $this->logger->error('Failed to get last status: ' . $e->getMessage());
            return null;
        }
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
        return $this->isHostAvailable($url);
    }

    private function changeStatus(string $status): void
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
