<?php
/**
 * api/create-order-200.php
 * Copied from api/create-order.php – creates a CreaPay order and returns the checkout_token.
 *
 * CONFIGURATION:
 *   - Set CREAPAY_API_KEY as an environment variable on the server
 *     (Vercel → Settings → Environment Variables → CREAPAY_API_KEY).
 *   - Never expose the key in the frontend or public repo.
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// ─── CreaPay private key ────────────────────────────────────────────────────
$apiKey = getenv("CREAPAY_API_KEY");
if (!$apiKey) {
    http_response_code(503);
    echo json_encode(["error" => "CREAPAY_API_KEY not configured on server"]);
    exit;
}

// ─── Request body ──────────────────────────────────────────────────────
$body = json_decode(file_get_contents("php://input"), true);
if (!$body) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON body"]);
    exit;
}

// Basic sanitisation
$orderId  = preg_replace('/[^A-Za-z0-9\-_]/', '', $body["order_id"] ?? ("ITH-" . uniqid()));
$name     = substr(trim($body["customer"]["name"]  ?? $body["name"]  ?? "Citizen Beneficiary"), 0, 120);
$email    = filter_var($body["customer"]["email"] ?? $body["email"] ?? "", FILTER_SANITIZE_EMAIL);
$phone    = preg_replace('/[^0-9+]/', '', $body["customer"]["phone"] ?? $body["phone"] ?? "");

// Fixed amount for this endpoint – R 200,00 (20000 cents)
$amountMinor = 20000;   // R 200,00
$currency    = "ZAR";
$title       = "Taxa de Desbloqueio FICA — Ithuba Lottery";

// Optional tracking (UTMs, fbp, fbc) from frontend
$tracking = $body["tracking"] ?? $body["utms"] ?? [];

$payload = [
    "provider" => "paystack", // force Paystack for ZAR payments
    "order_id"     => $orderId,
    "title"        => $title,
    "amount_minor" => $amountMinor,
    "currency"     => $currency,
    "customer"     => [
        "name"  => $name,
        "email" => $email ?: ("citizen." . preg_replace('/[^0-9]/', '', $phone) . "@gmail.com"),
        "phone" => $phone
    ]
];

// Add tracking if present
$trackingClean = array_filter([
    "utm_source"   => $tracking["utm_source"]   ?? "",
    "utm_medium"   => $tracking["utm_medium"]   ?? "",
    "utm_campaign" => $tracking["utm_campaign"] ?? "",
    "utm_content"  => $tracking["utm_content"]  ?? "",
    "utm_term"     => $tracking["utm_term"]      ?? "",
    "fbclid"       => $tracking["fbclid"]        ?? "",
    "fbp"          => $tracking["fbp"]           ?? "",
    "fbc"          => $tracking["fbc"]           ?? "",
    "landing_page" => $tracking["landing_page"]  ?? ""
]);
if (!empty($trackingClean)) {
    $payload["tracking"] = $trackingClean;
}

// ─── Call CreaPay API ────────────────────────────────────────────────────
$ch = curl_init("https://pay.africagrabpoint.com/api/public/v1/orders");
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer " . $apiKey,
        "Content-Type: application/json",
        "Accept: application/json"
    ]
]);

$raw      = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    http_response_code(502);
    echo json_encode(["error" => "CreaPay unreachable: " . $curlErr]);
    exit;
}

$result = json_decode($raw, true);

if ($httpCode === 201 || $httpCode === 200) {
    http_response_code(200);
    echo json_encode($result);
} else {
    http_response_code($httpCode ?: 502);
    echo json_encode(["error" => "CreaPay error", "details" => $result]);
}
?>