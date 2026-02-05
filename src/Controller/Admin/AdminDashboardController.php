<?php

namespace App\Controller\Admin;

use App\Repository\CategoryRepository;
use App\Repository\ContactRequestRepository;
use App\Repository\EventRepository;
use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use App\Entity\Category;
use App\Entity\Event;
use App\Form\CategoryType;
use App\Form\EventType;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminDashboardController extends AbstractController
{
    #[Route('', name: 'admin_dashboard')]
    public function index(
        Request $request,
        EventRepository $eventRepository,
        UserRepository $userRepository,
        CategoryRepository $categoryRepository,
        ContactRequestRepository $contactRequestRepository,
        ReportRepository $reportRepository
    ): Response {
        $tab = (string) $request->query->get('tab', 'events');
        $allowedTabs = ['events', 'users', 'categories', 'contacts', 'reports'];
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'events';
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $query = $request->query->all();
        unset($query['page']);

        $payload = [
            'tab' => $tab,
            'query' => $query,
            'page' => $page,
            'contactPendingCount' => $contactRequestRepository->countPending(),
            'reportPendingCount' => $reportRepository->countPending(),
            'events' => [],
            'users' => [],
            'categories' => [],
            'contacts' => [],
            'reports' => [],
            'pagination' => null,
            'sort' => null,
            'dir' => null,
            'eventForm' => null,
            'categoryForm' => null,
        ];

        if ($tab === 'events') {
            $sortKey = (string) $request->query->get('sort', 'date');
            $dir = strtolower((string) $request->query->get('dir', 'asc')) === 'desc' ? 'DESC' : 'ASC';
            $sortMap = [
                'title' => 'title',
                'date' => 'startAt',
                'status' => 'isPublished',
                'places' => 'capacity',
            ];
            $sortField = $sortMap[$sortKey] ?? 'startAt';
            $pagination = $eventRepository->searchAdminPaginated([], $sortField, $dir, $page, 12);

            $payload['events'] = $pagination['items'];
            $payload['pagination'] = $pagination;
            $payload['sort'] = $sortKey;
            $payload['dir'] = $dir;

            $event = new Event();
            $user = $this->getUser();
            if ($user instanceof \App\Entity\User) {
                $event->setOrganizer($user);
            }
            $payload['eventForm'] = $this->createForm(EventType::class, $event)->createView();
        } elseif ($tab === 'users') {
            $pagination = $userRepository->findPaginated($page, 12);
            $payload['users'] = $pagination['items'];
            $payload['pagination'] = $pagination;
        } elseif ($tab === 'categories') {
            $pagination = $categoryRepository->findPaginated($page, 12);
            $payload['categories'] = $pagination['items'];
            $payload['pagination'] = $pagination;
            $payload['categoryCounts'] = $categoryRepository->getEventCounts($pagination['items']);

            $payload['categoryForm'] = $this->createForm(CategoryType::class, new Category())->createView();
        } elseif ($tab === 'contacts') {
            $pagination = $contactRequestRepository->findPaginated($page, 12);
            $payload['contacts'] = $pagination['items'];
            $payload['pagination'] = $pagination;
        } elseif ($tab === 'reports') {
            $limit = 12;
            $offset = ($page - 1) * $limit;
            $qb = $reportRepository->createQueryBuilder('r')
                ->orderBy('r.createdAt', 'DESC')
                ->setFirstResult($offset)
                ->setMaxResults($limit);
            $paginator = new Paginator($qb);
            $total = count($paginator);
            $pagination = [
                'items' => iterator_to_array($paginator),
                'total' => $total,
                'page' => $page,
                'pages' => (int) ceil($total / $limit),
            ];
            $payload['reports'] = $pagination['items'];
            $payload['pagination'] = $pagination;
        }

        return $this->render('admin/index.html.twig', $payload);
    }
}
