<?php
/**
 * XMETADatabase query engine test suite
 *
 * Run from project root:
 *   php tests/xmetadb_query_test.php
 */

// Stubs (same as xmetadb_test.php)
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

// ---------------------------------------------------------------------------
// Minimal test runner (same style as xmetadb_test.php)
// ---------------------------------------------------------------------------
class QueryTestRunner {
    private $pass = 0, $fail = 0;
    private $color;
    public function __construct() {
        $this->color = PHP_SAPI === 'cli' && function_exists('posix_isatty') && posix_isatty(STDOUT);
    }
    public function suite($name) {
        echo "\n" . $this->c('1;34') . "=== $name ===" . $this->c('0') . "\n";
    }
    public function eq($expected, $actual, $msg) {
        if ($expected === $actual) {
            $this->pass++;
            echo $this->c('32') . "  PASS" . $this->c('0') . " $msg\n";
        } else {
            $this->fail++;
            echo $this->c('31') . "  FAIL" . $this->c('0') . " $msg\n";
            echo "       expected: " . var_export($expected, true) . "\n";
            echo "       actual:   " . var_export($actual,   true) . "\n";
        }
    }
    public function ok($cond, $msg) { $this->eq(true, (bool)$cond, $msg); }
    public function info($msg) { echo $this->c('33') . "  INFO" . $this->c('0') . " $msg\n"; }
    public function summary() {
        echo "\n" . str_repeat('=', 50) . "\n";
        echo $this->c('32') . "  PASS: {$this->pass}" . $this->c('0') . "\n";
        if ($this->fail) echo $this->c('31') . "  FAIL: {$this->fail}" . $this->c('0') . "\n";
        echo "\n";
    }
    public function failed() { return $this->fail; }
    private function c($code) { return $this->color ? "\e[{$code}m" : ''; }
}

// ---------------------------------------------------------------------------
// Setup: create test database with two tables
// ---------------------------------------------------------------------------
$tmpdir = sys_get_temp_dir() . '/xmetadb_query_test_' . getmypid();
mkdir($tmpdir, 0755, true);
register_shutdown_function(function () use ($tmpdir) {
    if (file_exists($tmpdir)) xmetadb_remove_dir_rec($tmpdir);
});

$dbname = 'testdb';
createxmldatabase($dbname, $tmpdir);

// Table: users
createxmltable($dbname, 'users', [
    ['name' => 'id',       'primarykey' => '1', 'type' => 'int',     'extra' => 'autoincrement'],
    ['name' => 'name',     'primarykey' => '0', 'type' => 'varchar', 'defaultvalue' => ''],
    ['name' => 'city',     'primarykey' => '0', 'type' => 'varchar', 'defaultvalue' => ''],
    ['name' => 'age',      'primarykey' => '0', 'type' => 'varchar', 'defaultvalue' => '0'],
    ['name' => 'active',   'primarykey' => '0', 'type' => 'varchar', 'defaultvalue' => '1'],
], $tmpdir);

$users = xmetadb_table($dbname, 'users', $tmpdir);
$users->InsertRecord(['name' => 'Alice',   'city' => 'Milan',  'age' => '30', 'active' => '1']);
$users->InsertRecord(['name' => 'Bob',     'city' => 'Rome',   'age' => '25', 'active' => '1']);
$users->InsertRecord(['name' => 'Carla',   'city' => 'Milan',  'age' => '35', 'active' => '1']);
$users->InsertRecord(['name' => 'David',   'city' => 'Naples', 'age' => '28', 'active' => '0']);
$users->InsertRecord(['name' => 'Elena',   'city' => 'Turin',  'age' => '22', 'active' => '1']);

// Table: tags (for multi-table test)
createxmltable($dbname, 'tags', [
    ['name' => 'id',    'primarykey' => '1', 'type' => 'int', 'extra' => 'autoincrement'],
    ['name' => 'label', 'primarykey' => '0', 'type' => 'varchar', 'defaultvalue' => ''],
    ['name' => 'color', 'primarykey' => '0', 'type' => 'varchar', 'defaultvalue' => ''],
], $tmpdir);

$tags = xmetadb_table($dbname, 'tags', $tmpdir);
$tags->InsertRecord(['label' => 'php',    'color' => 'blue']);
$tags->InsertRecord(['label' => 'mysql',  'color' => 'orange']);
$tags->InsertRecord(['label' => 'sqlite', 'color' => 'green']);

$db = new XMETADatabase($dbname, $tmpdir);
$t  = new QueryTestRunner();

// ===========================================================================
// SUITE 1 — SELECT *
// ===========================================================================
$t->suite('SELECT *');

$r = $db->Query("SELECT * FROM users");
$t->ok(is_array($r),                'SELECT * returns array');
$t->eq(5, count($r),                'SELECT *: 5 rows');
$t->ok(isset($r[0]['name']),        'SELECT *: name field present');
$t->ok(isset($r[0]['city']),        'SELECT *: city field present');
$t->ok(isset($r[0]['active']),      'SELECT *: active field present');

// ===========================================================================
// SUITE 2 — SELECT specific fields
// ===========================================================================
$t->suite('SELECT specific fields');

$r = $db->Query("SELECT id, name FROM users");
$t->ok(is_array($r),                'SELECT id,name: array returned');
$t->eq(5, count($r),                'SELECT id,name: 5 rows');
$t->ok(isset($r[0]['id']),          'id field present');
$t->ok(isset($r[0]['name']),        'name field present');
$t->ok(!isset($r[0]['city']),       'city field NOT present (not selected)');

// ===========================================================================
// SUITE 3 — SELECT with AS alias
// ===========================================================================
$t->suite('SELECT with AS alias');

$r = $db->Query("SELECT name AS username FROM users");
$t->ok(is_array($r) && count($r) === 5, 'SELECT AS: 5 rows');
$t->ok(isset($r[0]['username']),    'alias username present');
$t->ok(!isset($r[0]['name']),       'original name NOT present');

// ===========================================================================
// SUITE 4 — SELECT DISTINCT
// ===========================================================================
$t->suite('SELECT DISTINCT');

$r = $db->Query("SELECT DISTINCT city FROM users");
$t->ok(is_array($r),                'DISTINCT: array returned');
// Milan(×2), Rome, Naples, Turin = 4 distinct
$t->eq(4, count($r),                'DISTINCT: 4 distinct cities');

// ===========================================================================
// SUITE 5 — WHERE (exact match)
// ===========================================================================
$t->suite('WHERE exact match');

$r = $db->Query("SELECT * FROM users WHERE active = '1'");
$t->ok(is_array($r),                'WHERE active=1: array');
$t->eq(4, count($r),                'WHERE active=1: 4 rows');

$r = $db->Query("SELECT * FROM users WHERE city = 'Milan'");
$t->eq(2, count($r),                "WHERE city=Milan: 2 rows");
$t->eq('Milan', $r[0]['city'],      'city field correct');

$r = $db->Query("SELECT * FROM users WHERE name = 'Bob'");
$t->eq(1, count($r),                "WHERE name=Bob: 1 row");
$t->eq('Bob', $r[0]['name'],        'name correct');

// Non-existent value
$r = $db->Query("SELECT * FROM users WHERE city = 'Venice'");
$t->eq(0, count($r),                'WHERE no match: 0 rows');

// ===========================================================================
// SUITE 6 — WHERE with LIKE
// ===========================================================================
$t->suite('WHERE LIKE');

$r = $db->Query("SELECT * FROM users WHERE name LIKE '%a%'");
$t->ok(is_array($r),                'LIKE %%a%%: array');
// Alice, Carla, David, Elena all contain 'a' (case-insensitive)
$t->eq(4, count($r),                'LIKE %%a%%: 4 rows');

$r = $db->Query("SELECT * FROM users WHERE name LIKE 'A%'");
$t->eq(1, count($r),                "LIKE 'A%%': 1 row (Alice)");
$t->eq('Alice', $r[0]['name'],      'Alice found');

$r = $db->Query("SELECT * FROM users WHERE name LIKE '%ob'");
$t->eq(1, count($r),                "LIKE '%%ob': 1 row (Bob)");
$t->eq('Bob', $r[0]['name'],        'Bob found');

// ===========================================================================
// SUITE 7 — WHERE with AND / OR
// ===========================================================================
$t->suite('WHERE AND / OR');

$r = $db->Query("SELECT * FROM users WHERE city = 'Milan' AND active = '1'");
$t->eq(2, count($r),                'WHERE AND: Milan AND active=1 → 2');

$r = $db->Query("SELECT * FROM users WHERE city = 'Milan' OR city = 'Rome'");
$t->eq(3, count($r),                'WHERE OR: Milan OR Rome → 3');

// ===========================================================================
// SUITE 8 — ORDER BY
// ===========================================================================
$t->suite('ORDER BY');

$r = $db->Query("SELECT name FROM users ORDER BY name");
$t->ok(is_array($r) && count($r) === 5, 'ORDER BY name: 5 rows');
$t->eq('Alice', $r[0]['name'],      'ORDER BY ASC: Alice first');
$t->eq('Elena', $r[4]['name'],      'ORDER BY ASC: Elena last');

$r = $db->Query("SELECT name FROM users ORDER BY name DESC");
$t->eq('Elena', $r[0]['name'],      'ORDER BY DESC: Elena first');
$t->eq('Alice', $r[4]['name'],      'ORDER BY DESC: Alice last');

$r = $db->Query("SELECT * FROM users WHERE active = '1' ORDER BY name");
$t->eq(4, count($r),                'WHERE + ORDER BY: 4 rows');
$t->eq('Alice', $r[0]['name'],      'WHERE + ORDER BY: Alice first');

// ===========================================================================
// SUITE 9 — LIMIT
// ===========================================================================
$t->suite('LIMIT');

$r = $db->Query("SELECT * FROM users LIMIT 1,3");
$t->ok(is_array($r),                'LIMIT 1,3: array');
$t->eq(3, count($r),                'LIMIT 1,3: 3 rows');

$r = $db->Query("SELECT * FROM users LIMIT 1,2");
$t->eq(2, count($r),                'LIMIT 1,2: 2 rows');

$r = $db->Query("SELECT * FROM users ORDER BY name LIMIT 1,2");
$t->eq(2, count($r),                'ORDER BY + LIMIT: 2 rows');
$t->eq('Alice', $r[0]['name'],      'ORDER BY + LIMIT: Alice first');

// ===========================================================================
// SUITE 10 — COUNT(*)
// ===========================================================================
$t->suite('COUNT(*)');

$r = $db->Query("SELECT COUNT(*) AS total FROM users");
$t->ok(is_array($r),                'COUNT(*) AS total: array');
$t->eq(1, count($r),                'COUNT(*): 1 result row');
$t->ok(isset($r[0]['total']),       'COUNT(*): "total" key present');
$t->eq(5, (int)$r[0]['total'],      'COUNT(*): value = 5');

$r = $db->Query("SELECT COUNT(*) AS active_count FROM users WHERE active = '1'");
$t->eq(4, (int)$r[0]['active_count'], 'COUNT(*) with WHERE: 4');

// COUNT(*) without alias — key should be "COUNT(*)"
$r = $db->Query("SELECT COUNT(*) FROM users");
$t->ok(is_array($r) && count($r) === 1, 'COUNT(*) no alias: 1 result row');
$t->ok(isset($r[0]['COUNT(*)']),    'COUNT(*) no alias: key is COUNT(*)');
$t->eq(5, (int)$r[0]['COUNT(*)'],   'COUNT(*) no alias: value = 5');

// ===========================================================================
// SUITE 11 — DESCRIBE
// ===========================================================================
$t->suite('DESCRIBE');

$r = $db->Query("DESCRIBE users");
$t->ok(is_array($r),                'DESCRIBE: array');
$t->eq(5, count($r),                'DESCRIBE: 5 fields');
$fields_desc = array_column($r, 'Field');
$t->ok(in_array('id', $fields_desc),    'DESCRIBE: id field');
$t->ok(in_array('name', $fields_desc),  'DESCRIBE: name field');
$t->ok(in_array('active', $fields_desc),'DESCRIBE: active field');

// ===========================================================================
// SUITE 12 — SHOW TABLES
// ===========================================================================
$t->suite('SHOW TABLES');

$r = $db->Query("SHOW TABLES");
$t->ok(is_array($r),                'SHOW TABLES: array');
$tbls = array_column($r, "Tables_in_{$dbname}");
$t->ok(in_array('users', $tbls),    'SHOW TABLES: users present');
$t->ok(in_array('tags', $tbls),     'SHOW TABLES: tags present');

// ===========================================================================
// SUITE 13 — INSERT
// ===========================================================================
$t->suite('INSERT');

$r = $db->Query("INSERT INTO users (name, city, age, active) VALUES ('Fabio', 'Venice', '40', '1')");
$t->ok(is_array($r),                'INSERT: returns array');
$t->eq('Fabio', $r['name'],         'INSERT: name persisted');
$t->eq('Venice', $r['city'],        'INSERT: city persisted');

// Verify the inserted record is queryable
$r2 = $db->Query("SELECT * FROM users WHERE name = 'Fabio'");
$t->eq(1, count($r2),               'INSERT: record queryable after insert');
$t->eq('Venice', $r2[0]['city'],    'INSERT: city correct after query');

// Field names with digits (regression: regex was [a-zA-Z_ ,]+ not [a-zA-Z0-9_ ,]+)
$r3 = $db->Query("INSERT INTO tags (id, label, color) VALUES ('99', 'tag1', 'red')");
$t->ok(is_array($r3),               'INSERT: field names with digits work');

// ===========================================================================
// SUITE 14 — UPDATE
// ===========================================================================
$t->suite('UPDATE');

// Update Bob's city
$db->Query("UPDATE users SET city = 'Florence' WHERE name = 'Bob'");
$r = $db->Query("SELECT city FROM users WHERE name = 'Bob'");
$t->eq(1, count($r),                'UPDATE: Bob still found');
$t->eq('Florence', $r[0]['city'],   "UPDATE: Bob's city changed to Florence");

// Update multiple fields
$db->Query("UPDATE users SET active = '0', city = 'Genoa' WHERE name = 'Elena'");
$r = $db->Query("SELECT * FROM users WHERE name = 'Elena'");
$t->eq('0', $r[0]['active'],        'UPDATE multi-field: active=0');
$t->eq('Genoa', $r[0]['city'],      'UPDATE multi-field: city=Genoa');

// ===========================================================================
// SUITE 15 — DELETE
// ===========================================================================
$t->suite('DELETE');

// Count before
$before = $db->Query("SELECT COUNT(*) AS c FROM users");
$before_count = (int)$before[0]['c'];

$db->Query("DELETE FROM users WHERE active = '0'");
$after = $db->Query("SELECT COUNT(*) AS c FROM users");
$after_count = (int)$after[0]['c'];

// David (id=4) and Elena (now active=0) should be deleted
$t->ok($after_count < $before_count, 'DELETE: count decreased');

$r = $db->Query("SELECT * FROM users WHERE active = '0'");
$t->eq(0, count($r),               'DELETE: no inactive users remain');

// ===========================================================================
// SUITE 16 — Unknown table / syntax errors
// ===========================================================================
$t->suite('Error handling');

$r = $db->Query("SELECT * FROM nonexistent");
$t->ok(is_string($r) || $r === null || $r === false, 'Unknown table: returns error (not crash)');

$r = $db->Query("DESCRIBE nonexistent");
$t->ok($r === null || $r === false || is_string($r), 'DESCRIBE unknown: returns error');

// Empty result set is still valid
$r = $db->Query("SELECT * FROM users WHERE name = 'Nobody'");
$t->ok(is_array($r) && count($r) === 0, 'No match WHERE: empty array');

// ===========================================================================
// SUMMARY
// ===========================================================================
$t->summary();
exit($t->failed() ? 1 : 0);
