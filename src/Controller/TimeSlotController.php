<?php

namespace App\Controller;

use App\DTO\DayDTO;
use App\DTO\TimeSlotDTO;
use App\Entity\Day;
use App\Entity\TimeSlot;
use App\Repository\DayRepository;
use App\Repository\TimeSlotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @Route("/api/timeslots")
 */
class TimeSlotController extends AbstractController
{
    private $entityManager;
    private $serializer;
    private $timeSlotRepository;
    private $dayRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        TimeSlotRepository $timeSlotRepository,
        DayRepository $dayRepository
    ) {
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->timeSlotRepository = $timeSlotRepository;
        $this->dayRepository = $dayRepository;
    }

    /**
     * @Route("", methods={"POST"})
     */
    public function create(Request $request): JsonResponse
    {
        $data = $this->serializer->decode($request->getContent(), 'json');

        if (!is_array($data) || empty($data)) {
            return new JsonResponse(['message' => 'Invalid input'], 400);
        }

        $createdTimeSlots = [];
        $errors = [];

        foreach ($data as $index => $timeSlotData) {
            if (!isset($timeSlotData['startTime'], $timeSlotData['endTime'], $timeSlotData['startDay'], $timeSlotData['endDay'], $timeSlotData['type'])) {
                $errors[] = ['index' => $index, 'message' => 'Missing fields'];
                continue;
            }

            $startDay = $this->dayRepository->findOneBy(['dayOfWeek' => $timeSlotData['startDay']]);
            $endDay = $this->dayRepository->findOneBy(['dayOfWeek' => $timeSlotData['endDay']]);

            if (!$startDay || !$endDay) {
                $errors[] = ['index' => $index, 'message' => 'Invalid day reference'];
                continue;
            }

            try {
                $timeSlot = new TimeSlot();
                $timeSlot->setStartTime(\DateTime::createFromFormat('H:i', $timeSlotData['startTime']));
                $timeSlot->setEndTime(\DateTime::createFromFormat('H:i', $timeSlotData['endTime']));
                $timeSlot->setStartDay($startDay);
                $timeSlot->setEndDay($endDay);
                $timeSlot->setType($timeSlotData['type']);

                $this->entityManager->persist($timeSlot);
                $createdTimeSlots[] = $timeSlot;
            } catch (\Exception $e) {
                $errors[] = ['index' => $index, 'message' => $e->getMessage()];
            }
        }

        if (!empty($errors)) {
            return new JsonResponse(['errors' => $errors], 400);
        }

        $this->entityManager->flush();

        $responseData = $this->serializer->serialize($createdTimeSlots, 'json');

        return JsonResponse::fromJsonString($responseData, 201);
    }

    /**
     * @Route("", methods={"GET"})
     */
    public function index(): JsonResponse
    {
        $timeSlots = $this->timeSlotRepository->findAll();
        $result = [];
        foreach ($timeSlots as $timeSlot) {
            $item = new TimeSlotDTO(
                $timeSlot->getId(),
                $timeSlot->getStartTime()->format('H:i'),
                $timeSlot->getEndTime()->format('H:i'),
                new DayDTO($timeSlot->getStartDay()->getId(), $timeSlot->getStartDay()->getDayOfWeek(), $timeSlot->getStartDay()->getName()),
                new DayDTO($timeSlot->getEndDay()->getId(), $timeSlot->getEndDay()->getDayOfWeek(), $timeSlot->getEndDay()->getName()),
                $timeSlot->getType(),
            );
            $result[] = $item;
        }
        $data = $this->serializer->serialize($result, 'json');

        return JsonResponse::fromJsonString($data, 200);
    }

    /**
     * @Route("/{id}", methods={"GET"})
     */
    public function show(int $id): JsonResponse
    {
        $timeSlot = $this->timeSlotRepository->find($id);

        if (!$timeSlot) {
            return new JsonResponse(['message' => 'TimeSlot not found'], 404);
        }

        $timeSlotDTO = new TimeSlotDTO(
            $timeSlot->getId(),
            $timeSlot->getStartTime()->format('H:i'),
            $timeSlot->getEndTime()->format('H:i'),
            new DayDTO($timeSlot->getStartDay()->getId(), $timeSlot->getStartDay()->getDayOfWeek(), $timeSlot->getStartDay()->getName()),
            new DayDTO($timeSlot->getEndDay()->getId(), $timeSlot->getEndDay()->getDayOfWeek(), $timeSlot->getEndDay()->getName()),
            $timeSlot->getType(),
        );

        $data = $this->serializer->serialize($timeSlotDTO, 'json');

        return JsonResponse::fromJsonString($data, 200);
    }

    /**
     * @Route("/{id}", methods={"DELETE"})
     */
    public function delete(int $id): JsonResponse
    {
        $timeSlot = $this->timeSlotRepository->find($id);

        if (!$timeSlot) {
            return new JsonResponse(['message' => 'TimeSlot not found'], 404);
        }

        $this->entityManager->remove($timeSlot);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'TimeSlot deleted'], 200);
    }

    /**
     * @Route("/{id}", methods={"PUT"})
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $timeSlot = $this->timeSlotRepository->find($id);

        if (!$timeSlot) {
            return new JsonResponse(['message' => 'TimeSlot not found'], 404);
        }

        $data = $this->serializer->decode($request->getContent(), true);

        $timeSlot->setStartTime(\DateTime::createFromFormat('H:i', $data['startTime']));
        $timeSlot->setEndTime(\DateTime::createFromFormat('H:i', $data['endTime']));
        $timeSlot->setStartDay($this->dayRepository->findOneBy(['dayOfWeek' => $data['startDay']['dayOfWeek']]));
        $timeSlot->setEndDay($this->dayRepository->findOneBy(['dayOfWeek' => $data['endDay']['dayOfWeek']]));
        $timeSlot->setType($data['type']);

        $this->entityManager->flush();

        $responseData = $this->serializer->serialize($timeSlot, 'json');

        return JsonResponse::fromJsonString($responseData, 200);
    }

    /**
     * @Route("/day/{dayOfWeek}", methods={"GET"})
     */
    public function getByDay(int $dayOfWeek): JsonResponse
    {
        // Validate dayOfWeek
        if ($dayOfWeek < 0 || $dayOfWeek > 6) {
            return new JsonResponse(['message' => 'Invalid day of week'], 400);
        }

        // Find day entity by dayOfWeek
        $day = $this->dayRepository->findOneBy(['dayOfWeek' => $dayOfWeek]);

        if (!$day) {
            return new JsonResponse(['message' => 'Day not found'], 404);
        }

        // Find time slots where the day is either startDay or endDay
        $timeSlots = $this->timeSlotRepository->findByDay($day);

        $result = [];
        foreach ($timeSlots as $timeSlot) {
            $item = new TimeSlotDTO(
                $timeSlot->getId(),
                $timeSlot->getStartTime()->format('H:i'),
                $timeSlot->getEndTime()->format('H:i'),
                new DayDTO($timeSlot->getStartDay()->getId(), $timeSlot->getStartDay()->getDayOfWeek(), $timeSlot->getStartDay()->getName()),
                new DayDTO($timeSlot->getEndDay()->getId(), $timeSlot->getEndDay()->getDayOfWeek(), $timeSlot->getEndDay()->getName()),
                $timeSlot->getType(),
            );
            $result[] = $item;
        }
        $data = $this->serializer->serialize($result, 'json');

        return JsonResponse::fromJsonString($data, 200);
    }
}
