<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DesignController extends AbstractController
{
    #[Route('/design', name: 'app_design_index')]
    public function index(): Response
    {
        return $this->render('design/index.html.twig', [
            'controller_name' => 'DesignController',
            'active_menu' => 'dashboard',
            'active_page' => 'dashboard',
        ]);
    }

    #[Route('/design/form', name: 'app_design_form')]
    public function form(): Response
    {
        return $this->render('design/form/index.html.twig', [
            'controller_name' => 'DesignController',
            'active_menu' => 'forms',
            'active_page' => 'form_elements',
        ]);
    }

    #[Route('/design/tables', name: 'app_design_basic_tables')]
    public function basicTables(): Response
    {
        return $this->render('design/tables/basic.html.twig', [
            'controller_name' => 'DesignController',
            'active_menu' => 'tables',
            'active_page' => 'basic_tables',
        ]);
    }

    #[Route('/design/profile', name: 'app_design_user_profile')]
    public function userProfile(): Response
    {
        return $this->render('design/profile/index.html.twig', [
            'controller_name' => 'DesignController',
            'active_menu' => 'user_profile',
            'active_page' => 'user_profile',
        ]);
    }

    #[Route('/design/blank-page', name: 'app_design_blank_page')]
    public function blankPage(): Response
    {
        // Other Pages
        // fileManager, pricingTables, page404, page500, page503, success, faq, comingSoon, maintenance
        return $this->render('design/blank_page.html.twig', [
            'controller_name' => 'DesignController',
            'active_menu' => 'pages',
            'active_page' => 'blank_page',
        ]);
    }
}
