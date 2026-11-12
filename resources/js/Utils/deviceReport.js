// Laporan info perangkat web untuk Profil > Perangkat & sesi.
// Hanya membaca yang tersedia tanpa meminta izin baru: baterai (bila peramban
// mendukung), ukuran layar, jenis koneksi, dan lokasi HANYA bila izin lokasi
// untuk situs ini sudah pernah diberikan (mis. saat absensi).
import axios from 'axios';

const KEY = 'sinden.deviceReportAt';
const EVERY_MS = 5 * 60 * 1000;

export async function reportDevice() {
    try {
        const last = Number(sessionStorage.getItem(KEY) || 0);
        if (Date.now() - last < EVERY_MS) return;
        sessionStorage.setItem(KEY, String(Date.now()));

        const body = { screen: `${window.screen.width}x${window.screen.height}` };
        const conn = navigator.connection;
        if (conn?.effectiveType) body.network = conn.type && conn.type !== 'unknown' ? `${conn.type} (${conn.effectiveType})` : conn.effectiveType.toUpperCase();

        if (navigator.getBattery) {
            const b = await navigator.getBattery();
            body.battery = Math.round(b.level * 100);
            body.charging = b.charging;
        }

        if (navigator.permissions && navigator.geolocation) {
            const perm = await navigator.permissions.query({ name: 'geolocation' }).catch(() => null);
            if (perm?.state === 'granted') {
                const pos = await new Promise((resolve) => {
                    navigator.geolocation.getCurrentPosition(resolve, () => resolve(null), { maximumAge: 10 * 60 * 1000, timeout: 8000 });
                });
                if (pos) {
                    body.lat = pos.coords.latitude;
                    body.lng = pos.coords.longitude;
                    body.accuracy = pos.coords.accuracy;
                    body.location_at = pos.timestamp;
                }
            }
        }

        await axios.post(route('profile.devices.report'), body);
    } catch (e) {
        // Laporan perangkat bersifat tambahan: kegagalan diabaikan.
    }
}
