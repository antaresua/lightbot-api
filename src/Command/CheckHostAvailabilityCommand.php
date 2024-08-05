<?php

namespace App\Command;

use React\EventLoop\LoopInterface;
use React\Http\Browser;
use React\Promise\PromiseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\TimerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class CheckHostAvailabilityCommand extends Command
{
    protected static $defaultName = 'app:check-host-availability';

    private const API_STATUS_URL = 'https://bot.bondarenkoid.dev/api/light/status';
    private const API_CHANGE_STATUS_URL = 'https://bot.bondarenkoid.dev/api/light/';
    private const CHECK_INTERVAL = 10;
    private const STATUS_ON = 'on';
    private const STATUS_OFF = 'off';

    private Browser $browser;
    private LoggerInterface $logger;
    private LoopInterface $loop;
    private ?TimerInterface $timerId = null;
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient, Browser $browser, LoggerInterface $logger, LoopInterface $loop)
    {
        $this->httpClient = $httpClient;
        $this->browser = $browser;
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
        if ($this->timerId === null) {
            $this->timerId = $this->loop->addPeriodicTimer(self::CHECK_INTERVAL, function () use ($url) {
                $this->checkHostAndStatus($url);
            });
        }

        $this->loop->run();

        return Command::SUCCESS;
    }

    private function checkHostAndStatus(string $url): void
    {
        $this->checkHostAvailability($url)->then(
            function (bool $isAvailable) use ($url) {
                // Fetch the current status and then update it if needed
                $this->getCurrentStatus()->then(
                    function (?string $currentStatus) use ($isAvailable) {
                        if ($currentStatus === null) {
                            return;
                        }

                        if ($currentStatus === self::STATUS_ON && !$isAvailable) {
                            $this->updateStatus(self::STATUS_OFF);
                            $this->logger->info('Host is DOWN, but status is ON. Updating status to OFF.');
                        } elseif ($currentStatus === self::STATUS_OFF && $isAvailable) {
                            $this->logger->info('Host is UP, but status is OFF. Updating status to ON.');
                            $this->updateStatus(self::STATUS_ON);
                        }
                    }
                )->catch(function (Throwable $e) {
                    $this->logger->error('Error fetching current status: ' . $e->getMessage());
                });
            }
        )->catch(function (Throwable $e) use ($url) {
            $this->logger->error('Error checking host availability at ' . $url . ': ' . $e->getMessage());
        });
    }

    private function checkHostAvailability(string $url): PromiseInterface
    {
        return $this->browser->get($url)->then(
            function ($response) {
                $statusCode = $response->getStatusCode();
                return $statusCode === 200;
            }
        )->catch(function (Throwable $e) use ($url) {
            return false;
        });
    }

    private function getCurrentStatus(): PromiseInterface
    {
        return $this->browser->get(self::API_STATUS_URL)->then(
            function ($response) {
                $data = json_decode((string)$response->getBody(), true);
                return $data['status'] ?? null;
            }
        )->catch(function (Throwable $e) {
            $this->logger->error('Error fetching current status: ' . $e->getMessage());
            return null;
        });
    }

    private function updateStatus(string $newStatus): void
    {
        $url = self::API_CHANGE_STATUS_URL . $newStatus;

        $newStatus = strtoupper($newStatus);
        try {
            $response = $this->httpClient->request('POST', $url);
            $statusCode = $response->getStatusCode();
            if ($statusCode === 200) {
                $this->logger->info("Status updated to $newStatus successfully.");
            } else {
                $this->logger->error("Failed to update status to $newStatus. Response code: " . $statusCode);
            }
        } catch (ExceptionInterface $e) {
            $this->logger->error("Failed to update status to $newStatus: " . $e->getMessage());
        }
    }
}
