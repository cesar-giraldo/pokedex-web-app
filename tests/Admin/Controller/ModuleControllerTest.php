<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Entity\Module;
use App\Repository\ModuleRepository;
use App\Tests\Admin\Support\AdminAuthenticatedClientTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function is_array;

use const JSON_THROW_ON_ERROR;

#[Group('functional')]
final class ModuleControllerTest extends WebTestCase
{
    use AdminAuthenticatedClientTrait;

    protected function tearDown(): void
    {
        if (self::$booted) {
            $this->deleteBySlugPrefix('fnctl');
        }

        parent::tearDown();
    }

    public function testDeveloperSeesModuleSectionBetweenDateTimeAndBackup(): void
    {
        $client = static::createClient();
        $this->loginAsDeveloper($client);
        $this->deleteAllModules();

        $client->request('GET', '/admin/settings/general');

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        $dateTime = strpos($html, 'Fecha y hora');
        $modules = strpos($html, 'Administración de Módulos');
        $backup = strpos($html, 'Último Backup');

        self::assertNotFalse($dateTime);
        self::assertNotFalse($modules);
        self::assertNotFalse($backup);
        self::assertGreaterThan($dateTime, $modules);
        self::assertLessThan($backup, $modules);
        self::assertSelectorTextContains('body', 'No hay módulos definidos.');
    }

    public function testOperatorCannotCreateOrToggleModules(): void
    {
        $client = static::createClient();
        $this->loginAsOperator($client);

        $client->request('POST', '/admin/settings/modules');
        self::assertResponseStatusCodeSame(403);

        $client->request('POST', '/admin/settings/modules/1/enabled');
        self::assertResponseStatusCodeSame(403);
    }

    public function testDeveloperCanCreateAndEditModule(): void
    {
        $client = static::createClient();
        $this->loginAsDeveloper($client);

        $this->submitModule($client, [
            'module[name]' => 'Pokemones',
            'module[slug]' => 'fnctlpokemones',
            'module[sortOrder]' => '1',
            'module[description]' => 'Catalogo de pokemones',
        ]);

        self::assertResponseRedirects('/admin/settings/general?modules=edit');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-module-admin-target="editor"]:not(.hidden)');
        self::assertSelectorTextContains('body', 'Pokemones');
        self::assertSelectorExists('[data-module-view-name].line-through[title="Catalogo de pokemones"]');

        $module = $this->findBySlug('fnctlpokemones');
        self::assertNotNull($module);
        self::assertFalse($module->isEnabled());

        $crawler = $client->getCrawler();
        $token = (string) $crawler->filter('#module-form input[name="module[_token]"]')->attr('value');
        $client->request('POST', '/admin/settings/modules/' . $module->getId(), [
            'module' => [
                'parent' => '',
                'name' => 'Pokemones editado',
                'slug' => 'fnctlpokemones',
                'sortOrder' => '2.5',
                'description' => 'Catalogo actualizado',
                '_token' => $token,
            ],
        ]);

        self::assertResponseRedirects('/admin/settings/general?modules=edit');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Pokemones editado');

        $this->entityManager()->clear();
        $updated = $this->findBySlug('fnctlpokemones');
        self::assertNotNull($updated);
        self::assertSame('2.50', $updated->getSortOrder());
        self::assertSame('Catalogo actualizado', $updated->getDescription());
    }

    public function testInvalidAndDuplicateSlugStayOnTheModal(): void
    {
        $client = static::createClient();
        $this->loginAsDeveloper($client);
        $this->submitModule($client, [
            'module[name]' => 'Valido',
            'module[slug]' => 'fnctlvalido',
            'module[sortOrder]' => '1',
        ]);
        self::assertResponseRedirects();

        $this->submitModule($client, [
            'module[name]' => 'Invalido',
            'module[slug]' => 'No Valido',
            'module[sortOrder]' => '1',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('#module-modal.flex');
        self::assertSelectorTextContains('#module-modal', 'letras minúsculas');

        $this->submitModule($client, [
            'module[name]' => 'Duplicado',
            'module[slug]' => 'fnctlvalido',
            'module[sortOrder]' => '1',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#module-modal', 'Ya existe un módulo con este slug.');
    }

    public function testToggleCascadesAndBlocksChildrenOfInactiveParent(): void
    {
        $client = static::createClient();
        $this->loginAsDeveloper($client);

        $this->submitModule($client, [
            'module[name]' => 'Padre',
            'module[slug]' => 'fnctlpadre',
            'module[sortOrder]' => '1',
        ]);
        self::assertResponseRedirects();
        $parent = $this->findBySlug('fnctlpadre');
        self::assertNotNull($parent);

        $crawler = $client->request('GET', '/admin/settings/general?modules=edit');
        $form = $crawler->filter('#module-form')->form([
            'module[name]' => 'Hijo',
            'module[slug]' => 'fnctlhijo',
            'module[sortOrder]' => '1',
            'module[parent]' => (string) $parent->getId(),
        ]);
        $client->submit($form);
        self::assertResponseRedirects();

        $child = $this->findBySlug('fnctlhijo');
        self::assertNotNull($child);
        self::assertSame($parent->getId(), $child->getParent()?->getId());
        $parentId = (int) $parent->getId();
        $childId = (int) $child->getId();

        $enabled = $this->toggle($client, $parentId, true);
        self::assertTrue($enabled[$parentId]['isEnabled']);
        self::assertFalse($enabled[$parentId]['locked']);
        self::assertTrue($enabled[$childId]['isEnabled']);
        self::assertFalse($enabled[$childId]['locked']);

        $childDisabled = $this->toggle($client, $childId, false);
        self::assertFalse($childDisabled[$childId]['isEnabled']);
        $this->entityManager()->clear();
        $parentAfterChildToggle = $this->findBySlug('fnctlpadre');
        self::assertNotNull($parentAfterChildToggle);
        self::assertTrue($parentAfterChildToggle->isEnabled());

        $parentDisabled = $this->toggle($client, $parentId, false);
        self::assertFalse($parentDisabled[$parentId]['isEnabled']);
        self::assertFalse($parentDisabled[$childId]['isEnabled']);
        self::assertTrue($parentDisabled[$childId]['locked']);

        $token = $this->csrfToken($client);
        $client->request(
            'POST',
            '/admin/settings/modules/' . $childId . '/enabled',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $token,
                'HTTP_ACCEPT' => 'application/json',
            ],
            json_encode(['enabled' => true], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        $this->entityManager()->clear();
        $blockedChild = $this->findBySlug('fnctlhijo');
        self::assertNotNull($blockedChild);
        self::assertFalse($blockedChild->isEnabled());
    }

    /**
     * @param array<string, string> $values
     */
    private function submitModule(KernelBrowser $client, array $values): void
    {
        $crawler = $client->request('GET', '/admin/settings/general?modules=edit');
        $form = $crawler->filter('#module-form')->form($values);
        $client->submit($form);
    }

    /**
     * @return array<int, array{isEnabled: bool, locked: bool}>
     */
    private function toggle(KernelBrowser $client, int $id, bool $enabled): array
    {
        $token = $this->csrfToken($client);
        $client->request(
            'POST',
            '/admin/settings/modules/' . $id . '/enabled',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $token,
                'HTTP_ACCEPT' => 'application/json',
            ],
            json_encode(['enabled' => $enabled], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertTrue($payload['success'] ?? false);
        self::assertIsArray($payload['modules'] ?? null);

        $byId = [];
        foreach ($payload['modules'] as $item) {
            if (!is_array($item) || !isset($item['id'], $item['isEnabled'], $item['locked'])) {
                continue;
            }

            $byId[(int) $item['id']] = [
                'isEnabled' => (bool) $item['isEnabled'],
                'locked' => (bool) $item['locked'],
            ];
        }

        return $byId;
    }

    private function csrfToken(KernelBrowser $client): string
    {
        $crawler = $client->request('GET', '/admin/settings/general?modules=edit');
        $token = $crawler->filter('#module-admin')->attr('data-module-admin-csrf-token-value');
        self::assertIsString($token);

        return $token;
    }

    private function findBySlug(string $slug): ?Module
    {
        /** @var ModuleRepository $repository */
        $repository = static::getContainer()->get(ModuleRepository::class);
        $module = $repository->findOneBy(['slug' => $slug]);

        return $module instanceof Module ? $module : null;
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }

    private function deleteBySlugPrefix(string $prefix): void
    {
        $this->deleteModules('module.slug LIKE :prefix', ['prefix' => $prefix . '%']);
    }

    private function deleteAllModules(): void
    {
        $this->deleteModules('module.id IS NOT NULL', []);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function deleteModules(string $condition, array $parameters): void
    {
        $entityManager = $this->entityManager();
        $entityManager->clear();
        $query = $entityManager->createQueryBuilder()
            ->select('module')
            ->from(Module::class, 'module')
            ->where($condition);

        foreach ($parameters as $name => $value) {
            $query->setParameter($name, $value);
        }

        /** @var list<Module> $modules */
        $modules = $query->getQuery()->getResult();

        foreach ($modules as $module) {
            $module->setParent(null);
        }

        $entityManager->flush();

        foreach ($modules as $module) {
            $entityManager->remove($module);
        }

        $entityManager->flush();
    }
}
