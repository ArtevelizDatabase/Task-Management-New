<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Collaboration & organization tables + tb_task.project_id / parent_id.
 */
class NotionFeatures extends Migration
{
    public function up(): void
    {
        $this->createClients();
        $this->createProjects();
        $this->addTaskColumns();
        $this->createComments();
        $this->createActivityLog();
        $this->createRevisions();
        $this->createAttachments();
        $this->createTaskRelations();
        $this->createTaskAssignees();
        $this->createUserFavorites();
        $this->createTaskTemplates();
        $this->addFulltextIndexes();
    }

    public function down(): void
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0');

        foreach ([
            'tb_task_templates',
            'tb_user_favorites',
            'tb_task_assignees',
            'tb_task_relations',
            'tb_attachments',
            'tb_revisions',
            'tb_activity_log',
            'tb_comments',
        ] as $table) {
            if ($this->db->tableExists($table)) {
                $this->forge->dropTable($table, true);
            }
        }

        $this->dropTaskFkIfExists('fk_task_parent');
        $this->dropTaskFkIfExists('fk_task_project');

        if ($this->db->fieldExists('project_id', 'tb_task')) {
            $this->forge->dropColumn('tb_task', 'project_id');
        }
        if ($this->db->fieldExists('parent_id', 'tb_task')) {
            $this->forge->dropColumn('tb_task', 'parent_id');
        }

        if ($this->db->tableExists('tb_projects')) {
            $this->forge->dropTable('tb_projects', true);
        }
        if ($this->db->tableExists('tb_clients')) {
            $this->forge->dropTable('tb_clients', true);
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS=1');
    }

    private function dropTaskFkIfExists(string $constraint): void
    {
        if (! $this->db->tableExists('tb_task')) {
            return;
        }
        try {
            $this->db->query("ALTER TABLE tb_task DROP FOREIGN KEY {$constraint}");
        } catch (\Throwable) {
            // ignore
        }
    }

    private function createClients(): void
    {
        if ($this->db->tableExists('tb_clients')) {
            return;
        }
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => false],
            'contact'    => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'phone'      => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'status'     => ['type' => 'ENUM', 'constraint' => ['active', 'inactive'], 'default' => 'active'],
            'notes'      => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('tb_clients', true);
    }

    private function createProjects(): void
    {
        if ($this->db->tableExists('tb_projects')) {
            return;
        }
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'client_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => false],
            'description' => ['type' => 'TEXT', 'null' => true],
            'status'      => ['type' => 'ENUM', 'constraint' => ['active', 'completed', 'on_hold'], 'default' => 'active'],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('client_id');
        $this->forge->createTable('tb_projects', true);

        $this->db->query('ALTER TABLE tb_projects ADD CONSTRAINT fk_projects_client
            FOREIGN KEY (client_id) REFERENCES tb_clients(id) ON DELETE SET NULL');
    }

    private function taskColumnAfter(): string
    {
        if ($this->db->fieldExists('account_id', 'tb_task')) {
            return 'account_id';
        }
        if ($this->db->fieldExists('user_id', 'tb_task')) {
            return 'user_id';
        }

        return '';
    }

    private function addTaskColumns(): void
    {
        if (! $this->db->tableExists('tb_task')) {
            return;
        }

        $after = $this->taskColumnAfter();
        $projectCol = [
            'type'     => 'INT',
            'unsigned' => true,
            'null'     => true,
            'default'  => null,
        ];
        if ($after !== '') {
            $projectCol['after'] = $after;
        }

        if (! $this->db->fieldExists('project_id', 'tb_task')) {
            $this->forge->addColumn('tb_task', ['project_id' => $projectCol]);
            $this->db->query('ALTER TABLE tb_task ADD CONSTRAINT fk_task_project
                FOREIGN KEY (project_id) REFERENCES tb_projects(id) ON DELETE SET NULL');
        }

        $parentAfter = $this->db->fieldExists('project_id', 'tb_task') ? 'project_id' : $after;
        $parentCol   = [
            'type'     => 'INT',
            'unsigned' => true,
            'null'     => true,
            'default'  => null,
        ];
        if ($parentAfter !== '') {
            $parentCol['after'] = $parentAfter;
        }

        if (! $this->db->fieldExists('parent_id', 'tb_task')) {
            $this->forge->addColumn('tb_task', ['parent_id' => $parentCol]);
            $this->db->query('ALTER TABLE tb_task ADD CONSTRAINT fk_task_parent
                FOREIGN KEY (parent_id) REFERENCES tb_task(id) ON DELETE SET NULL');
        }
    }

    private function createComments(): void
    {
        if ($this->db->tableExists('tb_comments')) {
            return;
        }
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'task_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'user_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'body'       => ['type' => 'TEXT', 'null' => false],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('task_id');
        $this->forge->createTable('tb_comments', true);

        $this->db->query('ALTER TABLE tb_comments ADD CONSTRAINT fk_comments_task
            FOREIGN KEY (task_id) REFERENCES tb_task(id) ON DELETE CASCADE');
    }

    private function createActivityLog(): void
    {
        if ($this->db->tableExists('tb_activity_log')) {
            return;
        }
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'task_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'user_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'action'      => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false],
            'description' => ['type' => 'TEXT', 'null' => true],
            'old_value'   => ['type' => 'JSON', 'null' => true],
            'new_value'   => ['type' => 'JSON', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('task_id');
        $this->forge->createTable('tb_activity_log', true);

        $this->db->query('ALTER TABLE tb_activity_log ADD CONSTRAINT fk_actlog_task
            FOREIGN KEY (task_id) REFERENCES tb_task(id) ON DELETE CASCADE');
    }

    private function createRevisions(): void
    {
        if ($this->db->tableExists('tb_revisions')) {
            return;
        }
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'task_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'requested_by' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => false],
            'description'  => ['type' => 'TEXT', 'null' => false],
            'requested_at' => ['type' => 'DATE', 'null' => false],
            'due_date'     => ['type' => 'DATE', 'null' => true],
            'handled_by'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'status'       => ['type' => 'ENUM', 'constraint' => ['pending', 'in_progress', 'done', 'rejected'], 'default' => 'pending'],
            'handler_note' => ['type' => 'TEXT', 'null' => true],
            'resolved_at'  => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('task_id');
        $this->forge->createTable('tb_revisions', true);

        $this->db->query('ALTER TABLE tb_revisions ADD CONSTRAINT fk_revisions_task
            FOREIGN KEY (task_id) REFERENCES tb_task(id) ON DELETE CASCADE');
    }

    private function createAttachments(): void
    {
        if ($this->db->tableExists('tb_attachments')) {
            return;
        }
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'task_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'user_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'filename'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'original'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'mime_type'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'size'       => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('task_id');
        $this->forge->createTable('tb_attachments', true);

        $this->db->query('ALTER TABLE tb_attachments ADD CONSTRAINT fk_attachments_task
            FOREIGN KEY (task_id) REFERENCES tb_task(id) ON DELETE CASCADE');
    }

    private function createTaskRelations(): void
    {
        if ($this->db->tableExists('tb_task_relations')) {
            return;
        }
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'task_id'         => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'related_task_id' => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'relation_type'   => ['type' => 'ENUM', 'constraint' => ['blocks', 'blocked_by', 'relates_to', 'duplicate_of'], 'default' => 'relates_to'],
            'created_by'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['task_id', 'related_task_id', 'relation_type']);
        $this->forge->createTable('tb_task_relations', true);

        $this->db->query('ALTER TABLE tb_task_relations
            ADD CONSTRAINT fk_rel_task FOREIGN KEY (task_id) REFERENCES tb_task(id) ON DELETE CASCADE,
            ADD CONSTRAINT fk_rel_related FOREIGN KEY (related_task_id) REFERENCES tb_task(id) ON DELETE CASCADE');
    }

    private function createTaskAssignees(): void
    {
        if ($this->db->tableExists('tb_task_assignees')) {
            return;
        }
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'task_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'user_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'assigned_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'assigned_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['task_id', 'user_id']);
        $this->forge->createTable('tb_task_assignees', true);

        $this->db->query('ALTER TABLE tb_task_assignees ADD CONSTRAINT fk_assignees_task
            FOREIGN KEY (task_id) REFERENCES tb_task(id) ON DELETE CASCADE');
    }

    private function createUserFavorites(): void
    {
        if ($this->db->tableExists('tb_user_favorites')) {
            return;
        }
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'entity_type' => ['type' => 'ENUM', 'constraint' => ['task', 'project', 'client'], 'null' => false],
            'entity_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['user_id', 'entity_type', 'entity_id']);
        $this->forge->createTable('tb_user_favorites', true);
    }

    private function createTaskTemplates(): void
    {
        if ($this->db->tableExists('tb_task_templates')) {
            return;
        }
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => false],
            'description'  => ['type' => 'TEXT', 'null' => true],
            'created_by'   => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'field_values' => ['type' => 'JSON', 'null' => true],
            'is_public'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('tb_task_templates', true);
    }

    private function addFulltextIndexes(): void
    {
        try {
            $this->db->query('ALTER TABLE tb_comments ADD FULLTEXT idx_comment_search (body)');
        } catch (\Throwable) {
        }

        try {
            $this->db->query('ALTER TABLE tb_clients ADD FULLTEXT idx_client_search (name, contact)');
        } catch (\Throwable) {
        }
    }
}
