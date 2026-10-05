const express = require('express');
const cors = require('cors');
const fs = require('fs');
const path = require('path');
const QRCode = require('qrcode');
const pino = require('pino');
const {
    default: makeWASocket,
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
    makeCacheableSignalKeyStore,
    proto
} = require('@whiskeysockets/baileys');

const app = express();
const PORT = process.env.PORT || 3000;

app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

const logger = pino({ level: 'error' });
const SESSIONS_DIR = path.join(__dirname, 'sessions');

if (!fs.existsSync(SESSIONS_DIR)) {
    fs.mkdirSync(SESSIONS_DIR, { recursive: true });
}

// In-Memory Storage for Active Sessions
// Map<sessionId, { sock, status, qr, phone, name, chats: Map<jid, chatData>, messages: Map<jid, Array<msg>> }>
const sessions = new Map();

/**
 * Format phone number to WhatsApp JID (628xxx@s.whatsapp.net)
 */
function formatToJid(number) {
    if (!number) return null;
    let clean = number.toString().replace(/[^0-9]/g, '');
    if (clean.startsWith('0')) {
        clean = '62' + clean.slice(1);
    }
    if (!clean.endsWith('@s.whatsapp.net') && !clean.endsWith('@g.us')) {
        clean = `${clean}@s.whatsapp.net`;
    }
    return clean;
}

/**
 * Clean JID to readable phone number
 */
function jidToNumber(jid) {
    if (!jid) return '';
    return jid.replace('@s.whatsapp.net', '').replace('@g.us', '');
}

/**
 * Extract text from Baileys message object
 */
function extractMessageText(message) {
    if (!message) return '';
    return message.conversation ||
        message.extendedTextMessage?.text ||
        message.imageMessage?.caption ||
        message.videoMessage?.caption ||
        message.documentMessage?.title ||
        '[Media/Lampiran]';
}

/**
 * Initialize / start a session
 */
async function createSession(sessionId, label = '', customFolder = null) {
    if (sessions.has(sessionId)) {
        const existing = sessions.get(sessionId);
        if (existing.status === 'CONNECTED' && existing.sock) {
            return existing;
        }
    }

    const sessionFolder = customFolder || path.join(SESSIONS_DIR, sessionId);
    if (!fs.existsSync(sessionFolder)) {
        fs.mkdirSync(sessionFolder, { recursive: true });
    }

    const { state, saveCreds } = await useMultiFileAuthState(sessionFolder);
    const { version } = await fetchLatestBaileysVersion();

    const sessionData = {
        sessionId,
        label: label || sessionId,
        sock: null,
        status: 'CONNECTING', // CONNECTING, QR_READY, CONNECTED, DISCONNECTED
        qr: null,
        phone: null,
        name: null,
        chats: sessions.get(sessionId)?.chats || new Map(),
        messages: sessions.get(sessionId)?.messages || new Map(),
    };

    sessions.set(sessionId, sessionData);

    const sock = makeWASocket({
        version,
        logger,
        printQRInTerminal: false,
        auth: {
            creds: state.creds,
            keys: makeCacheableSignalKeyStore(state.keys, logger),
        },
        browser: ['SINDEN Desktop', 'Chrome', '128.0.0.0'],
        connectTimeoutMs: 60000,
        keepAliveIntervalMs: 30000,
    });

    sessionData.sock = sock;

    // Credentials update event
    sock.ev.on('creds.update', saveCreds);

    // Connection update event
    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            try {
                sessionData.qr = await QRCode.toDataURL(qr, { margin: 2, scale: 8 });
                sessionData.status = 'QR_READY';
            } catch (err) {
                console.error(`[${sessionId}] QR generation error:`, err);
            }
        }

        if (connection === 'close') {
            const statusCode = lastDisconnect?.error?.output?.statusCode;
            const shouldReconnect = statusCode !== DisconnectReason.loggedOut;
            console.log(`[${sessionId}] Koneksi ditutup: ${statusCode}, Reconnect: ${shouldReconnect}`);

            if (statusCode === DisconnectReason.loggedOut) {
                sessionData.status = 'DISCONNECTED';
                sessionData.qr = null;
                sessionData.phone = null;
                sessionData.name = null;
                // Delete local session folder on logout
                try {
                    if (fs.existsSync(sessionFolder)) {
                        fs.rmSync(sessionFolder, { recursive: true, force: true });
                    }
                } catch (e) {
                    console.error(`[${sessionId}] Gagal menghapus folder sesi:`, e);
                }
            } else if (shouldReconnect) {
                sessionData.status = 'CONNECTING';
                setTimeout(() => {
                    createSession(sessionId, sessionData.label).catch(console.error);
                }, 3000);
            } else {
                sessionData.status = 'DISCONNECTED';
            }
        } else if (connection === 'open') {
            sessionData.status = 'CONNECTED';
            sessionData.qr = null;
            const userJid = sock.user?.id || '';
            sessionData.phone = jidToNumber(userJid.split(':')[0] || userJid);
            sessionData.name = sock.user?.name || sessionData.phone;
            console.log(`[${sessionId}] Berhasil terhubung: ${sessionData.phone} (${sessionData.name})`);
        }
    });

    // Handle incoming & outgoing messages
    sock.ev.on('messages.upsert', async (m) => {
        if (!m.messages || m.messages.length === 0) return;

        for (const msg of m.messages) {
            if (!msg.message) continue;
            const jid = msg.key.remoteJid;
            if (!jid || jid.endsWith('@broadcast')) continue;

            const fromMe = Boolean(msg.key.fromMe);
            const body = extractMessageText(msg.message);
            const timestamp = msg.messageTimestamp ? new Date(msg.messageTimestamp * 1000).toISOString() : new Date().toISOString();
            const senderName = msg.pushName || jidToNumber(jid);

            // Update chat list
            if (!sessionData.chats.has(jid)) {
                sessionData.chats.set(jid, {
                    jid,
                    number: jidToNumber(jid),
                    name: senderName,
                    lastMessage: body,
                    timestamp,
                    unreadCount: fromMe ? 0 : 1,
                });
            } else {
                const existingChat = sessionData.chats.get(jid);
                existingChat.lastMessage = body;
                existingChat.timestamp = timestamp;
                if (!fromMe) {
                    existingChat.unreadCount = (existingChat.unreadCount || 0) + 1;
                }
                if (senderName && senderName !== existingChat.name && !existingChat.name.startsWith('62')) {
                    existingChat.name = senderName;
                }
            }

            // Update message history
            if (!sessionData.messages.has(jid)) {
                sessionData.messages.set(jid, []);
            }
            const chatMessages = sessionData.messages.get(jid);
            chatMessages.push({
                id: msg.key.id,
                fromMe,
                body,
                timestamp,
                status: fromMe ? 'SENT' : 'RECEIVED'
            });

            // Keep max 100 recent messages per chat in memory
            if (chatMessages.length > 100) {
                chatMessages.shift();
            }
        }
    });

    return sessionData;
}

/**
 * Auto-restore previous sessions from disk
 */
function restoreSessions() {
    // 1. Cek folder warisan lama 'auth_info_baileys' (agar koneksi WA yang sekarang tidak putus/perlu scan ulang)
    const legacyPaths = [
        path.join(__dirname, 'auth_info_baileys'),
        path.join(__dirname, '..', 'auth_info_baileys'),
        path.join(process.cwd(), 'auth_info_baileys'),
    ];

    let legacyFound = false;
    for (const lPath of legacyPaths) {
        if (fs.existsSync(path.join(lPath, 'creds.json'))) {
            console.log(`📡 Menemukan auth_info_baileys di ${lPath}, menghubungkan otomatis sebagai sesi 'dinas'...`);
            createSession('dinas', 'WA Dinas / Radar Utama', lPath).catch(console.error);
            legacyFound = true;
            break;
        }
    }

    // 2. Cek folder multi-sesi 'sessions/'
    try {
        const dirs = fs.readdirSync(SESSIONS_DIR, { withFileTypes: true });
        for (const dir of dirs) {
            if (dir.isDirectory()) {
                const sessionId = dir.name;
                if (sessionId === 'dinas' && legacyFound) continue;
                const credsPath = path.join(SESSIONS_DIR, sessionId, 'creds.json');
                if (fs.existsSync(credsPath)) {
                    console.log(`Memulihkan sesi tersimpan: ${sessionId}`);
                    createSession(sessionId).catch(console.error);
                }
            }
        }
    } catch (err) {
        console.error('Gagal memulihkan sesi:', err);
    }
}

// -------------------------------------------------------------
// REST API ROUTES
// -------------------------------------------------------------

// 1. Health check & Overview (Kompatibel dengan sistem lama dan baru)
app.get('/status', (req, res) => {
    const list = [];
    let legacyStatus = 'OFFLINE';
    let legacyQR = null;

    for (const [id, data] of sessions.entries()) {
        list.push({
            sessionId: id,
            label: data.label,
            status: data.status,
            phone: data.phone,
            name: data.name
        });

        if (data.status === 'CONNECTED') {
            legacyStatus = 'ONLINE';
        } else if (data.status === 'QR_READY' && legacyStatus !== 'ONLINE') {
            legacyStatus = 'WAITING_SCAN';
            legacyQR = data.qr;
        }
    }

    res.json({
        ok: true,
        status: legacyStatus,
        qr: legacyQR,
        uptime: process.uptime(),
        totalSessions: list.length,
        sessions: list
    });
});

// 2. Backward Compatible Endpoint for SINDEN: GET /send?number=...&msg=...
app.get('/send', async (req, res) => {
    const { number, msg, session } = req.query;
    if (!number || !msg) {
        return res.status(400).json({ status: 'error', ok: false, error: 'Parameter number dan msg wajib diisi.' });
    }

    // Cari sesi yang aktif: jika 'session' disertakan gunakan itu, jika tidak cari 'dinas' atau sesi pertama yang CONNECTED
    let targetSession = null;
    if (session && sessions.has(session) && sessions.get(session).status === 'CONNECTED') {
        targetSession = sessions.get(session);
    } else if (sessions.has('dinas') && sessions.get('dinas').status === 'CONNECTED') {
        targetSession = sessions.get('dinas');
    } else {
        for (const data of sessions.values()) {
            if (data.status === 'CONNECTED' && data.sock) {
                targetSession = data;
                break;
            }
        }
    }

    if (!targetSession) {
        return res.status(503).json({ status: 'not_ready', ok: false, error: 'Tidak ada sesi WhatsApp yang terhubung di sistem.' });
    }

    try {
        const jid = formatToJid(number);
        await targetSession.sock.sendMessage(jid, { text: msg });
        return res.json({ 
            status: 'success', 
            ok: true, 
            message: 'Pesan terkirim', 
            target: jid, 
            session: targetSession.sessionId 
        });
    } catch (err) {
        return res.status(500).json({ status: 'error', ok: false, message: err.message });
    }
});

// 3. List all sessions
app.get('/sessions', (req, res) => {
    const list = [];
    for (const [id, data] of sessions.entries()) {
        list.push({
            sessionId: id,
            label: data.label,
            status: data.status,
            phone: data.phone,
            name: data.name,
            totalChats: data.chats.size
        });
    }
    res.json({ ok: true, sessions: list });
});

// 4. Create new session (generates QR)
app.post('/sessions', async (req, res) => {
    const { sessionId, label } = req.body;
    if (!sessionId) {
        return res.status(400).json({ ok: false, error: 'sessionId wajib diisi.' });
    }

    try {
        const sessionData = await createSession(sessionId, label || sessionId);
        res.json({
            ok: true,
            sessionId,
            status: sessionData.status,
            qr: sessionData.qr
        });
    } catch (err) {
        res.status(500).json({ ok: false, error: err.message });
    }
});

// 5. Get QR code for session
app.get('/sessions/:id/qr', (req, res) => {
    const sessionId = req.params.id;
    const session = sessions.get(sessionId);

    if (!session) {
        return res.status(404).json({ ok: false, error: 'Sesi tidak ditemukan.' });
    }

    res.json({
        ok: true,
        sessionId,
        status: session.status,
        qr: session.qr,
        phone: session.phone
    });
});

// 6. Get Status of a session
app.get('/sessions/:id/status', (req, res) => {
    const sessionId = req.params.id;
    const session = sessions.get(sessionId);

    if (!session) {
        return res.status(404).json({ ok: false, error: 'Sesi tidak ditemukan.' });
    }

    res.json({
        ok: true,
        sessionId,
        status: session.status,
        phone: session.phone,
        name: session.name
    });
});

// 7. Logout session (disconnect and delete credentials)
app.post('/sessions/:id/logout', async (req, res) => {
    const sessionId = req.params.id;
    const session = sessions.get(sessionId);

    if (!session) {
        return res.status(404).json({ ok: false, error: 'Sesi tidak ditemukan.' });
    }

    try {
        if (session.sock) {
            try {
                await session.sock.logout();
            } catch (e) {
                // Ignore if socket already closed
            }
        }

        session.status = 'DISCONNECTED';
        session.qr = null;
        session.phone = null;
        session.name = null;
        sessions.delete(sessionId);

        const sessionFolder = path.join(SESSIONS_DIR, sessionId);
        if (fs.existsSync(sessionFolder)) {
            fs.rmSync(sessionFolder, { recursive: true, force: true });
        }

        res.json({ ok: true, message: `Sesi ${sessionId} berhasil di-logout dan dihapus.` });
    } catch (err) {
        res.status(500).json({ ok: false, error: err.message });
    }
});

// 8. Delete session
app.delete('/sessions/:id', (req, res) => {
    const sessionId = req.params.id;
    const session = sessions.get(sessionId);

    if (session && session.sock) {
        try {
            session.sock.end();
        } catch (e) {}
    }

    sessions.delete(sessionId);
    const sessionFolder = path.join(SESSIONS_DIR, sessionId);
    if (fs.existsSync(sessionFolder)) {
        fs.rmSync(sessionFolder, { recursive: true, force: true });
    }

    res.json({ ok: true, message: `Sesi ${sessionId} dihapus.` });
});

// 9. Get Chats for session
app.get('/sessions/:id/chats', (req, res) => {
    const sessionId = req.params.id;
    const session = sessions.get(sessionId);

    if (!session || session.status !== 'CONNECTED') {
        return res.status(400).json({ ok: false, error: 'Sesi belum terhubung.' });
    }

    const chatList = Array.from(session.chats.values()).sort((a, b) => {
        return new Date(b.timestamp) - new Date(a.timestamp);
    });

    res.json({ ok: true, chats: chatList });
});

// 10. Get Messages for a specific chat JID
app.get('/sessions/:id/chats/:jid/messages', (req, res) => {
    const { id: sessionId, jid } = req.params;
    const session = sessions.get(sessionId);

    if (!session || session.status !== 'CONNECTED') {
        return res.status(400).json({ ok: false, error: 'Sesi belum terhubung.' });
    }

    const messages = session.messages.get(jid) || [];
    // Mark as read in local chat list
    if (session.chats.has(jid)) {
        session.chats.get(jid).unreadCount = 0;
    }

    res.json({ ok: true, messages });
});

// 11. Send Message via specific session
app.post('/sessions/:id/send', async (req, res) => {
    const sessionId = req.params.id;
    const { number, message } = req.body;

    if (!number || !message) {
        return res.status(400).json({ ok: false, error: 'number dan message wajib diisi.' });
    }

    const session = sessions.get(sessionId);
    if (!session || session.status !== 'CONNECTED' || !session.sock) {
        return res.status(400).json({ ok: false, error: `Sesi ${sessionId} belum terhubung ke WhatsApp.` });
    }

    try {
        const jid = formatToJid(number);
        const result = await session.sock.sendMessage(jid, { text: message });

        // Record in in-memory chat
        const timestamp = new Date().toISOString();
        if (!session.chats.has(jid)) {
            session.chats.set(jid, {
                jid,
                number: jidToNumber(jid),
                name: jidToNumber(jid),
                lastMessage: message,
                timestamp,
                unreadCount: 0,
            });
        } else {
            const chat = session.chats.get(jid);
            chat.lastMessage = message;
            chat.timestamp = timestamp;
        }

        if (!session.messages.has(jid)) {
            session.messages.set(jid, []);
        }
        session.messages.get(jid).push({
            id: result.key.id,
            fromMe: true,
            body: message,
            timestamp,
            status: 'SENT'
        });

        res.json({ ok: true, message: 'Pesan berhasil terkirim.', jid });
    } catch (err) {
        res.status(500).json({ ok: false, error: err.message });
    }
});

// Start Server
app.listen(PORT, () => {
    console.log(`====================================================`);
    console.log(` SINDEN Multi-Session WhatsApp Gateway aktif di port ${PORT}`);
    console.log(`====================================================`);
    restoreSessions();
});
