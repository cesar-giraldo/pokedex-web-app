<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Pokemon;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(
    name: 'pokemon_internal_search',
    template: 'components/pokemon_internal_search.html.twig'
)]
final class PokemonInternalSearch
{
    use DefaultActionTrait;

    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger
    ) {
    }

    #[LiveProp(writable: true)]
    public string $name = '';

    #[LiveProp(writable: true)]
    public ?Pokemon $pokemon = null;

    #[LiveAction]
    public function search(): void
    {
        $result = $this->em->getRepository(Pokemon::class)->findOneByName($this->name);

        $this->logger->info('LiveComponent PokemonInternalSearch::search called', ['name' => $this->name]);

        if ($result instanceof Pokemon) {
            $this->pokemon = $result;
        } else {
            $this->pokemon = null;
        }
    }
}
