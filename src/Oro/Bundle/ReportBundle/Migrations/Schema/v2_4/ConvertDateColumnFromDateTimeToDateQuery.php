<?php

namespace Oro\Bundle\ReportBundle\Migrations\Schema\v2_4;

use Oro\Bundle\MigrationBundle\Migration\ArrayLogger;
use Oro\Bundle\MigrationBundle\Migration\ParametrizedMigrationQuery;
use Psr\Log\LoggerInterface;

class ConvertDateColumnFromDateTimeToDateQuery extends ParametrizedMigrationQuery
{
    private const TABLE = 'oro_calendar_date';
    private const ID_COLUMN = 'id';
    private const DATE_COLUMN = '`date`';

    public function getDescription()
    {
        $logger = new ArrayLogger();
        $logger->info(
            sprintf(
                'Convert a column %s with "datetime" type to "date" type, ' .
                'adding unique constraint and remove duplicates in %s',
                self::DATE_COLUMN,
                self::TABLE
            )
        );

        return $logger->getMessages();
    }

    public function execute(LoggerInterface $logger)
    {
        $updateSQL = sprintf(
            'ALTER TABLE %s MODIFY COLUMN %s DATE',
            self::TABLE,
            self::DATE_COLUMN
        );

        $deleteSQL = sprintf(
            'DELETE cd1 FROM %s AS cd1 JOIN %s cd2 ON cd1.%s = cd2.%s WHERE cd1.%s > cd2.%s',
            self::TABLE,
            self::TABLE,
            self::DATE_COLUMN,
            self::DATE_COLUMN,
            self::ID_COLUMN,
            self::ID_COLUMN
        );

        $createUniqueConstraintSQL = sprintf(
            'ALTER TABLE %s ADD CONSTRAINT oro_calendar_date_date_unique_idx UNIQUE (%s)',
            self::TABLE,
            self::DATE_COLUMN
        );

        $createCommentSQL = sprintf(
            "ALTER TABLE %s MODIFY COLUMN %s DATE COMMENT '(DC2Type:date)'",
            self::TABLE,
            self::DATE_COLUMN
        );

        $this->logQuery($logger, $updateSQL);
        $this->connection->executeStatement($updateSQL);
        $this->logQuery($logger, $deleteSQL);
        $this->connection->executeStatement($deleteSQL);
        $this->logQuery($logger, $createUniqueConstraintSQL);
        $this->connection->executeStatement($createUniqueConstraintSQL);
        $this->logQuery($logger, $createCommentSQL);
        $this->connection->executeStatement($createCommentSQL);
    }
}
