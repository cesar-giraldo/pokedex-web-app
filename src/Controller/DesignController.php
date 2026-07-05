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
        // fileManager, pricingTables, page500, page503, success, faq, comingSoon, maintenance
        return $this->render('design/blank_page.html.twig', [
            'controller_name' => 'DesignController',
            'active_menu' => 'pages',
            'active_page' => 'blank_page',
        ]);
    }

    #[Route('/design/page-not-found', name: 'app_design_page_404')]
    public function page404(): Response
    {
        // Other Pages
        // fileManager, pricingTables, page500, page503, success, faq, comingSoon, maintenance
        return $this->render('design/page_404.html.twig', [
            'controller_name' => 'DesignController',
            'active_menu' => 'pages',
            'active_page' => 'page_404',
        ]);
    }

    #[Route('/design/server-error', name: 'app_design_page_500')]
    public function page500(): Response
    {
        // Other Pages
        // fileManager, pricingTables, page503, success, faq, comingSoon, maintenance
        return $this->render('design/page_500.html.twig', [
            'controller_name' => 'DesignController',
            'active_menu' => 'pages',
            'active_page' => 'page_500',
        ]);
    }

    #[Route('/design/ui-elements/alerts', name: 'app_design_ui_elements_alerts')]
    public function alerts(): Response
    {
        return $this->render('design/ui_elements/alerts.html.twig', [
            'controller_name' => 'DesignController',
            'active_menu' => 'ui_elements',
            'active_page' => 'ui_alerts',
        ]);
    }

    #[Route('/design/ui-elements/badge', name: 'app_design_ui_elements_badge')]
    public function badge(): Response
    {
        return $this->render('design/ui_elements/badge.html.twig', [
            'controller_name' => 'DesignController',
            'active_menu' => 'ui_elements',
            'active_page' => 'ui_badge',
        ]);
    }

    #[Route('/design/ui-elements/buttons', name: 'app_design_ui_elements_buttons')]
    public function buttons(): Response
    {
        return $this->render('design/ui_elements/buttons.html.twig', [
            'controller_name' => 'DesignController',
            'active_menu' => 'ui_elements',
            'active_page' => 'ui_buttons',
        ]);
    }

    #[Route('/design/ui-elements/images', name: 'app_design_ui_elements_images')]
    public function images(): Response
    {
        return $this->render('design/ui_elements/images.html.twig', [
            'controller_name' => 'DesignController',
            'active_menu' => 'ui_elements',
            'active_page' => 'ui_images',
        ]);
    }

    #[Route('/design/ui-elements/videos', name: 'app_design_ui_elements_videos')]
    public function videos(): Response
    {
        return $this->render('design/ui_elements/videos.html.twig', [
            'controller_name' => 'DesignController',
            'active_menu' => 'ui_elements',
            'active_page' => 'ui_videos',
        ]);
    }
}
