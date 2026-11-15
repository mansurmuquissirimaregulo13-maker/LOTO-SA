import { createHmac, timingSafeEqual } from "node:crypto";

export const config = {
  api: {
    bodyParser: false,
  },
};

async function getRawBody(readable) {
  const chunks = [];
  for await (const chunk of readable) {
    chunks.push(typeof chunk === "string" ? Buffer.from(chunk) : chunk);
  }
  return Buffer.concat(chunks);
}

export default async function handler(req, res) {
  if (req.method !== "POST") {
    return res.status(405).json({ error: "Method Not Allowed" });
  }

  let rawBuffer;
  try {
    rawBuffer = await getRawBody(req);
  } catch (err) {
    return res.status(400).json({ error: "Could not read body" });
  }

  const raw = rawBuffer.toString("utf8");
  const timestamp = req.headers["x-creapay-timestamp"] || "";
  const signature = req.headers["x-creapay-signature"] || "";
  const eventId = req.headers["x-creapay-event-id"] || "";
  const secret = process.env.CREAPAY_WEBHOOK_SECRET;

  if (!secret) {
    console.error("[CreaPay Webhook] CREAPAY_WEBHOOK_SECRET is not configured");
    return res.status(503).json({ error: "Webhook secret not configured on server" });
  }

  // Validação da tolerância de tempo (máximo 5 minutos)
  if (!/^\d+$/.test(timestamp) || Math.abs(Date.now() / 1000 - Number(timestamp)) > 300) {
    console.error("[CreaPay Webhook] Timestamp expired or invalid:", timestamp);
    return res.status(401).json({ error: "Invalid timestamp" });
  }

  // Validação da assinatura HMAC SHA256 em tempo constante
  const expected = createHmac("sha256", secret).update(timestamp + "." + raw).digest("hex");
  const a = Buffer.from(signature);
  const b = Buffer.from(expected);

  if (a.length !== b.length || !timingSafeEqual(a, b)) {
    console.error("[CreaPay Webhook] Invalid signature received");
    return res.status(401).json({ error: "Invalid signature" });
  }

  let event;
  try {
    event = JSON.parse(raw);
  } catch (e) {
    return res.status(400).json({ error: "Invalid JSON format" });
  }

  if (event.id !== eventId) {
    return res.status(400).json({ error: "Event ID mismatch" });
  }

  console.log(`[CreaPay Webhook] Success: event ${event.type} for order ${event.data?.order_id}`);

  // Retorna HTTP 200 OK para a CreaPay
  return res.status(200).json({ received: true });
}
