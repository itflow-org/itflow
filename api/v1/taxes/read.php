<?php

/*
 * API - Taxes - Read
 * GET /api/v1/taxes/read.php
 *
 * The tax rates, each with the parts it is made of.
 *
 * Parameters (GET):
 *   api_key    required - Your API key
 *   tax_id     optional - One rate (archived or not)
 *   archived   optional - 1 to include archived rates in the list
 *   limit      optional - Max rows to return (default 50)
 *   offset     optional - Offset for pagination (default 0)
 *
 * Each rate: tax_id, tax_name, tax_percent (the whole rate), tax_description,
 * tax_registration_number, tax_archived_at, tax_replaced_by (the rate a
 * changed rate was replaced by, else 0), tax_default (the default for new
 * lines) and tax_components - name, percent, compound (charged on top of the
 * parts above) and registration_number - one for a rate without parts.
 *
 * Rates aren't client-scoped; access is gated by module_sales in enforce_api_rbac.php.
 */

require_once '../validate_api_key.php';

require_once '../require_get_method.php';

if (isset($_GET['tax_id'])) {
    $id = intval($_GET['tax_id']);
    $sql = mysqli_query($mysqli, "SELECT tax_id FROM taxes WHERE tax_id = $id");
} else {
    $archived = intval($_GET['archived'] ?? 0) === 1 ? '' : 'WHERE tax_archived_at IS NULL';
    $sql = mysqli_query($mysqli, "SELECT tax_id FROM taxes $archived ORDER BY tax_name ASC, tax_id ASC LIMIT $limit OFFSET $offset");
}

if ($sql && mysqli_num_rows($sql) > 0) {
    $default_id = taxSettings()['default_id'];
    $rows = [];
    while ($id_row = mysqli_fetch_row($sql)) {
        $tax = taxRow($id_row[0]);
        $rows[] = [
            'tax_id' => intval($tax['tax_id']),
            'tax_name' => $tax['tax_name'],
            'tax_percent' => floatval($tax['tax_percent']),
            'tax_description' => $tax['tax_description'],
            'tax_registration_number' => $tax['tax_registration_number'],
            'tax_archived_at' => $tax['tax_archived_at'],
            'tax_replaced_by' => intval($tax['tax_replaced_by']),
            'tax_default' => intval($tax['tax_id']) === $default_id,
            'tax_components' => array_map(function ($part) use ($tax) {
                return ['name' => $part['name'], 'percent' => $part['percent'], 'compound' => $part['compound'],
                    'registration_number' => $part['registration'] !== '' ? $part['registration'] : (string) $tax['tax_registration_number']];
            }, $tax['components']),
        ];
    }
    echo json_encode(['success' => 'True', 'count' => count($rows), 'data' => $rows]);
    exit();
}

// Output
require_once "../read_output.php";
