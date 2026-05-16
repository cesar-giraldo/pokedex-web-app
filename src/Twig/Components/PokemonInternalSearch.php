<?php

namespace App\Twig\Components;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use App\Entity\Pokemon;

#[AsLiveComponent()]
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
    public Pokemon|null $pokemon = null;


    #[LiveAction]
    public function search(): void
    {
        // Aquí puedes llamar a un servicio interno, repositorio o incluso
        // a otro controlador usando HttpClient o servicios de la app.
        // Por simplicidad suponemos que tu lógica interna devuelve un array.

        $result = $this->em->getRepository(Pokemon::class)->findOneByName($this->name);
        
        // $this->logger->info('LiveComponent PokemonInternalSearch::search called', ['name' => $this->name]);

        if ($result instanceof Pokemon) {
            $this->pokemon = $result;
        } else {
            $this->pokemon = null;
        }
    }
}
