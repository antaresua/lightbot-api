<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\Security\Core\User\UserInterface;

class JWTCreatedListener
{
    public function onJWTCreated(JWTCreatedEvent $event)
    {
        // Отримати користувача
        $user = $event->getUser();

        // Перевірити, чи є користувачем об'єкт UserInterface
        if (!$user instanceof UserInterface) {
            return;
        }

        // Отримати поточний payload
        $payload = $event->getData();

        // Додати поле name до payload
        $payload['name'] = $user->getName();

        // Оновити payload в події
        $event->setData($payload);
    }
}