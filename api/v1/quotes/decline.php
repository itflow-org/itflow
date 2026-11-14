<?php
/*
 * API - Quotes - Decline
 * POST /api/v1/quotes/decline.php
 *
 * Record that the client declined a quote, as Decline does on its page - the
 * quote_decline custom action runs.
 *
 * Parameters (POST, JSON body): api_key, quote_id, client_id (the quote's client)
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$quote_answer = 'Declined';
require_once 'quote_answer.php';
