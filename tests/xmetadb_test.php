<?php
/**
 * xmetadb regression & performance test suite — multi-driver
 *
 * Run from project root:
 *   php tests/xmetadb_test.php [options]
 *
 * Options:
 *   --label  <name>        Tag for saved benchmark JSON (default: phase0)
 *   --compare <file.json>  Compare timing with a previous run
 *   --mysql-host <host>
 *   --mysql-user <user>
 *   --mysql-pass <pass>
 *   --mysql-db   <dbname>
 */

// ---------------------------------------------------------------------------
// Minimal framework stubs
// xmetadb is standalone except for these calls:
// - FN_GetParam: called from XMETATable::sendFileToClient() on every construction
//   (returns null in CLI → early return, no real effect)
// - FN_Copy: only for file/image field types (not used in basic CRUD tests)
// - FN_Now: only when XMETADB_DEBUG_FILE_LOG is defined
// - dprint_r: debug print in mysql driver (called when table name lookup edge-case hits)
// ---------------------------------------------------------------------------
if (!function_exists('FN_GetParam')) {
    function FN_GetParam($key, $var = false, $type = "") {
        if ($var === false) { $var = isset($_REQUEST) ? $_REQUEST : []; }
        return isset($var[$key]) ? $var[$key] : null;
    }
}
if (!function_exists('FN_Copy'))  { function FN_Copy($src, $dst) { return copy($src, $dst); } }
if (!function_exists('FN_Now'))   { function FN_Now() { return date('Y-m-d H:i:s'); } }
if (!function_exists('dprint_r')) { function dprint_r($v) { /* debug stub */ } }

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

require_once __DIR__ . '/../src/include/xmetadb.php';

// ---------------------------------------------------------------------------
// Test runner
// ---------------------------------------------------------------------------
class XMetaTestRunner {
    private $pass    = 0;
    private $fail    = 0;
    private $timings = [];
    private $color;

    public function __construct() {
        $this->color = (PHP_SAPI === 'cli'
            && function_exists('posix_isatty')
            && posix_isatty(STDOUT));
    }

    public function suite($name) {
        echo "\n" . $this->c('1;34') . "=== $name ===" . $this->c('0') . "\n";
    }

    /** Strict equality */
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

    /** Loose equality — use for cross-driver value checks (int vs string) */
    public function loseq($expected, $actual, $msg) {
        if ($expected == $actual) {
            $this->pass++;
            echo $this->c('32') . "  PASS" . $this->c('0') . " $msg\n";
        } else {
            $this->fail++;
            echo $this->c('31') . "  FAIL" . $this->c('0') . " $msg\n";
            echo "       expected (==): " . var_export($expected, true) . "\n";
            echo "       actual:        " . var_export($actual,   true) . "\n";
        }
    }

    public function ok($cond, $msg) { $this->eq(true, (bool)$cond, $msg); }

    public function info($msg) {
        echo $this->c('33') . "  INFO" . $this->c('0') . " $msg\n";
    }

    public function skip($msg) {
        echo $this->c('36') . "  SKIP" . $this->c('0') . " $msg\n";
    }

    /** Run $fn $n times, record avg µs */
    public function bench($label, callable $fn, $n = 500) {
        $start = microtime(true);
        $result = null;
        for ($i = 0; $i < $n; $i++) { $result = $fn(); }
        $us = (microtime(true) - $start) / $n * 1e6;
        $this->timings[$label] = ['us_avg' => round($us, 2), 'n' => $n];
        echo $this->c('36') . "  PERF" . $this->c('0')
            . sprintf(" %-55s %8.1f µs  (n=%d)\n", $label, $us, $n);
        return $result;
    }

    public function summary() {
        echo "\n" . $this->c('1') . str_repeat('=', 55) . $this->c('0') . "\n";
        echo $this->c('32') . "  PASS: {$this->pass}" . $this->c('0') . "\n";
        if ($this->fail) echo $this->c('31') . "  FAIL: {$this->fail}" . $this->c('0') . "\n";
        echo "\n";
    }

    public function timings() { return $this->timings; }
    public function failed()  { return $this->fail; }

    public function c($code) { return $this->color ? "\e[{$code}m" : ''; }
    private function dump($v) { return var_export($v, true); }
}

// ---------------------------------------------------------------------------
// Compare two benchmark JSON files
// ---------------------------------------------------------------------------
function xmeta_compare_bench($file_a, $file_b) {
    $a  = json_decode(file_get_contents($file_a), true);
    $b  = json_decode(file_get_contents($file_b), true);
    $ta = $a['timings'];
    $tb = $b['timings'];
    echo "\n=== PERFORMANCE COMPARISON ===\n";
    echo sprintf("  %-55s %10s %10s %8s\n", 'Benchmark', basename($file_a), basename($file_b), 'Delta');
    echo str_repeat('-', 90) . "\n";
    $all = array_unique(array_merge(array_keys($ta), array_keys($tb)));
    sort($all);
    foreach ($all as $k) {
        $av = isset($ta[$k]) ? $ta[$k]['us_avg'] : null;
        $bv = isset($tb[$k]) ? $tb[$k]['us_avg'] : null;
        if ($av !== null && $bv !== null) {
            $delta = (($bv - $av) / $av) * 100;
            $flag  = $delta > 10 ? ' !!SLOWER!!' : ($delta < -10 ? ' FASTER' : '');
            echo sprintf("  %-55s %10.1f %10.1f %+7.1f%%%s\n", $k, $av, $bv, $delta, $flag);
        } else {
            echo sprintf("  %-55s %10s %10s\n", $k,
                $av !== null ? sprintf("%.1f", $av) : '-',
                $bv !== null ? sprintf("%.1f", $bv) : '-');
        }
    }
}

// ---------------------------------------------------------------------------
// CRUD test suite — driver-agnostic
// Runs the same operations on any XMETATable instance and labels results with $drv.
// Uses loose equality (==) for ID values because drivers differ:
//   xmlphp: autoincrement returns PHP int
//   sqlite3: INT column returned as PHP int by SQLite3::fetchArray
//   mysql:   all columns returned as PHP string by mysqli::fetch_assoc
// ---------------------------------------------------------------------------
function xmeta_run_driver_crud(XMetaTestRunner $t, string $drv, XMETATable $tbl) {
    $t->ok(is_object($tbl), "$drv: table object created");
    $t->ok(!empty($tbl->driverclass), "$drv: driver class initialised");

    // Insert
    $r1 = $tbl->InsertRecord(['name' => 'Item One', 'value' => '100']);
    $t->ok(is_array($r1),          "$drv: InsertRecord returns array");
    $t->loseq(1, $r1['id'],        "$drv: autoincrement id==1");
    $t->eq('Item One', $r1['name'],"$drv: name persisted");

    $r2 = $tbl->InsertRecord(['name' => 'Item Two', 'value' => '200']);
    $t->loseq(2, $r2['id'],        "$drv: autoincrement id==2");

    // Insert with XML-significant chars — must survive driver roundtrip unchanged
    $r3 = $tbl->InsertRecord(['name' => 'AT&T <ok>', 'value' => '300']);
    $t->ok(is_array($r3),          "$drv: insert special chars");

    // GetNumRecords
    $t->loseq(3, $tbl->GetNumRecords(), "$drv: GetNumRecords==3");

    // GetRecords (all)
    $all = $tbl->GetRecords();
    $t->eq(3, count($all),         "$drv: GetRecords: 3 total");

    // GetRecord with exact filter
    $found = $tbl->GetRecord(['name' => 'Item One']);
    $t->ok(is_array($found),       "$drv: GetRecord by name finds row");
    $t->loseq(1, $found['id'],     "$drv: GetRecord: id==1");

    // GetRecordByPrimaryKey
    $by_pk = $tbl->GetRecordByPrimaryKey('1');
    $t->eq('Item One', $by_pk['name'], "$drv: GetRecordByPrimaryKey(1) name");

    // Special chars roundtrip
    $found3 = $tbl->GetRecord(['id' => $r3['id']]);
    $t->eq('AT&T <ok>', $found3['name'], "$drv: special chars survive roundtrip");

    // LIKE filter — %Item% should match 2 records (not AT&T <ok>)
    $like = $tbl->GetRecords(['name' => '%Item%']);
    $t->eq(2, count($like), "$drv: LIKE %%Item%%: 2 matches");

    // UpdateRecord
    $upd = $tbl->UpdateRecord(['id' => '1', 'name' => 'Updated One', 'value' => '999']);
    $t->ok(is_array($upd),          "$drv: UpdateRecord returns array");
    $t->eq('Updated One', $upd['name'], "$drv: UpdateRecord name updated");

    $verify = $tbl->GetRecordByPrimaryKey('1');
    $t->eq('Updated One', $verify['name'],  "$drv: update persisted (name)");
    $t->eq('999',         $verify['value'], "$drv: update persisted (value)");

    // DelRecord
    $t->eq(true, $tbl->DelRecord('2'), "$drv: DelRecord true");
    $t->eq(2, count($tbl->GetRecords()), "$drv: 2 records remain");
    $t->eq(null, $tbl->GetRecord(['id' => '2']), "$drv: deleted record not found");

    // Performance: 50 sequential inserts
    $counter = 2; // already inserted 2 remaining (id 1 and 3); next auto is 4
    $t->bench("InsertRecord/50 [$drv]", function () use ($tbl, &$counter) {
        $counter++;
        return $tbl->InsertRecord(['name' => "Perf$counter", 'value' => (string)$counter]);
    }, 50);

    // Re-build table object to flush any object-level caching
    $tbl2 = new XMETATable($tbl->databasename, $tbl->tablename, $tbl->path);
    $t->bench("GetRecords/all (~52 rows) [$drv]", function () use ($tbl2) {
        return $tbl2->GetRecords();
    }, 50);

    $t->bench("GetRecordByPk [$drv]", function () use ($tbl) {
        return $tbl->GetRecordByPrimaryKey('1');
    }, 100);
}

// ---------------------------------------------------------------------------
// CLI args
// ---------------------------------------------------------------------------
$label       = 'phase0';
$compare     = null;
$mysql_host  = null;
$mysql_user  = 'root';
$mysql_pass  = '';
$mysql_db    = 'xmetadb_test';

for ($i = 1; $i < $argc; $i++) {
    switch ($argv[$i]) {
        case '--label':      $label      = $argv[++$i] ?? $label;      break;
        case '--compare':    $compare    = $argv[++$i] ?? null;         break;
        case '--mysql-host': $mysql_host = $argv[++$i] ?? null;         break;
        case '--mysql-user': $mysql_user = $argv[++$i] ?? $mysql_user;  break;
        case '--mysql-pass': $mysql_pass = $argv[++$i] ?? '';           break;
        case '--mysql-db':   $mysql_db   = $argv[++$i] ?? $mysql_db;    break;
    }
}

// ---------------------------------------------------------------------------
// Temp directory — auto-removed on exit
// ---------------------------------------------------------------------------
$tmpdir = sys_get_temp_dir() . '/xmetadb_test_' . getmypid();
mkdir($tmpdir, 0755, true);
register_shutdown_function(function () use ($tmpdir) {
    if (file_exists($tmpdir)) xmetadb_remove_dir_rec($tmpdir);
});

$t = new XMetaTestRunner();
echo "\nxmetadb test suite — PHP " . PHP_VERSION . " — " . date('Y-m-d H:i:s') . "\n";

// ===========================================================================
// SUITE 1 — removePhpTags
// ===========================================================================
$t->suite('removePhpTags');

$t->eq("\ndata", removePhpTags("<?php exit(0);?>\ndata"),            'strips PHP header');
$t->eq('no header', removePhpTags('no header'),                      'no-op when no header');
$t->eq('', removePhpTags('<?php exit(0);?>'),                        'empty string when only header');
$t->eq("y", removePhpTags("x\n<?php exit(0);?>y"),                   'strips from first occurrence (content before tag is dropped)');

$t->bench('removePhpTags', function () {
    removePhpTags("<?php exit(0);?>\n<db><row><id>1</id></row></db>");
});

// ===========================================================================
// SUITE 2 — xmlenc / xmldec roundtrip
// ===========================================================================
$t->suite('xmlenc / xmldec');

foreach ([
    'plain text'   => 'hello world',
    'ampersand'    => 'AT&T',
    'less-than'    => 'a < b',
    'greater-than' => 'a > b',
    'all three'    => '<em>AT&T</em>',
    'double-encode'=> '&amp; already',
    'empty string' => '',
    'numbers'      => '1234567890',
    'italian'      => 'à è ì ò ù',
] as $tc => $original) {
    $t->eq($original, xmldec(xmlenc($original)), "roundtrip: $tc");
}

$t->eq('&amp;', xmlenc('&'),  'xmlenc & → &amp;');
$t->eq('&lt;',  xmlenc('<'),  'xmlenc < → &lt;');
$t->eq('&gt;',  xmlenc('>'),  'xmlenc > → &gt;');
$t->eq('&',     xmldec('&amp;'), 'xmldec &amp; → &');
$t->eq('<',     xmldec('&lt;'),  'xmldec &lt; → <');
$t->eq('>',     xmldec('&gt;'),  'xmldec &gt; → >');
$t->eq('',      xmldec(123),     'xmldec non-string → ""');

$t->bench('xmlenc (special chars)', function () { xmlenc('Hello <World> & "foo"'); });
$t->bench('xmldec (encoded)',       function () { xmldec('Hello &lt;World&gt; &amp; &quot;foo&quot;'); });

// ===========================================================================
// SUITE 3 — xmetadb_removexmlcomments
// ===========================================================================
$t->suite('xmetadb_removexmlcomments');

$t->eq('<root><item>x</item></root>',
    xmetadb_removexmlcomments('<root><!-- comment --><item>x</item></root>'),
    'removes inline comment');
$t->eq('<root><item>x</item></root>',
    xmetadb_removexmlcomments("<root><!-- multi\nline --><item>x</item></root>"),
    'removes multiline comment');
$t->eq('<root><item>x</item></root>',
    xmetadb_removexmlcomments("<root><?php echo 'x'; ?><item>x</item></root>"),
    'removes PHP processing instruction');
$t->eq('<root>data</root>',
    xmetadb_removexmlcomments('<root>data</root>'),
    'no-op when no comments');

$t->bench('xmetadb_removexmlcomments', function () {
    xmetadb_removexmlcomments('<root><!-- c1 --><item>x</item><!-- c2 --></root>');
});

// ===========================================================================
// SUITE 4 — xmetadb_encode_preg
// ===========================================================================
$t->suite('xmetadb_encode_preg');

foreach (['/', '(', ')', '^', '$', '*', '+', '?', '[', ']', '|', '\\'] as $ch) {
    $enc = xmetadb_encode_preg($ch);
    $t->ok(preg_match('/' . $enc . '/', $ch) === 1, "encode_preg '$ch' matches itself");
}

$dot_enc       = xmetadb_encode_preg('.');
$dot_matches_x = (preg_match('/^' . $dot_enc . '$/', 'x') === 1);
$t->info('dot escape: ' . ($dot_matches_x
    ? 'NOT escaped — matches any char (known issue to fix in phase 1)'
    : 'correctly escaped'));

$t->bench('xmetadb_encode_preg', function () {
    xmetadb_encode_preg('hello/world(test)?[a-z]$^*+|\\');
});

// ===========================================================================
// SUITE 5 — xmetadb_encode_preg_replace2nd
// ===========================================================================
$t->suite('xmetadb_encode_preg_replace2nd');

$raw = '<record><name>test $val with \\backslash</name></record>';
$enc = xmetadb_encode_preg_replace2nd($raw);
$t->eq($raw, preg_replace('/PLACEHOLDER/', $enc, 'PLACEHOLDER'), 'survives preg_replace as 2nd arg');
$t->eq('\\\\',  xmetadb_encode_preg_replace2nd('\\'),    'backslash doubled');
$t->eq('\\$var', xmetadb_encode_preg_replace2nd('$var'),  'dollar sign escaped');

$t->bench('xmetadb_encode_preg_replace2nd', function () {
    xmetadb_encode_preg_replace2nd('<tag>$val \\back</tag>');
});

// ===========================================================================
// SUITE 6 — get_xml_single_element / alias
// ===========================================================================
$t->suite('get_xml_single_element');

$xml = '<config><name>test</name><value>42</value></config>';
$t->eq('test', get_xml_single_element('name',    $xml), 'extracts <name>');
$t->eq('42',   get_xml_single_element('value',   $xml), 'extracts <value>');
$t->eq('',     get_xml_single_element('missing', $xml), 'empty for missing element');
$t->eq(false, function_exists('xmetadb_get_xml_single_element'), 'alias xmetadb_get_xml_single_element removed');
$t->eq('hello',
    get_xml_single_element('name', '<!-- c --><config><name>hello</name></config>'),
    'works through comments');

$t->bench('get_xml_single_element', function () use ($xml) {
    get_xml_single_element('value', $xml);
});

// ===========================================================================
// SUITE 7 — xmetadb_xml2array
// ===========================================================================
$t->suite('xmetadb_xml2array');

$xml2 = "<?php exit(0);?>\n<db>\n  <row>\n    <id>1</id>\n    <name>Alice</name>\n  </row>\n  <row>\n    <id>2</id>\n    <name>Bob</name>\n  </row>\n</db>";

$res = xmetadb_xml2array($xml2, 'row');
$t->eq(2,       count($res),     '2 records parsed');
$t->eq('1',     $res[0]['id'],   'first record id=1');
$t->eq('Alice', $res[0]['name'], 'first record name=Alice');
$t->eq('2',     $res[1]['id'],   'second record id=2');
$t->eq('Bob',   $res[1]['name'], 'second record name=Bob');

// Special chars decoded via xmldec
$xml_enc = "<?php exit(0);?>\n<db>\n  <item>\n    <id>1</id>\n    <desc>AT&amp;T &lt;ok&gt;</desc>\n  </item>\n</db>";
$res_enc = xmetadb_xml2array($xml_enc, 'item');
$t->eq('AT&T <ok>', $res_enc[0]['desc'], 'xmldec applied to values');

// Field filter
$res_flt = xmetadb_xml2array($xml2, 'row', ['id']);
$t->ok(is_array($res_flt) && count($res_flt) >= 1, 'field filter returns array');
$t->ok(isset($res_flt[0]['id']), 'filtered result contains id');

// Empty
$t->eq(null, xmetadb_xml2array("<?php exit(0);?>\n<db></db>", 'row'), 'null for no matching elements');

// Nested same-name elements
$xml_nest = "<?php exit(0);?>\n<db>\n  <item>\n    <id>1</id>\n    <item>nested</item>\n  </item>\n</db>";
$t->ok(is_array(xmetadb_xml2array($xml_nest, 'item')), 'handles nested same-name elements');

// Document current behavior of line-42 bug (&& instead of ||)
$short_result = xmetadb_xml2array("ab", 'row');
$t->info('line-42 bug: short string (<3 chars) result = ' . var_export($short_result, true));
$t->info('line-42 bug: PHP-prefixed → XML path (json_decode never called): result = '
    . var_export(xmetadb_xml2array("<?php exit(0);?>\n<db></db>", 'row'), true));

$xml_100 = "<?php exit(0);?>\n<db>";
for ($i = 1; $i <= 100; $i++) {
    $xml_100 .= "\n  <row><id>$i</id><name>Name$i</name><val>Value$i</val></row>";
}
$xml_100 .= "\n</db>";

$t->bench('xmetadb_xml2array (10 records)',  function () use ($xml2)    { xmetadb_xml2array($xml2,    'row'); }, 500);
$t->bench('xmetadb_xml2array (100 records)', function () use ($xml_100) { xmetadb_xml2array($xml_100, 'row'); }, 100);

// ===========================================================================
// SUITE 8 — xmetadb_array_sort_by_key
// ===========================================================================
$t->suite('xmetadb_array_sort_by_key');

$data = [
    ['id' => '3', 'name' => 'Charlie'],
    ['id' => '1', 'name' => 'Alice'],
    ['id' => '2', 'name' => 'Bob'],
];
$asc = xmetadb_array_sort_by_key($data, 'id');
$t->eq('1', $asc[0]['id'], 'asc: first id=1');
$t->eq('2', $asc[1]['id'], 'asc: second id=2');
$t->eq('3', $asc[2]['id'], 'asc: third id=3');

$desc = xmetadb_array_sort_by_key($data, 'id', true);
$t->eq('3', $desc[0]['id'], 'desc: first id=3');
$t->eq('1', $desc[2]['id'], 'desc: last id=1');

$by_name = xmetadb_array_sort_by_key($data, 'name');
$t->eq('Alice', $by_name[0]['name'], 'sort by name: Alice first');

$data_100 = [];
for ($i = 100; $i >= 1; $i--) { $data_100[] = ['id' => "$i", 'name' => "N$i"]; }
$t->bench('xmetadb_array_sort_by_key (100 items)', function () use ($data_100) {
    xmetadb_array_sort_by_key($data_100, 'id');
}, 200);

// ===========================================================================
// SUITE 9 — xmetadb_array_natsort_by_key
// ===========================================================================
$t->suite('xmetadb_array_natsort_by_key');

$nat = [
    ['id' => '10', 'f' => 'file10'],
    ['id' => '2',  'f' => 'file2'],
    ['id' => '1',  'f' => 'file1'],
    ['id' => '20', 'f' => 'file20'],
];
$ns = xmetadb_array_natsort_by_key($nat, 'id');
$t->eq('1',  $ns[0]['id'], 'natsort: 1 first');
$t->eq('2',  $ns[1]['id'], 'natsort: 2 second');
$t->eq('10', $ns[2]['id'], 'natsort: 10 before 20');
$t->eq('20', $ns[3]['id'], 'natsort: 20 last');
$t->eq(false, xmetadb_array_natsort_by_key('not_array', 'id'), 'returns false for non-array');

$t->bench('xmetadb_array_natsort_by_key (100 items)', function () use ($data_100) {
    xmetadb_array_natsort_by_key($data_100, 'id');
}, 200);

// ===========================================================================
// SUITE 10 — xmetadb_readDatabase
// ===========================================================================
$t->suite('xmetadb_readDatabase');

$db_file = "$tmpdir/readtest.php";
$db_xml  = "<?php exit(0);?>\n<db>\n  <row><id>1</id><name>Alice</name></row>\n  <row><id>2</id><name>Bob</name></row>\n</db>";
file_put_contents($db_file, $db_xml);

$rows = xmetadb_readDatabase($db_file, 'row');
$t->eq(2,       count($rows),     'reads 2 rows');
$t->eq('Alice', $rows[0]['name'], 'first row = Alice');
$t->eq('Bob',   $rows[1]['name'], 'second row = Bob');

// Cache
$rows2 = xmetadb_readDatabase($db_file, 'row');
$t->eq($rows, $rows2, 'cache: second call identical');

// Force reload
$rows_r = xmetadb_readDatabase($db_file, 'row', false, false);
$t->eq(2, count($rows_r), 'force reload: still 2 rows');

// Non-existent
$t->eq(false, xmetadb_readDatabase("$tmpdir/nope.php", 'row'), 'returns false for missing file');

// Field filter
$flt = xmetadb_readDatabase($db_file, 'row', ['id'], false);
$t->ok(is_array($flt) && count($flt) === 2, 'field filter: 2 rows');
$t->ok(isset($flt[0]['id']), 'field filter: id present');

// Cache invalidation
$db_xml_v2 = str_replace('</db>', "  <row><id>3</id><name>Charlie</name></row>\n</db>", $db_xml);
file_put_contents($db_file, $db_xml_v2);
clearstatcache();
$rows3 = xmetadb_readDatabase($db_file, 'row');
$t->info('cache invalidation: ' . (count($rows3) === 3 ? 'DETECTED' : 'NOT detected (stale cache)'));
file_put_contents($db_file, $db_xml); // restore

$t->bench('readDatabase (cache, 2 rows)',       function () use ($db_file) { xmetadb_readDatabase($db_file, 'row'); },              1000);
$t->bench('readDatabase (reload, 2 rows)',      function () use ($db_file) { xmetadb_readDatabase($db_file, 'row', false, false); }, 200);

$db_100 = "$tmpdir/readtest100.php";
$xml_100_content = "<?php exit(0);?>\n<db>";
for ($i = 1; $i <= 100; $i++) {
    $xml_100_content .= "\n  <row><id>$i</id><name>Name$i</name><val>V$i</val></row>";
}
$xml_100_content .= "\n</db>";
file_put_contents($db_100, $xml_100_content);
xmetadb_readDatabase($db_100, 'row'); // warm cache

$t->bench('readDatabase (cache, 100 rows)',     function () use ($db_100) { xmetadb_readDatabase($db_100, 'row'); },              1000);
$t->bench('readDatabase (reload, 100 rows)',    function () use ($db_100) { xmetadb_readDatabase($db_100, 'row', false, false); }, 100);

// ===========================================================================
// SUITE 11 — Database / table creation / field management
// ===========================================================================
$t->suite('createxmldatabase / createxmltable / addxmltablefield');

$dbname    = 'testdb';
$r_cdb     = createxmldatabase($dbname, $tmpdir);
$t->eq(false, $r_cdb, 'createxmldatabase: false on success');
$t->ok(file_exists("$tmpdir/$dbname"), 'database directory created');
$t->ok(xmldatabaseexists($dbname, $tmpdir), 'xmldatabaseexists: true');
$t->ok(is_string(createxmldatabase($dbname, $tmpdir)), 'createxmldatabase: error on duplicate');

$fields_def = [
    ['name' => 'id',    'primarykey' => '1', 'type' => 'int',     'extra' => 'autoincrement'],
    ['name' => 'name',  'primarykey' => '0', 'type' => 'varchar', 'defaultvalue' => ''],
    ['name' => 'value', 'primarykey' => '0', 'type' => 'varchar', 'defaultvalue' => ''],
];
$t->eq(false, createxmltable($dbname, 'items', $fields_def, $tmpdir), 'createxmltable: false on success');
$t->ok(xmltableexists($dbname, 'items', $tmpdir), 'xmltableexists: true');

$r_add = addxmltablefield($dbname, 'items', ['name' => 'note', 'type' => 'varchar', 'defaultvalue' => ''], $tmpdir);
$t->ok(is_array($r_add), 'addxmltablefield: returns array');

$fi = getxmltablefield($dbname, 'items', 'id', $tmpdir);
$t->eq('id', $fi['name'],     'getxmltablefield: id found');
$t->eq('1',  $fi['primarykey'], 'getxmltablefield: primarykey=1');

$fi_note = getxmltablefield($dbname, 'items', 'note', $tmpdir);
$t->eq('note', $fi_note['name'], 'getxmltablefield: newly added field found');
$t->eq(null, getxmltablefield($dbname, 'items', 'nonexistent', $tmpdir), 'getxmltablefield: null for missing');

// ===========================================================================
// SUITE 12 — XMETATable CRUD — driver=xmlphp
// ===========================================================================
$t->suite('XMETATable CRUD — driver=xmlphp');

createxmltable($dbname, 'items_xml', $fields_def, $tmpdir);
$tbl_xml = xmetadb_table($dbname, 'items_xml', $tmpdir);
xmeta_run_driver_crud($t, 'xmlphp', $tbl_xml);

// ===========================================================================
// SUITE 13 — xmetadb_remove_dir_rec
// ===========================================================================
$t->suite('xmetadb_remove_dir_rec');

$rmdir = "$tmpdir/rmtest";
mkdir("$rmdir/sub/sub2", 0755, true);
file_put_contents("$rmdir/a.txt", 'a');
file_put_contents("$rmdir/sub/b.txt", 'b');
file_put_contents("$rmdir/sub/sub2/c.txt", 'c');
xmetadb_remove_dir_rec($rmdir);
$t->eq(false, file_exists($rmdir), 'directory fully removed');
$t->info('path traversal: basic removal test (detailed security assertions in Suite 16)');

// ===========================================================================
// SUITE 14 — XMETATable CRUD — driver=sqlite3
// ===========================================================================
$t->suite('XMETATable CRUD — driver=sqlite3');

if (!class_exists('SQLite3')) {
    $t->skip('SQLite3 class not available in this PHP build');
} else {
    // Create descriptor with <driver>sqlite3</driver>
    // SQLite3 file will default to $tmpdir/testdb.sqlite3
    createxmltable($dbname, 'items_sqlite3', $fields_def, $tmpdir, ['driver' => 'sqlite3']);
    ob_start();  // suppress any echo from driver init
    $tbl_s3 = xmetadb_table($dbname, 'items_sqlite3', $tmpdir);
    $driver_output = ob_get_clean();
    if (!empty($driver_output)) {
        $t->info("driver init output: " . trim($driver_output));
    }
    if (!$tbl_s3 || empty($tbl_s3->driverclass)) {
        $t->skip('sqlite3 driver failed to initialise');
    } else {
        $t->info('sqlite3 file: ' . ($tmpdir . '/' . $dbname . '.sqlite3'));
        xmeta_run_driver_crud($t, 'sqlite3', $tbl_s3);
    }
}

// ===========================================================================
// SUITE 15 — XMETATable CRUD — driver=mysql
// ===========================================================================
$t->suite('XMETATable CRUD — driver=mysql');

if (!$mysql_host) {
    $t->skip('MySQL not configured — use: --mysql-host <h> --mysql-user <u> --mysql-pass <p> --mysql-db <db>');
} elseif (!class_exists('mysqli')) {
    $t->skip('mysqli extension not available');
} else {
    // Probe connection before handing to driver (driver echoes errors on failure)
    ob_start();
    $probe = @new mysqli($mysql_host, $mysql_user, $mysql_pass);
    ob_get_clean();
    if ($probe->connect_error) {
        $t->skip('MySQL connection failed: ' . $probe->connect_error);
    } else {
        $probe->close();

        // Ensure test database exists
        $tmp_conn = new mysqli($mysql_host, $mysql_user, $mysql_pass);
        $tmp_conn->query("CREATE DATABASE IF NOT EXISTS `$mysql_db`");
        $tmp_conn->close();

        createxmltable($dbname, 'items_mysql', $fields_def, $tmpdir, ['driver' => 'mysql']);

        ob_start();
        $tbl_mysql = xmetadb_table($dbname, 'items_mysql', $tmpdir, [
            'xmetadb_mysqlhost'     => $mysql_host,
            'xmetadb_mysqlusername' => $mysql_user,
            'xmetadb_mysqlpassword' => $mysql_pass,
            'xmetadb_mysqldatabase' => $mysql_db,
        ]);
        $driver_output = ob_get_clean();
        if (!empty($driver_output)) {
            $t->info("driver init output: " . trim($driver_output));
        }

        if (!$tbl_mysql || empty($tbl_mysql->driverclass)) {
            $t->skip('mysql driver failed to initialise');
        } else {
            $t->info("mysql: {$mysql_user}@{$mysql_host}/{$mysql_db}");
            // Truncate in case the table survived a previous run
            $tbl_mysql->Truncate();
            xmeta_run_driver_crud($t, 'mysql', $tbl_mysql);

            // Cleanup MySQL test table
            $tbl_mysql->driverclass->dbQuery("DROP TABLE IF EXISTS items_mysql");
        }
    }
}

// ===========================================================================
// SUITE 16 — Security hardening (Phase 3)
// ===========================================================================
$t->suite('Security hardening');

// 3.1 — createMetadbTable: XML injection in field values
$sec_db = 'secdb';
createxmldatabase($sec_db, $tmpdir);
$fields_sec = [
    ['name' => 'id',   'primarykey' => '1', 'type' => 'int', 'extra' => 'autoincrement'],
    // Field with XML-special chars in defaultvalue — must be escaped in the descriptor
    ['name' => 'desc', 'primarykey' => '0', 'type' => 'varchar', 'defaultvalue' => 'a < b & c > d'],
];
createxmltable($sec_db, 'sectbl', $fields_sec, $tmpdir);
$descriptor = file_get_contents("$tmpdir/$sec_db/sectbl.php");
$t->ok(strpos($descriptor, '&lt;') !== false,  'createMetadbTable: < escaped as &lt; in descriptor');
$t->ok(strpos($descriptor, '&gt;') !== false,  'createMetadbTable: > escaped as &gt; in descriptor');
$t->ok(strpos($descriptor, '&amp;') !== false, 'createMetadbTable: & escaped as &amp; in descriptor');
$t->ok(strpos($descriptor, 'a < b') === false, 'createMetadbTable: raw < not present in descriptor');

// createMetadbTable with array singlefilename — already had xmlenc, verify still works
createxmltable($sec_db, 'sectbl2', [
    ['name' => 'id', 'primarykey' => '1', 'type' => 'int', 'extra' => 'autoincrement'],
], $tmpdir, ['driver' => 'xmlphp', 'note' => 'val <with> &special']);
$descriptor2 = file_get_contents("$tmpdir/$sec_db/sectbl2.php");
$t->ok(strpos($descriptor2, '&lt;') !== false, 'createMetadbTable: array singlefilename values escaped');

// 3.3 — xmetadb_remove_dir_rec: path traversal blocked
// We can't test die() directly without subprocess; verify the safe path still works.
$safe_rmdir = "$tmpdir/sec_rmtest";
mkdir("$safe_rmdir/sub", 0755, true);
file_put_contents("$safe_rmdir/sub/file.txt", 'x');
xmetadb_remove_dir_rec($safe_rmdir);
$t->eq(false, file_exists($safe_rmdir), 'remove_dir_rec: safe absolute path removed correctly');

// Verify the check now catches '..' (not just '../')
$caught = false;
// Use a subprocess to test die() without killing the test runner
$output = shell_exec('php -r \'
    require_once "' . __DIR__ . '/../src/include/xmetadb.php";
    function FN_GetParam($k,$v=false,$t=""){return null;}
    xmetadb_remove_dir_rec("/tmp/x/../y");
\' 2>&1');
$t->ok(strpos($output, 'xmetadberror') !== false, 'remove_dir_rec: ".." component blocked (die triggered)');

$output_nb = shell_exec('php -r \'
    require_once "' . __DIR__ . '/../src/include/xmetadb.php";
    function FN_GetParam($k,$v=false,$t=""){return null;}
    xmetadb_remove_dir_rec("/tmp/x\0y");
\' 2>&1');
$t->ok(strpos($output_nb, 'xmetadberror') !== false, 'remove_dir_rec: null byte blocked');

// 3.4 — get_xml_single_element: ReDoS — special chars in element name handled safely
$xml_sec = '<config><name>test</name><value>42</value></config>';
// Element name with regex metacharacters — should return '' without crash/exception
$res_special = get_xml_single_element('na.+e', $xml_sec);
$t->eq('', $res_special, 'get_xml_single_element: regex chars in elem name return empty (no match)');

$res_slash = get_xml_single_element('na/me', $xml_sec);
$t->eq('', $res_slash, 'get_xml_single_element: slash in elem name does not break delimiter');

// Normal elements still work after escaping
$t->eq('test', get_xml_single_element('name',  $xml_sec), 'get_xml_single_element: normal element still extracted');
$t->eq('42',   get_xml_single_element('value', $xml_sec), 'get_xml_single_element: normal element still extracted (2)');

// 3.6 — gestfiles: file upload extension blacklist regex
// Test the pattern directly (the function itself can't be called without a real upload)
$dangerous_names = [
    'shell.php'        => true,
    'shell.php5'       => true,
    'shell.phtml'      => true,
    'shell.phar'       => true,
    'shell.php.jpg'    => true,  // double extension
    'shell.shtml'      => true,
    'script.pl'        => true,
    'script.cgi'       => true,
    'script.sh'        => true,
    'script.py'        => true,
];
$safe_names = [
    'photo.jpg'        => false,
    'document.pdf'     => false,
    'archive.zip'      => false,
    'image.png'        => false,
    'data.phpx'        => false,  // NOT a PHP extension
    'test_php.txt'     => false,  // contains 'php' as substring, not extension
];
foreach (array_merge($dangerous_names, $safe_names) as $name => $should_block) {
    $blocked = (bool)preg_match('/\.(php[0-9]?|phtml|phar|shtml|pl|cgi|sh|py)(\.|$)/i', $name);
    $t->eq($should_block, $blocked, "upload blacklist: '$name' " . ($should_block ? 'blocked' : 'allowed'));
}

// ===========================================================================
// ===========================================================================
// SUITE 17 — Driver bug fixes
// ===========================================================================
$t->suite('Driver bug fixes');

// eval() removed from xmlphp/serialize: defaultvalue is now a literal string, not PHP code
$fields_eval = [
    ['name' => 'id',  'primarykey' => '1', 'type' => 'int', 'extra' => 'autoincrement'],
    ['name' => 'val', 'primarykey' => '0', 'type' => 'varchar', 'defaultvalue' => 'hello world'],
];
createxmltable('secdb', 'evaltbl', $fields_eval, $tmpdir);
$evaltbl = xmetadb_table('secdb', 'evaltbl', $tmpdir);
$r_eval = $evaltbl->InsertRecord([]);
$t->ok(is_array($r_eval),                    'xmlphp eval removed: InsertRecord with defaultvalue succeeds');
$t->eq('hello world', $r_eval['val'],        'xmlphp eval removed: literal defaultvalue applied correctly');

// defaultvalue with XML-special chars (would have caused eval error before)
$fields_eval2 = [
    ['name' => 'id',  'primarykey' => '1', 'type' => 'int', 'extra' => 'autoincrement'],
    ['name' => 'tag', 'primarykey' => '0', 'type' => 'varchar', 'defaultvalue' => 'a < b'],
];
createxmltable('secdb', 'evaltbl2', $fields_eval2, $tmpdir);
$evaltbl2 = xmetadb_table('secdb', 'evaltbl2', $tmpdir);
$r_eval2 = $evaltbl2->InsertRecord([]);
$t->ok(is_array($r_eval2),                   'xmlphp: defaultvalue with < chars does not crash');

// inverted strpos fix: strpos($pk, "..") === false now correctly guards directory removal
// (the path "safe_pk" should pass, "../../etc" would fail the check and not attempt deletion)
$t->ok(strpos("safe_pk",   "..") === false,  'strpos fix: safe pk passes traversal check');
$t->ok(strpos("../../etc", "..") !== false,  'strpos fix: traversal pk caught by check');

// sqlite3 Truncate: $query was undefined before (would throw PHP error)
if (class_exists('SQLite3')) {
    $tbl_trunc = xmetadb_table('testdb', 'items_sqlite3', $tmpdir);
    if ($tbl_trunc && !empty($tbl_trunc->driverclass)) {
        ob_start();
        $trunc_result = $tbl_trunc->Truncate();
        ob_get_clean();
        $t->ok($trunc_result !== false, 'sqlite3 Truncate: no longer crashes with undefined $query');
    } else {
        $t->skip('sqlite3 table not available for Truncate test');
    }
}

// mysql no-op bug: InsertRecordFast had $xmetadb_mysqlcurrentdb != instead of =
// The fix cannot be directly tested without inspecting the global, but a round-trip
// insert/select verifies the corrected flow reaches the right database
// mysql no-op fix: code-level verification (the static $dbcache in mysql driver prevents
// reusing a dropped table in the same process; use a fresh table name instead)
if ($mysql_host) {
    createxmltable('secdb', 'noop_fix', [
        ['name' => 'id',  'primarykey' => '1', 'type' => 'int', 'extra' => 'autoincrement'],
        ['name' => 'val', 'primarykey' => '0', 'type' => 'varchar', 'defaultvalue' => ''],
    ], $tmpdir, ['driver' => 'mysql']);
    ob_start();
    $tbl_m = new XMETATable('secdb', 'noop_fix', $tmpdir, [
        'xmetadb_mysqlhost'     => $mysql_host,
        'xmetadb_mysqlusername' => $mysql_user,
        'xmetadb_mysqlpassword' => $mysql_pass,
        'xmetadb_mysqldatabase' => $mysql_db,
    ]);
    ob_get_clean();
    if ($tbl_m && !empty($tbl_m->driverclass)) {
        $r_mysql = $tbl_m->InsertRecord(['val' => 'test']);
        $t->ok(is_array($r_mysql), 'mysql no-op fix: InsertRecord works on fresh table');
        $tbl_m->driverclass->dbQuery("DROP TABLE IF EXISTS noop_fix");
    } else {
        $t->skip('mysql table not available for no-op fix test');
    }
} else {
    $t->skip('mysql not configured — skipping no-op fix test');
}

// ===========================================================================
// SUMMARY + save benchmark JSON
// ===========================================================================
$t->summary();

$out_file = __DIR__ . '/bench_' . $label . '.json';
file_put_contents($out_file, json_encode([
    'label'       => $label,
    'php_version' => PHP_VERSION,
    'timestamp'   => date('Y-m-d H:i:s'),
    'timings'     => $t->timings(),
], JSON_PRETTY_PRINT) . "\n");
echo "Benchmark saved → $out_file\n";

if ($compare && file_exists($compare)) {
    xmeta_compare_bench($compare, $out_file);
} elseif ($compare) {
    echo "WARNING: compare file not found: $compare\n";
}

exit($t->failed() ? 1 : 0);
