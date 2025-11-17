<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// --- Configuration ---
$lm_studio_host = "http://localhost:1234"; // Your LM Studio Server URL
$lm_studio_endpoint = "/v1/models";
$lm_studio_url = $lm_studio_host . $lm_studio_endpoint;

// --- cURL Setup ---
$ch = curl_init($lm_studio_url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json'
));

// Execute the cURL request
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    // cURL connection error
    http_response_code(500);
    echo json_encode(['error' => 'cURL Error: ' . curl_error($ch)]);
} else if ($http_code >= 400) {
    // LM Studio API error
    http_response_code($http_code);
    echo json_encode(['error' => 'LM Studio API returned HTTP Error: ' . $http_code, 'response' => $response]);
} else {
    // Success: output the JSON response from LM Studio
    http_response_code(200);
    echo $response;
}

curl_close($ch);
?>