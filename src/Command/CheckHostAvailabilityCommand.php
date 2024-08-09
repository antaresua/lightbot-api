<?php

namespace App\Command;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Serializer\SerializerInterface;

class CheckHostAvailabilityCommand extends Command
{
    protected static $defaultName = 'app:check-host-availability';

    private const API_STATUS_URL = 'https://bot.bondarenkoid.dev/api/light/status';
    private const API_CHANGE_STATUS_URL = 'https://bot.bondarenkoid.dev/api/light/';
    private const CHECK_INTERVAL = 10;
    private const STATUS_ON = 'on';
    private const STATUS_OFF = 'off';

    private LoggerInterface $logger;
    private LoopInterface $loop;
    private ?TimerInterface $timerId = null;
    private ClientInterface $httpClient;
    private ?string $lastKnownStatus = null;
    private SerializerInterface $serializer;

    public function __construct(
        SerializerInterface $serializer,
        ClientInterface $httpClient,
        LoggerInterface $logger,
        LoopInterface $loop
    ) {
        $this->serializer = $serializer;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->loop = $loop;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Checks host availability and updates the status accordingly.')
            ->addArgument('host', InputArgument::REQUIRED, 'The host to check')
            ->addArgument('port', InputArgument::REQUIRED, 'The port to check');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = $input->getArgument('host');
        $port = $input->getArgument('port');
        $url = "http://{$host}:{$port}";

        // Ensure only one timer is created
        if (null === $this->timerId) {
            $this->timerId = $this->loop->addPeriodicTimer(self::CHECK_INTERVAL, function () use ($url) {
                $this->checkHostAndStatus($url);
            });
        }

        $this->loop->run();

        return Command::SUCCESS;
    }

    private function checkHostAndStatus(string $url): void
    {
        $isAvailable = $this->checkHostAvailability($url);
        $currentStatus = $this->getCurrentStatus();

        if (null !== $currentStatus && $currentStatus !== $this->lastKnownStatus) {
            if ($isAvailable && self::STATUS_OFF === $currentStatus) {
                $this->updateStatus(self::STATUS_ON);
                $this->logger->info('Host is UP, updating status to ON.');
            } elseif (!$isAvailable && self::STATUS_ON === $currentStatus) {
                $this->updateStatus(self::STATUS_OFF);
                $this->logger->info('Host is DOWN, updating status to OFF.');
            }
            $this->lastKnownStatus = $currentStatus;
        }
    }

    private function checkHostAvailability(string $url): bool
    {
        try {
            // Виконання GET запиту
            $response = $this->httpClient->request('GET', $url);

            return 200 === $response->getStatusCode();
        } catch (GuzzleException $e) {
            $this->logger->error('Error checking host availability: '.$e->getMessage());

            return false;
        }
    }

    private function getCurrentStatus(): ?string
    {
        try {
            $response = $this->httpClient->request('GET', self::API_STATUS_URL);
            $data = $this->serializer->decode($response->getBody()->getContents(), 'json');

            return $data['status'] ?? null;
        } catch (GuzzleException $e) {
            $this->logger->error('Error fetching current status: '.$e->getMessage());

            return null;
        }
    }

    private function updateStatus(string $newStatus): void
    {
        $url = self::API_CHANGE_STATUS_URL.$newStatus;

        try {
            $response = $this->httpClient->request('POST', $url);
            $statusCode = $response->getStatusCode();
            if (200 === $statusCode) {
                $this->logger->info("Status updated to $newStatus successfully.");
            } else {
                $this->logger->error("Failed to update status to $newStatus. Response code: ".$statusCode);
            }
        } catch (GuzzleException $e) {
            $this->logger->error("Failed to update status to $newStatus: ".$e->getMessage());
        }
    }
}
