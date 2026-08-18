<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Controller\Concerns\AdminPaginatorTrait;
use App\Admin\Form\SearchUserType;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class UserController extends AbstractController
{
    use AdminPaginatorTrait;

    #[Route('/users', name: 'app_backend_users')]
    public function index(UserRepository $userRepository, Request $request): Response
    {
        $form = $this->createForm(SearchUserType::class);
        $form->handleRequest($request);

        $term = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $term = $data['q'] ?? null;
        }

        $sort = $request->query->get('sort', 'u.createdAt');
        $direction = $request->query->get('direction', 'desc');
        $isDeveloper = $this->isGranted('ROLE_DEVELOPER');

        $queryBuilder = $userRepository->findBackendUsersQueryBuilder(
            $term,
            $sort,
            $direction,
            [
                'excludeDevelopers' => !$isDeveloper,
                'excludeHidden' => !$isDeveloper,
            ],
        );

        $pagination = $this->getPagination($queryBuilder, $request);

        return $this->render('@admin/users/index.html.twig', [
            'controller_name' => 'UserController',
            'active_menu' => 'user_profile',
            'active_page' => 'user_list',
            'search_form' => $form->createView(),
            'current_sort' => $sort,
            'current_direction' => $direction,
            'search_term' => $term,
            ...$pagination,
        ]);
    }
}
