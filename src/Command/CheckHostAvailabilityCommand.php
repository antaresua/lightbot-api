<?php

declare(strict_types=1);

namespace App\Command;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TransferException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[AsCommand(name: 'app:check-host-availability')]
class CheckHostAvailabilityCommand extends Command
{
    private const int CHECK_INTERVAL      = 10;
    private const string STATUS_ON        = 'on';
    private const string STATUS_OFF       = 'off';
    private ?TimerInterface $timerId      = null;
    private bool $unableToConnectLastTime = false;
    private string $apiStatusUrl;
    private string $apiChangeStatusUrl;

    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly LoopInterface $loop,
        ParameterBagInterface $params,
    ) {
        parent::__construct();

        $this->apiStatusUrl       = $params->get('api_status_url');
        $this->apiChangeStatusUrl = $params->get('api_change_status_url');
    }

    protected function configure(): void
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
        $url  = "http://$host:$port";

        // Ensure only one timer is created
        if (null === $this->timerId) {
            $this->timerId = $this->loop->addPeriodicTimer(self::CHECK_INTERVAL, function () use ($url): void {
                $this->checkHostAndStatus($url);
            });
        }

        $this->loop->run();

        return Command::SUCCESS;
    }

    private function checkHostAndStatus(string $url): void
    {
        $isAvailable   = $this->checkHostAvailability($url);
        $hostStatus    = $isAvailable ? self::STATUS_ON : self::STATUS_OFF;
        $currentStatus = $this->getCurrentStatus();

        if ($hostStatus !== $currentStatus) {
            if (self::STATUS_ON === $hostStatus) {
                $this->logger->info('Host is UP, updating status to ON.');
                $this->updateStatus(self::STATUS_ON);
            } else {
                $this->logger->info('Host is DOWN, updating status to OFF.');
                $this->updateStatus(self::STATUS_OFF);
            }
        }
    }

    private function checkHostAvailability(string $url): bool
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout'         => 5, // Максимальний час очікування відповіді
                'connect_timeout' => 2, // Час очікування з'єднання
            ]);

            $this->unableToConnectLastTime = false;

            return 200 === $response->getStatusCode();
        } catch (ConnectException) {
            if (!$this->unableToConnectLastTime) {
                $this->logger->info('Unable to connect to the host...');
            }

            $this->unableToConnectLastTime = true;

            return false;
        } catch (ServerException $e) {
            $this->logger->error('Server error: '.$e->getMessage());

            return false;
        } catch (ClientException $e) {
            $this->logger->error('Client error: '.$e->getMessage());

            return false;
        } catch (TransferException $e) {
            $this->logger->error('Transfer error: '.$e->getMessage());

            return false;
        }
    }

    private function getCurrentStatus(): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $this->apiStatusUrl);
            $data     = $this->serializer->decode($response->getBody()->getContents(), 'json');

            return $data['status'] ?? null;
        } catch (TransferException $e) {
            $this->logger->error('Error fetching current status: '.$e->getMessage());

            return null;
        }
    }

    private function updateStatus(string $newStatus): void
    {
        $url       = $this->apiChangeStatusUrl.'/'.$newStatus;
        $newStatus = strtoupper($newStatus);

        try {
            $response   = $this->httpClient->request('POST', $url);
            $statusCode = $response->getStatusCode();
            if (200 === $statusCode) {
                $this->logger->info("Status updated to $newStatus successfully.");
            } else {
                $this->logger->error("Failed to update status to $newStatus. Response code: ".$statusCode);
            }
        } catch (TransferException $e) {
            $this->logger->error("Failed to update status to $newStatus: ".$e->getMessage());
        }
    }
}
