<?php
/**
 * XMETADatabase::Query() on MySQL: the native SQL path must give the same
 * results as the PHP engine.
 *
 * Every query runs twice on the same MySQL table, once natively and once with
 * xmetadb_native_query = "0" (PHP engine), and the results are compared.
 *
 *   php tests/xmetadb_native_query_test.php --mysql-host <h> --mysql-user <u> --mysql-pass <p> [--mysql-db <db>]
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
require_once __DIR__ . '/../src/include/xmetadb/XMETADatabase.php';

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
if (!getenv("KEEP")) $conn->query("DROP TABLE IF EXISTS people");

$tmpdir = sys_get_temp_dir() . '/xmetadb_native_test_' . getmypid();
mkdir($tmpdir, 0755, true);
register_shutdown_function(function () use ($tmpdir) {
    if (file_exists($tmpdir)) xmetadb_remove_dir_rec($tmpdir);
});

$dbname = 'testdb';
createxmldatabase($dbname, $tmpdir);
createxmltable($dbname, 'people', [
    ['name' => 'id',      'primarykey' => '1', 'type' => 'int', 'extra' => 'autoincrement'],
    ['name' => 'name',    'primarykey' => '0', 'type' => 'varchar'],
    ['name' => 'city',    'primarykey' => '0', 'type' => 'varchar'],
    ['name' => 'age',     'primarykey' => '0', 'type' => 'varchar'],
    ['name' => 'active',  'primarykey' => '0', 'type' => 'varchar'],
    ['name' => 'note',    'primarykey' => '0', 'type' => 'varchar'],
    ['name' => 'updated', 'primarykey' => '0', 'type' => 'varchar'],
    ['name' => 'checked', 'primarykey' => '0', 'type' => 'varchar'],
], $tmpdir, ['driver' => 'mysql']);

$params = [
    'xmetadb_mysqlhost'     => $mysql_host,
    'xmetadb_mysqlusername' => $mysql_user,
    'xmetadb_mysqlpassword' => $mysql_pass,
    'xmetadb_mysqldatabase' => $mysql_db,
];
$table = xmetadb_table($dbname, 'people', $tmpdir, $params);
$rows = [
    ['Alice',   'Milan',  '30',  '1', 'first',     '2026-01-10 10:00:00', '2026-01-01 00:00:00'],
    ['Bob',     'Rome',   '25',  '1', '',          '2026-01-02 10:00:00', '2026-01-05 00:00:00'],
    ['carla',   'Milan',  '100', '0', "o'neil",    '2026-02-01 10:00:00', '2026-02-01 10:00:00'],
    ['David',   'Naples', '9',   '1', 'a_b',       '2026-03-01 10:00:00', '2026-01-01 00:00:00'],
    ['Elena',   'milan',  '',    '1', 'axb',       '2026-01-01 10:00:00', '2026-01-02 00:00:00'],
    ['Franco',  'Turin',  'n/a', '',  'MiXeD',     '2026-04-01 10:00:00', '2026-04-01 10:00:00'],
    ['Giulia',  'Rome',   '25',  '1', '100%',      '2026-05-01 10:00:00', '2026-01-01 00:00:00'],
];
foreach ($rows as $r) {
    $table->InsertRecord(array_combine(['name', 'city', 'age', 'active', 'note', 'updated', 'checked'], $r));
}
// NULLs: the PHP engine sees them as ''
$conn->query("ALTER TABLE people MODIFY note VARCHAR(255) NULL, MODIFY age VARCHAR(255) NULL");
$conn->query("INSERT INTO people (id, name, city, age, active, note, updated, checked) VALUES (100, 'Hugo', 'Rome', NULL, '1', NULL, '2026-06-01 10:00:00', '2026-01-01 00:00:00')");

$native = new XMETADatabase($dbname, $tmpdir, $params);
$engine = new XMETADatabase($dbname, $tmpdir, $params + ['xmetadb_native_query' => '0']);

// [query, expected to run natively]
$queries = [
    ["SELECT * FROM people", true],
    ["SELECT * FROM people WHERE city = 'Milan'", true],
    ["SELECT * FROM people WHERE city LIKE 'Milan'", true],
    ["SELECT * FROM people WHERE city LIKE 'milan'", true],
    ["SELECT * FROM people WHERE city LIKE '%MIL%'", true],
    ["SELECT * FROM people WHERE name LIKE 'c%'", true],
    ["SELECT * FROM people WHERE name LIKE '%O'", true],
    ["SELECT * FROM people WHERE note LIKE '%_%'", true],
    ["SELECT * FROM people WHERE note LIKE '%0%%'", true],
    ["SELECT * FROM people WHERE note LIKE \"%o'n%\"", true],
    ["SELECT * FROM people WHERE note LIKE ''", true],
    ["SELECT * FROM people WHERE note <> ''", true],
    ["SELECT * FROM people WHERE city <> 'Rome'", true],
    ["SELECT * FROM people WHERE city <> '%il%'", true],
    ["SELECT * FROM people WHERE active <> 1", true],
    ["SELECT * FROM people WHERE active = 1", true],
    ["SELECT * FROM people WHERE age > '25'", true],
    ["SELECT * FROM people WHERE age >= 25", true],
    ["SELECT * FROM people WHERE age < 'b'", true],
    ["SELECT * FROM people WHERE updated > checked", true],
    ["SELECT * FROM people WHERE updated > checked OR note LIKE '' OR note LIKE '0'", true],
    ["SELECT * FROM people WHERE (city = 'Milan' OR city = 'Rome') AND active = '1'", true],
    ["select name, city from people where active LIKE '1' and city LIKE 'Rome'", true],
    ["SELECT name AS n, city FROM people WHERE active = '1'", true],
    ["SELECT * FROM people ORDER BY name", true],
    ["SELECT * FROM people ORDER BY name DESC", true],
    ["SELECT * FROM people ORDER BY age", true],
    ["SELECT * FROM people ORDER BY age DESC", true],
    ["SELECT name, note FROM people ORDER BY note", true],
    ["SELECT * FROM people WHERE city = 'Rome' ORDER BY updated DESC", true],
    ["SELECT * FROM people ORDER BY name LIMIT 1,3", true],
    ["SELECT * FROM people ORDER BY name LIMIT 3,2", true],
    ["SELECT * FROM people ORDER BY name LIMIT 3", true],
    ["SELECT DISTINCT city FROM people ORDER BY city", true],
    ["SELECT DISTINCT city FROM people ORDER BY city DESC", true],
    ["SELECT COUNT(*) AS c FROM people", true],
    ["SELECT COUNT(*) FROM people WHERE city = 'Rome'", true],
    ["SELECT count(*) AS total FROM people WHERE note LIKE '%a%'", true],
    // not handled natively: same answer from the PHP engine
    ["SELECT * FROM people ORDER BY city, name", false],
    ["SELECT COUNT(*) AS c FROM people LIMIT 1,2", false],
    ["SELECT DISTINCT city FROM people ORDER BY name", false],
    ["SELECT DISTINCT city, active FROM people ORDER BY active", false],
    ["SELECT DISTINCT * FROM people ORDER BY name", false],
    ["SELECT * FROM people WHERE nosuchfield = '1'", false],
    ["SELECT * FROM people WHERE name LIKE 'x' OR '1' = '1'", false],
    ["SELECT * FROM people WHERE name LIKE 'x'; DROP TABLE people", false],
    ["SELECT * FROM people WHERE name LIKE 'x' UNION SELECT * FROM people", false],
    ["SELECT * FROM people WHERE checked LIKE 'x'", true],
];

$canon = function ($res, $ordered) {
    if (!is_array($res))
        return $res;
    if (!$ordered) {
        $res = array_map('serialize', $res);
        sort($res);
    }
    return $res;
};

$pass = $fail = 0;
foreach ($queries as [$q, $expect_native]) {
    $ordered = (bool)preg_match('/ORDER BY/i', $q);
    $before = $native->nativequeries;
    $a = @$native->Query($q);
    $used_native = $native->nativequeries > $before;
    $b = @$engine->Query($q);
    $same = $canon($a, $ordered) === $canon($b, $ordered);
    if ($same && $used_native === $expect_native) {
        $pass++;
        echo "  PASS " . ($used_native ? "[native] " : "[engine] ") . "$q\n";
    } else {
        $fail++;
        echo "  FAIL " . ($used_native ? "[native] " : "[engine] ") . "$q\n";
        if (!$same) {
            echo "       native: " . json_encode($a) . "\n";
            echo "       engine: " . json_encode($b) . "\n";
        }
    }
}

// The PHP engine finds nothing when the value holds an escaped quote (as the
// addslashes()'d FK search builds it); the native path matches it
$before = $native->nativequeries;
$r = $native->Query("SELECT name FROM people WHERE note LIKE '%o\\'n%'");
if ($native->nativequeries > $before && $r === [['name' => 'carla']]) { $pass++; echo "  PASS [native] escaped quote in a LIKE value\n"; }
else { $fail++; echo "  FAIL [native] escaped quote in a LIKE value: " . json_encode($r) . "\n"; }

$check = $conn->query("SELECT COUNT(*) FROM people")->fetch_row();
if ((int)$check[0] === count($rows) + 1) { $pass++; echo "  PASS table intact after the injection attempts\n"; }
else { $fail++; echo "  FAIL table changed by an injection attempt\n"; }

if (!getenv("KEEP")) $conn->query("DROP TABLE IF EXISTS people");
echo "\n  PASS: $pass\n" . ($fail ? "  FAIL: $fail\n" : "");
exit($fail ? 1 : 0);
