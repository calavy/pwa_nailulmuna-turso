<?php

declare(strict_types=1);

/** Whether PDO sqlite in this PHP build accepts ADD COLUMN IF NOT EXISTS. */
function turso_pdo_sqlite_supports_add_column_if_not_exists(): bool
{
    static $supported = null;
    if ($supported !== null) {
        return $supported;
    }
    $supported = false;
    try {
        $p = new PDO('sqlite::memory:');
        $p->exec('CREATE TABLE t(a INTEGER);');
        $p->exec('ALTER TABLE t ADD COLUMN IF NOT EXISTS b INTEGER;');
        $supported = true;
    } catch (Throwable $e) {
        $supported = false;
    }

    return $supported;
}

/**
 * Convert MySQL SHOW CREATE TABLE DDL to SQLite CREATE + separate index statements.
 *
 * @return array{create: string, indexes: list<string>, warnings: list<string>}
 */
function turso_convert_mysql_create_table(string $mysqlDdl): array
{
    $warnings = [];
    $ddl = trim($mysqlDdl);
    if ($ddl === '') {
        return ['create' => '', 'indexes' => [], 'warnings' => ['empty DDL']];
    }

    $ddl = preg_replace('/`/', '', $ddl) ?? $ddl;
    $ddl = preg_replace('/\)\s*ENGINE\s*=.*$/is', ')', $ddl) ?? $ddl;
    $ddl = preg_replace('/\s+AUTO_INCREMENT\s*=\s*\d+/i', '', $ddl) ?? $ddl;

    if (!preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(\w+)\s*\((.*)\)\s*;?\s*$/is', $ddl, $m)) {
        return ['create' => '-- unparseable CREATE: ' . substr($ddl, 0, 120), 'indexes' => [], 'warnings' => ['parse failed']];
    }

    $table = $m[1];
    $inner = turso_strip_mysql_comment_clauses($m[2]);
    $parts = turso_split_sql_column_list($inner);
    $columnLines = [];
    $fkLines = [];
    $indexes = [];
    $primaryKeyCols = null;
    $columnParts = [];

    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $upper = strtoupper(ltrim($part));
        if (str_starts_with($upper, 'PRIMARY KEY')) {
            if (preg_match('/PRIMARY\s+KEY\s*\(([^)]+)\)/i', $part, $pk)) {
                $primaryKeyCols = array_map(static fn ($c) => trim($c), explode(',', $pk[1]));
            }
            continue;
        }
        $idxParsed = turso_parse_mysql_index_part($part);
        if ($idxParsed !== null) {
            $indexes[] = ($idxParsed['unique'] ? 'CREATE UNIQUE INDEX IF NOT EXISTS ' : 'CREATE INDEX IF NOT EXISTS ')
                . $idxParsed['name'] . ' ON ' . $table . ' (' . $idxParsed['columns'] . ');';
            continue;
        }
        if (str_starts_with($upper, 'CONSTRAINT ') || str_starts_with($upper, 'FOREIGN KEY')) {
            $fk = turso_convert_foreign_key_clause($part, $table);
            if ($fk !== null) {
                $fkLines[] = $fk;
            } else {
                $warnings[] = "skipped FK on {$table}: {$part}";
            }
            continue;
        }
        if (str_starts_with($upper, 'FULLTEXT') || str_starts_with($upper, 'SPATIAL')) {
            $warnings[] = "skipped index type on {$table}: {$part}";
            continue;
        }
        $columnParts[] = $part;
    }

    foreach ($columnParts as $part) {
        $colName = '';
        if (preg_match('/^(\w+)\s+/i', $part, $cm)) {
            $colName = $cm[1];
        }
        $colLine = turso_convert_column_definition($part);
        $isSinglePk = $primaryKeyCols !== null
            && count($primaryKeyCols) === 1
            && strcasecmp($primaryKeyCols[0], $colName) === 0;
        if ($isSinglePk) {
            $colLine = preg_replace('/\bAUTO_INCREMENT\b/i', '', $colLine) ?? $colLine;
            $colLine = preg_replace('/\s+NOT NULL/i', '', $colLine, 1) ?? $colLine;
            if (preg_match('/\bAUTO_INCREMENT\b/i', $part) || preg_match('/\bINTEGER\b/i', $colLine)) {
                $colLine = preg_replace(
                    '/^(\w+)\s+INTEGER(?:\s*\(\s*\d+\s*\))?/i',
                    '$1 INTEGER PRIMARY KEY AUTOINCREMENT',
                    $colLine
                ) ?? $colLine;
            } elseif (!preg_match('/PRIMARY\s+KEY/i', $colLine)) {
                $colLine .= ' PRIMARY KEY';
            }
        }
        $columnLines[] = $colLine;
    }

    $columns = array_merge($columnLines, $fkLines);

    if ($columns === []) {
        return ['create' => '-- empty columns for ' . $table, 'indexes' => [], 'warnings' => $warnings];
    }

    $create = 'CREATE TABLE IF NOT EXISTS ' . $table . " (\n    " . implode(",\n    ", $columns) . "\n);";

    return ['create' => $create, 'indexes' => $indexes, 'warnings' => $warnings];
}

function turso_strip_mysql_comment_clauses(string $inner): string
{
    $s = preg_replace("/COMMENT\s+'(?:[^'\\\\]|\\\\.|'')*'/is", '', $inner) ?? $inner;

    return preg_replace('/\s{2,}/', ' ', $s) ?? $s;
}

/** @return list<string> */
function turso_split_sql_column_list(string $inner): array
{
    $parts = [];
    $depth = 0;
    $buf = '';
    $len = strlen($inner);
    for ($i = 0; $i < $len; $i++) {
        $c = $inner[$i];
        if ($c === '(') {
            $depth++;
        } elseif ($c === ')') {
            $depth--;
        }
        if ($c === ',' && $depth === 0) {
            $parts[] = trim($buf);
            $buf = '';
            continue;
        }
        $buf .= $c;
    }
    if (trim($buf) !== '') {
        $parts[] = trim($buf);
    }

    return $parts;
}

function turso_normalize_index_columns(string $cols): string
{
    $cols = preg_replace('/`\s*/', '', $cols) ?? $cols;
    while (preg_match('/(\w+)\s*\(\s*\d+\s*\)/', $cols)) {
        $cols = preg_replace('/(\w+)\s*\(\s*\d+\s*\)/', '$1', $cols) ?? $cols;
    }

    return trim($cols);
}

/** @return array{unique: bool, name: string, columns: string}|null */
function turso_parse_mysql_index_part(string $part): ?array
{
    if (!preg_match('/^(UNIQUE\s+KEY|UNIQUE\s+INDEX|KEY|INDEX)\s+(\w+)\s*\(/i', $part, $m)) {
        return null;
    }
    $start = strpos($part, '(');
    if ($start === false) {
        return null;
    }
    $depth = 0;
    $buf = '';
    $len = strlen($part);
    for ($i = $start; $i < $len; $i++) {
        $c = $part[$i];
        if ($c === '(') {
            if ($depth > 0) {
                $buf .= $c;
            }
            $depth++;
            continue;
        }
        if ($c === ')') {
            $depth--;
            if ($depth === 0) {
                break;
            }
            $buf .= $c;
            continue;
        }
        if ($depth > 0) {
            $buf .= $c;
        }
    }

    return [
        'unique' => stripos($m[1], 'UNIQUE') !== false,
        'name' => $m[2],
        'columns' => turso_normalize_index_columns($buf),
    ];
}

function turso_convert_foreign_key_clause(string $part, string $table): ?string
{
    if (preg_match('/CONSTRAINT\s+\w+\s+(FOREIGN\s+KEY\s*\([^)]+\)\s*REFERENCES\s+\w+\s*\([^)]+\).*)/is', $part, $cm)) {
        $part = $cm[1];
    }
    if (!preg_match('/FOREIGN\s+KEY\s*\(([^)]+)\)\s*REFERENCES\s+(\w+)\s*\(([^)]+)\)(.*)$/is', $part, $m)) {
        return null;
    }
    $local = turso_normalize_index_columns($m[1]);
    $refTable = $m[2];
    $refCols = turso_normalize_index_columns($m[3]);
    $tail = strtoupper($m[4]);
    $onDelete = '';
    if (str_contains($tail, 'ON DELETE CASCADE')) {
        $onDelete = ' ON DELETE CASCADE';
    } elseif (str_contains($tail, 'ON DELETE SET NULL')) {
        $onDelete = ' ON DELETE SET NULL';
    }
    $onUpdate = '';
    if (str_contains($tail, 'ON UPDATE CASCADE')) {
        $onUpdate = ' ON UPDATE CASCADE';
    }

    return 'FOREIGN KEY (' . $local . ') REFERENCES ' . $refTable . ' (' . $refCols . ')' . $onDelete . $onUpdate;
}

function turso_convert_column_definition(string $part): string
{
    $s = $part;
    $s = preg_replace('/\s+CHARACTER\s+SET\s+\w+/i', '', $s) ?? $s;
    $s = preg_replace('/\s+COLLATE\s+\w+/i', '', $s) ?? $s;
    $s = preg_replace('/\s+CHECK\s*\((?:[^()]*|\([^()]*\))*\)/i', '', $s) ?? $s;
    $s = preg_replace("/COMMENT\s+'[^']*'/i", '', $s) ?? $s;
    $s = preg_replace('/\s+ON\s+UPDATE\s+CURRENT_TIMESTAMP(\(\))?/i', '', $s) ?? $s;
    $s = preg_replace('/\bCURRENT_TIMESTAMP\s*\(\s*\)/i', "___DTNOW___", $s) ?? $s;
    $s = preg_replace('/\bcurrent_timestamp\s*\(\s*\)/i', '___DTNOW___', $s) ?? $s;
    $s = preg_replace('/\bCURRENT_TIMESTAMP\b/i', '___DTNOW___', $s) ?? $s;
    $s = preg_replace('/\b(?:TINY|SMALL|MEDIUM|BIG)?INT\s*\(\s*\d+\s*\)/i', 'INTEGER', $s) ?? $s;
    $s = preg_replace('/\bBIGINT\s+UNSIGNED\b/i', 'INTEGER', $s) ?? $s;
    $s = preg_replace('/\bINT\s+UNSIGNED\b/i', 'INTEGER', $s) ?? $s;
    $s = preg_replace('/\b(SMALL|MEDIUM|TINY)INT\s+UNSIGNED\b/i', 'INTEGER', $s) ?? $s;
    $s = preg_replace('/\bBIGINT\b/i', 'INTEGER', $s) ?? $s;
    $s = preg_replace('/\bINT\b/i', 'INTEGER', $s) ?? $s;
    $s = preg_replace('/\bTINYINT\s*\(\s*1\s*\)/i', 'INTEGER', $s) ?? $s;
    $s = preg_replace('/\bTINYINT\b/i', 'INTEGER', $s) ?? $s;
    $s = preg_replace('/\bSMALLINT\b/i', 'INTEGER', $s) ?? $s;
    $s = preg_replace('/\bMEDIUMINT\b/i', 'INTEGER', $s) ?? $s;
    $s = preg_replace('/\bUNSIGNED\b/i', '', $s) ?? $s;
    $s = preg_replace('/\bLONGTEXT\b/i', 'TEXT', $s) ?? $s;
    $s = preg_replace('/\bMEDIUMTEXT\b/i', 'TEXT', $s) ?? $s;
    $s = preg_replace('/\bTINYTEXT\b/i', 'TEXT', $s) ?? $s;
    $s = preg_replace('/\bVARCHAR\s*\(\s*\d+\s*\)/i', 'TEXT', $s) ?? $s;
    $s = preg_replace('/\bCHAR\s*\(\s*\d+\s*\)/i', 'TEXT', $s) ?? $s;
    $s = preg_replace('/\bENUM\s*\([^)]+\)/i', 'TEXT', $s) ?? $s;
    $s = preg_replace('/\bSET\s*\([^)]+\)/i', 'TEXT', $s) ?? $s;
    $s = preg_replace('/\bJSON\b/i', 'TEXT', $s) ?? $s;
    $s = preg_replace('/\bDECIMAL\s*\(\s*\d+\s*,\s*\d+\s*\)/i', 'REAL', $s) ?? $s;
    $s = preg_replace('/\bDOUBLE\s*\(\s*\d+\s*,\s*\d+\s*\)/i', 'REAL', $s) ?? $s;
    $s = preg_replace('/\bFLOAT\b/i', 'REAL', $s) ?? $s;
    $s = preg_replace('/\bDATETIME\b/i', 'TEXT', $s) ?? $s;
    $s = preg_replace('/\bTIMESTAMP\b/i', 'TEXT', $s) ?? $s;
    $s = preg_replace('/\bDATE\b/i', 'TEXT', $s) ?? $s;
    $s = preg_replace('/(?<!da)TIME\b/i', 'TEXT', $s) ?? $s;
    $s = preg_replace('/\bBLOB\b/i', 'BLOB', $s) ?? $s;
    $s = preg_replace('/\bAUTO_INCREMENT\b/i', '', $s) ?? $s;
    $s = preg_replace('/\bAUTOINCREMENT\s*\(\s*\d+\s*\)/i', 'AUTOINCREMENT', $s) ?? $s;
    $s = preg_replace('/\bINTEGER\s*\(\s*\d+\s*\)/i', 'INTEGER', $s) ?? $s;
    $s = preg_replace('/\s{2,}/', ' ', $s) ?? $s;
    $s = str_replace('___DTNOW___', "(datetime('now'))", $s);

    return trim($s);
}

/**
 * Convert standalone ALTER from MySQL dump to SQLite statement(s).
 *
 * @return list<string>
 */
function turso_convert_mysql_alter(string $alter): array
{
    $s = trim(preg_replace('/`/', '', $alter) ?? $alter);
    $s = rtrim($s, ';');

    if (preg_match('/^ALTER\s+TABLE\s+(\w+)\s+ADD\s+(UNIQUE\s+)?INDEX\s+(?:IF\s+NOT\s+EXISTS\s+)?(\w+)\s*\(([^)]+)\)/i', $s, $m)) {
        $unique = trim($m[2]) !== '';
        $cols = turso_normalize_index_columns($m[4]);

        return [($unique ? 'CREATE UNIQUE INDEX IF NOT EXISTS ' : 'CREATE INDEX IF NOT EXISTS ')
            . $m[3] . ' ON ' . $m[1] . ' (' . $cols . ');'];
    }

    if (preg_match('/^ALTER\s+TABLE\s+(\w+)\s+ADD\s+(?:CONSTRAINT\s+\w+\s+)?FOREIGN\s+KEY/i', $s)) {
        return ['-- skip FK alter (define in CREATE): ' . $s];
    }

    if (preg_match('/^ALTER\s+TABLE\s+(\w+)\s+ADD\s+COLUMN\s+(?:IF\s+NOT\s+EXISTS\s+)?(.+)$/is', $s, $m)) {
        $table = $m[1];
        $colDef = turso_convert_column_definition($m[2]);
        $colDef = preg_replace('/\s+AFTER\s+\w+/i', '', $colDef) ?? $colDef;
        $colDef = preg_replace('/\s+FIRST\b/i', '', $colDef) ?? $colDef;
        $stmt = 'ALTER TABLE ' . $table . ' ADD COLUMN ' . $colDef . ';';
        if (!turso_pdo_sqlite_supports_add_column_if_not_exists()) {
            return [$stmt];
        }

        return ['ALTER TABLE ' . $table . ' ADD COLUMN IF NOT EXISTS ' . $colDef . ';'];
    }

    if (preg_match('/\bMODIFY\s+COLUMN\b/i', $s)) {
        return ['-- skip MODIFY (live CREATE is source): ' . $s];
    }

    return ['-- skip alter: ' . $s];
}

/** @return list<string> */
function turso_validate_sqlite_schema_sql(string $sql): array
{
    $issues = [];
    if (!turso_pdo_sqlite_supports_add_column_if_not_exists()
        && preg_match('/ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS/i', $sql)
    ) {
        $issues[] = 'ADD COLUMN IF NOT EXISTS present but PDO sqlite does not support it';
    }
    if (preg_match('/\bUNSIGNED\b/i', $sql)) {
        $issues[] = 'UNSIGNED still present';
    }
    if (preg_match('/\bVARCHAR\s*\(/i', $sql)) {
        $issues[] = 'VARCHAR( still present';
    }
    if (preg_match('/\sAFTER\s+\w+/i', $sql)) {
        $issues[] = 'AFTER clause still present';
    }
    if (preg_match('/\bAUTO_INCREMENT\b/i', $sql)) {
        $issues[] = 'AUTO_INCREMENT still present';
    }
    if (preg_match('/TEXT\s*\(\s*[\'"]now[\'"]\s*\)/i', $sql)) {
        $issues[] = 'broken datetime default (TEXT(\'now\'))';
    }

    return $issues;
}
