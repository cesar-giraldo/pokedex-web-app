<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Enum\LegalDocumentType;
use App\Entity\LegalDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LegalDocument>
 */
class LegalDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LegalDocument::class);
    }

    public function findOneByType(LegalDocumentType $type): ?LegalDocument
    {
        $document = $this->findOneBy(['type' => $type]);

        return $document instanceof LegalDocument ? $document : null;
    }

    /**
     * @return list<LegalDocument>
     */
    public function findAllOrdered(): array
    {
        /** @var list<LegalDocument> $documents */
        $documents = $this->createQueryBuilder('document')
            ->orderBy('document.id', 'ASC')
            ->getQuery()
            ->getResult();

        usort(
            $documents,
            static fn (LegalDocument $left, LegalDocument $right): int => $left->getType()->acceptanceOrder() <=> $right->getType()->acceptanceOrder(),
        );

        return $documents;
    }
}
