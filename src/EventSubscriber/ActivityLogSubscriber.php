<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Bundle\SecurityBundle\Security;

class ActivityLogSubscriber implements EventSubscriber
{
    public function __construct(
        private Security $security,
    ) {}

    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
            Events::preRemove,
        ];
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof User || $entity instanceof Product) {
            $this->logActivity($args, 'CREATE');
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof User || $entity instanceof Product) {
            $this->logActivity($args, 'UPDATE');
        }
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        // Logging delete after flush to avoid transaction issues
    }

    private function logActivity(LifecycleEventArgs $args, string $action): void
    {
        $user = $this->security->getUser();

        if (!$user) {
            return;
        }

        $entity = $args->getObject();
        $entityManager = $args->getObjectManager();

        $log = new ActivityLog();
        $log->setUser($user);
        $log->setRole(implode(', ', $user->getRoles()));
        $log->setAction($action . ' ' . (new \ReflectionClass($entity))->getShortName());
        $log->setEntityType((new \ReflectionClass($entity))->getShortName());
        $log->setEntityId(method_exists($entity, 'getId') ? $entity->getId() : null);

        $entityManager->persist($log);
        $entityManager->flush();
    }

    private function logActivityNoFlush(LifecycleEventArgs $args, string $action): void
    {
        $user = $this->security->getUser();

        if (!$user) {
            return;
        }

        $entity = $args->getObject();
        $entityManager = $args->getObjectManager();

        $log = new ActivityLog();
        $log->setUser($user);
        $log->setRole(implode(', ', $user->getRoles()));
        $log->setAction($action . ' ' . (new \ReflectionClass($entity))->getShortName());
        $log->setEntityType((new \ReflectionClass($entity))->getShortName());
        $log->setEntityId(method_exists($entity, 'getId') ? $entity->getId() : null);

        $entityManager->persist($log);
        $entityManager->flush();
    }
}