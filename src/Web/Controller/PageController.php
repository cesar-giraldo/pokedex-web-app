<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Entity\Enum\SupportedLanguage;
use App\Web\Service\PublicStructuredData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PageController extends AbstractController
{
    public function __construct(
        private readonly PublicStructuredData $structuredData,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(
        '/{_locale}/about',
        name: 'app_public_about',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        methods: ['GET'],
    )]
    public function about(Request $request): Response
    {
        return $this->render('@web/page/about.html.twig', [
            'structured_data_json' => $this->pageData($request, 'app_public_about', 'about.title', 'about.meta_description'),
        ]);
    }

    #[Route(
        '/{_locale}/contact',
        name: 'app_public_contact',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        methods: ['GET'],
    )]
    public function contact(Request $request): Response
    {
        return $this->render('@web/page/contact.html.twig', [
            'structured_data_json' => $this->pageData($request, 'app_public_contact', 'contact.title', 'contact.meta_description'),
        ]);
    }

    private function pageData(Request $request, string $route, string $titleKey, string $descriptionKey): string
    {
        return $this->structuredData->webPageJson(
            $request->getLocale(),
            $route,
            $this->translator->trans($titleKey),
            $this->translator->trans($descriptionKey),
        );
    }
}
