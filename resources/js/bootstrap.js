import * as Ably from 'ably';
import { ChatClient } from '@ably/chat';

const userId = document.querySelector('meta[name="user-id"]')?.content || 'guest';

const realtime = new Ably.Realtime({ 
    key: import.meta.env.VITE_ABLY_KEY,
    clientId: userId 
});

// Pastikan window.chatClient terdefinisi dengan benar
window.chatClient = new ChatClient(realtime);

console.log("Radar SI SINDEN: Mesin Siap");