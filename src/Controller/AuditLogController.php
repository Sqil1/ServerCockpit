<?php

namespace App\Controller;

use App\Data\AuditLogSearchData;
use App\Entity\AuditLog;
use App\Form\AuditLogSearchType;
use App\Repository\AuditLogRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuditLogController extends AbstractController
{
    #[Route('/admin/audit', name: 'app_audit_log_index')]
    public function index(Request $request, AuditLogRepository $auditLogRepository, PaginatorInterface $paginator): Response
    {
        $search = new AuditLogSearchData();
        $form = $this->createForm(AuditLogSearchType::class, $search);
        $form->handleRequest($request);

        $query = $auditLogRepository->findByFilters($search);

        $logs = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('audit_log/index.html.twig', [
            'logs' => $logs,
            'form' => $form,
        ]);
    }

    #[Route('/admin/audit/{id}', name: 'app_audit_log_index_show', methods: ['GET'])]
    public function show(AuditLog $auditLog): Response
    {
        return $this->render('audit_log/show.html.twig', [
            'log' => $auditLog,
        ]);
    }
}
