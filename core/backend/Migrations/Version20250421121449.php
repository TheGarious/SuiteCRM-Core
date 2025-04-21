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
final class Version20250421121449 extends BaseMigration implements ContainerAwareInterface
{
    public function getDescription(): string
    {
        return 'Update Email Marketing Status';
    }

    public function up(Schema $schema): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->container->get('entity_manager');

        $query = "UPDATE email_marketing SET status = 'draft' WHERE status = 'inactive'";

        try {
            $entityManager->getConnection()->executeQuery($query);
        } catch (\Exception $e) {
            $this->log('Failed to update Email Marketing Table setting status to draft. Error: ' . $e->getMessage());
        }
    }

    public function down(Schema $schema): void
    {
    }
}
