<?php

namespace App\Controller;

use App\Entity\Day;
use App\Repository\DayRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @Route("/api/days")
 */
class DayController extends AbstractController
{
    private $entityManager;
    private $serializer;
    private $dayRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        DayRepository $dayRepository
    ) {
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->dayRepository = $dayRepository;
    }

    /**
     * @Route("", methods={"GET"})
     */
    public function index(): JsonResponse
    {
        $days = $this->dayRepository->findAll();
        $data = $this->serializer->serialize($days, 'json');

        return JsonResponse::fromJsonString($data, 200);
    }

    /**
     * @Route("/{id}", methods={"GET"})
     */
    public function show(int $id): JsonResponse
    {
        $day = $this->dayRepository->find($id);

        if (!$day) {
            return new JsonResponse(['message' => 'Day not found'], 404);
        }

        $data = $this->serializer->serialize($day, 'json');

        return JsonResponse::fromJsonString($data, 200);
    }

    /**
     * @Route("", methods={"POST"})
     */
    public function create(Request $request): JsonResponse
    {
        // Отримуємо JSON дані з запиту
        $data = json_decode($request->getContent(), true);

        // Перевіряємо, чи це масив і чи містить він дні з необхідними полями
        if (!is_array($data) || empty($data) || !isset($data[0]['dayOfWeek']) || !isset($data[0]['name'])) {
            return new JsonResponse(['message' => 'Invalid input'], 400);
        }

        $createdDays = [];

        foreach ($data as $dayData) {
            // Перевіряємо, чи містять дані необхідні поля
            if (!isset($dayData['dayOfWeek']) || !isset($dayData['name'])) {
                return new JsonResponse(['message' => 'Invalid day data'], 400);
            }

            $day = new Day();
            $day->setDayOfWeek($dayData['dayOfWeek']);
            $day->setName($dayData['name']);

            $this->entityManager->persist($day);
            $createdDays[] = $day;
        }

        // Зберігаємо дні в базу даних
        $this->entityManager->flush();

        // Сериалізуємо створені дні у форматі JSON
        $responseData = $this->serializer->serialize($createdDays, 'json', ['groups' => 'day']);

        return JsonResponse::fromJsonString($responseData, 201);
    }


    /**
     * @Route("/{id}", methods={"PUT"})
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $day = $this->dayRepository->find($id);

        if (!$day) {
            return new JsonResponse(['message' => 'Day not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        $day->setDayOfWeek($data['dayOfWeek']);
        $day->setName($data['name']);

        $this->entityManager->flush();

        $responseData = $this->serializer->serialize($day, 'json');

        return JsonResponse::fromJsonString($responseData, 200);
    }

    /**
     * @Route("/{id}", methods={"DELETE"})
     */
    public function delete(int $id): JsonResponse
    {
        $day = $this->dayRepository->find($id);

        if (!$day) {
            return new JsonResponse(['message' => 'Day not found'], 404);
        }

        $this->entityManager->remove($day);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Day deleted'], 200);
    }
}
