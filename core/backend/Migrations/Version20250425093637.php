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
final class Version20250425093637 extends BaseMigration implements ContainerAwareInterface
{
    public function getDescription(): string
    {
        return 'Set Legacy Campaign Schedulers to Inactive';
    }

    public function up(Schema $schema): void
    {
        $legacySchedulerKeys = [
            'function::runMassEmailCampaign'
        ];

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->container->get('entity_manager');

        $keys = implode(', ', $legacySchedulerKeys);

        try {
            $entityManager->getConnection()->executeQuery("UPDATE schedulers SET status = 'Inactive' WHERE job IN ('$keys')");
        } catch (\Exception $e) {
        }
    }

    public function down(Schema $schema): void
    {
    }
}
