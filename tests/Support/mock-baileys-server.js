/**
 * Standalone Baileys Mock Server for JAWIRBLAST E2E Testing (ES Module)
 *
 * Provides HTTP REST endpoints on port 3000 and simulates automated WhatsApp
 * tick progression (Centang 1 -> 2 -> Biru) via webhooks to Laravel backend.
 *
 * Run with: node tests/Support/mock-baileys-server.js
 */

import http from 'node:http';
import https from 'node:https';
import crypto from 'node:crypto';
import { URL, fileURLToPath, pathToFileURL } from 'node:url';

const PORT = parseInt(process.env.PORT || '3000', 10);
const LARAVEL_WEBHOOK_URL = process.env.LARAVEL_WEBHOOK_URL || 'http://127.0.0.1:8000/api/wa/webhook';
const SERVICE_KEY = process.env.INTERNAL_SERVICE_KEY || process.env.BAILEYS_SERVICE_KEY || process.env.BAILEYS_INTERNAL_KEY || 'jawirblast_internal_secret_key';

export const serverState = {
  mode: 'AUTO_TICK', // 'AUTO_TICK', 'MANUAL', 'FAIL', 'NETWORK_ERR'
  connectionStatus: 'CONNECTED', // 'CONNECTED', 'DISCONNECTED', 'QR_READY'
  userJid: '628123456789@s.whatsapp.net',
  pairingCode: 'TEST-8888',
  qrCode: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY44YAAAAASUVORK5CYII=',
  history: [],
};

// Dispatch Webhook to Laravel Backend
export function sendWebhook(payload) {
  return new Promise((resolve) => {
    try {
      const parsedUrl = new URL(LARAVEL_WEBHOOK_URL);
      const postData = JSON.stringify(payload);
      const isHttps = parsedUrl.protocol === 'https:';
      const client = isHttps ? https : http;

      const options = {
        hostname: parsedUrl.hostname,
        port: parsedUrl.port || (isHttps ? 443 : 80),
        path: parsedUrl.pathname + parsedUrl.search,
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Content-Length': Buffer.byteLength(postData),
          'X-Service-Key': SERVICE_KEY,
        },
      };

      const req = client.request(options, (res) => {
        let responseBody = '';
        res.on('data', (chunk) => { responseBody += chunk; });
        res.on('end', () => {
          console.log(`[MockServer] Webhook sent [${payload.event} - ${payload.status || ''}] -> Status ${res.statusCode}`);
          resolve({ statusCode: res.statusCode, body: responseBody });
        });
      });

      req.on('error', (err) => {
        console.error(`[MockServer] Failed to send webhook:`, err.message);
        resolve({ error: err.message });
      });

      req.write(postData);
      req.end();
    } catch (e) {
      console.error(`[MockServer] Webhook URL error:`, e.message);
      resolve({ error: e.message });
    }
  });
}

// Automated Delivery Tick Progression: Centang 1 -> 2 -> Biru
export function scheduleTickCycle(waMessageId, phone) {
  const cleanPhone = (phone || '').replace(/[^0-9]/g, '');
  const remoteJid = `${cleanPhone}@s.whatsapp.net`;

  // Step 1: Centang 1 (SENT / 2) after 100ms
  setTimeout(() => {
    sendWebhook({
      event: 'message.update',
      wa_message_id: waMessageId,
      remote_jid: remoteJid,
      status: 'SENT',
      status_code: 2,
      timestamp: Date.now(),
    });
  }, 100);

  // Step 2: Centang 2 Abu-abu (DELIVERED / 3) after 600ms
  setTimeout(() => {
    sendWebhook({
      event: 'message.update',
      wa_message_id: waMessageId,
      remote_jid: remoteJid,
      status: 'DELIVERED',
      status_code: 3,
      timestamp: Date.now(),
    });
  }, 600);

  // Step 3: Centang 2 Biru (READ / 4) after 1500ms
  setTimeout(() => {
    sendWebhook({
      event: 'message.update',
      wa_message_id: waMessageId,
      remote_jid: remoteJid,
      status: 'READ',
      status_code: 4,
      timestamp: Date.now(),
    });
  }, 1500);
}

export const server = http.createServer((req, res) => {
  const parsedUrl = new URL(req.url, `http://${req.headers.host || 'localhost'}`);
  const method = req.method.toUpperCase();
  const pathname = parsedUrl.pathname;

  let body = '';
  req.on('data', chunk => { body += chunk; });
  req.on('end', () => {
    let json = {};
    if (body) {
      try { json = JSON.parse(body); } catch (_) {}
    }

    res.setHeader('Content-Type', 'application/json');

    // --- Control Endpoints (for testing control) ---
    if (pathname === '/mock/mode' && method === 'POST') {
      serverState.mode = json.mode || 'AUTO_TICK';
      res.writeHead(200);
      return res.end(JSON.stringify({ success: true, mode: serverState.mode }));
    }

    if (pathname === '/mock/status' && method === 'POST') {
      if (json.status !== undefined) serverState.connectionStatus = json.status;
      if (json.userJid !== undefined) serverState.userJid = json.userJid;
      res.writeHead(200);
      return res.end(JSON.stringify({ success: true, state: serverState }));
    }

    if (pathname === '/mock/status' && method === 'GET') {
      res.writeHead(200);
      return res.end(JSON.stringify({ success: true, state: serverState }));
    }

    if (pathname === '/mock/history' && method === 'GET') {
      res.writeHead(200);
      return res.end(JSON.stringify({ count: serverState.history.length, messages: serverState.history }));
    }

    if (pathname === '/mock/reset' && method === 'POST') {
      serverState.history = [];
      serverState.mode = 'AUTO_TICK';
      serverState.connectionStatus = 'CONNECTED';
      serverState.userJid = '628123456789@s.whatsapp.net';
      res.writeHead(200);
      return res.end(JSON.stringify({ success: true }));
    }

    if (pathname === '/mock/trigger-tick' && method === 'POST') {
      sendWebhook({
        event: 'message.update',
        wa_message_id: json.wa_message_id,
        remote_jid: json.remote_jid || '628123456789@s.whatsapp.net',
        status: json.status || 'READ',
        status_code: json.status_code || 4,
        timestamp: Date.now(),
      });
      res.writeHead(200);
      return res.end(JSON.stringify({ success: true, message: 'Tick triggered' }));
    }

    // --- Verify Service Key for regular API ---
    const authHeader = req.headers['x-service-key'];
    if (authHeader !== SERVICE_KEY) {
      res.writeHead(401);
      return res.end(JSON.stringify({ success: false, message: 'Unauthorized: Invalid X-Service-Key' }));
    }

    // --- WhatsApp Microservice Contract Endpoints ---
    if (pathname === '/api/session/status' && method === 'GET') {
      res.writeHead(200);
      return res.end(JSON.stringify({
        success: true,
        status: serverState.connectionStatus,
        userJid: serverState.userJid,
      }));
    }

    if (pathname === '/api/session/start' && method === 'POST') {
      res.writeHead(200);
      return res.end(JSON.stringify({
        success: true,
        message: 'Socket initializing',
      }));
    }

    if (pathname === '/api/session/qr' && method === 'GET') {
      if (serverState.connectionStatus === 'CONNECTED') {
        res.writeHead(400);
        return res.end(JSON.stringify({ success: false, message: 'Already connected' }));
      }
      res.writeHead(200);
      return res.end(JSON.stringify({ success: true, qr: serverState.qrCode }));
    }

    if (pathname === '/api/session/pairing-code' && method === 'POST') {
      if (serverState.mode === 'FAIL') {
        res.writeHead(500);
        return res.end(JSON.stringify({ success: false, message: 'Simulated socket pairing failure' }));
      }
      res.writeHead(200);
      return res.end(JSON.stringify({ success: true, code: serverState.pairingCode }));
    }

    if (pathname === '/api/session/logout' && method === 'POST') {
      serverState.connectionStatus = 'DISCONNECTED';
      serverState.userJid = null;
      res.writeHead(200);
      return res.end(JSON.stringify({ success: true, message: 'Logged out' }));
    }

    if (pathname === '/api/message/send' && method === 'POST') {
      if (serverState.mode === 'FAIL') {
        res.writeHead(500);
        return res.end(JSON.stringify({ success: false, message: 'Simulated message dispatch failure' }));
      }

      const waMessageId = '3EB0' + crypto.randomBytes(6).toString('hex').toUpperCase();
      const record = {
        recipient_id: json.recipient_id,
        phone: json.phone,
        message: json.message,
        media_path: json.media_path,
        wa_message_id: waMessageId,
        received_at: new Date().toISOString(),
      };
      serverState.history.push(record);

      if (serverState.mode === 'AUTO_TICK') {
        scheduleTickCycle(waMessageId, json.phone || '');
      }

      res.writeHead(200);
      return res.end(JSON.stringify({
        success: true,
        wa_message_id: waMessageId,
        status: 'SENT',
      }));
    }

    // Default 404
    res.writeHead(404);
    res.end(JSON.stringify({ success: false, message: 'Endpoint not found' }));
  });
});

// Auto-run if executed directly as entrypoint
const isMain = process.argv[1] && (
  process.argv[1].endsWith('mock-baileys-server.js') ||
  (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href)
);

if (isMain) {
  server.listen(PORT, '127.0.0.1', () => {
    console.log(`[MockServer] WhatsApp Baileys Mock running at http://127.0.0.1:${PORT}`);
    console.log(`[MockServer] Forwarding Webhooks to: ${LARAVEL_WEBHOOK_URL}`);
    console.log(`[MockServer] Current Mode: ${serverState.mode}`);
  });

  const shutdown = () => {
    console.log('[MockServer] Shutting down gracefully...');
    server.close(() => {
      process.exit(0);
    });
  };

  process.on('SIGINT', shutdown);
  process.on('SIGTERM', shutdown);
}

export default server;
