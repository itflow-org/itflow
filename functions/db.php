<?php

// Prepared-statement DB wrapper layer.
// NOTE: NEW DB LAYER - adoption pending. Not yet called from app code;
// intended target for the gradual prepared-statement migration. Do not remove.
// Split from the former monolithic functions.php


/**
 * Simple mysqli helper functions
 * - Prepared statements under the hood
 * - "Old style" INSERT/UPDATE SET feeling
 */

/**
 * Core executor: prepares, binds, executes.
 *
 * @throws Exception on error
 */
function dbExecute(mysqli $mysqli, string $sql, array $params = []): mysqli_stmt
{
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception('MySQLi prepare error: ' . $mysqli->error . ' | SQL: ' . $sql);
    }

    if (!empty($params)) {
        $types  = '';
        $values = [];

        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } elseif (is_bool($param)) {
                $types .= 'i';
                $param  = $param ? 1 : 0;
            } elseif (is_null($param)) {
                $types .= 's';
                $param  = null;
            } else {
                $types .= 's';
            }
            $values[] = $param;
        }

        if (!$stmt->bind_param($types, ...$values)) {
            throw new Exception('MySQLi bind_param error: ' . $stmt->error . ' | SQL: ' . $sql);
        }
    }

    if (!$stmt->execute()) {
        throw new Exception('MySQLi execute error: ' . $stmt->error . ' | SQL: ' . $sql);
    }

    return $stmt;
}

/**
 * Fetch all rows as associative arrays.
 */
function dbFetchAll(mysqli $mysqli, string $sql, array $params = []): array
{
    $stmt   = dbExecute($mysqli, $sql, $params);
    $result = $stmt->get_result();
    if ($result === false) {
        return [];
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Fetch a single row (assoc) or null if none.
 */
function dbFetchOne(mysqli $mysqli, string $sql, array $params = []): ?array
{
    $stmt   = dbExecute($mysqli, $sql, $params);
    $result = $stmt->get_result();
    if ($result === false) {
        return null;
    }
    $row = $result->fetch_assoc();
    return $row !== null ? $row : null;
}

/**
 * Fetch a single scalar value (first column of first row) or null.
 */
function dbFetchValue(mysqli $mysqli, string $sql, array $params = [])
{
    $row = dbFetchOne($mysqli, $sql, $params);
    if ($row === null) {
        return null;
    }
    return reset($row);
}

/**
 * INSERT using "SET" style.
 * Example:
 *   $id = dbInsert($mysqli, 'clients', [
 *       'client_name' => $name,
 *       'client_type' => $type,
 *   ]);
 *
 * @return int insert_id
 *
 * @throws InvalidArgumentException
 * @throws Exception
 */
function dbInsert(mysqli $mysqli, string $table, array $data): int
{
    if (empty($data)) {
        throw new InvalidArgumentException('dbInsert called with empty $data');
    }

    $setParts = [];
    foreach ($data as $column => $_) {
        $setParts[] = "$column = ?";
    }

    $sql    = "INSERT INTO $table SET " . implode(', ', $setParts);
    $params = array_values($data);

    dbExecute($mysqli, $sql, $params);

    return $mysqli->insert_id;
}

function dbUpdate(
    mysqli $mysqli,
    string $table,
    array $data,
    $where,
    array $whereParams = []
): int {
    if (empty($data)) {
        throw new InvalidArgumentException('dbUpdate called with empty $data');
    }
    if (empty($where)) {
        throw new InvalidArgumentException('dbUpdate requires a WHERE clause');
    }

    $setParts = [];
    foreach ($data as $column => $_) {
        $setParts[] = "$column = ?";
    }

    if (is_array($where)) {
        $whereParts  = [];
        $whereParams = [];
        foreach ($where as $column => $value) {
            $whereParts[]  = "$column = ?";
            $whereParams[] = $value;
        }
        $whereSql = implode(' AND ', $whereParts);
    } else {
        $whereSql = $where;
    }

    $sql    = "UPDATE $table SET " . implode(', ', $setParts) . " WHERE $whereSql";
    $params = array_merge(array_values($data), $whereParams);

    $stmt = dbExecute($mysqli, $sql, $params);
    return $stmt->affected_rows;
}

/**
 * DELETE helper.
 *
 * WHERE can be:
 *   - array: ['client_id' => $id] (auto "client_id = ?")
 *   - string: 'client_id = ?' (use with $whereParams)
 *
 * @return int affected_rows
 *
 * @throws InvalidArgumentException
 * @throws Exception
 */
function dbDelete(
    mysqli $mysqli,
    string $table,
    $where,
    array $whereParams = []
): int {
    if (empty($where)) {
        throw new InvalidArgumentException('dbDelete requires a WHERE clause');
    }

    if (is_array($where)) {
        $whereParts  = [];
        $whereParams = [];
        foreach ($where as $column => $value) {
            $whereParts[]  = "$column = ?";
            $whereParams[] = $value;
        }
        $whereSql = implode(' AND ', $whereParts);
    } else {
        $whereSql = $where;
    }

    $sql  = "DELETE FROM $table WHERE $whereSql";
    $stmt = dbExecute($mysqli, $sql, $whereParams);
    return $stmt->affected_rows;
}

/**
 * Transaction helpers (optional sugar).
 */
function dbBegin(mysqli $mysqli): void
{
    $mysqli->begin_transaction();
}

function dbCommit(mysqli $mysqli): void
{
    $mysqli->commit();
}

function dbRollback(mysqli $mysqli): void
{
    $mysqli->rollback();
}
