<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Enum\LegalDocumentType;
use App\Entity\Enum\LegalDocumentVersionStatus;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use function is_string;

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

    /**
     * @return list<LegalDocumentType>
     */
    public function findPublishedDocumentTypes(): array
    {
        /** @var list<mixed> $types */
        $types = $this->createQueryBuilder('version')
            ->select('document.type')
            ->distinct()
            ->innerJoin('version.document', 'document')
            ->andWhere('version.status = :published')
            ->setParameter('published', LegalDocumentVersionStatus::Published)
            ->getQuery()
            ->getSingleColumnResult();

        $published = [];

        foreach ($types as $type) {
            $resolved = $this->resolveDocumentType($type);

            if ($resolved instanceof LegalDocumentType) {
                $published[] = $resolved;
            }
        }

        return $published;
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

    private function resolveDocumentType(mixed $type): ?LegalDocumentType
    {
        if ($type instanceof LegalDocumentType) {
            return $type;
        }

        if (!is_string($type)) {
            return null;
        }

        return LegalDocumentType::tryFrom($type);
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
