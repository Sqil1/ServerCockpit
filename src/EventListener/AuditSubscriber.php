<?php

namespace App\EventListener;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
class AuditSubscriber
{
    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private EntityManagerInterface $entityManager
    ) {}

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof AuditLog) {
            return;
        }

        $this->createLog('CREATE', $entity, null, $this->entityToArray($entity));
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof AuditLog) {
            return;
        }

        $excludedFields = ['password'];

        $unitOfWork = $this->entityManager->getUnitOfWork();
        $changeSet = $unitOfWork->getEntityChangeSet($entity);

        $oldData = [];
        $newData = [];

        foreach ($changeSet as $field => $changes) {
            if (in_array($field, $excludedFields)) {
                continue;  // Ignore ce champ
            }
            $oldData[$field] = $this->formatValue($changes[0]);
            $newData[$field] = $this->formatValue($changes[1]);
        }

        $this->createLog('UPDATE', $entity, $oldData, $newData);
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof AuditLog) {
            return;
        }

        $this->createLog('DELETE', $entity, $this->entityToArray($entity), null);
    }

    private function createLog(string $action, object $entity, ?array $oldData, ?array $newData): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $auditLog = new AuditLog();
        $auditLog->setAction($action);
        $auditLog->setEntityType($this->getEntityName($entity));
        $auditLog->setEntityId(method_exists($entity, 'getId') ? $entity->getId() : null);
        $auditLog->setOldData($oldData);
        $auditLog->setNewData($newData);

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $auditLog->setUser($user);
        }

        if ($request) {
            $auditLog->setIpAddress($request->getClientIp());
            $auditLog->setUserAgent($request->headers->get('User-Agent'));
        }

        $this->entityManager->persist($auditLog);
        $this->entityManager->flush();
    }

    private function getEntityName(object $entity): string
    {
        $className = get_class($entity);
        return substr($className, strrpos($className, '\\') + 1);
    }

    private function entityToArray(object $entity): array
    {
        $data = [];
        $excludedFields = ['password'];  // Champs à exclure

        $reflection = new \ReflectionClass($entity);

        foreach ($reflection->getProperties() as $property) {
            if (in_array($property->getName(), $excludedFields)) {
                continue;  // Ignore ce champ
            }

            $property->setAccessible(true);
            $value = $property->getValue($entity);
            $data[$property->getName()] = $this->formatValue($value);
        }

        return $data;
    }

    private function formatValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof User) {
            return ['id' => $value->getId(), 'email' => $value->getEmail()];
        }

        if (is_object($value)) {
            return method_exists($value, 'getId') ? $value->getId() : (string) $value;
        }

        return $value;
    }
}