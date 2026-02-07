<?php

namespace App\Controller;

use App\Service\SystemMonitoringService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard', name: 'dashboard_')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly SystemMonitoringService $monitoringService
    ) {}

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        try {
            $systemInfo = $this->monitoringService->getSystemInfo();

            $initialData = [
                'cpu' => $this->monitoringService->getCpuUsage(),
                'memory' => $this->monitoringService->getMemoryUsage(),
                'disk' => $this->monitoringService->getDiskUsage('/'),
                'load' => $this->monitoringService->getLoadAverage(),
                'network' => $this->monitoringService->getNetworkStats(),
            ];

            return $this->render('dashboard/dashboard.html.twig', [
                'system_info' => $systemInfo,
                'initial_data' => $initialData,
                'page_title' => 'Dashboard - ServerCockpit'
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du chargement: ' . $e->getMessage());

            return $this->render('dashboard/dashboard.html.twig', [
                'system_info' => null,
                'initial_data' => null,
                'page_title' => 'Dashboard - ServerCockpit'
            ]);
        }
    }

    // API principale - utilisée par le JS pour rafraîchir
    #[Route('/api/system-stats', name: 'api_system_stats', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getSystemStats(): JsonResponse
    {
        try {
            $stats = [
                'cpu' => $this->monitoringService->getCpuUsage(),
                'memory' => $this->monitoringService->getMemoryUsage(),
                'disk' => $this->monitoringService->getDiskUsage('/'),
                'network' => $this->monitoringService->getNetworkStats(),
                'load' => $this->monitoringService->getLoadAverage(),
                'system' => $this->monitoringService->getSystemInfo(),
                'timestamp' => time(),
                'server_time' => date('Y-m-d H:i:s')
            ];

            $response = $this->json($stats);
            $response->setSharedMaxAge(2);

            return $response;
        } catch (\Exception $e) {
            return $this->json([
                'error' => true,
                'message' => 'Impossible de récupérer les statistiques système',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Health check pour monitoring externe
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function healthCheck(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'timestamp' => time(),
            'server' => gethostname(),
            'php_version' => PHP_VERSION
        ]);
    }
}