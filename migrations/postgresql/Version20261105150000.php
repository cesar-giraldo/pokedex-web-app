<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105150000 extends AbstractMigration
{
    private const string ADMIN_SECURE_AREA_SLUG = 'module_admin_secure_area';

    private const string PREVIOUS_ADMIN_SECURE_AREA_DESCRIPTION = 'Módulo de login en el admin (por defecto siempre habilitado).';

    public function getDescription(): string
    {
        return 'Seed missing platform modules and correct the Admin Secure Area description';
    }

    public function up(Schema $schema): void
    {
        /** @var list<array{name: string, slug: string, sortOrder: string, parentSlug: ?string, description: ?string}> $modules */
        $modules = require dirname(__DIR__) . '/platform_modules_seed.php';
        $adminDescription = null;

        foreach ($modules as $module) {
            if (self::ADMIN_SECURE_AREA_SLUG === $module['slug']) {
                $adminDescription = $module['description'];
            }

            if (null === $module['parentSlug']) {
                $this->addSql(
                    'INSERT INTO modules (name, slug, sort_order, is_enabled, description, created_at, parent_id)
                    SELECT ?, ?, ?, TRUE, ?, CURRENT_TIMESTAMP, NULL
                    WHERE NOT EXISTS (SELECT 1 FROM modules existing WHERE existing.slug = ?)',
                    [$module['name'], $module['slug'], $module['sortOrder'], $module['description'], $module['slug']],
                );

                continue;
            }

            $this->addSql(
                'INSERT INTO modules (name, slug, sort_order, is_enabled, description, created_at, parent_id)
                SELECT ?, ?, ?, TRUE, ?, CURRENT_TIMESTAMP, module_parent.id
                FROM modules module_parent
                WHERE module_parent.slug = ?
                AND NOT EXISTS (SELECT 1 FROM modules existing WHERE existing.slug = ?)',
                [
                    $module['name'],
                    $module['slug'],
                    $module['sortOrder'],
                    $module['description'],
                    $module['parentSlug'],
                    $module['slug'],
                ],
            );
        }

        $this->addSql(
            'UPDATE modules SET description = ? WHERE slug = ? AND description LIKE ?',
            [$adminDescription, self::ADMIN_SECURE_AREA_SLUG, '%login en el admin%'],
        );
    }

    public function down(Schema $schema): void
    {
        /** @var list<array{name: string, slug: string, sortOrder: string, parentSlug: ?string, description: ?string}> $modules */
        $modules = require dirname(__DIR__) . '/platform_modules_seed.php';
        $adminDescription = null;

        foreach ($modules as $module) {
            if (self::ADMIN_SECURE_AREA_SLUG === $module['slug']) {
                $adminDescription = $module['description'];
            }
        }

        $this->addSql(
            'UPDATE modules SET description = ? WHERE slug = ? AND description = ?',
            [self::PREVIOUS_ADMIN_SECURE_AREA_DESCRIPTION, self::ADMIN_SECURE_AREA_SLUG, $adminDescription],
        );
    }
}
