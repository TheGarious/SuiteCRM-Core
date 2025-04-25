<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250425093118 extends BaseMigration implements ContainerAwareInterface
{
    public function getDescription(): string
    {
        return 'Add more_information field to emailman table';
    }

    public function up(Schema $schema): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->container->get('entity_manager');

        try {
            $entityManager->getConnection()->executeQuery('ALTER TABLE emailman ADD COLUMN `more_information` varchar(255) NULL');
        } catch (\Exception $e) {
        }
    }

    public function down(Schema $schema): void
    {
    }
}
