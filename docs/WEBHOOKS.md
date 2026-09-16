# Webhooks

LinkForge can POST signed JSON payloads to your endpoint when events occur.

## Setup

1. Go to **Webhooks** in the sidebar.
2. Click **Add Webhook**.
3. Enter a name and an HTTPS endpoint URL.
4. Select the events you want to receive.
5. Copy the signing secret shown after creation.

## Events

| Event | When it fires |
|---|---|
| `link.created` | A new link is created |
| `link.updated` | A link's metadata changes |
| `link.deleted` | A link is deleted |
| `link.clicked` | Someone clicks a short link (throttled to 1 per link per 60s) |

## Payload format

All events POST a JSON body with this structure:

\`\`\`json
{
  "event": "link.created",
  "timestamp": 1789530808,
  "data": {
    "id": 42,
    "title": "Summer Sale",
    "short_code": "sale-2026",
    "short_url": "https://go.example.com/sale-2026",
    "destination_url": "https://example.com/summer",
    "created_at": "2026-09-16T09:23:28+00:00"
  }
}
\`\`\`

Fields inside `data` vary by event.

## Headers

| Header | Value |
|---|---|
| `Content-Type` | `application/json` |
| `X-LinkForge-Event` | The event name |
| `X-LinkForge-Delivery` | Unique delivery ID for debugging |
| `X-LinkForge-Signature` | `t=TIMESTAMP,v1=HMAC` |

## Verifying the signature

The signature is an HMAC-SHA256 of `timestamp + "." + raw_body`, keyed by
your webhook secret. Verify it to ensure the request really came from us.

### PHP

\`\`\`php
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_X_LINKFORGE_SIGNATURE'] ?? '';

// Parse "t=...,v1=..."
preg_match('/t=(\d+),v1=([a-f0-9]+)/', $sigHeader, $m);
$timestamp = $m[1] ?? '';
$signature = $m[2] ?? '';

$expected = hash_hmac('sha256', $timestamp . '.' . $payload, YOUR_WEBHOOK_SECRET);

if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    exit('Invalid signature');
}

// Reject old deliveries (>5 min drift)
if (abs(time() - (int)$timestamp) > 300) {
    http_response_code(401);
    exit('Stale delivery');
}

$data = json_decode($payload, true);
// Process $data['event'] and $data['data']
\`\`\`

### Node.js

\`\`\`javascript
const crypto = require('crypto');
const express = require('express');
const app = express();

app.post('/webhook', express.raw({ type: 'application/json' }), (req, res) => {
    const signature = req.headers['x-linkforge-signature'];
    const [tPart, vPart] = signature.split(',');
    const timestamp = tPart.split('=')[1];
    const expected = crypto
        .createHmac('sha256', process.env.WEBHOOK_SECRET)
        .update(timestamp + '.' + req.body.toString())
        .digest('hex');

    if (!crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(vPart.split('=')[1]))) {
        return res.status(401).send('Invalid signature');
    }

    const payload = JSON.parse(req.body);
    console.log('Event:', payload.event);
    res.json({ ok: true });
});
\`\`\`

### Python

\`\`\`python
import hmac, hashlib, time, json
from flask import Flask, request

app = Flask(__name__)

@app.route('/webhook', methods=['POST'])
def webhook():
    signature = request.headers.get('X-LinkForge-Signature', '')
    parts = dict(p.split('=', 1) for p in signature.split(','))
    timestamp = parts.get('t', '')
    sig = parts.get('v1', '')

    expected = hmac.new(
        WEBHOOK_SECRET.encode(),
        f"{timestamp}.{request.data.decode()}".encode(),
        hashlib.sha256
    ).hexdigest()

    if not hmac.compare_digest(expected, sig):
        return 'Invalid signature', 401

    payload = json.loads(request.data)
    print('Event:', payload['event'])
    return 'OK', 200
\`\`\`

## Retries

If your endpoint returns a non-2xx response, LinkForge retries:
- Attempt 1: immediate
- Attempt 2: 1 minute later
- Attempt 3: 5 minutes later

After 3 failures, the delivery is marked **failed** and no further retries occur.
You can manually retry failed deliveries from the Webhooks page.

## Timeouts

Requests time out after **5 seconds**. Respond fast — queue heavy work.

## Best practices

- **Always respond 2xx immediately**, then process async. Otherwise timeouts trigger retries.
- **Verify the signature** on every request. Don't trust the body without it.
- **Handle duplicate deliveries** — a network blip could cause a retry even if you already processed the event. Use the `X-LinkForge-Delivery` header as an idempotency key.
- **Use HTTPS.** Webhooks over HTTP expose your secret and payload to interception.
\`\`\`