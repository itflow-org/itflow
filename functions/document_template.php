<?php

/*
 * ITFlow - document templates (Admin > Document Templates): documents ready
 * to start a client's from - a runbook, a build sheet, a plan - with their
 * images, kept in uploads/document_templates/<id>/. A document made from one
 * gets a copy of what it says and its images, and remembers which template it
 * came from (documents.document_template_id, from database 3.2.5 on), so each
 * template's use is counted.
 *
 * As a document is made it is filled in: placeholders from the client -
 * [Client], [Site], [Contact], [Date] and the rest (DOCUMENT_TEMPLATE_PLACEHOLDERS),
 * the site and contact the ones picked - tables of the client's records as
 * they are then - [Networks], [Servers], [Domains] (DOCUMENT_TEMPLATE_TABLES) -
 * and the answers to what it asks for: any other [word] is a fill-in, asked
 * as plain text unless set up on the template's page as a date, a choice from
 * a list or yes or no, required or with help (document_template_fill_ins,
 * from database 3.2.6 on). One left unanswered stays in the document.
 *
 * A template can say who should have a document from it - every client, or
 * the ones with a client tag - and how often the documents made from it are
 * reviewed (database 3.2.7). Its page shows which clients are missing one and
 * which documents are due a review; a client's Files and overview say the
 * same for that client. A document is reviewed when it is edited or marked as
 * reviewed (documents.document_reviewed_at), and can be linked to the template
 * it came from after the fact, so one written by hand counts too.
 *
 * Shared by admin/document_templates.php, admin/document_template.php,
 * admin/modals/document_template/*, admin/post/document_template.php and the
 * New document from a template window and its handler on the agent side
 * (agent/modals/document/document_add_from_template.php, agent/post/document.php).
 */

/** Where a template's images are kept, from the app's root - its folder under uploads/document_templates/. */
function documentTemplateDir($document_template_id) {
    return dirname(__DIR__) . '/uploads/document_templates/' . intval($document_template_id);
}

/** One template, archived or not, with who made it and last changed it - or null. */
function documentTemplateRow($document_template_id) {
    global $mysqli;
    $document_template_id = intval($document_template_id);
    if ($document_template_id <= 0) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_template_id, document_template_name, document_template_description, document_template_content,
            document_template_required, document_template_review_months, document_template_created_at, document_template_updated_at, document_template_archived_at, document_template_created_by, document_template_updated_by,
            creator.user_name AS created_by_name, updater.user_name AS updated_by_name
        FROM document_templates
        LEFT JOIN users creator ON creator.user_id = document_template_created_by AND document_template_created_by > 0
        LEFT JOIN users updater ON updater.user_id = document_template_updated_by AND document_template_updated_by > 0
        WHERE document_template_id = $document_template_id"));
    return $row ?: null;
}

/*
 * How the templates have been used - template id => count (documents made from
 * it that are not archived), clients (how many clients have one) and last (when
 * the latest was made). Ones not used aren't in it.
 */
function documentTemplateUsage(array $document_template_ids) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $document_template_ids)));
    $usage = [];
    if (!$ids) {
        return $usage;
    }
    $sql = mysqli_query($mysqli, "SELECT document_template_id, COUNT(*) AS n, COUNT(DISTINCT document_client_id) AS clients, MAX(document_created_at) AS last
        FROM documents WHERE document_template_id IN (" . implode(',', $ids) . ") AND document_archived_at IS NULL GROUP BY document_template_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $usage[intval($row['document_template_id'])] = ['count' => intval($row['n']), 'clients' => intval($row['clients']), 'last' => $row['last']];
    }
    return $usage;
}

/*
 * The template window's fields, checked - or null with $error saying why.
 * $post: name, description (one line on what it is for) and content (the
 * editor's HTML, kept as written - it is made safe where it is shown).
 */
function documentTemplateFields(array $post, &$error = '') {
    $error = '';
    $name = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($post['name'] ?? ''))));
    if ($name === '') {
        $error = 'A document template needs a name';
        return null;
    }
    if (mb_strlen($name) > 200) {
        $error = 'Keep the name to 200 characters';
        return null;
    }
    $description = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($post['description'] ?? ''))));
    if (mb_strlen($description) > 500) {
        $error = 'Keep the summary to 500 characters';
        return null;
    }
    return ['name' => $name, 'description' => $description, 'content' => (string) ($post['content'] ?? '')];
}

/*
 * Save a template's fields (documentTemplateFields()) - a new one with
 * $document_template_id 0. Pasted-in images are kept in its folder, and on a
 * change the ones it no longer shows are taken away. Its id.
 */
function documentTemplateSave($document_template_id, array $f) {
    global $mysqli, $session_user_id;
    $document_template_id = intval($document_template_id);
    $user_id = intval($session_user_id ?? 0);
    $set = "document_template_name = '" . escapeSql($f['name']) . "', document_template_description = '" . escapeSql($f['description']) . "'";
    $new = !$document_template_id;
    if ($new) {
        // Made first, so its images have a folder named for it
        mysqli_query($mysqli, "INSERT INTO document_templates SET $set, document_template_content = '', document_template_created_by = $user_id");
        $document_template_id = intval(mysqli_insert_id($mysqli));
    }
    $content = saveBase64Images($f['content'], dirname(__DIR__) . '/uploads/document_templates/', 'uploads/document_templates/', $document_template_id);
    cleanupUnusedImages($content, documentTemplateDir($document_template_id), '/uploads/document_templates/' . $document_template_id);
    // A new one hasn't been changed yet - its content going in isn't a change
    mysqli_query($mysqli, "UPDATE document_templates SET $set, document_template_content = '" . mysqli_real_escape_string($mysqli, $content) . "',
        " . ($new ? 'document_template_updated_at = NULL' : "document_template_updated_by = $user_id") . " WHERE document_template_id = $document_template_id");
    return $document_template_id;
}

/*
 * A template's HTML with its images pointed at another folder of copies - for a
 * copy of the template, or a document made from it. $to_web is the new
 * folder's web path ('/uploads/documents/12/').
 */
function documentTemplateCopyImages($document_template_id, $to_dir, $to_web, $html) {
    $document_template_id = intval($document_template_id);
    copyDirectory(documentTemplateDir($document_template_id), $to_dir);
    return str_replace("/uploads/document_templates/$document_template_id/", $to_web, (string) $html);
}

/*
 * A new template starting from this one - "Copy of", with copies of its images
 * and its fill-ins, and how often its documents are reviewed. A copy isn't
 * required of anyone until it is set up to be (its tags come along for that). Its id, or 0.
 */
function documentTemplateCopy($document_template_id) {
    global $mysqli, $session_user_id;
    $row = documentTemplateRow($document_template_id);
    if (!$row) {
        return 0;
    }
    $user_id = intval($session_user_id ?? 0);
    mysqli_query($mysqli, "INSERT INTO document_templates SET document_template_name = '" . escapeSql(mb_substr('Copy of ' . $row['document_template_name'], 0, 200)) . "',
        document_template_description = '" . escapeSql((string) $row['document_template_description']) . "', document_template_content = '',
        document_template_review_months = " . intval($row['document_template_review_months']) . ", document_template_created_by = $user_id");
    $new_id = intval(mysqli_insert_id($mysqli));
    mysqli_query($mysqli, "INSERT INTO document_template_required_tags (document_template_id, tag_id)
        SELECT $new_id, tag_id FROM document_template_required_tags WHERE document_template_id = " . intval($row['document_template_id']));
    $content = documentTemplateCopyImages($row['document_template_id'], documentTemplateDir($new_id), "/uploads/document_templates/$new_id/", $row['document_template_content']);
    mysqli_query($mysqli, "UPDATE document_templates SET document_template_content = '" . mysqli_real_escape_string($mysqli, $content) . "', document_template_updated_at = NULL WHERE document_template_id = $new_id");
    // And what it asks for
    mysqli_query($mysqli, "INSERT INTO document_template_fill_ins (document_fill_in_document_template_id, document_fill_in_name, document_fill_in_type, document_fill_in_options, document_fill_in_required, document_fill_in_help, document_fill_in_order)
        SELECT $new_id, document_fill_in_name, document_fill_in_type, document_fill_in_options, document_fill_in_required, document_fill_in_help, document_fill_in_order
        FROM document_template_fill_ins WHERE document_fill_in_document_template_id = " . intval($row['document_template_id']));
    return $new_id;
}

/* Delete a template and its folder of images. The documents made from it stay, no longer linked to it. */
function documentTemplateDelete($document_template_id) {
    global $mysqli;
    $document_template_id = intval($document_template_id);
    mysqli_query($mysqli, "DELETE FROM document_templates WHERE document_template_id = $document_template_id");
    mysqli_query($mysqli, "UPDATE documents SET document_template_id = 0, document_updated_at = document_updated_at WHERE document_template_id = $document_template_id");
    removeDirectory(documentTemplateDir($document_template_id));
}

/*
 * Make a client's document from a template - what it says and its images,
 * copied, in $folder_id, called $name (the template's when '') - remembering
 * the template. Filled in for the client (documentTemplateFill()): its
 * placeholders - the site and contact the ones picked ($location_id,
 * $contact_id), or the client's primary ones - its tables of records, and
 * the answers to its fill-ins ($answers, from ticketFillInAnswers()); the
 * name and summary too. The new document's id, or 0 when the template is gone
 * or archived.
 */
function documentTemplateMakeDocument($document_template_id, $client_id, $name = '', $description = '', $folder_id = 0, $location_id = 0, $contact_id = 0, array $answers = []) {
    global $mysqli, $session_user_id, $session_name;
    $row = documentTemplateRow($document_template_id);
    if (!$row || $row['document_template_archived_at']) {
        return 0;
    }
    $template_id = intval($row['document_template_id']);
    $client_id = intval($client_id);
    $values = documentTemplateValues($client_id, $location_id, $contact_id, stripslashes((string) ($session_name ?? '')));
    foreach ($answers as $answer) {
        $text = ticketFillInText($answer);
        if ($text !== '') {
            $values[strtolower($answer['name'])] = $values[strtolower($answer['name'])] ?? $text;
        }
    }
    $name = mb_substr(ticketPlaceholderFill(trim((string) $name) !== '' ? trim((string) $name) : (string) $row['document_template_name'], $values), 0, 200);
    $description = ticketPlaceholderFill(trim((string) $description) !== '' ? trim((string) $description) : (string) $row['document_template_description'], $values);
    mysqli_query($mysqli, "INSERT INTO documents SET document_name = '" . escapeSql($name) . "', document_description = '" . escapeSql($description) . "',
        document_content = '', document_content_raw = '', document_folder_id = " . intval($folder_id) . ", document_created_by = " . intval($session_user_id ?? 0) . ",
        document_client_id = $client_id, document_template_id = $template_id");
    $document_id = intval(mysqli_insert_id($mysqli));

    // The template's images come along, and its HTML is pointed at the copies - then it is filled in
    $html = documentTemplateCopyImages($template_id, dirname(__DIR__) . "/uploads/documents/$document_id", "/uploads/documents/$document_id/", $row['document_template_content']);
    $html = documentTemplateFill($html, $client_id, $values);
    mysqli_query($mysqli, "UPDATE documents SET document_content = '" . mysqli_real_escape_string($mysqli, $html) . "',
        document_content_raw = '" . escapeSql(documentContentRaw($name, $html)) . "' WHERE document_id = $document_id");
    return $document_id;
}

/* What a template says, as a line of plain text - for the lists. */
function documentTemplatePreview($html, $length = 160) {
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', (string) $html)), ENT_QUOTES, 'UTF-8')));
    return mb_strimwidth($text, 0, $length, '...');
}

/* The words in square brackets in a template's text - [Client], [Date of visit] - each once, as written first. */
function documentTemplateBracketsIn($html) {
    $text = html_entity_decode(strip_tags(str_replace('<', ' <', (string) $html)), ENT_QUOTES, 'UTF-8');
    preg_match_all('/\[([A-Za-z][A-Za-z0-9 _\-\/\x{00A0}]{0,40})\]/u', $text, $matches);
    $found = [];
    foreach ($matches[1] as $word) {
        $word = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $word));
        if ($word !== '' && !isset($found[mb_strtolower($word)])) {
            $found[mb_strtolower($word)] = $word;
        }
    }
    return array_values($found);
}

/*
 * A template's HTML (already made safe) with its [words] marked, in its text
 * only - for its page: a placeholder grey, a table of records teal, a fill-in blue.
 */
function documentTemplateMarkBrackets($html) {
    $tones = [
        'placeholder' => 'text-bg-light border',
        'table' => 'text-info-emphasis bg-info-subtle border border-info-subtle',
        'fill_in' => 'text-primary-emphasis bg-primary-subtle border border-primary-subtle',
    ];
    $pieces = preg_split('/(<[^>]*>)/', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($pieces as $i => $piece) {
        if ($i % 2 === 0 && $piece !== '') {
            $pieces[$i] = preg_replace_callback('/\[([A-Za-z][A-Za-z0-9 _\-\/]{0,40})\]/', function ($match) use ($tones) {
                return '<span class="badge rounded-pill ' . $tones[documentTemplateWordKind($match[1])] . ' font-monospace fw-normal">[' . $match[1] . ']</span>';
            }, $piece);
        }
    }
    return implode('', $pieces);
}

/* ---------------------------------------------------------------- filled in as a document is made */

/** The placeholders filled in from the client as a document is made - what each becomes. */
const DOCUMENT_TEMPLATE_PLACEHOLDERS = [
    'Client' => "the client's name",
    'Website' => "the client's website",
    'Site' => 'the site picked - its name',
    'Address' => "the site's address, on one line",
    'Site Phone' => "the site's phone number",
    'Contact' => 'the contact picked - their name',
    'First Name' => "the contact's first name",
    'Contact Email' => "the contact's email",
    'Contact Phone' => "the contact's phone number",
    'Agent' => 'you, making it',
    'Date' => "today's date",
    'Month' => 'the month - October 2026',
    'Quarter' => 'the quarter - Q4 2026',
    'Year' => 'the year',
];

/** The placeholders that come from the site picked, and from the contact picked - lowercase. */
const DOCUMENT_TEMPLATE_SITE_PLACEHOLDERS = ['site', 'address', 'site phone'];
const DOCUMENT_TEMPLATE_CONTACT_PLACEHOLDERS = ['contact', 'first name', 'contact email', 'contact phone'];

/*
 * The client's records that go in as a table, as they are when the document is
 * made - what each holds. On a line of its own, [Networks] becomes the table;
 * in a sentence, their names.
 */
const DOCUMENT_TEMPLATE_TABLES = [
    'Contacts' => 'the contacts - name, title, email and phone',
    'Locations' => 'the sites - name, address, phone and hours',
    'Assets' => 'every asset - name, type, make and model, serial, site and who has it',
    'Servers' => 'servers and virtual machines - name, operating system, IP address and site',
    'Workstations' => 'desktops and laptops - name, make and model, serial and who has it',
    'Network Equipment' => 'firewalls, routers, switches and access points - name, make and model, IP address and site',
    'Printers' => 'printers - name, make and model, IP address and site',
    'Networks' => 'networks - name, VLAN, network, gateway, DHCP range and site',
    'Domains' => 'domains - name, registrar and when it expires',
    'Certificates' => 'certificates - name, domain, issuer and when it expires',
    'Licenses' => 'licenses - name, type, seats and when it expires',
    'Vendors' => 'vendors - name, phone, email, website and account number',
    'Services' => 'services - name, category and importance',
];

/*
 * A fill-in's name, tidied - as it goes in square brackets - or '' when it
 * can't be one: the placeholders and the tables of records are taken.
 */
function documentFillInName($name) {
    $name = trim(preg_replace('/\s+/', ' ', strip_tags((string) $name)), " []");
    $taken = array_change_key_case(DOCUMENT_TEMPLATE_PLACEHOLDERS + DOCUMENT_TEMPLATE_TABLES);
    if (!preg_match('/^' . TICKET_PLACEHOLDER_NAME . '$/', $name) || isset($taken[strtolower($name)])) {
        return '';
    }
    return $name;
}

/* What a [word] is in a document template - 'placeholder', 'table' or 'fill_in'. */
function documentTemplateWordKind($word) {
    $word = strtolower(preg_replace('/\s+/', ' ', trim((string) $word)));
    if (isset(array_change_key_case(DOCUMENT_TEMPLATE_PLACEHOLDERS)[$word])) {
        return 'placeholder';
    }
    return isset(array_change_key_case(DOCUMENT_TEMPLATE_TABLES)[$word]) ? 'table' : 'fill_in';
}

/*
 * The fill-ins set up on templates - [template id => [['id', 'name', 'type',
 * 'options' => [..], 'required' => bool, 'help'], ...]], in their order.
 */
function documentTemplateFillInRowsFor(array $document_template_ids) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $document_template_ids)));
    $rows = array_fill_keys($ids, []);
    if (!$ids) {
        return $rows;
    }
    $sql = mysqli_query($mysqli, "SELECT document_fill_in_id, document_fill_in_document_template_id, document_fill_in_name, document_fill_in_type, document_fill_in_options, document_fill_in_required, document_fill_in_help
        FROM document_template_fill_ins WHERE document_fill_in_document_template_id IN (" . implode(',', $ids) . ") ORDER BY document_fill_in_order ASC, document_fill_in_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['document_fill_in_document_template_id'])][] = [
            'id' => intval($row['document_fill_in_id']),
            'name' => (string) $row['document_fill_in_name'],
            'type' => isset(TICKET_FILL_IN_TYPES[$row['document_fill_in_type']]) ? $row['document_fill_in_type'] : 'text',
            'options' => ticketFillInOptions($row['document_fill_in_options']),
            'required' => (bool) intval($row['document_fill_in_required']),
            'help' => (string) $row['document_fill_in_help'],
        ];
    }
    return $rows;
}

/* The words in square brackets in a template's name, summary and what it says (documentTemplateBracketsIn()). */
function documentTemplateBracketsInAll(array $template) {
    return documentTemplateBracketsIn(escapeHtml($template['document_template_name'] ?? '') . "\n" . escapeHtml($template['document_template_description'] ?? '') . "\n" . ($template['document_template_content'] ?? ''));
}

/*
 * Everything a template asks for when a document is made, in order - the ones
 * set up (their rows), then the other bracketed words in its name, summary
 * and what it says that aren't placeholders or tables, as plain optional
 * text. Each ['id' (0 when not set up), 'name', 'type', 'options', 'required',
 * 'help', 'in_text'].
 */
function documentTemplateFillIns(array $template, array $rows) {
    $in_text = [];
    foreach (documentTemplateBracketsInAll($template) as $name) {
        if (documentTemplateWordKind($name) === 'fill_in') {
            $in_text[strtolower($name)] = $name;
        }
    }
    $fill_ins = [];
    foreach ($rows as $row) {
        $fill_ins[strtolower($row['name'])] = $row + ['in_text' => isset($in_text[strtolower($row['name'])])];
    }
    foreach ($in_text as $key => $name) {
        $fill_ins[$key] = $fill_ins[$key] ?? ['id' => 0, 'name' => $name, 'type' => 'text', 'options' => [], 'required' => false, 'help' => '', 'in_text' => true];
    }
    return array_values($fill_ins);
}

/** Everything one template asks for (documentTemplateFillIns()) - [] when it is gone. */
function documentTemplateFillInsFor($document_template_id) {
    $template = documentTemplateRow($document_template_id);
    if (!$template) {
        return [];
    }
    $id = intval($template['document_template_id']);
    return documentTemplateFillIns($template, documentTemplateFillInRowsFor([$id])[$id]);
}

/*
 * A fill-in renamed: its [Name] in the template's name, summary and what it
 * says goes with it. The new name holds nothing HTML could misread
 * (documentFillInName()).
 */
function documentTemplateRenameFillIn($document_template_id, $old, $new) {
    global $mysqli;
    $template = documentTemplateRow($document_template_id);
    if (!$template || $old === $new) {
        return;
    }
    $rename = [strtolower($old) => "[$new]"];
    mysqli_query($mysqli, "UPDATE document_templates SET
        document_template_name = '" . escapeSql(ticketPlaceholderFill($template['document_template_name'], $rename)) . "',
        document_template_description = '" . escapeSql(ticketPlaceholderFill($template['document_template_description'], $rename)) . "',
        document_template_content = '" . mysqli_real_escape_string($mysqli, ticketPlaceholderFill($template['document_template_content'], $rename)) . "'
        WHERE document_template_id = " . intval($template['document_template_id']));
}

/* The placeholders, tables and fill-ins a template uses, in its name, summary and what it says - lowercase name => name, by kind. */
function documentTemplateWordsUsed(array $template) {
    $used = ['placeholder' => [], 'table' => [], 'fill_in' => []];
    foreach (documentTemplateBracketsInAll($template) as $word) {
        $used[documentTemplateWordKind($word)][strtolower($word)] = $word;
    }
    return $used;
}

/** A client's current sites, the primary one first - [id => row]. */
function documentTemplateClientLocations($client_id) {
    global $mysqli;
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT location_id, location_name, location_address, location_city, location_state, location_zip, location_country, location_phone_country_code, location_phone, location_hours, location_primary
        FROM locations WHERE location_client_id = " . intval($client_id) . " AND location_archived_at IS NULL ORDER BY location_primary DESC, location_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['location_id'])] = $row;
    }
    return $rows;
}

/** A client's current contacts, the primary one first - [id => row]. */
function documentTemplateClientContacts($client_id) {
    global $mysqli;
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title, contact_email, contact_phone_country_code, contact_phone, contact_extension, contact_mobile_country_code, contact_mobile, contact_primary
        FROM contacts WHERE contact_client_id = " . intval($client_id) . " AND contact_archived_at IS NULL ORDER BY contact_primary DESC, contact_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['contact_id'])] = $row;
    }
    return $rows;
}

/*
 * What the placeholders become for a client - lowercase name => value, raw;
 * '' for what it doesn't have. The site and contact are the ones picked when
 * they are the client's, none for -1 (- None - picked), else its primary
 * ones. $agent_name: who is making it.
 */
function documentTemplateValues($client_id, $location_id, $contact_id, $agent_name) {
    global $mysqli;
    $client = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_name, client_website FROM clients WHERE client_id = " . intval($client_id)));
    if (!$client) {
        return [];
    }
    $locations = documentTemplateClientLocations($client_id);
    $location = intval($location_id) < 0 ? null : ($locations[intval($location_id)] ?? (reset($locations) ?: null));
    $contacts = documentTemplateClientContacts($client_id);
    $contact = intval($contact_id) < 0 ? null : ($contacts[intval($contact_id)] ?? (reset($contacts) ?: null));
    $month = intval(date('n'));
    $contact_name = trim((string) ($contact['contact_name'] ?? ''));
    return [
        'client' => (string) $client['client_name'],
        'website' => (string) $client['client_website'],
        'site' => (string) ($location['location_name'] ?? ''),
        'address' => $location ? formatAddress($location['location_address'], $location['location_city'], $location['location_state'], $location['location_zip'], $location['location_country'], ', ') : '',
        'site phone' => $location && trim((string) $location['location_phone']) !== '' ? (string) formatPhoneNumber($location['location_phone'], $location['location_phone_country_code']) : '',
        'contact' => $contact_name,
        'first name' => $contact_name !== '' ? preg_split('/\s+/u', $contact_name)[0] : '',
        'contact email' => (string) ($contact['contact_email'] ?? ''),
        'contact phone' => $contact && trim((string) $contact['contact_phone']) !== '' ? (string) formatPhoneNumber($contact['contact_phone'], $contact['contact_phone_country_code'])
            : ($contact && trim((string) $contact['contact_mobile']) !== '' ? (string) formatPhoneNumber($contact['contact_mobile'], $contact['contact_mobile_country_code']) : ''),
        'agent' => (string) $agent_name,
        'date' => date('M j, Y'),
        'month' => date('F Y'),
        'quarter' => 'Q' . intval(ceil($month / 3)) . ' ' . date('Y'),
        'year' => date('Y'),
    ];
}

/*
 * One of DOCUMENT_TEMPLATE_TABLES for a client, as it is now - ['head' =>
 * [column labels], 'rows' => [[cell text, ...], ...], 'names' => [the
 * records' names]]. Raw text. Null for no such table.
 */
function documentTemplateTableData($table, $client_id) {
    global $mysqli;
    $client_id = intval($client_id);
    $date = function ($value) {
        return $value && strtotime($value) ? date('M j, Y', strtotime($value)) : '';
    };
    $phone = function ($number, $code) {
        return trim((string) $number) !== '' ? (string) formatPhoneNumber($number, $code) : '';
    };
    $assets = function (array $types, array $columns) use ($mysqli, $client_id) {
        $where = $types ? " AND asset_type IN ('" . implode("','", array_map('escapeSql', $types)) . "')" : '';
        $sql = mysqli_query($mysqli, "SELECT asset_name, asset_type, asset_make, asset_model, asset_serial, asset_os, location_name, contact_name,
                (SELECT interface_ip FROM asset_interfaces WHERE interface_asset_id = asset_id AND interface_archived_at IS NULL ORDER BY interface_primary DESC, interface_id ASC LIMIT 1) AS ip
            FROM assets
            LEFT JOIN locations ON location_id = asset_location_id AND asset_location_id > 0
            LEFT JOIN contacts ON contact_id = asset_contact_id AND asset_contact_id > 0
            WHERE asset_client_id = $client_id AND asset_archived_at IS NULL$where ORDER BY asset_name ASC");
        $labels = ['name' => 'Name', 'type' => 'Type', 'model' => 'Make and model', 'serial' => 'Serial', 'os' => 'Operating system', 'ip' => 'IP address', 'site' => 'Site', 'who' => 'Assigned to'];
        $data = ['head' => array_map(function ($column) use ($labels) {
            return $labels[$column];
        }, $columns), 'rows' => [], 'names' => []];
        while ($row = mysqli_fetch_assoc($sql)) {
            $cells = ['name' => $row['asset_name'], 'type' => $row['asset_type'], 'model' => trim($row['asset_make'] . ' ' . $row['asset_model']), 'serial' => $row['asset_serial'],
                'os' => $row['asset_os'], 'ip' => $row['ip'], 'site' => $row['location_name'], 'who' => $row['contact_name']];
            $data['rows'][] = array_map(function ($column) use ($cells) {
                return (string) ($cells[$column] ?? '');
            }, $columns);
            $data['names'][] = (string) $row['asset_name'];
        }
        return $data;
    };
    $rows = function ($sql, array $head, callable $cells, $name_key) use ($mysqli) {
        $data = ['head' => $head, 'rows' => [], 'names' => []];
        $result = mysqli_query($mysqli, $sql);
        while ($row = mysqli_fetch_assoc($result)) {
            $data['rows'][] = array_map('strval', $cells($row));
            $data['names'][] = (string) $row[$name_key];
        }
        return $data;
    };

    switch ($table) {
        case 'contacts':
            return $rows("SELECT contact_name, contact_title, contact_email, contact_phone, contact_phone_country_code, contact_mobile, contact_mobile_country_code, contact_primary FROM contacts
                WHERE contact_client_id = $client_id AND contact_archived_at IS NULL ORDER BY contact_primary DESC, contact_name ASC", ['Name', 'Title', 'Email', 'Phone', 'Mobile'], function ($r) use ($phone) {
                return [$r['contact_name'] . (intval($r['contact_primary']) ? ' (primary)' : ''), $r['contact_title'], $r['contact_email'], $phone($r['contact_phone'], $r['contact_phone_country_code']), $phone($r['contact_mobile'], $r['contact_mobile_country_code'])];
            }, 'contact_name');
        case 'locations':
            return $rows("SELECT location_name, location_address, location_city, location_state, location_zip, location_country, location_phone, location_phone_country_code, location_hours, location_primary FROM locations
                WHERE location_client_id = $client_id AND location_archived_at IS NULL ORDER BY location_primary DESC, location_name ASC", ['Site', 'Address', 'Phone', 'Hours'], function ($r) use ($phone) {
                return [$r['location_name'] . (intval($r['location_primary']) ? ' (primary)' : ''), formatAddress($r['location_address'], $r['location_city'], $r['location_state'], $r['location_zip'], $r['location_country'], ', '),
                    $phone($r['location_phone'], $r['location_phone_country_code']), $r['location_hours']];
            }, 'location_name');
        case 'assets':
            return $assets([], ['name', 'type', 'model', 'serial', 'site', 'who']);
        case 'servers':
            return $assets(['Server', 'Virtual Machine'], ['name', 'type', 'os', 'ip', 'site']);
        case 'workstations':
            return $assets(['Desktop', 'Laptop'], ['name', 'type', 'model', 'serial', 'who']);
        case 'network equipment':
            return $assets(['Firewall/Router', 'Switch', 'Access Point'], ['name', 'type', 'model', 'ip', 'site']);
        case 'printers':
            return $assets(['Printer'], ['name', 'model', 'ip', 'site']);
        case 'networks':
            return $rows("SELECT network_name, network_vlan, network, network_gateway, network_dhcp_range, location_name FROM networks
                LEFT JOIN locations ON location_id = network_location_id AND network_location_id > 0
                WHERE network_client_id = $client_id AND network_archived_at IS NULL ORDER BY network_name ASC", ['Name', 'VLAN', 'Network', 'Gateway', 'DHCP range', 'Site'], function ($r) {
                return [$r['network_name'], $r['network_vlan'], $r['network'], $r['network_gateway'], $r['network_dhcp_range'], $r['location_name']];
            }, 'network_name');
        case 'domains':
            return $rows("SELECT domain_name, vendor_name, domain_expire FROM domains
                LEFT JOIN vendors ON vendor_id = domain_registrar AND domain_registrar > 0
                WHERE domain_client_id = $client_id AND domain_archived_at IS NULL ORDER BY domain_name ASC", ['Domain', 'Registrar', 'Expires'], function ($r) use ($date) {
                return [$r['domain_name'], $r['vendor_name'], $date($r['domain_expire'])];
            }, 'domain_name');
        case 'certificates':
            return $rows("SELECT certificate_name, certificate_domain, certificate_issued_by, certificate_expire FROM certificates
                WHERE certificate_client_id = $client_id AND certificate_archived_at IS NULL ORDER BY certificate_name ASC", ['Name', 'Domain', 'Issued by', 'Expires'], function ($r) use ($date) {
                return [$r['certificate_name'], $r['certificate_domain'], $r['certificate_issued_by'], $date($r['certificate_expire'])];
            }, 'certificate_name');
        case 'licenses':
            return $rows("SELECT software_name, software_type, software_license_type, software_seats, software_expire FROM software
                WHERE software_client_id = $client_id AND software_archived_at IS NULL ORDER BY software_name ASC", ['Name', 'Type', 'License', 'Seats', 'Expires'], function ($r) use ($date) {
                return [$r['software_name'], $r['software_type'], $r['software_license_type'], $r['software_seats'], $date($r['software_expire'])];
            }, 'software_name');
        case 'vendors':
            return $rows("SELECT vendor_name, vendor_phone, vendor_phone_country_code, vendor_email, vendor_website, vendor_account_number FROM vendors
                WHERE vendor_client_id = $client_id AND vendor_archived_at IS NULL ORDER BY vendor_name ASC", ['Name', 'Phone', 'Email', 'Website', 'Account number'], function ($r) use ($phone) {
                return [$r['vendor_name'], $phone($r['vendor_phone'], $r['vendor_phone_country_code']), $r['vendor_email'], $r['vendor_website'], $r['vendor_account_number']];
            }, 'vendor_name');
        case 'services':
            return $rows("SELECT service_name, service_category, service_importance FROM services
                WHERE service_client_id = $client_id AND service_archived_at IS NULL ORDER BY service_name ASC", ['Name', 'Category', 'Importance'], function ($r) {
                return [$r['service_name'], $r['service_category'], $r['service_importance']];
            }, 'service_name');
    }
    return null;
}

/* A table of records (documentTemplateTableData()) as HTML - with one empty row to fill in when there are none. */
function documentTemplateTableHtml(array $data) {
    $html = '<table style="width:100%" border="1"><tbody><tr>';
    foreach ($data['head'] as $label) {
        $html .= '<th>' . escapeHtml($label) . '</th>';
    }
    $html .= '</tr>';
    foreach ($data['rows'] ?: [array_fill(0, count($data['head']), '')] as $cells) {
        $html .= '<tr>';
        foreach ($cells as $cell) {
            $html .= '<td>' . escapeHtml($cell) . '</td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table>';
}

/*
 * What a template says, filled in for a client: its tables of records - a
 * [Networks] on a line of its own becomes the table, one in a sentence their
 * names - then its placeholders and the fill-ins' answers ($values, lowercase
 * name => value). A [word] with nothing to put in stays as it is.
 */
function documentTemplateFill($html, $client_id, array $values) {
    $html = (string) $html;
    $space = '(?:\s|&nbsp;|\x{00A0})';
    foreach (array_keys(DOCUMENT_TEMPLATE_TABLES) as $table) {
        $words = implode($space . '+', array_map(function ($word) {
            return preg_quote($word, '/');
        }, explode(' ', $table)));
        if (!preg_match('/\[' . $words . '\]/iu', $html)) {
            continue;
        }
        $data = documentTemplateTableData(strtolower($table), $client_id);
        // On a line of its own - a paragraph or heading holding nothing else - the table
        $html = preg_replace_callback('/<(p|div|h[1-6])\b[^>]*>' . $space . '*\[' . $words . '\]' . $space . '*<\/\1>/iu', function () use ($data) {
            return documentTemplateTableHtml($data);
        }, $html);
        // In a sentence, the names
        $html = preg_replace_callback('/\[' . $words . '\]/iu', function () use ($data) {
            return escapeHtml($data['names'] ? implode(', ', $data['names']) : 'none');
        }, $html);
    }
    return ticketPlaceholderFill($html, $values, true);
}

/* ---------------------------------------------------------------- who should have one, and reviews */

/** Who should have a document from a template (document_template_required). */
const DOCUMENT_TEMPLATE_REQUIRED_NONE = 0;
const DOCUMENT_TEMPLATE_REQUIRED_EVERY = 1;
const DOCUMENT_TEMPLATE_REQUIRED_TAGGED = 2;

/** How often the documents made from a template are reviewed - months => what it says; 0 never. */
const DOCUMENT_TEMPLATE_REVIEW_MONTHS = [0 => 'Never', 3 => 'Every 3 months', 6 => 'Every 6 months', 12 => 'Every year', 24 => 'Every 2 years'];

/** The client tags templates are required by - [template id => [tag id => tag name]], in name order. */
function documentTemplateRequiredTags(array $document_template_ids) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $document_template_ids)));
    $tags = array_fill_keys($ids, []);
    if (!$ids) {
        return $tags;
    }
    $sql = mysqli_query($mysqli, "SELECT document_template_id, tags.tag_id, tag_name FROM document_template_required_tags
        JOIN tags ON tags.tag_id = document_template_required_tags.tag_id
        WHERE document_template_id IN (" . implode(',', $ids) . ") ORDER BY tag_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $tags[intval($row['document_template_id'])][intval($row['tag_id'])] = (string) $row['tag_name'];
    }
    return $tags;
}

/*
 * Set who should have a document from a template - $required one of the
 * DOCUMENT_TEMPLATE_REQUIRED_ values, $tag_ids the client tags for TAGGED -
 * and how often its documents are reviewed. Not a change to what it says, so
 * when it last changed stays as it was.
 */
function documentTemplateSetRequired($document_template_id, $required, array $tag_ids, $review_months) {
    global $mysqli;
    $document_template_id = intval($document_template_id);
    $required = in_array(intval($required), [DOCUMENT_TEMPLATE_REQUIRED_EVERY, DOCUMENT_TEMPLATE_REQUIRED_TAGGED], true) ? intval($required) : DOCUMENT_TEMPLATE_REQUIRED_NONE;
    $review_months = isset(DOCUMENT_TEMPLATE_REVIEW_MONTHS[intval($review_months)]) ? intval($review_months) : 0;
    mysqli_query($mysqli, "UPDATE document_templates SET document_template_required = $required, document_template_review_months = $review_months,
        document_template_updated_at = document_template_updated_at WHERE document_template_id = $document_template_id");
    // Client tags only - any others are left out
    $tag_ids = array_values(array_unique(array_filter(array_map('intval', $tag_ids))));
    mysqli_query($mysqli, "DELETE FROM document_template_required_tags WHERE document_template_id = $document_template_id");
    if ($tag_ids) {
        mysqli_query($mysqli, "INSERT INTO document_template_required_tags (document_template_id, tag_id)
            SELECT $document_template_id, tag_id FROM tags WHERE tag_type = 1 AND tag_id IN (" . implode(',', $tag_ids) . ")");
    }
}

/*
 * The clients a template is required of, as SQL on clients: current clients,
 * not leads - every one, or the ones with any of its tags (none when it has
 * no tags left, or isn't required).
 */
function documentTemplateScopeSql($required, array $tag_ids) {
    $sql = "clients.client_archived_at IS NULL AND clients.client_lead = 0";
    if (intval($required) === DOCUMENT_TEMPLATE_REQUIRED_EVERY) {
        return $sql;
    }
    $tag_ids = array_values(array_filter(array_map('intval', $tag_ids)));
    if (intval($required) !== DOCUMENT_TEMPLATE_REQUIRED_TAGGED || !$tag_ids) {
        return '0';
    }
    return "$sql AND EXISTS (SELECT 1 FROM client_tags WHERE client_tags.client_id = clients.client_id AND client_tags.tag_id IN (" . implode(',', $tag_ids) . "))";
}

/** Who a template is required of, in words - '' when it isn't. */
function documentTemplateScopeText($required, array $tags) {
    if (intval($required) === DOCUMENT_TEMPLATE_REQUIRED_EVERY) {
        return 'Every client';
    }
    if (intval($required) !== DOCUMENT_TEMPLATE_REQUIRED_TAGGED) {
        return '';
    }
    return $tags ? 'Clients tagged ' . implode(' or ', $tags) : 'Clients with a tag - none picked';
}

/*
 * How far a required template reaches - ['clients' => how many it is
 * required of, 'covered' => how many of them have a current document from
 * it, 'missing' => [client id => name] the rest, by name].
 */
function documentTemplateCoverage($document_template_id, $required, array $tag_ids) {
    global $mysqli;
    $document_template_id = intval($document_template_id);
    $coverage = ['clients' => 0, 'covered' => 0, 'missing' => []];
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, EXISTS (SELECT 1 FROM documents WHERE document_client_id = clients.client_id
            AND document_template_id = $document_template_id AND document_archived_at IS NULL) AS covered
        FROM clients WHERE " . documentTemplateScopeSql($required, $tag_ids) . " ORDER BY client_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $coverage['clients']++;
        if (intval($row['covered'])) {
            $coverage['covered']++;
        } else {
            $coverage['missing'][intval($row['client_id'])] = (string) $row['client_name'];
        }
    }
    return $coverage;
}

/*
 * The required templates a client has no current document from - [template
 * id => name]. None for an archived client or a lead.
 */
function documentTemplatesMissingFor($client_id) {
    global $mysqli;
    $client_id = intval($client_id);
    $missing = [];
    $sql = mysqli_query($mysqli, "SELECT document_template_id, document_template_name FROM document_templates
        WHERE document_template_archived_at IS NULL AND document_template_required IN (" . DOCUMENT_TEMPLATE_REQUIRED_EVERY . ", " . DOCUMENT_TEMPLATE_REQUIRED_TAGGED . ")
        AND EXISTS (SELECT 1 FROM clients WHERE clients.client_id = $client_id AND clients.client_archived_at IS NULL AND clients.client_lead = 0)
        AND (document_template_required = " . DOCUMENT_TEMPLATE_REQUIRED_EVERY . " OR EXISTS (SELECT 1 FROM document_template_required_tags rt
            JOIN client_tags ON client_tags.tag_id = rt.tag_id AND client_tags.client_id = $client_id
            WHERE rt.document_template_id = document_templates.document_template_id))
        AND NOT EXISTS (SELECT 1 FROM documents WHERE document_client_id = $client_id AND documents.document_template_id = document_templates.document_template_id AND document_archived_at IS NULL)
        ORDER BY document_template_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $missing[intval($row['document_template_id'])] = (string) $row['document_template_name'];
    }
    return $missing;
}

/* When a document was last looked at - made, edited or marked as reviewed - as SQL on documents. */
function documentReviewedAtSql() {
    return "GREATEST(documents.document_created_at, COALESCE(documents.document_updated_at, documents.document_created_at), COALESCE(documents.document_reviewed_at, documents.document_created_at))";
}

/*
 * A document due a review, as SQL on documents joined to document_templates:
 * its template - a current one - says how often, and it has been longer than
 * that since it was last looked at.
 */
function documentReviewDueSql() {
    return "document_templates.document_template_archived_at IS NULL AND document_templates.document_template_review_months > 0
        AND " . documentReviewedAtSql() . " < NOW() - INTERVAL document_templates.document_template_review_months MONTH";
}

/*
 * The current documents due a review, the longest overdue first - for one
 * template, or one client ($where: SQL on documents, document_templates and
 * clients). Each with document_id, document_name, document_client_id,
 * client_name, document_template_id, document_template_name, reviewed_at and
 * document_template_review_months.
 */
function documentsReviewDue($where) {
    global $mysqli;
    $due = [];
    $sql = mysqli_query($mysqli, "SELECT documents.document_id, document_name, document_client_id, client_name, documents.document_template_id, document_template_name,
            " . documentReviewedAtSql() . " AS reviewed_at, document_template_review_months
        FROM documents
        JOIN document_templates ON document_templates.document_template_id = documents.document_template_id
        JOIN clients ON clients.client_id = documents.document_client_id AND clients.client_archived_at IS NULL
        WHERE documents.document_archived_at IS NULL AND " . documentReviewDueSql() . " AND ($where)
        ORDER BY reviewed_at ASC, documents.document_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $due[intval($row['document_id'])] = $row;
    }
    return $due;
}

/** How many current documents from each template are due a review - [template id => count]; ones with none aren't in it. */
function documentTemplateDueCounts(array $document_template_ids) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $document_template_ids)));
    $counts = [];
    if (!$ids) {
        return $counts;
    }
    $sql = mysqli_query($mysqli, "SELECT documents.document_template_id, COUNT(*) FROM documents
        JOIN document_templates ON document_templates.document_template_id = documents.document_template_id
        JOIN clients ON clients.client_id = documents.document_client_id AND clients.client_archived_at IS NULL
        WHERE documents.document_template_id IN (" . implode(',', $ids) . ") AND documents.document_archived_at IS NULL AND " . documentReviewDueSql() . "
        GROUP BY documents.document_template_id");
    while ($row = mysqli_fetch_row($sql)) {
        $counts[intval($row[0])] = intval($row[1]);
    }
    return $counts;
}

/*
 * Where a document stands for review, from its row (document_created_at,
 * document_updated_at, document_reviewed_at, and its template's
 * document_template_review_months and document_template_archived_at) -
 * ['last' => when it was last looked at, 'reviewed' => whether that was a
 * review rather than an edit, 'next' => when it is due, 'due' => bool], or
 * null when its template doesn't ask for reviews.
 */
function documentReviewState(array $row) {
    $months = intval($row['document_template_review_months'] ?? 0);
    if ($months <= 0 || !empty($row['document_template_archived_at'])) {
        return null;
    }
    $times = array_filter([$row['document_created_at'] ?? null, $row['document_updated_at'] ?? null, $row['document_reviewed_at'] ?? null]);
    $last = max(array_map('strtotime', $times));
    $next = strtotime("+$months months", $last);
    return [
        'last' => date('Y-m-d H:i:s', $last),
        'reviewed' => !empty($row['document_reviewed_at']) && strtotime($row['document_reviewed_at']) === $last,
        'next' => date('Y-m-d H:i:s', $next),
        'due' => $next < time(),
    ];
}

/*
 * Documents from a template for several clients at once, each filled in from
 * its own client (its primary site and contact), the answers to the fill-ins
 * the same for all ($answers, from ticketFillInAnswers()), at the top level of
 * its Files. With $skip_existing, a client with a current document from it is
 * passed over. ['made' => [client id => document id], 'skipped' => [client id
 * => name]] - only current clients.
 */
function documentTemplateMakeForClients($document_template_id, array $client_ids, array $answers, $skip_existing = true) {
    global $mysqli;
    $document_template_id = intval($document_template_id);
    $result = ['made' => [], 'skipped' => []];
    $client_ids = array_values(array_unique(array_filter(array_map('intval', $client_ids))));
    if (!$client_ids) {
        return $result;
    }
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, EXISTS (SELECT 1 FROM documents WHERE document_client_id = client_id
            AND document_template_id = $document_template_id AND document_archived_at IS NULL) AS has_one
        FROM clients WHERE client_archived_at IS NULL AND client_id IN (" . implode(',', $client_ids) . ") ORDER BY client_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $client_id = intval($row['client_id']);
        if ($skip_existing && intval($row['has_one'])) {
            $result['skipped'][$client_id] = (string) $row['client_name'];
            continue;
        }
        $document_id = documentTemplateMakeDocument($document_template_id, $client_id, '', '', 0, 0, 0, $answers);
        if (!$document_id) {
            break;
        }
        $result['made'][$client_id] = $document_id;
    }
    return $result;
}
