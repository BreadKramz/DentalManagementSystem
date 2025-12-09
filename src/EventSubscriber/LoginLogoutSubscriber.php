<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LoginLogoutSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            InteractiveLoginEvent::class => 'onLogin',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        $log = new ActivityLog();
        $log->setUser($user);
        $log->setUsername($user->getUserIdentifier());
        $log->setRole(implode(', ', $user->getRoles()));
        $log->setAction('LOGIN');

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();

        if ($token && $user = $token->getUser()) {
            $log = new ActivityLog();
            $log->setUser($user);
            $log->setUsername($user->getUserIdentifier());
            $log->setRole(implode(', ', $user->getRoles()));
            $log->setAction('LOGOUT');

            $this->entityManager->persist($log);
            $this->entityManager->flush();
        }
    }
}