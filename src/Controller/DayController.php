<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\DayDTO;
use App\Entity\Day;
use App\Repository\DayRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/days')]
class DayController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface $serializer,
        private readonly DayRepository $dayRepository,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        try {
            $days = $this->dayRepository->findAll();
            $daysDTOs = array_map(fn ($day) => new DayDTO($day->getId(), $day->getDayOfWeek(), $day->getName()), $days);

            return new JsonResponse($daysDTOs, Response::HTTP_OK);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
            $day = $this->dayRepository->find($id);

            if (!$day) {
                return new JsonResponse(['message' => 'Day not found'], Response::HTTP_NOT_FOUND);
            }

            $data = new DayDTO($day->getId(), $day->getDayOfWeek(), $day->getName());

            return new JsonResponse($data, Response::HTTP_OK);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
            // Отримуємо JSON дані з запиту
            $data = $this->serializer->decode($request->getContent(), 'json');

            // Перевіряємо, чи це масив і чи містить він дні з необхідними полями
            if (!is_array($data) || empty($data) || !isset($data[0]['dayOfWeek']) || !isset($data[0]['name'])) {
                return new JsonResponse(['message' => 'Invalid input'], Response::HTTP_BAD_REQUEST);
            }

            $createdDays = [];

            foreach ($data as $dayData) {
                // Перевіряємо, чи містять дані необхідні поля
                if (!isset($dayData['dayOfWeek']) || !isset($dayData['name'])) {
                    return new JsonResponse(['message' => 'Invalid day data'], Response::HTTP_BAD_REQUEST);
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
            $daysDTOs = array_map(fn ($day) => new DayDTO($day->getId(), $day->getDayOfWeek(), $day->getName()), $createdDays);

            return new JsonResponse($daysDTOs, Response::HTTP_CREATED);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
            $day = $this->dayRepository->find($id);

            if (!$day) {
                return new JsonResponse(['message' => 'Day not found'], Response::HTTP_NOT_FOUND);
            }

            $data = $this->serializer->decode($request->getContent(), 'json');

            $day->setDayOfWeek($data['dayOfWeek']);
            $day->setName($data['name']);

            $this->entityManager->flush();

            $data = new DayDTO($day->getId(), $day->getDayOfWeek(), $day->getName());

            return new JsonResponse($data, Response::HTTP_OK);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
            $day = $this->dayRepository->find($id);

            if (!$day) {
                return new JsonResponse(['message' => 'Day not found'], Response::HTTP_NOT_FOUND);
            }

            $this->entityManager->remove($day);
            $this->entityManager->flush();

            return new JsonResponse(['message' => 'Day deleted'], Response::HTTP_NO_CONTENT);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
