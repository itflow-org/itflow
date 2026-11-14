<?php
/*
 * API - Quotes - Accept
 * POST /api/v1/quotes/accept.php
 *
 * Record that the client accepted a quote, as Accept does on its page - the
 * quote_accept custom action runs. Then invoice it with quotes/invoice.php.
 *
 * Parameters (POST, JSON body): api_key, quote_id, client_id (the quote's client)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$quote_answer = 'Accepted';
require_once 'quote_answer.php';
