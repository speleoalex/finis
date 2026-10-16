<?php
namespace Xmetadb;

/**
 * Per-request lookup of foreign key records.
 *
 * Lists and exports resolve each foreign key value (e.g. a region id) to the
 * fields to show (e.g. its name). Doing it with one GetRecord() per row and
 * per field makes one query per row (N+1). This class keeps, for the current
 * request only, the rows already read from each foreign table:
 *
 * - the first lookups of a table run GetRecord() as before, so a page that
 *   shows a single record reads nothing more than it used to;
 * - after XMETAFKLookup::$bulk_after distinct values, the link field and the
 *   requested fields of the whole table are read with a single query and the
 *   next lookups are served from memory;
 * - a value not found exactly in memory (or containing LIKE wildcards) falls
 *   back to GetRecord(), so results stay the same as the per-row query
 *   (GetRecord() matches with LIKE, i.e. case-insensitively on MySQL).
 *
 * Nothing is kept between requests. Writes made through XMETATable in the
 * same request (InsertRecord, UpdateRecord, DelRecord, ...) forget the cached
 * rows of that table.
 *
 * @package xmetadb
 */
class XMETAFKLookup
{
    /** distinct values looked up one by one before the whole table is read */
    public static $bulk_after = 5;

    /** @var array tablekey => cachekey => array('rows'=>..., 'seen'=>int, 'all'=>bool) */
    private static $cache = array();

    /** @var array tablekey => querykey => records (see GetRecords()) */
    private static $records = array();

    /**
     * Returns the record of $table whose $linkfield matches $value,
     * like $table->GetRecord(array($linkfield => $value)).
     *
     * @param XMETATable $table foreign table
     * @param string $linkfield field of $table that holds the linked value
     * @param string $value linked value
     * @param array|string $showfields fields that will be read from the record
     *        (comma separated or array); their language variants
     *        (field_xx) are read too
     * @return array|null
     */
    static function GetRecord($table, $linkfield, $value, $showfields = array())
    {
        if (!is_object($table))
            return null;
        if (!is_array($showfields))
            $showfields = explode(",", $showfields);
        $value = (string) $value;
        $tablekey = self::TableKey($table);
        $cachekey = $linkfield . "|" . implode(",", $showfields);
        if (!isset(self::$cache[$tablekey][$cachekey]))
        {
            self::$cache[$tablekey][$cachekey] = array('rows' => array(), 'seen' => 0, 'all' => false);
        }
        $c = &self::$cache[$tablekey][$cachekey];
        if (array_key_exists($value, $c['rows']))
        {
            return $c['rows'][$value];
        }
        $wildcards = (strpbrk($value, "%_\\") !== false);
        if (!$c['all'] && !$wildcards && ++$c['seen'] > self::$bulk_after)
        {
            self::LoadAll($table, $linkfield, $showfields, $c);
            if (array_key_exists($value, $c['rows']))
            {
                return $c['rows'][$value];
            }
        }
        $c['rows'][$value] = $table->GetRecord(array($linkfield => $value));
        return $c['rows'][$value];
    }

    /**
     * Forgets the cached rows of a table (called when the table is written).
     *
     * @param XMETATable $table
     */
    static function Forget($table)
    {
        unset(self::$cache[self::TableKey($table)]);
        unset(self::$records[self::TableKey($table)]);
    }

    /**
     * Forgets everything.
     */
    static function Clear()
    {
        self::$cache = array();
        self::$records = array();
    }

    /**
     * Returns $table->GetRecords($restr, false, false, false, false, $fields),
     * reading the table only once per request for the same $restr and $fields.
     *
     * Used by the form options of foreign key fields: several fields (in one
     * form or in different forms of the same page) often point to the same
     * table, e.g. the four cave fields of the bibliography, and each one used
     * to read the whole table again.
     *
     * @param XMETATable $table foreign table
     * @param array|false $restr restriction, as for GetRecords()
     * @param array $fields fields to read
     * @return array|null
     */
    static function GetRecords($table, $restr, $fields)
    {
        if (!is_object($table))
            return null;
        $tablekey = self::TableKey($table);
        $querykey = serialize(array($restr, $fields));
        if (!isset(self::$records[$tablekey]) || !array_key_exists($querykey, self::$records[$tablekey]))
        {
            self::$records[$tablekey][$querykey] = $table->GetRecords($restr, false, false, false, false, $fields);
        }
        return self::$records[$tablekey][$querykey];
    }

    private static function TableKey($table)
    {
        return "{$table->path}|{$table->databasename}|{$table->tablename}";
    }

    /**
     * Reads $linkfield and $showfields (with their language variants) of all
     * records with one query. The first record wins when a value repeats,
     * like GetRecord().
     */
    private static function LoadAll($table, $linkfield, $showfields, &$c)
    {
        $c['all'] = true;
        if (!isset($table->fields[$linkfield]))
            return;
        $fields = array($linkfield);
        foreach ($table->fields as $fieldname => $field)
        {
            foreach ($showfields as $showfield)
            {
                $showfield = trim($showfield);
                if ($fieldname === $showfield || strpos($fieldname, $showfield . "_") === 0)
                {
                    $fields[] = $fieldname;
                }
            }
        }
        $fields = array_values(array_unique($fields));
        $records = $table->GetRecords(false, false, false, false, false, $fields);
        if (!is_array($records))
            return;
        foreach ($records as $record)
        {
            if (!isset($record[$linkfield]))
                continue;
            $key = (string) $record[$linkfield];
            if (!array_key_exists($key, $c['rows']) || $c['rows'][$key] === null)
            {
                $c['rows'][$key] = $record;
            }
        }
    }
}

class_alias('Xmetadb\XMETAFKLookup', 'XMETAFKLookup');
