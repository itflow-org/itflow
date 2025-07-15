<?php

/*
 * ITFlow - GET/POST request handler for AI Functions
 */

// TODO: Should this be moved to AJAX?

if (isset($_GET['ai_reword'])) {

    header('Content-Type: application/json');

    $sql = mysqli_query($mysqli, "SELECT * FROM ai_models LEFT JOIN ai_providers ON ai_model_ai_provider_id = ai_provider_id WHERE ai_model_use_case = 'General' LIMIT 1");

    $row = mysqli_fetch_array($sql);
    $model_name = $row['ai_model_name'];
    $promptText = $row['ai_model_prompt'];
    $url = $row['ai_provider_api_url'];
    $key = $row['ai_provider_api_key'];

    // Collecting the input data from the AJAX request.
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE); // Convert JSON into array.

    $userText = $input['text'];

    // Preparing the data for the OpenAI Chat API request.
    $data = [
        "model" => "$model_name", // Specify the model
        "messages" => [
            ["role" => "system", "content" => $promptText],
            ["role" => "user", "content" => $userText],
        ],
        "temperature" => 0.7
    ];

    // Initialize cURL session to the OpenAI Chat API.
    $ch = curl_init("$url");

    // Set cURL options for the request.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $key,
    ]);

    // Execute the cURL session and capture the response.
    $response = curl_exec($ch);
    curl_close($ch);

    // Decode the JSON response.
    $responseData = json_decode($response, true);

    // Check if the response contains the expected data and return it.
    if (isset($responseData['choices'][0]['message']['content'])) {
        // Get the response content.
        $content = $responseData['choices'][0]['message']['content'];

        // Clean any leading "html" word or other unwanted text at the beginning.
        $content = preg_replace('/^html/i', '', $content);  // Remove any occurrence of 'html' at the start

        // Clean the response content to remove backticks or code block markers.
        $cleanedContent = str_replace('```', '', $content); // Remove backticks if they exist.

        // Trim any leading/trailing whitespace.
        $cleanedContent = trim($cleanedContent);

        // Return the cleaned response.
        echo json_encode(['rewordedText' => $cleanedContent]);
    } else {
        // Handle errors or unexpected response structure.
        echo json_encode(['rewordedText' => 'Failed to get a response from the AI API.']);
    }

}

if (isset($_GET['ai_ticket_summary'])) {

    $sql = mysqli_query($mysqli, "SELECT * FROM ai_models LEFT JOIN ai_providers ON ai_model_ai_provider_id = ai_provider_id WHERE ai_model_use_case = 'General' LIMIT 1");

    $row = mysqli_fetch_array($sql);
    $model_name = $row['ai_model_name'];
    $url = $row['ai_provider_api_url'];
    $key = $row['ai_provider_api_key'];

    // Retrieve the ticket_id from POST
    $ticket_id = intval($_POST['ticket_id']);

    // Query the database for ticket details
    // (You can reuse code from ticket.php or write a simplified query here)
    $sql = mysqli_query($mysqli, "
        SELECT ticket_subject, ticket_details
        FROM tickets
        WHERE ticket_id = $ticket_id
        LIMIT 1
    ");
    $row = mysqli_fetch_assoc($sql);
    $ticket_subject = $row['ticket_subject'];
    $ticket_details = strip_tags($row['ticket_details']); // strip HTML for cleaner prompt

    // Get ticket replies
    $sql_replies = mysqli_query($mysqli, "
        SELECT ticket_reply, ticket_reply_type
        FROM ticket_replies
        WHERE ticket_reply_ticket_id = $ticket_id
        AND ticket_reply_archived_at IS NULL
        ORDER BY ticket_reply_id ASC
    ");

    $all_replies_text = "";
    while ($reply = mysqli_fetch_assoc($sql_replies)) {
        $reply_type = $reply['ticket_reply_type'];
        $reply_text = strip_tags($reply['ticket_reply']);
        $all_replies_text .= "\n[$reply_type]: $reply_text";
    }

    // Craft a prompt for ChatGPT
    $prompt = "Based on the language detection (case not detected use default English), dont show \"Language Detected\", and Summarize using this language, the following ticket and its responses in a concise and clear way. The summary should be short, highlight the main issue, the actions taken, and any resolution steps:\n\nTicket Subject: $ticket_subject\nTicket Details: $ticket_details\nReplies:$all_replies_text\n\nShort Summary:";

    // Prepare the POST data
    $post_data = [
        "model" => "$model_name",
        "messages" => [
            ["role" => "system", "content" => "You are a helpful assistant."],
            ["role" => "user", "content" => $prompt]
        ],
        "temperature" => 0.7
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $key
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo "Error: " . curl_error($ch);
        exit;
    }
    curl_close($ch);

    $response_data = json_decode($response, true);
    $summary = $response_data['choices'][0]['message']['content'] ?? "No summary available.";

    // Print the summary
    echo nl2br(htmlentities($summary));
}

if (isset($_GET['ai_create_document_template'])) {
    // get_ai_document_template.php

    header('Content-Type: text/html; charset=UTF-8');

    $sql = mysqli_query($mysqli, "SELECT * FROM ai_models LEFT JOIN ai_providers ON ai_model_ai_provider_id = ai_provider_id WHERE ai_model_use_case = 'General' LIMIT 1");

    $row = mysqli_fetch_array($sql);
    $model_name = $row['ai_model_name'];
    $url = $row['ai_provider_api_url'];
    $key = $row['ai_provider_api_key'];

    $prompt = $_POST['prompt'] ?? '';

    // Basic validation
    if(empty($prompt)){
        echo "No prompt provided.";
        exit;
    }

    // Prepare prompt
    $system_message = "You are a helpful IT documentation assistant. You will create a well-structured HTML template for IT documentation based on a given prompt. Include headings, subheadings, bullet points, and possibly tables for clarity. No Lorem Ipsum, use realistic placeholders and professional language.";
    $user_message = "Create an HTML formatted IT documentation template based on the following request:\n\n\"$prompt\"\n\nThe template should be structured, professional, and useful for IT staff. Include relevant sections, instructions, prerequisites, and best practices.";

    $post_data = [
        "model" => "$model_name",
        "messages" => [
            ["role" => "system", "content" => $system_message],
            ["role" => "user", "content" => $user_message]
        ],
        "temperature" => 0.7
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $key
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo "Error: " . curl_error($ch);
        exit;
    }
    curl_close($ch);

    $response_data = json_decode($response, true);
    $template = $response_data['choices'][0]['message']['content'] ?? "<p>No content returned from AI.</p>";

    // Print the generated HTML template directly
    echo $template;
}
