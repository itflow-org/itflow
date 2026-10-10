<?php
/*
 * The client's tag pills, each opening the clients list filtered to that tag,
 * then a "+" pill that opens Add Tags for this client. Used by the client
 * header (inc_client_top_head.php) and the client overview. Expects
 * $client_tags (from inc_all_client.php) and $client_id.
 */
foreach ($client_tags as $client_tag) {
    $client_tag_id = intval($client_tag['tag_id']);
    $client_tag_name = escapeHtml($client_tag['tag_name']);
    $client_tag_color = preg_match('/^#[0-9a-f]{3,8}$/i', (string) $client_tag['tag_color']) ? $client_tag['tag_color'] : 'var(--bs-secondary)';
    $client_tag_icon = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $client_tag['tag_icon'])) ?: 'tag';
    ?>
    <a href="clients.php?tags[]=<?= $client_tag_id ?>" class="itflow-client-tag" style="--itflow-tag-color: <?= $client_tag_color ?>" title="Clients tagged <?= $client_tag_name ?>">
        <i class="fas fa-fw fa-<?= $client_tag_icon ?>" aria-hidden="true"></i><?= $client_tag_name ?>
    </a>
<?php }
if (lookupUserPermission("module_client") >= 2) { ?>
    <a href="#" class="itflow-client-tag itflow-client-tag-add ajax-modal" data-modal-url="modals/client/client_bulk_assign_tags.php?client_ids[]=<?= $client_id ?>" title="Add tags">
        <i class="fas fa-fw fa-plus" aria-hidden="true"></i><?= $client_tags ? '<span class="visually-hidden">Add tags</span>' : 'Tag' ?>
    </a>
<?php }
