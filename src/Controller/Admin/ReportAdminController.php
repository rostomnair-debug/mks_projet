<?php

namespace App\Controller\Admin;

use App\Repository\ReportRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reports')]
#[IsGranted('ROLE_ADMIN')]
class ReportAdminController extends AbstractController
{
    #[Route('/', name: 'admin_report_index')]
    public function index(Request $request, ReportRepository $reportRepository): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 12;
        $offset = ($page - 1) * $limit;

        $qb = $reportRepository->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($qb);
        $total = count($paginator);

        $pagination = [
            'items' => iterator_to_array($paginator),
            'total' => $total,
            'page' => $page,
            'pages' => (int) ceil($total / $limit),
        ];

        $query = $request->query->all();
        unset($query['page']);

        return $this->render('admin/report/index.html.twig', [
            'reports' => $pagination['items'],
            'pagination' => $pagination,
            'query' => $query,
        ]);
    }
}
