<?php

/*
 * ITFlow - Database update to version 3.1.3 (from 3.1.2)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // What a tag means - shown when a tag is pointed at, and in Admin > Tags
    mysqli_query($mysqli, "ALTER TABLE `tags` ADD COLUMN `tag_description` varchar(255) DEFAULT NULL AFTER `tag_name`");

    // Every colour as #rrggbb, so a pill's text can be picked to read on it -
    // short hex grows to six digits, Bootstrap's colour names become theirs,
    // and anything else (an empty colour, "dark") becomes grey
    $tag_named_colors = [
        'primary' => '#0d6efd', 'secondary' => '#6c757d', 'success' => '#198754', 'danger' => '#dc3545', 'warning' => '#ffc107',
        'info' => '#0dcaf0', 'light' => '#adb5bd', 'dark' => '#343a40', 'blue' => '#0d6efd', 'indigo' => '#6610f2', 'purple' => '#6f42c1',
        'pink' => '#d63384', 'red' => '#dc3545', 'orange' => '#fd7e14', 'yellow' => '#ffc107', 'green' => '#198754', 'teal' => '#20c997',
        'cyan' => '#0dcaf0', 'gray' => '#6c757d', 'grey' => '#6c757d', 'black' => '#212529', 'white' => '#adb5bd',
    ];
    $sql = mysqli_query($mysqli, "SELECT tag_id, tag_color, tag_icon FROM tags");
    while ($row = mysqli_fetch_assoc($sql)) {
        $color = strtolower(trim((string) $row['tag_color']));
        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $color, $m)) {
            $color = "#$m[1]$m[1]$m[2]$m[2]$m[3]$m[3]";
        } elseif (preg_match('/^#[0-9a-f]{8}$/', $color)) {
            $color = substr($color, 0, 7);
        } elseif (!preg_match('/^#[0-9a-f]{6}$/', $color)) {
            $color = $tag_named_colors[$color] ?? '#6c757d';
        }
        // An icon is the Font Awesome name alone - "fa-star" and "fas fa-star" are "star"
        $icon = preg_replace('/^(fa[srb]? +)?fa-/', '', strtolower(trim((string) $row['tag_icon'])));
        $icon = preg_replace('/[^a-z0-9-]/', '', $icon);
        $tag_id = intval($row['tag_id']);
        mysqli_query($mysqli, "UPDATE tags SET tag_color = '$color', tag_icon = " . ($icon === '' ? 'NULL' : "'$icon'") . " WHERE tag_id = $tag_id");
    }
