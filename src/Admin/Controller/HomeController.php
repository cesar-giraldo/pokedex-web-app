<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_OPERATOR')]
final class HomeController extends AbstractController
{
    #[Route('/home', name: 'app_backend_home')]
    public function index(): Response
    {
        return $this->render('@admin/index.html.twig', [
            'controller_name' => 'HomeController',
            'active_menu' => 'dashboard',
            'active_page' => 'dashboard',
        ]);
    }
}
