<?php

namespace Oro\Bundle\WorkflowBundle\Migrations\Schema;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\EntityBundle\EntityConfig\DatagridScope;
use Oro\Bundle\EntityConfigBundle\Entity\ConfigModel;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Migration\ExtendOptionsManager;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtension;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtensionAwareInterface;
use Oro\Bundle\EntityExtendBundle\Migration\OroOptions;
use Oro\Bundle\MigrationBundle\Migration\Installation;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * Created all tables required for WorkflowBundle.
 *
 * @SuppressWarnings(PHPMD.TooManyMethods)
 */
class OroWorkflowBundleInstaller implements Installation, ExtendExtensionAwareInterface
{
    /**
     * @var ExtendExtension
     */
    protected $extendExtension;

    /**
     * {@inheritdoc}
     */
    public function setExtendExtension(ExtendExtension $extendExtension)
    {
        $this->extendExtension = $extendExtension;
    }

    /**
     * {@inheritdoc}
     */
    public function getMigrationVersion()
    {
        return 'v2_5';
    }

    /**
     * {@inheritdoc}
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        /** Tables generation **/
        $this->createOroWorkflowItemTable($schema);
        $this->createOroWorkflowEntityAclTable($schema);
        $this->createOroWorkflowTransitionLogTable($schema);
        $this->createOroProcessJobTable($schema);
        $this->createOroProcessTriggerTable($schema);
        $this->createOroWorkflowEntityAclIdentTable($schema);
        $this->createOroWorkflowDefinitionTable($queries);
        $this->createOroProcessDefinitionTable($schema);
        $this->createOroWorkflowRestrictionTable($schema);
        $this->createOroWorkflowRestrictionIdentityTable($schema);
        $this->createOroWorkflowStepTable($schema);
        $this->createOroWorkflowTransTriggerTable($schema);
        $this->createOroWorkflowScopesTable($schema);

        /** Foreign keys generation **/
        $this->addOroWorkflowItemForeignKeys($queries);
        $this->addOroWorkflowEntityAclForeignKeys($queries);
        $this->addOroWorkflowTransitionLogForeignKeys($schema);
        $this->addOroProcessJobForeignKeys($schema);
        $this->addOroProcessTriggerForeignKeys($schema);
        $this->addOroWorkflowEntityAclIdentForeignKeys($schema);
        $this->addOroWorkflowDefinitionForeignKeys($queries);
        $this->addOroWorkflowRestrictionForeignKeys($queries);
        $this->addOroWorkflowRestrictionIdentityForeignKeys($schema);
        $this->addOroWorkflowStepForeignKeys($queries);
        $this->addOroWorkflowTransTriggerForeignKeys($queries);
        $this->addOroWorkflowScopesForeignKeys($queries);

        $this->addWorkflowFieldsToEmailNotificationTable($schema, $queries);
    }

    /**
     * Create oro_workflow_item table
     *
     * @param Schema $schema
     */
    protected function createOroWorkflowItemTable(Schema $schema)
    {
        $table = $schema->createTable('oro_workflow_item');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('current_step_id', 'integer', ['notnull' => false]);
        $table->addColumn('workflow_name', 'string', ['length' => 255]);
        $table->addColumn('entity_id', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('entity_class', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('created', 'datetime', []);
        $table->addColumn('updated', 'datetime', ['notnull' => false]);
        $table->addColumn('data', 'text', ['notnull' => false]);
        $table->addIndex(['workflow_name'], 'idx_169789ae1bbc6e3d', []);
        $table->addIndex(['entity_class', 'entity_id'], 'oro_workflow_item_entity_idx', []);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['current_step_id'], 'idx_169789aed9bf9b19', []);
        $table->addUniqueIndex(['entity_id', 'workflow_name'], 'oro_workflow_item_entity_definition_unq');
    }

    /**
     * Create oro_workflow_entity_acl table
     *
     * @param Schema $schema
     */
    protected function createOroWorkflowEntityAclTable(Schema $schema)
    {
        $table = $schema->createTable('oro_workflow_entity_acl');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('workflow_name', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('workflow_step_id', 'integer', ['notnull' => false]);
        $table->addColumn('attribute', 'string', ['length' => 255]);
        $table->addColumn('entity_class', 'string', ['length' => 255]);
        $table->addColumn('updatable', 'boolean', []);
        $table->addColumn('deletable', 'boolean', []);
        $table->addIndex(['workflow_name'], 'idx_c54c5e5b1bbc6e3d', []);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['workflow_step_id'], 'idx_c54c5e5b71fe882c', []);
        $table->addUniqueIndex(['workflow_name', 'attribute', 'workflow_step_id'], 'oro_workflow_acl_unique_idx');
    }

    /**
     * Create oro_workflow_transition_log table
     *
     * @param Schema $schema
     */
    protected function createOroWorkflowTransitionLogTable(Schema $schema)
    {
        $table = $schema->createTable('oro_workflow_transition_log');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('step_from_id', 'integer', ['notnull' => false]);
        $table->addColumn('step_to_id', 'integer', ['notnull' => false]);
        $table->addColumn('workflow_item_id', 'integer', ['notnull' => false]);
        $table->addColumn('transition', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('transition_date', 'datetime', []);
        $table->addIndex(['step_to_id'], 'idx_b3d57b302c17bd9a', []);
        $table->addIndex(['step_from_id'], 'idx_b3d57b30c8335fe4', []);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['workflow_item_id'], 'idx_b3d57b301023c4ee', []);
    }

    /**
     * Create oro_process_job table
     *
     * @param Schema $schema
     */
    protected function createOroProcessJobTable(Schema $schema)
    {
        $table = $schema->createTable('oro_process_job');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('process_trigger_id', 'integer', ['notnull' => false]);
        $table->addColumn('entity_id', 'integer', ['notnull' => false]);
        $table->addColumn('entity_hash', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('serialized_data', 'text', ['notnull' => false]);
        $table->addIndex(['entity_hash'], 'process_job_entity_hash_idx', []);
        $table->addIndex(['process_trigger_id'], 'idx_1957630f196ffde', []);
        $table->setPrimaryKey(['id']);
    }

    /**
     * Create oro_process_trigger table
     *
     * @param Schema $schema
     */
    protected function createOroProcessTriggerTable(Schema $schema)
    {
        $table = $schema->createTable('oro_process_trigger');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('definition_name', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('event', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('field', 'string', ['notnull' => false, 'length' => 150]);
        $table->addColumn('queued', 'boolean', []);
        $table->addColumn('time_shift', 'integer', ['notnull' => false]);
        $table->addColumn('cron', 'string', ['length' => 100, 'notnull' => false]);
        $table->addColumn('created_at', 'datetime', []);
        $table->addColumn('updated_at', 'datetime', []);
        $table->addColumn('priority', 'smallint', []);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['definition_name'], 'idx_48b327bccb9d81d2', []);
        $table->addUniqueIndex(['event', 'field', 'definition_name', 'cron'], 'process_trigger_unique_idx');
    }

    /**
     * Create oro_workflow_entity_acl_ident table
     *
     * @param Schema $schema
     */
    protected function createOroWorkflowEntityAclIdentTable(Schema $schema)
    {
        $table = $schema->createTable('oro_workflow_entity_acl_ident');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('workflow_entity_acl_id', 'integer', ['notnull' => false]);
        $table->addColumn('workflow_item_id', 'integer', ['notnull' => false]);
        $table->addColumn('entity_class', 'string', ['length' => 255]);
        $table->addColumn('entity_id', 'integer', []);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['entity_id', 'entity_class'], 'oro_workflow_entity_acl_identity_idx', []);
        $table->addUniqueIndex(
            ['workflow_entity_acl_id', 'entity_id', 'workflow_item_id'],
            'oro_workflow_entity_acl_identity_unique_idx'
        );
        $table->addIndex(['workflow_item_id'], 'idx_367002f11023c4ee', []);
        $table->addIndex(['workflow_entity_acl_id'], 'idx_367002f1160f68fb', []);
    }

    /**
     * Create oro_workflow_definition table
     *
     * @param QueryBag $queries
     */
    protected function createOroWorkflowDefinitionTable(QueryBag $queries)
    {
        $queries->addPreQuery('
            CREATE TABLE oro_workflow_definition (
                name VARCHAR(255) NOT NULL,
                start_step_id INT,
                label VARCHAR(255) NOT NULL,
                related_entity VARCHAR(255) NOT NULL,
                entity_attribute_name VARCHAR(255) NOT NULL,
                steps_display_ordered TINYINT(1) NOT NULL,
                `system` TINYINT(1) NOT NULL,
                active TINYINT(1) NOT NULL,
                priority INT NOT NULL,
                configuration LONGTEXT NOT NULL COMMENT "(DC2Type:array)(DC2Type:array)",
                exclusive_active_groups LONGTEXT COMMENT "(DC2Type:simple_array)(DC2Type:simple_array)",
                exclusive_record_groups LONGTEXT COMMENT "(DC2Type:simple_array)(DC2Type:simple_array)",
                created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime)",
                updated_at DATETIME NOT NULL COMMENT "(DC2Type:datetime)",
                applications LONGTEXT NOT NULL COMMENT "(DC2Type:simple_array)(DC2Type:simple_array)",
                PRIMARY KEY (name)
            );

            CREATE INDEX idx_6f737c368377424f ON oro_workflow_definition (start_step_id)
        ');
    }

    /**
     * Create oro_process_definition table
     *
     * @param Schema $schema
     */
    protected function createOroProcessDefinitionTable(Schema $schema)
    {
        $table = $schema->createTable('oro_process_definition');
        $table->addColumn('name', 'string', ['length' => 255]);
        $table->addColumn('label', 'string', ['length' => 255]);
        $table->addColumn('enabled', 'boolean', []);
        $table->addColumn('related_entity', 'string', ['length' => 255]);
        $table->addColumn('execution_order', 'smallint', []);
        $table->addColumn(
            'exclude_definitions',
            'simple_array',
            ['notnull' => false, 'comment' => '(DC2Type:simple_array)']
        );
        $table->addColumn('actions_configuration', 'array', ['comment' => '(DC2Type:array)']);
        $table->addColumn('created_at', 'datetime', []);
        $table->addColumn('updated_at', 'datetime', []);
        $table->addColumn(
            'pre_conditions_configuration',
            'array',
            ['notnull' => false, 'comment' => '(DC2Type:array)']
        );
        $table->setPrimaryKey(['name']);
    }

    /**
     * Create oro_workflow_restriction table
     *
     * @param Schema $schema
     */
    protected function createOroWorkflowRestrictionTable(Schema $schema)
    {
        $table = $schema->createTable('oro_workflow_restriction');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('workflow_name', 'string', ['length' => 255]);
        $table->addColumn('workflow_step_id', 'integer', ['notnull' => false]);
        $table->addColumn('attribute', 'string', ['length' => 255]);
        $table->addColumn('field', 'string', ['length' => 150]);
        $table->addColumn('entity_class', 'string', ['length' => 255]);
        $table->addColumn('mode', 'string', ['length' => 8]);
        $table->addColumn('mode_values', 'json_array', ['notnull' => false, 'comment' => '(DC2Type:json_array)']);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(
            ['workflow_name', 'workflow_step_id', 'field', 'entity_class', 'mode'],
            'oro_workflow_restriction_idx'
        );
    }

    /**
     * Create oro_workflow_restriction_ident table
     *
     * @param Schema $schema
     */
    protected function createOroWorkflowRestrictionIdentityTable(Schema $schema)
    {
        $table = $schema->createTable('oro_workflow_restriction_ident');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('workflow_restriction_id', 'integer', ['notnull' => false]);
        $table->addColumn('workflow_item_id', 'integer');
        $table->addColumn('entity_id', 'integer', []);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['entity_id'], 'oro_workflow_restr_ident_idx', []);
        $table->addUniqueIndex(
            ['workflow_restriction_id', 'entity_id', 'workflow_item_id'],
            'oro_workflow_restr_ident_unique_idx'
        );
    }

    /**
     * Create oro_workflow_step table
     *
     * @param Schema $schema
     */
    protected function createOroWorkflowStepTable(Schema $schema)
    {
        $table = $schema->createTable('oro_workflow_step');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('workflow_name', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('name', 'string', ['length' => 255]);
        $table->addColumn('label', 'string', ['length' => 255]);
        $table->addColumn('step_order', 'integer', []);
        $table->addColumn('is_final', 'boolean', []);
        $table->addIndex(['name'], 'oro_workflow_step_name_idx', []);
        $table->addIndex(['workflow_name'], 'idx_4a35528c1bbc6e3d', []);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['workflow_name', 'name'], 'oro_workflow_step_unique_idx');
    }

    /**
     * Create oro_workflow_trans_trigger table
     *
     * @param Schema $schema
     */
    protected function createOroWorkflowTransTriggerTable(Schema $schema)
    {
        $table = $schema->createTable('oro_workflow_trans_trigger');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('workflow_name', 'string', ['length' => 255]);
        $table->addColumn('entity_class', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('queued', 'boolean', []);
        $table->addColumn('transition_name', 'string', ['length' => 255]);
        $table->addColumn('created_at', 'datetime', []);
        $table->addColumn('updated_at', 'datetime', []);
        $table->addColumn('type', 'string', ['length' => 255]);
        $table->addColumn('cron', 'string', ['notnull' => false, 'length' => 100]);
        $table->addColumn('filter', 'text', ['notnull' => false]);
        $table->addColumn('event', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('field', 'string', ['notnull' => false, 'length' => 150]);
        $table->addColumn('require', 'text', ['notnull' => false]);
        $table->addColumn('relation', 'text', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
    }

    /**
     * Create oro_workflow_scopes table
     *
     * @param Schema $schema
     */
    protected function createOroWorkflowScopesTable(Schema $schema)
    {
        $table = $schema->createTable('oro_workflow_scopes');
        $table->addColumn('workflow_name', 'string', ['length' => 255]);
        $table->addColumn('scope_id', 'integer', []);
        $table->setPrimaryKey(['workflow_name', 'scope_id']);
    }

    /**
     * Add oro_workflow_item foreign keys.
     *
     * @param QueryBag $queries
     */
    protected function addOroWorkflowItemForeignKeys(QueryBag $queries)
    {
        $queries->addQuery('
            ALTER TABLE oro_workflow_item
            ADD FOREIGN KEY (current_step_id) REFERENCES oro_workflow_step(id) ON DELETE SET NULL,
            ADD FOREIGN KEY (workflow_name) REFERENCES oro_workflow_definition(name) ON DELETE CASCADE;
        ');
    }

    /**
     * Add oro_workflow_entity_acl foreign keys.
     *
     * @param QueryBag $queries
     */
    protected function addOroWorkflowEntityAclForeignKeys(QueryBag $queries)
    {
        $queries->addQuery('
            ALTER TABLE oro_workflow_entity_acl
            ADD FOREIGN KEY (workflow_name) REFERENCES oro_workflow_definition(name) ON DELETE CASCADE,
            ADD FOREIGN KEY (workflow_step_id) REFERENCES oro_workflow_step(id) ON DELETE CASCADE;
        ');
    }

    /**
     * Add oro_workflow_transition_log foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroWorkflowTransitionLogForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_workflow_transition_log');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_workflow_step'),
            ['step_from_id'],
            ['id'],
            ['onUpdate' => null, 'onDelete' => 'SET NULL']
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_workflow_step'),
            ['step_to_id'],
            ['id'],
            ['onUpdate' => null, 'onDelete' => 'SET NULL']
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_workflow_item'),
            ['workflow_item_id'],
            ['id'],
            ['onUpdate' => null, 'onDelete' => 'CASCADE']
        );
    }

    /**
     * Add oro_process_job foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroProcessJobForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_process_job');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_process_trigger'),
            ['process_trigger_id'],
            ['id'],
            ['onUpdate' => null, 'onDelete' => 'CASCADE']
        );
    }

    /**
     * Add oro_process_trigger foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroProcessTriggerForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_process_trigger');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_process_definition'),
            ['definition_name'],
            ['name'],
            ['onUpdate' => null, 'onDelete' => 'CASCADE']
        );
    }

    /**
     * Add oro_workflow_entity_acl_ident foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroWorkflowEntityAclIdentForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_workflow_entity_acl_ident');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_workflow_entity_acl'),
            ['workflow_entity_acl_id'],
            ['id'],
            ['onUpdate' => null, 'onDelete' => 'CASCADE']
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_workflow_item'),
            ['workflow_item_id'],
            ['id'],
            ['onUpdate' => null, 'onDelete' => 'CASCADE']
        );
    }

    /**
     * Add oro_workflow_definition foreign keys.
     *
     * @param QueryBag $queries
     */
    protected function addOroWorkflowDefinitionForeignKeys(QueryBag $queries)
    {
        $queries->addQuery('
            ALTER TABLE oro_workflow_definition
            ADD FOREIGN KEY (start_step_id) REFERENCES oro_workflow_step(id) ON DELETE SET NULL
        ');
    }

    /**
     * Add oro_workflow_restriction foreign keys.
     *
     * @param QueryBag $queries
     */
    protected function addOroWorkflowRestrictionForeignKeys(QueryBag $queries)
    {
        $queries->addQuery('
            ALTER TABLE oro_workflow_restriction
            ADD FOREIGN KEY (workflow_step_id) REFERENCES oro_workflow_step(id) ON DELETE CASCADE,
            ADD FOREIGN KEY (workflow_name) REFERENCES oro_workflow_definition(name) ON DELETE CASCADE
        ');
    }

    /**
     * Add oro_workflow_restriction_ident foreign keys.
     *
     * @param Schema $schema
     */
    protected function addOroWorkflowRestrictionIdentityForeignKeys(Schema $schema)
    {
        $table = $schema->getTable('oro_workflow_restriction_ident');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_workflow_restriction'),
            ['workflow_restriction_id'],
            ['id'],
            ['onUpdate' => null, 'onDelete' => 'CASCADE']
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_workflow_item'),
            ['workflow_item_id'],
            ['id'],
            ['onUpdate' => null, 'onDelete' => 'CASCADE']
        );
    }

    /**
     * Add oro_workflow_step foreign keys.
     *
     * @param QueryBag $queries
     */
    protected function addOroWorkflowStepForeignKeys(QueryBag $queries)
    {
        $queries->addQuery('
            ALTER TABLE oro_workflow_step
            ADD FOREIGN KEY (workflow_name) REFERENCES oro_workflow_definition(name) ON DELETE CASCADE
        ');
    }

    /**
     * Add oro_workflow_trans_trigger foreign keys.
     *
     * @param QueryBag $queries
     */
    protected function addOroWorkflowTransTriggerForeignKeys(QueryBag $queries)
    {
        $queries->addQuery('
            ALTER TABLE oro_workflow_trans_trigger
            ADD FOREIGN KEY (workflow_name) REFERENCES oro_workflow_definition(name) ON DELETE CASCADE
        ');
    }

    /**
     * Add oro_workflow_scopes foreign keys.
     *
     * @param QueryBag $queries
     */
    protected function addOroWorkflowScopesForeignKeys(QueryBag $queries)
    {
        $queries->addQuery('
            ALTER TABLE oro_workflow_scopes
            ADD FOREIGN KEY (scope_id) REFERENCES oro_scope(id) ON DELETE CASCADE,
            ADD FOREIGN KEY (workflow_name) REFERENCES oro_workflow_definition(name) ON DELETE CASCADE
        ');
    }

    /**
     * @param Schema $schema
     */
    protected function addWorkflowFieldsToEmailNotificationTable(Schema $schema, QueryBag $queries)
    {
        $queries->addQuery('
            ALTER TABLE oro_notification_email_notif
            ADD workflow_definition_name VARCHAR(255),
            ADD FOREIGN KEY (workflow_definition_name) REFERENCES oro_workflow_definition(name) ON DELETE SET NULL ON UPDATE RESTRICT,
            ADD INDEX IDX_A3D00FDF93298D04 (workflow_definition_name)
        ');

        $table = $schema->getTable('oro_notification_email_notif');
        $table->addColumn(
            'workflow_transition_name',
            'string',
            [
                OroOptions::KEY => [
                    ExtendOptionsManager::MODE_OPTION => ConfigModel::MODE_READONLY,
                    'entity' => ['label' => 'oro.workflow.workflowdefinition.transition_name.label'],
                    'extend' => [
                        'owner' => ExtendScope::OWNER_CUSTOM,
                        'is_extend' => true,
                        'length' => 255
                    ],
                    'datagrid' => [
                        'is_visible' => DatagridScope::IS_VISIBLE_TRUE,
                        'show_filter' => true,
                        'order' => 40
                    ],
                    'form' => ['is_enabled' => false],
                    'view' => ['is_displayable' => false],
                    'merge' => ['display' => false],
                    'dataaudit' => ['auditable' => false]
                ],
            ]
        );
    }
}
