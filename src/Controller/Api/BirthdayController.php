<?php

namespace App\Controller\Api;

use App\Entity\Birthday;
use App\Repository\BirthdayRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/birthdays')]
class BirthdayController extends AbstractController
{
    #[Route('', name: 'api_birthdays_index', methods: ['GET'])]
    public function index(BirthdayRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $birthdays = $repo->findBy(['user' => $this->getUser()], ['id' => 'DESC']);

        $data = array_map(fn (Birthday $b) => [
            'id' => $b->getId(),
            'name' => $b->getName(),
            'birthday' => $b->getBirthday()?->format('Y-m-d'),
        ], $birthdays);

        return $this->json($data);
    }

    #[Route('/today', name: 'api_birthdays_today', methods: ['GET'])]
    public function today(BirthdayRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $birthdays = $repo->findBy(['user' => $this->getUser()]);

        $today = (new \DateTimeImmutable('today'))->format('m-d');

        $filtered = array_values(array_filter($birthdays, function (Birthday $b) use ($today) {
            return $b->getBirthday()?->format('m-d') === $today;
        }));

        $data = array_map(fn (Birthday $b) => [
            'id' => $b->getId(),
            'name' => $b->getName(),
            'birthday' => $b->getBirthday()?->format('Y-m-d'),
        ], $filtered);

        return $this->json($data);
    }

    #[Route('', name: 'api_birthdays_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($payload['name'], $payload['birthday'])) {
            return $this->json(['error' => 'Champs requis: name, birthday.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $date = new \DateTimeImmutable($payload['birthday']);
        } catch (\Throwable) {
            return $this->json(['error' => 'Format birthday invalide (attendu: YYYY-MM-DD).'], Response::HTTP_BAD_REQUEST);
        }

        $birthday = new Birthday();
        $birthday->setName((string) $payload['name']);
        $birthday->setBirthday($date);
        $birthday->setUser($this->getUser());

        $errors = $validator->validate($birthday);
        if (count($errors) > 0) {
            $list = [];
            foreach ($errors as $e) {
                $list[] = [
                    'field' => $e->getPropertyPath(),
                    'message' => $e->getMessage(),
                ];
            }
            return $this->json(['errors' => $list], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $em->persist($birthday);
        $em->flush();

        return $this->json([
            'id' => $birthday->getId(),
            'name' => $birthday->getName(),
            'birthday' => $birthday->getBirthday()?->format('Y-m-d'),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_birthdays_patch', methods: ['PATCH'])]
    public function patch(
        int $id,
        Request $request,
        BirthdayRepository $repo,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $birthday = $repo->find($id);
        if (!$birthday) {
            return $this->json(['error' => 'Ressource introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if ($birthday->getUser()?->getId() !== $this->getUser()?->getId()) {
            return $this->json(['error' => 'Accès interdit.'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if (array_key_exists('name', $payload)) {
            $birthday->setName((string) $payload['name']);
        }

        if (array_key_exists('birthday', $payload)) {
            try {
                $birthday->setBirthday(new \DateTimeImmutable((string) $payload['birthday']));
            } catch (\Throwable) {
                return $this->json(['error' => 'Format birthday invalide (attendu: YYYY-MM-DD).'], Response::HTTP_BAD_REQUEST);
            }
        }

        $errors = $validator->validate($birthday);
        if (count($errors) > 0) {
            $list = [];
            foreach ($errors as $e) {
                $list[] = [
                    'field' => $e->getPropertyPath(),
                    'message' => $e->getMessage(),
                ];
            }
            return $this->json(['errors' => $list], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $em->flush();

        return $this->json([
            'id' => $birthday->getId(),
            'name' => $birthday->getName(),
            'birthday' => $birthday->getBirthday()?->format('Y-m-d'),
        ]);
    }

    #[Route('/{id}', name: 'api_birthdays_delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        BirthdayRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $birthday = $repo->find($id);
        if (!$birthday) {
            return $this->json(['error' => 'Ressource introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if ($birthday->getUser()?->getId() !== $this->getUser()?->getId()) {
            return $this->json(['error' => 'Accès interdit.'], Response::HTTP_FORBIDDEN);
        }

        $em->remove($birthday);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
