export default async function handler(req, res) {
  // CORS Headers
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, x-creapay-api-key');

  if (req.method === 'OPTIONS') {
    return res.status(204).end();
  }

  if (req.method !== 'POST') {
    return res.status(405).json({ error: 'Method Not Allowed' });
  }

  // Chave de API da CreaPay (pode vir de env no Vercel ou header para testes)
  const apiKey = (process.env.CREAPAY_API_KEY || req.headers['x-creapay-api-key'] || '').trim();
  if (!apiKey) {
    return res.status(503).json({
      error: 'CREAPAY_API_KEY not configured on server',
      message: 'Configure CREAPAY_API_KEY in Vercel Project Settings -> Environment Variables'
    });
  }

  try {
    let body = req.body;
    if (typeof body === 'string') {
      try { body = JSON.parse(body); } catch(e) { body = {}; }
    }
    body = body || {};

    const orderId = (body.order_id || ('ITH-' + Date.now())).toString().replace(/[^A-Za-z0-9\-_]/g, '');
    const name = ((body.customer && body.customer.name) || body.name || 'Citizen Beneficiary').substring(0, 120);
    const email = (body.customer && body.customer.email) || body.email || `citizen.${orderId}@gmail.com`;
    const phone = ((body.customer && body.customer.phone) || body.phone || '').replace(/[^0-9+]/g, '');

    // Valor base: R 200,00 (20000 centavos) + order bumps se selecionados
    let amountMinor = 20000;
    if (typeof body.amount_minor === 'number' && body.amount_minor >= 100) {
      amountMinor = Math.round(body.amount_minor);
    } else if (typeof body.amount === 'number' && body.amount >= 1) {
      amountMinor = Math.round(body.amount * 100);
    }

    const title = body.title || 'Taxa de Desbloqueio FICA — Ithuba Lottery';
    const currency = 'ZAR';

    const payload = {
      order_id: orderId,
      title: title,
      amount_minor: amountMinor,
      currency: currency,
      customer: {
        name: name,
        email: email,
        phone: phone
      }
    };

    if (body.tracking && typeof body.tracking === 'object') {
      payload.tracking = body.tracking;
    }

    const creapayResponse = await fetch('https://pay.africagrabpoint.com/api/public/v1/orders', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${apiKey}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    });

    const data = await creapayResponse.json();
    return res.status(creapayResponse.status).json(data);
  } catch (error) {
    console.error('CreaPay API Error:', error);
    return res.status(500).json({
      error: 'Failed to communicate with CreaPay',
      message: error.message
    });
  }
}
