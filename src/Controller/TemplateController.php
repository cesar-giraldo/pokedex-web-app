<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TemplateController extends AbstractController
{
    #[Route('/design/form', name: 'app_design_form')]
    public function formIndex(): Response
    {
        return $this->render('design/form/index.html.twig', [
            'controller_name' => 'TemplateController',
            'active_menu' => 'forms',
            'active_page' => 'formElements',
        ]);
    }

    #[Route('/design/basic-table', name: 'app_design_table_basic')]
    public function basicTableIndex(): Response
    {
        return $this->render('design/tables/basic.html.twig', [
            'controller_name' => 'TemplateController',
            'active_menu' => 'tables',
            'active_page' => 'basicTables',
        ]);
    }
}
