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
    private SystemMonitoringService $monitoringService;

    public function __construct(SystemMonitoringService $monitoringService)
    {
        $this->monitoringService = $monitoringService;
    }

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $systemInfo = $this->monitoringService->getSystemInfo();

        return $this->render('dashboard/dashboard.html.twig', [
            'system_info' => $systemInfo,
            'page_title' => 'Dashboard - ServerCockpit'
        ]);
    }


    /**
     * API: Toutes les métriques système en une fois
     * Appelée par JavaScript toutes les X secondes
     */
    #[Route('/api/system-stats', name: 'api_system_stats', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getSystemStats(): JsonResponse
    {
        try {
            // Collecte de toutes les données en une fois
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

            return $this->json($stats);

        } catch (\Exception $e) {
            // En cas d'erreur, retourner un JSON d'erreur
            return $this->json([
                'error' => true,
                'message' => 'Impossible de récupérer les statistiques système',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    /**
     * API: CPU uniquement (si on veut séparer les appels)
     */
    #[Route('/api/cpu', name: 'api_cpu', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getCpuStats(): JsonResponse
    {
        try {
            return $this->json([
                'cpu' => $this->monitoringService->getCpuUsage(),
                'load' => $this->monitoringService->getLoadAverage(),
                'timestamp' => time()
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Mémoire uniquement
     */
    #[Route('/api/memory', name: 'api_memory', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getMemoryStats(): JsonResponse
    {
        try {
            return $this->json([
                'memory' => $this->monitoringService->getMemoryUsage(),
                'timestamp' => time()
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Test de connectivité (healthcheck)
     */
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
