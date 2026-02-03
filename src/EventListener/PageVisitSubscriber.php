<?php

namespace App\EventListener;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class PageVisitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $route = $request->attributes->get('_route');

        $ignoredRoutes = ['_wdt', '_profiler', 'app_login', 'app_logout'];
        if (!$route || in_array($route, $ignoredRoutes) || str_starts_with($route, '_')) {
            return;
        }

        if (!$response->isSuccessful() || !str_contains($response->headers->get('Content-Type', ''), 'html')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $auditLog = new AuditLog();
        $auditLog->setAction('PAGE_VIEW');
        $auditLog->setEntityType('Page');
        $auditLog->setEntityId(null);
        $auditLog->setUser($user);
        $auditLog->setDescription($route . ' (' . $request->getPathInfo() . ')');
        $auditLog->setIpAddress($request->getClientIp());
        $auditLog->setUserAgent($request->headers->get('User-Agent'));

        $this->entityManager->persist($auditLog);
        $this->entityManager->flush();
    }
}