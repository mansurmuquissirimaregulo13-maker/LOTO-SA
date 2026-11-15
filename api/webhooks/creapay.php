<?php
/**
 * api/webhooks/creapay.php
 * Webhook oficial da CreaPay
 */

$raw = file_get_contents("php://input");
$headers = getallheaders();

$timestamp = $headers["x-creapay-timestamp"] ?? $headers["X-Creapay-Timestamp"] ?? "";
$signature = $headers["x-creapay-signature"] ?? $headers["X-Creapay-Signature"] ?? "";
$eventId   = $headers["x-creapay-event-id"]   ?? $headers["X-Creapay-Event-Id"]   ?? "";
$secret    = getenv("CREAPAY_WEBHOOK_SECRET");

if (!$secret) {
    http_response_code(503);
    echo json_encode(["error" => "CREAPAY_WEBHOOK_SECRET not set"]);
    exit;
}

if (!ctype_digit($timestamp) || abs(time() - (int)$timestamp) > 300) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid timestamp"]);
    exit;
}

$expected = hash_hmac("sha256", $timestamp . "." . $raw, $secret);
if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid signature"]);
    exit;
}

$event = json_decode($raw, true);
if (!$event || ($event["id"] ?? "") !== $eventId) {
    http_response_code(400);
    echo json_encode(["error" => "Event ID mismatch"]);
    exit;
}

http_response_code(200);
echo json_encode(["received" => true]);
