<?php
// Set headers for CORS and Streaming
header("Content-Type: text/event-stream");
header("Cache-Control: no-cache");
header("Connection: keep-alive");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// CRITICAL FIX: Explicitly disable output buffering before any output is sent.
// This is the most crucial step for the connection_aborted() check to work.
while (ob_get_level()) {
    ob_end_clean();
}
// Do not ignore user aborts (i.e., allow the script to stop when the client closes the connection)
ignore_user_abort(false);

// --- Configuration ---
$lm_studio_host = "http://localhost:1234"; // Your LM Studio Server URL
$lm_studio_endpoint = "/v1/chat/completions";
$lm_studio_url = $lm_studio_host . $lm_studio_endpoint;

// Set default system message
$default_system_message = "You are a helpful and creative assistant.";

// --- Input Handling ---
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['model']) || !isset($data['user_message'])) {
    http_response_code(400);
    echo "Error: Missing required parameters (model or user_message)";
    exit;
}

$model = $data['model'];
$user_message = $data['user_message'];
$client_system_prompt = trim($data['system_prompt'] ?? '');

// --- Message Preparation ---
$messages = [];
$system_message_content = $client_system_prompt ?: $default_system_message;
$messages[] = ["role" => "system", "content" => $system_message_content];
$messages[] = ["role" => "user", "content" => $user_message];

// --- Request Body Construction ---
$request_body = json_encode([
    "model" => $model,
    "messages" => $messages,
    "temperature" => 0.7,
    "stream" => true, // Ensure streaming is enabled
]);

// --- cURL Setup for Streaming ---
$ch = curl_init($lm_studio_url);

curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $request_body);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'Content-Length: ' . strlen($request_body)
));

// Set a long timeout and disable signals. The stop logic relies on the write function.
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 5000); 
curl_setopt($ch, CURLOPT_TIMEOUT_MS, 0); 
curl_setopt($ch, CURLOPT_NOSIGNAL, 1); 

// Enable streaming output directly
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
    // CRITICAL STOP LOGIC: Check if the client connection is still active
    if (connection_aborted()) {
        error_log("Client disconnected (STOP button pressed). Terminating LLM generation job.");
        // Returning 0 signals cURL to stop the transfer and results in CURLE_WRITE_ERROR (23).
        return 0; 
    }
    
    // Forward the data chunk to the client
    echo $data;
    
    // Flush the output buffer to send the data immediately
    flush(); 
    
    return strlen($data);
});

// Execute the cURL request
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error_no = curl_errno($ch);

if ($curl_error_no !== 0) {
    $error_msg = curl_error($ch);
    
    // If error is NOT CURLE_WRITE_ERROR (23 - which is our intentional stop), then report it.
    if ($curl_error_no !== 23) {
        http_response_code(500);
        echo "data: {\"error\":\"cURL Error: " . addslashes($error_msg) . "\", \"code\": " . $curl_error_no . "}\n\n";
    }
} else if ($http_code >= 400) {
    // If LM Studio returned an HTTP error
    http_response_code($http_code);
    echo "data: {\"error\":\"LM Studio API returned HTTP Error: " . $http_code . "\", \"response\":\"" . addslashes($response) . "\"}\n\n";
}

curl_close($ch);

// Ensure all data is sent before the script truly exits.
flush();
?>