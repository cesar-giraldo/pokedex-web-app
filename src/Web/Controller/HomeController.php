<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\UserRole;
use App\Entity\Pokemon;
use App\Entity\User;
use App\Legal\LegalLanguageResolver;
use App\Repository\UserRepository;
use App\Web\Service\PublicLanguageResolver;
use App\Web\Service\PublicPokemonCatalog;
use App\Web\Service\PublicStructuredData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Throwable;

use function is_string;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly GeneralSettingsProvider $settings,
        private readonly PublicLanguageResolver $languages,
        private readonly LegalLanguageResolver $legalLanguages,
        private readonly UserRepository $users,
        private readonly PublicPokemonCatalog $catalog,
        private readonly PublicStructuredData $structuredData,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function root(Request $request): Response
    {
        $settings = $this->settings->get();
        $user = $this->authenticatedPublicUser($request);
        $locale = $user instanceof User
            ? $this->legalLanguages->preferredForUser($user, $settings)->value
            : $this->languages->defaultLanguage($settings)->value;

        return $this->redirectToRoute('app_public_home', [
            '_locale' => $locale,
        ]);
    }

    /**
     * `/` queda fuera del firewall público. La sesión de la web está en `_security_public`.
     */
    private function authenticatedPublicUser(Request $request): ?User
    {
        if (!$request->hasSession()) {
            return null;
        }

        $token = $request->getSession()->get('_security_public');

        if (is_string($token)) {
            try {
                $token = unserialize($token);
            } catch (Throwable) {
                return null;
            }
        }

        if (!$token instanceof TokenInterface) {
            return null;
        }

        $sessionUser = $token->getUser();

        if (!$sessionUser instanceof User) {
            return null;
        }

        $user = $this->users->findOneByNickname($sessionUser->getUserIdentifier());

        if (!$user instanceof User || !$user->hasAssignedRole(UserRole::User)) {
            return null;
        }

        return $user;
    }

    #[Route(
        '/{_locale}',
        name: 'app_public_home',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        methods: ['GET'],
    )]
    public function index(Request $request): Response
    {
        return $this->render('@web/home/index.html.twig', [
            'slides' => $this->catalog->featuredImages(),
            'structured_data_json' => $this->structuredData->websiteJson($request->getLocale()),
        ]);
    }

    #[Route('/internal-pokemon-search/{name}', name: 'app_internal_pokemon_search', methods: ['GET'])]
    public function internalPokemonSearch(EntityManagerInterface $em, string $name): JsonResponse
    {
        $pokemon = $em->getRepository(Pokemon::class)->findOneByName($name);

        if ($pokemon instanceof Pokemon && true === $pokemon->isHidden()) {
            $pokemon = null;
        }

        $response = [
            'success' => $pokemon instanceof Pokemon,
            'data' => $pokemon ? [
                'name' => $pokemon->getName(),
                'spriteFront' => $pokemon->getSpriteFront(),
                'healthPoints' => $pokemon->getHealthPoints(),
            ] : null,
            'errors' => $pokemon ? null : ['Pokemon not found'],
        ];

        return $this->json($response);
    }
}
