<?php

/*
 * The collation of ITFlow's tables against db.sql - for Admin > Debug, which
 * reports where they differ (healthStructureDifferences()) and offers to put
 * them right (admin/post/debug.php).
 *
 * Every table in db.sql is utf8mb4_general_ci, with two columns utf8mb4_bin
 * on purpose (calendars.calendar_feed_key and user_calendar_feeds.feed_key,
 * so that two keys differing only by case are two keys). A table can end up
 * otherwise: made by a database update that left the collation to the server
 * (2.1.6 to 2.4.1 did, and 3.6.5 put those right), restored from a dump made
 * on another server, or changed by hand. Nothing shows until a query puts
 * text from two collations side by side - a UNION, a comparison across two
 * tables - and stops with "Illegal mix of collations".
 *
 * The repair is database update 3.6.5's, with db.sql saying what each column
 * should be: one ALTER a table, each column changed with MODIFY from its own
 * line of SHOW CREATE TABLE with only the collation replaced. Not CONVERT TO
 * CHARACTER SET, which takes every text column in the table - the binary ones
 * with the rest - and turns a utf8mb3 TEXT into a MEDIUMTEXT. Only tables and
 * columns db.sql has are looked at; anything else in the database is left.
 */

/** A text column's type as SHOW CREATE TABLE and db.sql write it, for a regex. An enum's values are quoted, with a quote inside one doubled. */
function databaseCollationTextType() {
    return "(?:var)?char\\(\\d+\\)|(?:tiny|medium|long)?text|(?:enum|set)\\('(?:[^']|'')*'(?:,'(?:[^']|'')*')*\\)";
}

/*
 * What db.sql gives each table - [table => ['collation' => its default,
 * 'columns' => [text column => collation]]]. [] when db.sql isn't there.
 */
function databaseCollationExpected() {
    static $expected = null;
    if ($expected !== null) {
        return $expected;
    }
    $expected = [];
    $file = dirname(__DIR__) . '/db.sql';
    if (!is_file($file)) {
        return $expected;
    }
    if (preg_match_all('/CREATE TABLE\s+`([^`]+)`\s*\((.*?)\n\)([^;]*);/s', (string) file_get_contents($file), $creates, PREG_SET_ORDER)) {
        foreach ($creates as $create) {
            if (!preg_match('/\bCOLLATE=(\w+)/', $create[3], $default)) {
                continue;
            }
            $columns = [];
            foreach (explode("\n", $create[2]) as $line) {
                if (preg_match('/^\s*`([^`]+)`\s+(?:' . databaseCollationTextType() . ')(?:\s+CHARACTER SET\s+\w+)?(?:\s+COLLATE\s+(\w+))?/i', $line, $column)) {
                    $columns[$column[1]] = !empty($column[2]) ? $column[2] : $default[1];
                }
            }
            $expected[$create[1]] = ['collation' => $default[1], 'columns' => $columns];
        }
    }
    return $expected;
}

/*
 * Where the database's collations aren't db.sql's - [table => ['expected' =>
 * the table's default in db.sql, 'found' => its default here ('' when that
 * is right), 'columns' => [column => ['expected', 'found']]]], only the
 * tables with something different. null when db.sql isn't there or the
 * database can't be asked. A table or column the database doesn't have is
 * healthStructureDifferences()'s to report, not this.
 */
function databaseCollationDifferences() {
    global $mysqli;
    $expected = databaseCollationExpected();
    if (!$expected) {
        return null;
    }
    $tables = [];
    $columns = [];
    try {
        $result = mysqli_query($mysqli, "SELECT TABLE_NAME AS table_name, TABLE_COLLATION AS table_collation
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'");
        while ($result && $row = mysqli_fetch_assoc($result)) {
            $tables[$row['table_name']] = (string) $row['table_collation'];
        }
        $result = mysqli_query($mysqli, "SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name, COLLATION_NAME AS collation_name
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND COLLATION_NAME IS NOT NULL
            ORDER BY TABLE_NAME, ORDINAL_POSITION");
        while ($result && $row = mysqli_fetch_assoc($result)) {
            $columns[$row['table_name']][$row['column_name']] = (string) $row['collation_name'];
        }
    } catch (Throwable $e) {
        return null;
    }
    if (!$tables) {
        return null;
    }
    $differences = [];
    foreach ($expected as $table => $want) {
        if (!isset($tables[$table])) {
            continue;
        }
        $difference = ['expected' => $want['collation'], 'found' => $tables[$table] !== $want['collation'] ? $tables[$table] : '', 'columns' => []];
        foreach ($want['columns'] as $column => $collation) {
            $found = $columns[$table][$column] ?? null;
            if ($found !== null && $found !== $collation) {
                $difference['columns'][$column] = ['expected' => $collation, 'found' => $found];
            }
        }
        if ($difference['found'] !== '' || $difference['columns']) {
            $differences[$table] = $difference;
        }
    }
    return $differences;
}

/*
 * Give every table and text column in databaseCollationDifferences() the
 * collation db.sql has for it. Returns ['changed' => [table, ...], 'failed'
 * => [table => why]].
 *
 * A table's ALTER changes all of it or none of it, so one that fails is
 * exactly as it was and the rest go on. What stops one is a unique text
 * column holding two values the collation it has tells apart and the one it
 * should have doesn't - 'cafe' and 'café', or a space at the end.
 *
 * records.record_updated_at has a zero date for its default, and MySQL 8 as
 * it comes (strict, NO_ZERO_DATE) refuses any ALTER of a table with one. Zero
 * dates are allowed on this connection while the tables are changed and the
 * mode is put back after; strict mode itself stays on, so text that would
 * not fit is an error and never cut short.
 */
function databaseCollationRepair() {
    global $mysqli;
    $changed = [];
    $failed = [];
    $differences = databaseCollationDifferences();
    if (!$differences) {
        return ['changed' => $changed, 'failed' => $failed];
    }

    try {
        mysqli_query($mysqli, "SET @itflow_sql_mode = @@SESSION.sql_mode");
        mysqli_query($mysqli, "SET SESSION sql_mode = REPLACE(REPLACE(@@SESSION.sql_mode, 'NO_ZERO_IN_DATE', ''), 'NO_ZERO_DATE', '')");
    } catch (Throwable $e) {
        // Carry on with the mode as it is - records may then be one that fails
    }

    foreach ($differences as $table => $difference) {
        try {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[a-z0-9]+_[a-z0-9_]+$/', $difference['expected'])) {
                throw new RuntimeException("its name or its collation in db.sql was not understood");
            }
            $changes = [];

            if ($difference['columns']) {
                $result = mysqli_query($mysqli, "SHOW CREATE TABLE `$table`");
                $row = $result ? mysqli_fetch_row($result) : null;
                if (!$row || empty($row[1])) {
                    throw new RuntimeException("its definition could not be read");
                }
                foreach ($difference['columns'] as $column => $collation) {
                    // `name` type [CHARACTER SET x] [COLLATE y] and the rest - NOT NULL, DEFAULT,
                    // COMMENT - which goes back exactly as the server wrote it
                    if (!preg_match('/^[A-Za-z0-9_]+$/', $column) || !preg_match('/^[a-z0-9]+_[a-z0-9_]+$/', $collation['expected'])
                        || !preg_match('/^\s*[`"]?' . $column . '[`"]?\s+(' . databaseCollationTextType() . ')(?:\s+CHARACTER SET\s+\w+)?(?:\s+COLLATE\s+\w+)?(.*?),?\s*$/mi', $row[1], $match)) {
                        throw new RuntimeException("the definition of its column $column was not understood");
                    }
                    $changes[] = "MODIFY `$column` {$match[1]} CHARACTER SET " . strtok($collation['expected'], '_') . " COLLATE {$collation['expected']}{$match[2]}";
                }
            }

            if ($difference['found'] !== '') {
                $changes[] = "DEFAULT CHARACTER SET " . strtok($difference['expected'], '_') . " COLLATE {$difference['expected']}";
            }

            if (!mysqli_query($mysqli, "ALTER TABLE `$table` " . implode(', ', $changes))) {
                throw new RuntimeException(mysqli_error($mysqli));
            }
            $changed[] = $table;

        } catch (Throwable $e) {
            $failed[$table] = $e->getMessage();
        }
    }

    try {
        mysqli_query($mysqli, "SET SESSION sql_mode = IFNULL(@itflow_sql_mode, @@SESSION.sql_mode)");
    } catch (Throwable $e) {
        // The connection ends with the request
    }

    return ['changed' => $changed, 'failed' => $failed];
}
