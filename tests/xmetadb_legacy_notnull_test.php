<?php
/**
 * Inserts on a table created by an older finis: columns NOT NULL without a
 * default must not make the INSERT fail in strict mode (MariaDB default).
 *
 * Old finis created TEXT and some other columns NOT NULL without a default;
 * an INSERT that leaves them out (a field the form does not post, a column
 * that is no longer in the descriptor) failed with
 * "Field 'x' doesn't have a default value".
 *
 *   php tests/xmetadb_legacy_notnull_test.php --mysql-host <h> --mysql-user <u> --mysql-pass <p> [--mysql-db <db>]
 */

if (!function_exists('FN_GetParam')) {
    function FN_GetParam($key, $var = false, $type = '') {
        if ($var === false) { $var = isset($_REQUEST) ? $_REQUEST : []; }
        return isset($var[$key]) ? $var[$key] : null;
    }
}
if (!function_exists('FN_Copy'))  { function FN_Copy($src, $dst) { return copy($src, $dst); } }
if (!function_exists('FN_Now'))   { function FN_Now() { return date('Y-m-d H:i:s'); } }
if (!function_exists('dprint_r')) { function dprint_r($v) {} }

error_reporting(E_ALL & ~E_DEPRECATED);

require_once __DIR__ . '/../src/include/xmetadb.php';

$mysql_host = null;
$mysql_user = 'root';
$mysql_pass = '';
$mysql_db   = 'xmetadb_test';
for ($i = 1; $i < $argc; $i++) {
    switch ($argv[$i]) {
        case '--mysql-host': $mysql_host = $argv[++$i] ?? null;        break;
        case '--mysql-user': $mysql_user = $argv[++$i] ?? $mysql_user; break;
        case '--mysql-pass': $mysql_pass = $argv[++$i] ?? '';          break;
        case '--mysql-db':   $mysql_db   = $argv[++$i] ?? $mysql_db;   break;
    }
}
if (!$mysql_host || !class_exists('mysqli')) {
    echo "SKIP: needs mysqli and --mysql-host <h> --mysql-user <u> --mysql-pass <p>\n";
    exit(0);
}

$conn = new mysqli($mysql_host, $mysql_user, $mysql_pass);
$conn->query("CREATE DATABASE IF NOT EXISTS `$mysql_db`");
$conn->select_db($mysql_db);
$conn->query("DROP TABLE IF EXISTS legacy_areas");
// the schema of an old install: TEXT and other columns NOT NULL without a
// default, plus a column that is not in the descriptor any more
$conn->query("CREATE TABLE legacy_areas (
    `id` INT NOT NULL PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL DEFAULT '',
    `description` TEXT NOT NULL,
    `closed_notes` TEXT NOT NULL,
    `depth` INT NOT NULL,
    `surveyed` DATE NOT NULL,
    `state` ENUM('open','closed') NOT NULL,
    `signature` VARCHAR(255) NOT NULL,
    `optional` TEXT NULL
) DEFAULT CHARACTER SET utf8 COLLATE utf8_bin");
$conn->query("INSERT INTO legacy_areas VALUES (1, 'Old', 'kept', 'kept too', 12, '2001-02-03', 'closed', 'sig', NULL)");

$tmpdir = sys_get_temp_dir() . '/xmetadb_legacy_test_' . getmypid();
mkdir($tmpdir, 0755, true);
register_shutdown_function(function () use ($tmpdir) {
    if (file_exists($tmpdir)) xmetadb_remove_dir_rec($tmpdir);
});

$dbname = 'testdb';
createxmldatabase($dbname, $tmpdir);
createxmltable($dbname, 'legacy_areas', [
    ['name' => 'id',           'primarykey' => '1', 'type' => 'int', 'extra' => 'autoincrement'],
    ['name' => 'name',         'primarykey' => '0', 'type' => 'varchar'],
    ['name' => 'description',  'primarykey' => '0', 'type' => 'text'],
    ['name' => 'closed_notes', 'primarykey' => '0', 'type' => 'text'],
    ['name' => 'depth',        'primarykey' => '0', 'type' => 'int'],
    ['name' => 'surveyed',     'primarykey' => '0', 'type' => 'date'],
    ['name' => 'state',        'primarykey' => '0', 'type' => 'varchar'],
    ['name' => 'optional',     'primarykey' => '0', 'type' => 'text'],
], $tmpdir, ['driver' => 'mysql']);

$params = [
    'xmetadb_mysqlhost'     => $mysql_host,
    'xmetadb_mysqlusername' => $mysql_user,
    'xmetadb_mysqlpassword' => $mysql_pass,
    'xmetadb_mysqldatabase' => $mysql_db,
];
$conn->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES'");
$table = xmetadb_table($dbname, 'legacy_areas', $tmpdir, $params);
// the table connection must be strict too, whatever the server default is
$table->driverclass->conn->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

$pass = 0;
$fail = 0;
$check = function ($ok, $what) use (&$pass, &$fail) {
    if ($ok) { $pass++; echo "  PASS $what\n"; }
    else     { $fail++; echo "  FAIL $what\n"; }
};
$row = function ($id) use ($conn) {
    $r = $conn->query("SELECT * FROM legacy_areas WHERE id = " . intval($id));
    return $r ? $r->fetch_assoc() : null;
};

$check(array_keys($table->driverclass->LegacyNotNullColumns()) == ['description', 'closed_notes', 'depth', 'surveyed', 'state', 'signature'],
    "legacy NOT NULL columns detected: " . implode(",", array_keys($table->driverclass->LegacyNotNullColumns())));

$r = $table->InsertRecord(['name' => 'only a name']);
$check(is_array($r) && !empty($r['id']), "InsertRecord with only the name");
$new = $row($r['id'] ?? 0);
$check($new && $new['description'] === '' && $new['closed_notes'] === '' && $new['depth'] === '0'
    && $new['surveyed'] === '0000-00-00' && $new['state'] === 'open' && $new['signature'] === '' && $new['optional'] === null,
    "missing columns filled like non-strict MySQL: " . json_encode($new));

$r = $table->InsertRecord(['name' => 'empty values', 'description' => '', 'depth' => '', 'surveyed' => '']);
$check(is_array($r) && !empty($r['id']), "InsertRecord with empty values in legacy columns");

$r = $table->InsertRecord(['name' => 'full', 'description' => 'd', 'closed_notes' => "o'n", 'depth' => '7', 'surveyed' => '2020-01-02', 'state' => 'closed']);
$full = $row($r['id'] ?? 0);
$check($full && $full['description'] === 'd' && $full['closed_notes'] === "o'n" && $full['depth'] === '7'
    && $full['surveyed'] === '2020-01-02' && $full['state'] === 'closed', "values given are stored unchanged");

$ok = $table->driverclass->InsertRecordFast(['id' => 500, 'name' => 'fast']);
$check($ok === true && $row(500), "InsertRecordFast with only the name");

$u = $table->UpdateRecordBypk(['id' => 1, 'depth' => '', 'description' => ''], 'id', 1);
$old = $row(1);
$check(is_array($u) && $old['depth'] === '0' && $old['description'] === '' && $old['closed_notes'] === 'kept too'
    && $old['surveyed'] === '2001-02-03' && $old['signature'] === 'sig', "update with empty values, other values unchanged");

// a fresh table (current finis) has no legacy columns: nothing changes
$conn->query("DROP TABLE IF EXISTS fresh_areas");
createxmltable($dbname, 'fresh_areas', [
    ['name' => 'id',   'primarykey' => '1', 'type' => 'int', 'extra' => 'autoincrement'],
    ['name' => 'name', 'primarykey' => '0', 'type' => 'varchar'],
    ['name' => 'text', 'primarykey' => '0', 'type' => 'text'],
    ['name' => 'n',    'primarykey' => '0', 'type' => 'int'],
], $tmpdir, ['driver' => 'mysql']);
$fresh = xmetadb_table($dbname, 'fresh_areas', $tmpdir, $params);
$check($fresh->driverclass->LegacyNotNullColumns() === [], "fresh table has no legacy NOT NULL columns");
$r = $fresh->InsertRecord(['name' => 'x']);
$check(is_array($r) && !empty($r['id']), "insert into a fresh table");

if (!getenv("KEEP")) {
    $conn->query("DROP TABLE IF EXISTS legacy_areas");
    $conn->query("DROP TABLE IF EXISTS fresh_areas");
}
echo "\n  PASS: $pass\n" . ($fail ? "  FAIL: $fail\n" : "");
exit($fail ? 1 : 0);
