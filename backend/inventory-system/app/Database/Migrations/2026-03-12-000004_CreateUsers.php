<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsers extends Migration
{
    private const FK_BRANCHES_MANAGER_ID_USERS = 'fk_branches_manager_id_users';

    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'branch_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'password_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'default'    => 1,
            ],
            'last_login_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('branch_id');
        $this->forge->addKey('email', false, true);

        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'CASCADE', 'SET NULL');

        $this->forge->createTable('users', true, [
            'ENGINE'         => 'InnoDB',
            'DEFAULT CHARSET'=> 'utf8mb4',
            'COLLATE'        => 'utf8mb4_unicode_ci',
        ]);

        // Add the circular FK after both tables exist.
        $this->db->query(
            'ALTER TABLE `branches` '
            . 'ADD CONSTRAINT `' . self::FK_BRANCHES_MANAGER_ID_USERS . '` '
            . 'FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) '
            . 'ON UPDATE CASCADE ON DELETE SET NULL'
        );
    }

    public function down()
    {
        // Drop circular FK first so users can be dropped.
        $this->db->query(
            'ALTER TABLE `branches` DROP FOREIGN KEY `' . self::FK_BRANCHES_MANAGER_ID_USERS . '`'
        );

        $this->forge->dropTable('users', true);
    }
}
