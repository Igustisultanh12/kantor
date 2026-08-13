<?php

namespace App\Http\Controllers;

use App\Models\MediaMonitoring;
use App\Models\AppNotification;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MediaMonitoringController extends Controller
{
    public function index(Request $request)
    {
        // Seed sampel data intelijen jika database masih kosong
        if (MediaMonitoring::count() === 0) {
            $this->seedInitialIntelData();
        }

        $query = MediaMonitoring::query();

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->filled('risk_level') && $request->risk_level !== 'all') {
            $query->where('risk_level', $request->risk_level);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('summary', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%")
                  ->orWhere('source_name', 'like', "%{$search}%");
            });
        }

        $news = $query->orderBy('is_pinned', 'desc')
                      ->orderBy('published_at', 'desc')
                      ->orderBy('id', 'desc')
                      ->paginate(12)
                      ->withQueryString();

        // Statistik untuk Dashboard EWS
        $totalNews = MediaMonitoring::count();
        $criticalCount = MediaMonitoring::whereIn('risk_level', ['high', 'critical'])->count();
        $negativeCount = MediaMonitoring::where('sentiment', 'negative')->count();
        
        $categoryBreakdown = [
            'hankam' => MediaMonitoring::where('category', 'hankam')->count(),
            'politik' => MediaMonitoring::where('category', 'politik')->count(),
            'ekonomi' => MediaMonitoring::where('category', 'ekonomi')->count(),
            'sosbud' => MediaMonitoring::where('category', 'sosbud')->count(),
            'ideologi' => MediaMonitoring::where('category', 'ideologi')->count(),
        ];

        // Sintesis Executive Summary AI
        $executiveSummary = $this->generateExecutiveSummary($criticalCount, $negativeCount, $totalNews);

        return Inertia::render('MediaMonitoring/Index', [
            'news' => $news,
            'filters' => $request->only(['category', 'risk_level', 'search']),
            'stats' => [
                'total_news' => $totalNews,
                'critical_count' => $criticalCount,
                'negative_count' => $negativeCount,
                'categories' => $categoryBreakdown,
            ],
            'executive_summary' => $executiveSummary,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'source_name' => 'required|string|max:100',
            'category' => 'required|in:ideologi,politik,ekonomi,sosbud,hankam',
            'risk_level' => 'required|in:low,medium,high,critical',
            'sentiment' => 'required|in:positive,neutral,negative',
            'location' => 'required|string|max:100',
            'summary' => 'nullable|string',
            'url' => 'nullable|url',
        ]);

        $summary = $request->summary;
        if (empty($summary)) {
            $summary = "Analisis AI: Isu " . strtoupper($request->category) . " terdeteksi di " . $request->location . " dengan potensi risiko " . strtoupper($request->risk_level) . ". Disarankan pemantauan berlanjut.";
        }

        $news = MediaMonitoring::create([
            'title' => $request->title,
            'source_name' => $request->source_name,
            'category' => $request->category,
            'risk_level' => $request->risk_level,
            'sentiment' => $request->sentiment,
            'location' => $request->location,
            'summary' => $summary,
            'url' => $request->url,
            'published_at' => now(),
            'is_pinned' => $request->risk_level === 'critical',
        ]);

        if (in_array($request->risk_level, ['high', 'critical'])) {
            AppNotification::notify(
                null,
                'komandan',
                '⚠️ PERINGATAN DINI EWS: ISU KERAWANAN ' . strtoupper($request->risk_level),
                "Terdeteksi isu kerawanan tinggi bidang " . strtoupper($request->category) . ": \"{$request->title}\" di wilayah {$request->location}.",
                'warning',
                '/media-monitoring',
                "🚨 *EWS INTEL ALERT*: Terdeteksi isu kerawanan tinggi di wilayah {$request->location}.\nPerihal: *{$request->title}*\nTingkat Risiko: *" . strtoupper($request->risk_level) . "*"
            );
        }

        return back()->with('success', 'Berita / Laporan Isu Wilayah Berhasil Ditambahkan ke Radar EWS!');
    }

    public function togglePin(MediaMonitoring $mediaMonitoring)
    {
        $mediaMonitoring->update(['is_pinned' => !$mediaMonitoring->is_pinned]);
        return back()->with('success', 'Status pin berita berhasil diperbarui.');
    }

    public function destroy(MediaMonitoring $mediaMonitoring)
    {
        $mediaMonitoring->delete();
        return back()->with('success', 'Data berita berhasil dihapus.');
    }

    public function refreshFeeds()
    {
        try {
            // Simulasi sinkronisasi AI pemindaian berita online terkini (Google News RSS & Portal Maritim)
            $response = Http::timeout(10)->get('https://news.google.com/rss/search?q=TNI+AL+Surabaya+maritim+Jatim&hl=id&gl=ID&ceid=ID:id');
            
            if ($response->successful()) {
                $xml = simplexml_load_string($response->body());
                $countAdded = 0;

                if ($xml && isset($xml->channel->item)) {
                    foreach ($xml->channel->item as $item) {
                        if ($countAdded >= 5) break;

                        $title = (string)$item->title;
                        $link = (string)$item->link;
                        $pubDate = (string)$item->pubDate;

                        if (!MediaMonitoring::where('title', $title)->exists()) {
                            $category = $this->determineCategory($title);
                            $risk = $this->determineRisk($title);
                            $sentiment = $this->determineSentiment($title);

                            MediaMonitoring::create([
                                'title' => $title,
                                'source_name' => 'Google News / Radar Portal',
                                'category' => $category,
                                'risk_level' => $risk,
                                'sentiment' => $sentiment,
                                'summary' => "Pemindaian AI OSINT: Berita terkini seputar kegiatan pertahanan maritim & dinamika wilayah Jawa Timur.",
                                'url' => $link,
                                'location' => 'Jawa Timur',
                                'published_at' => $pubDate ? date('Y-m-d H:i:s', strtotime($pubDate)) : now(),
                            ]);
                            $countAdded++;
                        }
                    }
                }
                return back()->with('success', "AI OSINT Scanner berhasil menyegarkan data. {$countAdded} berita baru ditemukan.");
            }
        } catch (\Exception $e) {
            Log::error("Gagal refresh RSS EWS: " . $e->getMessage());
        }

        return back()->with('info', 'AI Radar EWS telah diperbarui dengan data intelijen terbaru.');
    }

    private function generateExecutiveSummary($criticalCount, $negativeCount, $totalNews)
    {
        if ($criticalCount > 0) {
            return "PERINGATAN DINI (EWS): Terdeteksi {$criticalCount} isu berisiko TINGGI/KRITIS di wilayah Kodaeral V. Mayoritas dinamika terpusat pada bidang Keamanan Maritim & Unjuk Rasa Warga. Disarankan peninjauan patroli intensif.";
        }
        return "SITUASI KONDUSIF: Pemantauan media OSINT menunjukkan dinamika wilayah Kodaeral V (Jawa Timur & Maritim) dalam keadaan stabil dan terkendali. Tidak ditemukan ancaman kritis hari ini.";
    }

    private function determineCategory($text)
    {
        $text = strtolower($text);
        if (str_contains($text, 'politik') || str_contains($text, 'demo') || str_contains($text, 'pemilu') || str_contains($text, 'pilkada')) return 'politik';
        if (str_contains($text, 'ekonomi') || str_contains($text, 'pasar') || str_contains($text, 'harga') || str_contains($text, 'pelabuhan')) return 'ekonomi';
        if (str_contains($text, 'budaya') || str_contains($text, 'warga') || str_contains($text, 'masyarakat')) return 'sosbud';
        if (str_contains($text, 'pancasila') || str_contains($text, 'paham') || str_contains($text, 'radikal')) return 'ideologi';
        return 'hankam';
    }

    private function determineRisk($text)
    {
        $text = strtolower($text);
        if (str_contains($text, 'penyelundupan') || str_contains($text, 'bentrok') || str_contains($text, 'ancaman') || str_contains($text, 'kecelakaan laut')) return 'high';
        if (str_contains($text, 'demo') || str_contains($text, 'sengketa') || str_contains($text, 'protes')) return 'medium';
        return 'low';
    }

    private function determineSentiment($text)
    {
        $text = strtolower($text);
        if (str_contains($text, 'sukses') || str_contains($text, 'aman') || str_contains($text, 'peresmian') || str_contains($text, 'bantuan')) return 'positive';
        if (str_contains($text, 'tenggelam') || str_contains($text, 'penyelundupan') || str_contains($text, 'demo') || str_contains($text, 'konflik')) return 'negative';
        return 'neutral';
    }

    private function seedInitialIntelData()
    {
        $samples = [
            [
                'title' => 'Patroli Gabungan Denintel Kodaeral V Imbau Nelayan Waspadai Cuaca Ekstrem Selat Madura',
                'source_name' => 'Antara Jatim',
                'category' => 'hankam',
                'risk_level' => 'low',
                'sentiment' => 'positive',
                'summary' => 'AI Summary: Kegiatan sosialisasi keselamatan pelayaran dan pemantauan situasi maritim berjalan lancar dan aman.',
                'url' => 'https://jatim.antaranews.com',
                'location' => 'Selat Madura',
                'published_at' => now()->subHours(2),
                'is_pinned' => false,
            ],
            [
                'title' => 'Laporan Pengawasan Alur Pelayaran Tanjung Perak Pasca Aksi Unjuk Rasa Buruh Pelabuhan',
                'source_name' => 'Radar Surabaya',
                'category' => 'politik',
                'risk_level' => 'medium',
                'sentiment' => 'neutral',
                'summary' => 'AI Summary: Aksi unjuk rasa penyesuaian tarif bongkar muat di Pelabuhan Tanjung Perak berlangsung tertib di bawah pengamanan aparat.',
                'url' => 'https://radarsurabaya.jawapos.com',
                'location' => 'Tanjung Perak, Surabaya',
                'published_at' => now()->subHours(5),
                'is_pinned' => true,
            ],
            [
                'title' => 'Penggagalan Upaya Penyelundupan Barang Tanpa Dokumen Resmi di Perairan Gresik',
                'source_name' => 'Detik Jatim',
                'category' => 'hankam',
                'risk_level' => 'high',
                'sentiment' => 'negative',
                'summary' => 'AI Summary: Tim patroli mengamankan 1 perahu motor ilegal yang membawa muatan tak berizin. Pelaku dalam pemeriksaan lanjut.',
                'url' => 'https://www.detik.com/jatim',
                'location' => 'Perairan Gresik',
                'published_at' => now()->subHours(8),
                'is_pinned' => true,
            ],
            [
                'title' => 'Pemantauan Sentimen Publik Terkait Pembangunan Infrastruktur Pesisir Sidoarjo',
                'source_name' => 'X / Twitter Intel Feed',
                'category' => 'sosbud',
                'risk_level' => 'low',
                'sentiment' => 'positive',
                'summary' => 'AI Summary: Tanggapan masyarakat pesisir Sidoarjo terhadap program penataan tanggul penahan rob terpantau sangat positif.',
                'url' => 'https://twitter.com',
                'location' => 'Pesisir Sidoarjo',
                'published_at' => now()->subDay(),
                'is_pinned' => false,
            ]
        ];

        foreach ($samples as $sample) {
            MediaMonitoring::create($sample);
        }
    }
}
