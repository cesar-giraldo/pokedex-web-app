<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Entity\Module;
use App\Module\EventSubscriber\ModuleAccessSubscriber;
use App\Repository\ModuleRepository;
use App\Tests\Admin\Support\AdminAuthenticatedClientTrait;
use App\Tests\Web\Support\AcceptsPendingLegalDocumentsTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class DesignControllerTest extends WebTestCase
{
    use AcceptsPendingLegalDocumentsTrait;
    use AdminAuthenticatedClientTrait;

    private const string PARENT_SLUG = 'module_admin_secure_area';

    private const string KIT_SLUG = 'module_admin_ui_kit';

    private ?bool $originalParentEnabled = null;

    private ?bool $originalKitEnabled = null;

    private ?int $originalKitParentId = null;

    private bool $kitHadNoParent = false;

    private bool $createdParent = false;

    private bool $createdKit = false;

    protected function tearDown(): void
    {
        if (self::$booted) {
            $this->restoreModules();
        }

        parent::tearDown();
    }

    public function testDeveloperCanOpenUiKitWhenModuleAndParentAreEnabled(): void
    {
        $client = static::createClient();
        $this->acceptPendingLegalDocuments($this->loginAsDeveloper($client));
        $this->setModuleState(true, true);

        $client->request('GET', '/admin/ui-kit/index');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/admin/ui-kit/index"]');

        $client->request('GET', '/admin/home');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/admin/ui-kit/index"]');
    }

    public function testOperatorIsRedirectedFromUiKitPages(): void
    {
        $client = static::createClient();
        $this->acceptPendingLegalDocuments($this->loginAsOperator($client));
        $this->setModuleState(true, true);

        $this->assertDenied($client, '/admin/ui-kit/index');
        $this->assertDenied($client, '/admin/ui-kit/forms');
    }

    public function testDeveloperIsRedirectedWhenUiKitIsDisabled(): void
    {
        $client = static::createClient();
        $this->acceptPendingLegalDocuments($this->loginAsDeveloper($client));
        $this->setModuleState(true, false);

        $this->assertDenied($client, '/admin/ui-kit/index');
    }

    public function testDeveloperIsRedirectedWhenParentIsDisabled(): void
    {
        $client = static::createClient();
        $this->acceptPendingLegalDocuments($this->loginAsDeveloper($client));
        $this->setModuleState(false, true);

        $this->assertDenied($client, '/admin/ui-kit/index');
    }

    public function testAnonymousUserIsSentToLogin(): void
    {
        $client = static::createClient();
        $this->setModuleState(true, true);

        $client->request('GET', '/admin/ui-kit/index');

        self::assertResponseRedirects('/admin/login');
        $client->followRedirect();
        self::assertStringNotContainsString(ModuleAccessSubscriber::DENIED_MESSAGE, (string) $client->getResponse()->getContent());
    }

    private function assertDenied(KernelBrowser $client, string $path): void
    {
        $client->request('GET', $path);

        self::assertResponseRedirects('/admin/home');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', ModuleAccessSubscriber::DENIED_MESSAGE);
        self::assertStringNotContainsString('/admin/ui-kit/index', (string) $client->getResponse()->getContent());
    }

    private function setModuleState(bool $parentEnabled, bool $kitEnabled): void
    {
        $entityManager = $this->entityManager();
        $repository = $this->moduleRepository();
        $parent = $repository->findOneBy(['slug' => self::PARENT_SLUG]);

        if (!$parent instanceof Module) {
            $parent = new Module('Admin Secure Area', self::PARENT_SLUG, '2.00');
            $entityManager->persist($parent);
            $this->createdParent = true;
        } elseif (null === $this->originalParentEnabled) {
            $this->originalParentEnabled = $parent->isEnabled();
        }

        $kit = $repository->findOneBy(['slug' => self::KIT_SLUG]);

        if (!$kit instanceof Module) {
            $kit = new Module('UI Kit', self::KIT_SLUG, '2.30');
            $kit->setParent($parent);
            $entityManager->persist($kit);
            $this->createdKit = true;
        } elseif (null === $this->originalKitEnabled) {
            $this->originalKitEnabled = $kit->isEnabled();
            $kitParent = $kit->getParent();

            if (!$kitParent instanceof Module) {
                $this->kitHadNoParent = true;
                $kit->setParent($parent);
            } elseif (self::PARENT_SLUG !== $kitParent->getSlug()) {
                $this->originalKitParentId = $kitParent->getId();
                $kit->setParent($parent);
            }
        }

        $parent->setIsEnabled($parentEnabled);
        $kit->setIsEnabled($kitEnabled);
        $entityManager->flush();
    }

    private function restoreModules(): void
    {
        $entityManager = $this->entityManager();
        $repository = $this->moduleRepository();
        $kit = $repository->findOneBy(['slug' => self::KIT_SLUG]);
        $parent = $repository->findOneBy(['slug' => self::PARENT_SLUG]);

        if ($this->createdKit && $kit instanceof Module) {
            $kit->setParent(null);
            $entityManager->flush();
            $entityManager->remove($kit);
            $entityManager->flush();
            $kit = null;
        }

        if ($this->createdParent && $parent instanceof Module) {
            $entityManager->remove($parent);
            $entityManager->flush();
            $parent = null;
        }

        if (!$this->createdKit && $kit instanceof Module) {
            if (null !== $this->originalKitEnabled) {
                $kit->setIsEnabled($this->originalKitEnabled);
            }

            if ($this->kitHadNoParent) {
                $kit->setParent(null);
            } elseif (null !== $this->originalKitParentId) {
                $originalParent = $entityManager->find(Module::class, $this->originalKitParentId);

                if ($originalParent instanceof Module) {
                    $kit->setParent($originalParent);
                }
            }
        }

        if (!$this->createdParent && $parent instanceof Module && null !== $this->originalParentEnabled) {
            $parent->setIsEnabled($this->originalParentEnabled);
        }

        $entityManager->flush();
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }

    private function moduleRepository(): ModuleRepository
    {
        /** @var ModuleRepository $repository */
        $repository = static::getContainer()->get(ModuleRepository::class);

        return $repository;
    }
}
