<?php
/**
 * api/record-sale.php
 * Regista internamente a venda após confirmação de pagamento.
 * Este endpoint é chamado pelo frontend após CreaPay.pay() retornar "approved".
 * A entrega real deve ser autorizada pelo WEBHOOK CreaPay (x-creapay-signature).
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

$body = json_decode(file_get_contents("php://input"), true);
if (!$body) {
    http_response_code(400);
    echo json_encode(["ok" => false]);
    exit;
}

// Registo simples em ficheiro de log (substitua por base de dados em produção)
$logLine = date("Y-m-d H:i:s") . " | " .
    "order=" . ($body["order_id"] ?? "?") . " | " .
    "name="  . ($body["name"]     ?? "?") . " | " .
    "phone=" . ($body["phone"]    ?? "?") . " | " .
    "bank="  . ($body["bank"]     ?? "?") . " | " .
    "amount=". ($body["amount"]   ?? "?") . " ZAR | " .
    "utms="  . json_encode($body["utms"] ?? []) . PHP_EOL;

@file_put_contents(__DIR__ . "/sales.log", $logLine, FILE_APPEND | LOCK_EX);

http_response_code(200);
echo json_encode(["ok" => true]);
