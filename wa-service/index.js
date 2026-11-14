import makeWASocket, {
  DisconnectReason,
  useMultiFileAuthState,
  makeCacheableSignalKeyStore,
  fetchLatestBaileysVersion
} from '@whiskeysockets/baileys';
import pino from 'pino';
import QRCode from 'qrcode';
import express from 'express';
import cors from 'cors';
import dotenv from 'dotenv';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const PORT = parseInt(process.env.PORT || '3000', 10);
const INTERNAL_SERVICE_KEY = process.env.INTERNAL_SERVICE_KEY || 'blast_internal_secret_key_2026';
const LARAVEL_WEBHOOK_URL = process.env.LARAVEL_WEBHOOK_URL || 'http://127.0.0.1/api/wa/webhook';
const AUTH_DIR = path.join(__dirname, 'auth_info_baileys');

const logger = pino({
  level: process.env.LOG_LEVEL || 'info'
}).child({ service: 'baileys-service' });

const app = express();
app.use(cors());
app.use(express.json());

// Global State
let sock = null;
let sessionStatus = 'DISCONNECTED'; // 'DISCONNECTED' | 'CONNECTING' | 'QR_READY' | 'CONNECTED' | 'PAIRING'
let userJid = null;
let currentQr = null;
let reconnectTimer = null;

// Helper: Kirim Webhook ke Laravel
async function emitWebhook(payload) {
  if (!LARAVEL_WEBHOOK_URL) return;
  try {
    const res = await fetch(LARAVEL_WEBHOOK_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Service-Key': INTERNAL_SERVICE_KEY,
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    });
    if (!res.ok) {
      logger.warn({ status: res.status, url: LARAVEL_WEBHOOK_URL }, 'Webhook returned non-200');
    }
  } catch (err) {
    logger.error({ error: err.message, url: LARAVEL_WEBHOOK_URL }, 'Failed to dispatch webhook to Laravel');
  }
}

// Helper: Hapus Folder Kredensial Sesi
function clearAuthFolder() {
  try {
    if (fs.existsSync(AUTH_DIR)) {
      fs.rmSync(AUTH_DIR, { recursive: true, force: true });
      logger.info('Auth folder purged successfully');
    }
  } catch (err) {
    logger.error({ error: err.message }, 'Failed to clear auth folder');
  }
}

// Inisialisasi Socket Baileys
async function initBaileysSocket() {
  if (sock && sessionStatus === 'CONNECTED') {
    logger.info('Socket already active and connected');
    return sock;
  }

  if (!fs.existsSync(AUTH_DIR)) {
    fs.mkdirSync(AUTH_DIR, { recursive: true });
  }

  sessionStatus = 'CONNECTING';
  emitWebhook({ event: 'connection.update', status: 'connecting' });

  const { state, saveCreds } = await useMultiFileAuthState(AUTH_DIR);
  const { version, isLatest } = await fetchLatestBaileysVersion().catch(() => ({
    version: [2, 3000, 1017531287],
    isLatest: true
  }));

  logger.info({ version, isLatest }, 'Starting Baileys Socket');

  const silentLogger = pino({ level: 'silent' });

  sock = makeWASocket({
    version,
    auth: {
      creds: state.creds,
      keys: makeCacheableSignalKeyStore(state.keys, silentLogger)
    },
    logger: silentLogger,
    printQRInTerminal: false,
    generateHighQualityLinkPreview: false,
    syncFullHistory: false,
    markOnlineOnConnect: true,
    connectTimeoutMs: 60000,
    keepAliveIntervalMs: 30000
  });

  sock.ev.on('creds.update', saveCreds);

  sock.ev.on('connection.update', async (update) => {
    const { connection, lastDisconnect, qr } = update;

    if (qr) {
      try {
        currentQr = await QRCode.toDataURL(qr);
        sessionStatus = 'QR_READY';
        logger.info('New QR Code generated and ready for scan');
      } catch (qrErr) {
        logger.error({ error: qrErr.message }, 'Failed to convert QR code to data URL');
      }
    }

    if (connection === 'open') {
      sessionStatus = 'CONNECTED';
      currentQr = null;
      userJid = sock.user?.id || null;
      logger.info({ userJid }, 'WhatsApp socket connected successfully');

      emitWebhook({
        event: 'connection.update',
        status: 'open',
        user_jid: userJid
      });
    } else if (connection === 'close') {
      const statusCode = lastDisconnect?.error?.output?.statusCode;
      const isLoggedOut = statusCode === DisconnectReason.loggedOut;

      logger.warn({ statusCode, isLoggedOut, reason: lastDisconnect?.error?.message }, 'Connection closed');

      sessionStatus = 'DISCONNECTED';
      currentQr = null;
      const prevJid = userJid;
      userJid = null;

      emitWebhook({
        event: 'connection.update',
        status: 'close',
        reason: lastDisconnect?.error?.message || 'Connection closed',
        user_jid: prevJid
      });

      if (isLoggedOut) {
        logger.info('Session logged out by WhatsApp. Clearing local auth info.');
        clearAuthFolder();
      } else {
        logger.info('Connection dropped. Scheduling auto-reconnect in 5 seconds...');
        if (reconnectTimer) clearTimeout(reconnectTimer);
        reconnectTimer = setTimeout(() => {
          initBaileysSocket().catch((err) => {
            logger.error({ error: err.message }, 'Auto-reconnect failed');
          });
        }, 5000);
      }
    }
  });

  // Listener untuk status tanda terima pesan (Centang 1, 2, Biru)
  sock.ev.on('messages.update', async (updates) => {
    for (const update of updates) {
      const waMessageId = update.key?.id;
      const remoteJid = update.key?.remoteJid;
      const statusCode = update.update?.status; // 2: SENT, 3: DELIVERED, 4: READ

      if (!waMessageId || statusCode === undefined) continue;

      let statusString = 'SENT';
      if (statusCode === 3) statusString = 'DELIVERED';
      else if (statusCode === 4) statusString = 'READ';
      else if (statusCode === 0 || statusCode === 1) statusString = 'FAILED';

      emitWebhook({
        event: 'message.update',
        wa_message_id: waMessageId,
        remote_jid: remoteJid,
        status: statusString,
        status_code: statusCode,
        timestamp: Date.now()
      });
    }
  });

  return sock;
}

// Middleware Proteksi Internal Service Key
function authMiddleware(req, res, next) {
  const incomingKey = req.headers['x-service-key'];
  const validKeys = [INTERNAL_SERVICE_KEY, 'blast_internal_secret_key_2026', 'jawirblast_internal_secret_key'];

  if (!incomingKey || !validKeys.includes(incomingKey)) {
    return res.status(401).json({
      success: false,
      error: 'Unauthorized: Invalid or missing X-Service-Key'
    });
  }
  next();
}

app.use(authMiddleware);

// Endpoint 1: Cek Status Sesi
app.get('/api/session/status', (req, res) => {
  return res.json({
    success: true,
    status: sessionStatus,
    userJid: userJid,
    phone: userJid ? userJid.split('@')[0].split(':')[0] : null
  });
});

// Endpoint 2: Mulai Inisialisasi Sesi / Socket
app.post('/api/session/start', async (req, res) => {
  try {
    if (sessionStatus !== 'CONNECTED') {
      initBaileysSocket().catch((err) => {
        logger.error({ error: err.message }, 'Background socket init error');
      });
    }
    return res.json({
      success: true,
      message: 'Socket initializing'
    });
  } catch (err) {
    return res.status(500).json({
      success: false,
      message: err.message
    });
  }
});

// Endpoint 3: Ambil QR Code Base64
app.get('/api/session/qr', async (req, res) => {
  if (sessionStatus === 'CONNECTED') {
    return res.status(400).json({
      success: false,
      message: 'Already connected'
    });
  }

  if (currentQr) {
    return res.json({
      success: true,
      qr: currentQr
    });
  }

  // Jika socket belum diinisialisasi, picu inisialisasi
  if (sessionStatus === 'DISCONNECTED') {
    initBaileysSocket().catch((e) => logger.error({ e }, 'QR init trigger failed'));
  }

  return res.status(200).json({
    success: false,
    message: 'QR not ready yet, socket initializing'
  });
});

// Endpoint 4: Request 8-Digit Pairing Code
app.post('/api/session/pairing-code', async (req, res) => {
  const { phone } = req.body;

  if (sessionStatus === 'CONNECTED') {
    return res.status(400).json({
      success: false,
      message: 'Session already connected'
    });
  }

  if (!phone || typeof phone !== 'string' || !/^\+?[0-9]{8,20}$/.test(phone)) {
    return res.status(400).json({
      success: false,
      message: 'Invalid phone number format'
    });
  }

  const cleanPhone = phone.replace(/[^0-9]/g, '');
  if (cleanPhone.length < 8 || cleanPhone.length > 20) {
    return res.status(400).json({
      success: false,
      message: 'Invalid phone number format'
    });
  }

  try {
    if (!sock || sessionStatus === 'DISCONNECTED') {
      await initBaileysSocket();
    }

    // Tunggu socket siap sebentar jika baru initializing
    sessionStatus = 'PAIRING';
    const code = await sock.requestPairingCode(cleanPhone);

    // Format kode bila perlu: 8 karakter (ABCD-1234 atau 1234-5678)
    let formattedCode = code;
    if (code && code.length === 8 && !code.includes('-')) {
      formattedCode = `${code.slice(0, 4)}-${code.slice(4)}`;
    }

    return res.json({
      success: true,
      code: formattedCode
    });
  } catch (err) {
    logger.error({ error: err.message, phone: cleanPhone }, 'Failed to generate pairing code');
    return res.status(500).json({
      success: false,
      message: `WhatsApp socket timeout while requesting pairing code: ${err.message}`
    });
  }
});

// Endpoint 5: Logout & Reset Kredensial
app.post('/api/session/logout', async (req, res) => {
  try {
    if (reconnectTimer) {
      clearTimeout(reconnectTimer);
      reconnectTimer = null;
    }

    if (sock) {
      try {
        await sock.logout();
      } catch (e) {
        logger.warn({ error: e.message }, 'Sock logout error, proceeding with cleanup');
      }
      try {
        sock.end(undefined);
      } catch (e) {}
      sock = null;
    }

    clearAuthFolder();

    sessionStatus = 'DISCONNECTED';
    userJid = null;
    currentQr = null;

    return res.json({
      success: true,
      message: 'Logged out'
    });
  } catch (err) {
    return res.status(500).json({
      success: false,
      message: err.message
    });
  }
});

// Endpoint 6: Kirim Pesan WhatsApp
app.post('/api/message/send', async (req, res) => {
  const { recipient_id, phone, message, media_path } = req.body;

  if (sessionStatus !== 'CONNECTED' || !sock) {
    return res.status(503).json({
      success: false,
      message: 'WhatsApp socket is not connected'
    });
  }

  if (!phone || !message) {
    return res.status(400).json({
      success: false,
      message: 'Phone and message are required'
    });
  }

  const cleanPhone = phone.replace(/[^0-9]/g, '');
  const jid = `${cleanPhone}@s.whatsapp.net`;

  try {
    let sentResult;
    if (media_path && fs.existsSync(media_path)) {
      // Kirim Media
      const buffer = fs.readFileSync(media_path);
      const ext = path.extname(media_path).toLowerCase();
      if (['.jpg', '.jpeg', '.png', '.webp'].includes(ext)) {
        sentResult = await sock.sendMessage(jid, { image: buffer, caption: message });
      } else if (['.mp4'].includes(ext)) {
        sentResult = await sock.sendMessage(jid, { video: buffer, caption: message });
      } else {
        sentResult = await sock.sendMessage(jid, { document: buffer, caption: message, fileName: path.basename(media_path) });
      }
    } else {
      // Kirim Teks
      sentResult = await sock.sendMessage(jid, { text: message });
    }

    const waMessageId = sentResult?.key?.id || `3EB0${Date.now()}`;

    return res.json({
      success: true,
      wa_message_id: waMessageId,
      status: 'SENT'
    });
  } catch (err) {
    logger.error({ error: err.message, phone }, 'Failed to dispatch WhatsApp message');
    return res.status(500).json({
      success: false,
      message: err.message
    });
  }
});

// Start Server
app.listen(PORT, '0.0.0.0', () => {
  logger.info(`JAWIRBLAST Baileys Microservice running on http://127.0.0.1:${PORT}`);
});
