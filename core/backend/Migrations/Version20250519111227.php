<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250519111227 extends BaseMigration implements ContainerAwareInterface
{
    public function getDescription(): string
    {
        return 'Update Outbound Emails is_personal field';
    }

    public function up(Schema $schema): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->container->get('entity_manager');

        try {
            $entityManager->getConnection()->executeQuery("UPDATE outbound_email SET is_personal = 1 WHERE type = 'user'");
        } catch (\Exception $e) {
        }

    }

    public function down(Schema $schema): void
    {
    }
}
