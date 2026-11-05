<?php

declare(strict_types=1);

namespace App\Tests\Web\Support;

use App\Entity\Module;
use App\Repository\ModuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @phpstan-require-extends WebTestCase
 */
trait EnablesWebSecureAreaTrait
{
    private const string WEB_SECURE_AREA_SLUG = 'module_web_secure_area';

    private ?bool $originalWebSecureAreaEnabled = null;

    private bool $createdWebSecureArea = false;

    protected function createClientWithSecureArea(): KernelBrowser
    {
        $client = static::createClient();
        $this->enableWebSecureArea();

        return $client;
    }

    protected function enableWebSecureArea(): void
    {
        $this->setWebSecureAreaEnabled(true);
    }

    protected function setWebSecureAreaEnabled(bool $enabled): void
    {
        $entityManager = $this->webSecureAreaEntityManager();
        $module = $this->webSecureAreaRepository()->findOneBy(['slug' => self::WEB_SECURE_AREA_SLUG]);

        if (!$module instanceof Module) {
            $module = new Module('Web Secure Area', self::WEB_SECURE_AREA_SLUG, '1.00');
            $entityManager->persist($module);
            $this->createdWebSecureArea = true;
        } elseif (!$this->createdWebSecureArea && null === $this->originalWebSecureAreaEnabled) {
            $this->originalWebSecureAreaEnabled = $module->isEnabled();
        }

        $module->setIsEnabled($enabled);
        $entityManager->flush();
    }

    protected function restoreWebSecureArea(): void
    {
        if (!$this->createdWebSecureArea && null === $this->originalWebSecureAreaEnabled) {
            return;
        }

        $entityManager = $this->webSecureAreaEntityManager();
        $module = $this->webSecureAreaRepository()->findOneBy(['slug' => self::WEB_SECURE_AREA_SLUG]);

        if (!$module instanceof Module) {
            return;
        }

        if ($this->createdWebSecureArea) {
            $entityManager->remove($module);
            $entityManager->flush();

            return;
        }

        $module->setIsEnabled($this->originalWebSecureAreaEnabled);
        $entityManager->flush();
    }

    private function webSecureAreaEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }

    private function webSecureAreaRepository(): ModuleRepository
    {
        /** @var ModuleRepository $repository */
        $repository = static::getContainer()->get(ModuleRepository::class);

        return $repository;
    }
}
