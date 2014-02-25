<?php

namespace Oro\Bundle\BatchBundle\Migrations\Schemas\v1_0;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\InstallerBundle\Migrations\Migration;

class OroBatchBundle implements Migration
{
    /**
     * @inheritdoc
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function up(Schema $schema)
    {
        // @codingStandardsIgnoreStart

        /** Generate table oro_batch_job_execution **/
        $table = $schema->createTable('oro_batch_job_execution');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('job_instance_id', 'integer', []);
        $table->addColumn('status', 'integer', []);
        $table->addColumn('start_time', 'datetime', ['notnull' => false]);
        $table->addColumn('end_time', 'datetime', ['notnull' => false]);
        $table->addColumn('create_time', 'datetime', ['notnull' => false]);
        $table->addColumn('updated_time', 'datetime', ['notnull' => false]);
        $table->addColumn('exit_code', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('exit_description', 'text', ['notnull' => false]);
        $table->addColumn('failure_exceptions', 'array', ['notnull' => false, 'comment' => '(DC2Type:array)']);
        $table->addColumn('log_file', 'string', ['notnull' => false, 'length' => 255]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['job_instance_id'], 'IDX_66BCFEA7593D6954', []);
        /** End of generate table oro_batch_job_execution **/

        /** Generate table oro_batch_job_instance **/
        $table = $schema->createTable('oro_batch_job_instance');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('code', 'string', ['length' => 100]);
        $table->addColumn('label', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('alias', 'string', ['length' => 50]);
        $table->addColumn('status', 'integer', []);
        $table->addColumn('connector', 'string', ['length' => 255]);
        $table->addColumn('type', 'string', ['length' => 255]);
        $table->addColumn('rawConfiguration', 'array', ['comment' => '(DC2Type:array)']);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['code'], 'UNIQ_35B1ECC777153098');
        /** End of generate table oro_batch_job_instance **/

        /** Generate table oro_batch_mapping_field **/
        $table = $schema->createTable('oro_batch_mapping_field');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('item_id', 'integer', ['notnull' => false]);
        $table->addColumn('source', 'string', ['length' => 255]);
        $table->addColumn('destination', 'string', ['length' => 255]);
        $table->addColumn('identifier', 'boolean', []);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['item_id'], 'IDX_45243258126F525E', []);
        /** End of generate table oro_batch_mapping_field **/

        /** Generate table oro_batch_mapping_item **/
        $table = $schema->createTable('oro_batch_mapping_item');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);
        /** End of generate table oro_batch_mapping_item **/

        /** Generate table oro_batch_step_execution **/
        $table = $schema->createTable('oro_batch_step_execution');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('job_execution_id', 'integer', ['notnull' => false]);
        $table->addColumn('step_name', 'string', ['notnull' => false, 'length' => 100]);
        $table->addColumn('status', 'integer', []);
        $table->addColumn('read_count', 'integer', []);
        $table->addColumn('write_count', 'integer', []);
        $table->addColumn('filter_count', 'integer', []);
        $table->addColumn('start_time', 'datetime', ['notnull' => false]);
        $table->addColumn('end_time', 'datetime', ['notnull' => false]);
        $table->addColumn('exit_code', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('exit_description', 'text', ['notnull' => false]);
        $table->addColumn('terminate_only', 'boolean', ['notnull' => false]);
        $table->addColumn('failure_exceptions', 'array', ['notnull' => false, 'comment' => '(DC2Type:array)']);
        $table->addColumn('errors', 'array', ['comment' => '(DC2Type:array)']);
        $table->addColumn('warnings', 'array', ['comment' => '(DC2Type:array)']);
        $table->addColumn('summary', 'array', ['comment' => '(DC2Type:array)']);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['job_execution_id'], 'IDX_3B30CD3C5871C06B', []);
        /** End of generate table oro_batch_step_execution **/

        /** Generate foreign keys for table oro_batch_job_execution **/
        $table = $schema->getTable('oro_batch_job_execution');
        $table->addForeignKeyConstraint($schema->getTable('oro_batch_job_instance'), ['job_instance_id'], ['id'], ['onDelete' => 'CASCADE', 'onUpdate' => null]);
        /** End of generate foreign keys for table oro_batch_job_execution **/

        /** Generate foreign keys for table oro_batch_mapping_field **/
        $table = $schema->getTable('oro_batch_mapping_field');
        $table->addForeignKeyConstraint($schema->getTable('oro_batch_mapping_item'), ['item_id'], ['id'], ['onDelete' => null, 'onUpdate' => null]);
        /** End of generate foreign keys for table oro_batch_mapping_field **/

        /** Generate foreign keys for table oro_batch_step_execution **/
        $table = $schema->getTable('oro_batch_step_execution');
        $table->addForeignKeyConstraint($schema->getTable('oro_batch_job_execution'), ['job_execution_id'], ['id'], ['onDelete' => 'CASCADE', 'onUpdate' => null]);
        /** End of generate foreign keys for table oro_batch_step_execution **/

        // @codingStandardsIgnoreEnd

        return [];
    }
}
