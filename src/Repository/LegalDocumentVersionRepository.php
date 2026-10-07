<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Enum\LegalDocumentVersionStatus;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LegalDocumentVersion>
 */
class LegalDocumentVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LegalDocumentVersion::class);
    }

    public function findPublished(LegalDocument $document): ?LegalDocumentVersion
    {
        return $this->findOneByDocumentAndStatus($document, LegalDocumentVersionStatus::Published);
    }

    public function findDraft(LegalDocument $document): ?LegalDocumentVersion
    {
        return $this->findOneByDocumentAndStatus($document, LegalDocumentVersionStatus::Draft);
    }

    public function findOneByDocumentAndNumber(LegalDocument $document, int $versionNumber): ?LegalDocumentVersion
    {
        $version = $this->findOneBy([
            'document' => $document,
            'versionNumber' => $versionNumber,
        ]);

        return $version instanceof LegalDocumentVersion ? $version : null;
    }

    public function nextVersionNumber(LegalDocument $document): int
    {
        $max = $this->createQueryBuilder('version')
            ->select('MAX(version.versionNumber)')
            ->andWhere('version.document = :document')
            ->setParameter('document', $document)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $max + 1;
    }

    public function countReleased(LegalDocument $document): int
    {
        return (int) $this->createQueryBuilder('version')
            ->select('COUNT(version.id)')
            ->andWhere('version.document = :document')
            ->andWhere('version.status != :draft')
            ->setParameter('document', $document)
            ->setParameter('draft', LegalDocumentVersionStatus::Draft)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<LegalDocumentVersion>
     */
    public function findPublishedRequiringAcceptance(): array
    {
        /** @var list<LegalDocumentVersion> $versions */
        $versions = $this->createQueryBuilder('version')
            ->addSelect('document')
            ->innerJoin('version.document', 'document')
            ->andWhere('version.status = :published')
            ->andWhere('version.requiresReacceptance = true')
            ->setParameter('published', LegalDocumentVersionStatus::Published)
            ->getQuery()
            ->getResult();

        usort(
            $versions,
            static fn (LegalDocumentVersion $left, LegalDocumentVersion $right): int => $left->getDocument()->getType()->acceptanceOrder() <=> $right->getDocument()->getType()->acceptanceOrder(),
        );

        return $versions;
    }

    private function findOneByDocumentAndStatus(LegalDocument $document, LegalDocumentVersionStatus $status): ?LegalDocumentVersion
    {
        $version = $this->createQueryBuilder('version')
            ->andWhere('version.document = :document')
            ->andWhere('version.status = :status')
            ->setParameter('document', $document)
            ->setParameter('status', $status)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $version instanceof LegalDocumentVersion ? $version : null;
    }
}
