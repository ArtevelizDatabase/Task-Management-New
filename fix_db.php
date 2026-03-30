<?php
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
require FCPATH . '../app/Config/Paths.php';
$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'Boot.php';

require_once SYSTEMPATH . 'Config/DotEnv.php';
(new CodeIgniter\Config\DotEnv(ROOTPATH))->load();

$db = \Config\Database::connect();
$db->query("ALTER TABLE tb_fields MODIFY COLUMN type ENUM('text','date','select','boolean','textarea','number','email','richtext') NOT NULL");
$db->query("UPDATE tb_fields SET type='richtext' WHERE field_key='test_rich_text' OR type=''");
echo "Updated ENUM and records.\n";
print_r($db->query("SELECT id, field_key, type FROM tb_fields WHERE type='richtext'")->getResultArray());
