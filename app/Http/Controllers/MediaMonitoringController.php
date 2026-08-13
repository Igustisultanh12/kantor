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
        // Jika belum pernah tarik berita asli atau berita kurang dari 3, tarik berita OSINT asli secara otomatis!
        if (MediaMonitoring::where('source_name', 'NOT LIKE', '%Staf Intel%')->count() < 4) {
            $this->fetchLiveRssNews(true);
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

    public function clearAll()
    {
        MediaMonitoring::truncate();
        return back()->with('success', 'Seluruh data berita di Radar EWS berhasil dibersihkan.');
    }

    public function refreshFeeds(Request $request)
    {
        $topic = $request->input('topic');
        $newCount = $this->fetchLiveRssNews(true, $topic);
        
        $msg = $topic 
            ? "AI OSINT Scanner berhasil memindai topik khusus: \"{$topic}\". {$newCount} berita/postingan relevan ditemukan."
            : "AI OSINT Scanner berhasil menyegarkan data. {$newCount} berita asli terkini telah diambil dari portal online.";

        return back()->with('success', $msg);
    }

    /**
     * Pemindaian Otomatis Multi-Kanal KHUSUS WILAYAH KERJA KODAERAL V (Dapat Menerima Parameter Topik Khusus)
     */
    private function fetchLiveRssNews($clearSamples = false, $customTopic = null)
    {
        // Bersihkan berita luar wilayah jika diminta
        if ($clearSamples && empty($customTopic)) {
            $all = MediaMonitoring::where('source_name', 'NOT LIKE', '%Staf Intel%')->get();
            foreach ($all as $item) {
                if (!$this->isKodaeralVLocation($item->title . ' ' . $item->summary . ' ' . $item->location)) {
                    $item->delete();
                }
            }
        }

        if (!empty($customTopic)) {
            $encodedTopic = urlencode($customTopic);
            $sources = [
                "https://news.google.com/rss/search?q={$encodedTopic}+Surabaya&hl=id&gl=ID&ceid=ID:id",
                "https://news.google.com/rss/search?q={$encodedTopic}+Jawa+Timur&hl=id&gl=ID&ceid=ID:id",
                "https://news.google.com/rss/search?q=site:x.com+OR+site:twitter.com+{$encodedTopic}&hl=id&gl=ID&ceid=ID:id",
                "https://news.google.com/rss/search?q=site:youtube.com+{$encodedTopic}&hl=id&gl=ID&ceid=ID:id",
            ];
        } else {
            $sources = [
                // Kanal Berita Utama & Maritim Khusus Kodaeral V / Jatim
                'https://news.google.com/rss/search?q=TNI+AL+Surabaya&hl=id&gl=ID&ceid=ID:id',
                'https://news.google.com/rss/search?q=Pelabuhan+Tanjung+Perak+Surabaya&hl=id&gl=ID&ceid=ID:id',
                'https://news.google.com/rss/search?q=Maritim+Jawa+Timur&hl=id&gl=ID&ceid=ID:id',
                'https://news.google.com/rss/search?q=Pengamanan+Surabaya&hl=id&gl=ID&ceid=ID:id',
                'https://news.google.com/rss/search?q=Penyelundupan+Jawa+Timur&hl=id&gl=ID&ceid=ID:id',
                'https://news.google.com/rss/search?q=Denintel+Kodaeral+V&hl=id&gl=ID&ceid=ID:id',
                'https://jatim.antaranews.com/rss/terkini.xml',

                // Kanal Media Sosial OSINT Khusus Surabaya / Jatim
                'https://news.google.com/rss/search?q=site:x.com+OR+site:twitter.com+Surabaya&hl=id&gl=ID&ceid=ID:id',
                'https://news.google.com/rss/search?q=site:youtube.com+TNI+AL+Surabaya&hl=id&gl=ID&ceid=ID:id',
                'https://news.google.com/rss/search?q=site:instagram.com+Surabaya+maritim&hl=id&gl=ID&ceid=ID:id',
            ];
        }

        $addedCount = 0;

        foreach ($sources as $sourceUrl) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept'     => 'application/xml, text/xml, */*'
                ])->timeout(8)->get($sourceUrl);

                if (!$response->successful()) {
                    continue;
                }

                $body = $response->body();
                $xml = @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);

                if (!$xml || !isset($xml->channel->item)) {
                    continue;
                }

                foreach ($xml->channel->item as $item) {
                    if ($addedCount >= 25) break;

                    $rawTitle = trim((string)$item->title);
                    $link = trim((string)$item->link);
                    $pubDateStr = trim((string)$item->pubDate);
                    $description = strip_tags(trim((string)($item->description ?? '')));

                    if (empty($rawTitle) || empty($link)) {
                        continue;
                    }

                    $fullText = $rawTitle . ' ' . $description;

                    // SYARAT MUTLAK: WAJIB TERMASUK DALAM WILAYAH KERJA KODAERAL V!
                    if (!$this->isKodaeralVLocation($fullText)) {
                        continue;
                    }

                    // Deteksi Sumber & Kanal (Berita vs Sosmed X/Twitter/Youtube/Instagram)
                    $publisher = 'Portal Berita Online';
                    $title = $rawTitle;

                    if (str_contains($rawTitle, ' - ')) {
                        $parts = explode(' - ', $rawTitle);
                        $publisher = array_pop($parts);
                        $title = implode(' - ', $parts);
                    }

                    // Penyesuaian nama kanal medsos
                    if (str_contains($link, 'x.com') || str_contains($link, 'twitter.com') || str_contains(strtolower($publisher), 'x') || str_contains(strtolower($publisher), 'twitter')) {
                        $publisher = 'X (Twitter) Feed';
                    } elseif (str_contains($link, 'youtube.com') || str_contains(strtolower($publisher), 'youtube')) {
                        $publisher = 'YouTube Video Feed';
                    } elseif (str_contains($link, 'instagram.com') || str_contains(strtolower($publisher), 'instagram')) {
                        $publisher = 'Instagram Post';
                    } elseif (str_contains($link, 'tiktok.com') || str_contains(strtolower($publisher), 'tiktok')) {
                        $publisher = 'TikTok Video';
                    }

                    // Cek duplikasi judul
                    if (MediaMonitoring::where('title', $title)->exists()) {
                        continue;
                    }

                    $category = $this->determineCategory($fullText);
                    $risk = $this->determineRisk($fullText);
                    $sentiment = $this->determineSentiment($fullText);
                    $location = $this->determineLocation($fullText);
                    $summary = $this->generateSummaryFromHeadline($title, $publisher, $category, $risk);

                    MediaMonitoring::create([
                        'title' => $title,
                        'source_name' => $publisher,
                        'category' => $category,
                        'risk_level' => $risk,
                        'sentiment' => $sentiment,
                        'summary' => $summary,
                        'url' => $link,
                        'location' => $location,
                        'published_at' => $pubDateStr ? date('Y-m-d H:i:s', strtotime($pubDateStr)) : now(),
                        'is_pinned' => ($risk === 'critical' || $risk === 'high'),
                    ]);

                    $addedCount++;
                }
            } catch (\Exception $e) {
                Log::error("Gagal menarik berita RSS EWS dari {$sourceUrl}: " . $e->getMessage());
            }
        }

        return $addedCount;
    }

    /**
     * Pengecekan Mutlak Wilayah Hukum / Kerja Kodaeral V (Jawa Timur, Pelabuhan, Pesisir)
     */
    private function isKodaeralVLocation($text)
    {
        $text = strtolower($text);

        $kodaeralKeywords = [
            'kodaeral', 'lantamal v', 'lantamal 5', 'denintel', 'surabaya', 'tanjung perak', 
            'selat madura', 'gresik', 'sidoarjo', 'pasuruan', 'probolinggo', 'situbondo', 
            'banyuwangi', 'malang', 'tuban', 'lamongan', 'madura', 'bangkalan', 'sampang', 
            'pamekasan', 'sumenep', 'jawa timur', 'jatim', 'semarang', 'tanjung emas', 
            'cilacap', 'tegal', 'pekalongan', 'jepara', 'rembang', 'bali', 'benoa', 
            'gilimanuk', 'ntb', 'lembar', 'mataram', 'bima', 'koarmada ii', 'koarmada 2'
        ];

        foreach ($kodaeralKeywords as $kw) {
            if (str_contains($text, $kw)) {
                return true;
            }
        }

        return false;
    }

    private function generateExecutiveSummary($criticalCount, $negativeCount, $totalNews)
    {
        if ($criticalCount > 0) {
            return "PERINGATAN DINI (EWS): Pemindaian AI OSINT mendeteksi {$criticalCount} isu berisiko TINGGI/KRITIS khusus Wilayah Kerja Kodaeral V. Mayoritas dinamika terpusat pada bidang Pertahanan, Keamanan Maritim & Unjuk Rasa Warga. Disarankan peninjauan patroli intensif.";
        }
        return "SITUASI KONDUSIF: Pemantauan berita OSINT terkini menunjukkan dinamika Wilayah Kerja Kodaeral V (Surabaya, Selat Madura & Jatim) dalam keadaan stabil dan terkendali. Tidak ditemukan ancaman kritis hari ini.";
    }

    private function generateSummaryFromHeadline($title, $publisher, $category, $risk)
    {
        return "Ringkasan AI OSINT ({$publisher}): Berita dipublikasikan terkait Wilayah Kodaeral V pada bidang " . strtoupper($category) . " dengan tingkat risiko " . strtoupper($risk) . ". Memerlukan pemantauan berkala.";
    }

    private function determineCategory($text)
    {
        $text = strtolower($text);
        if (str_contains($text, 'politik') || str_contains($text, 'demo') || str_contains($text, 'pemilu') || str_contains($text, 'pilkada') || str_contains($text, 'dprd') || str_contains($text, 'bupati') || str_contains($text, 'walikota')) return 'politik';
        if (str_contains($text, 'ekonomi') || str_contains($text, 'pasar') || str_contains($text, 'harga') || str_contains($text, 'pelabuhan') || str_contains($text, 'ekspor') || str_contains($text, 'impor') || str_contains($text, 'saham')) return 'ekonomi';
        if (str_contains($text, 'budaya') || str_contains($text, 'warga') || str_contains($text, 'masyarakat') || str_contains($text, 'bansos') || str_contains($text, 'bencana') || str_contains($text, 'banjir')) return 'sosbud';
        if (str_contains($text, 'pancasila') || str_contains($text, 'paham') || str_contains($text, 'radikal') || str_contains($text, 'teror')) return 'ideologi';
        return 'hankam';
    }

    private function determineRisk($text)
    {
        $text = strtolower($text);
        if (str_contains($text, 'penyelundupan') || str_contains($text, 'bentrok') || str_contains($text, 'ancaman') || str_contains($text, 'kecelakaan') || str_contains($text, 'tenggelam') || str_contains($text, 'narkoba') || str_contains($text, 'teror')) return 'high';
        if (str_contains($text, 'demo') || str_contains($text, 'sengketa') || str_contains($text, 'protes') || str_contains($text, 'macet') || str_contains($text, 'sidang')) return 'medium';
        return 'low';
    }

    private function determineSentiment($text)
    {
        $text = strtolower($text);
        if (str_contains($text, 'sukses') || str_contains($text, 'aman') || str_contains($text, 'peresmian') || str_contains($text, 'bantuan') || str_contains($text, 'juara') || str_contains($text, 'prestasi')) return 'positive';
        if (str_contains($text, 'tenggelam') || str_contains($text, 'penyelundupan') || str_contains($text, 'demo') || str_contains($text, 'konflik') || str_contains($text, 'kecelakaan') || str_contains($text, 'korban')) return 'negative';
        return 'neutral';
    }

    private function determineLocation($text)
    {
        $text = strtolower($text);
        if (str_contains($text, 'tanjung perak')) return 'Tanjung Perak, Surabaya';
        if (str_contains($text, 'surabaya')) return 'Surabaya';
        if (str_contains($text, 'selat madura')) return 'Selat Madura';
        if (str_contains($text, 'gresik')) return 'Gresik';
        if (str_contains($text, 'sidoarjo')) return 'Sidoarjo';
        if (str_contains($text, 'banyuwangi')) return 'Banyuwangi';
        if (str_contains($text, 'madura') || str_contains($text, 'bangkalan') || str_contains($text, 'sampang') || str_contains($text, 'pamekasan') || str_contains($text, 'sumenep')) return 'Madura';
        if (str_contains($text, 'tuban')) return 'Tuban';
        if (str_contains($text, 'lamongan')) return 'Lamongan';
        if (str_contains($text, 'pasuruan')) return 'Pasuruan';
        if (str_contains($text, 'probolinggo')) return 'Probolinggo';
        if (str_contains($text, 'situbondo')) return 'Situbondo';
        if (str_contains($text, 'malang')) return 'Malang';
        if (str_contains($text, 'semarang') || str_contains($text, 'tanjung emas')) return 'Semarang (Kodaeral V)';
        if (str_contains($text, 'cilacap')) return 'Cilacap (Kodaeral V)';
        if (str_contains($text, 'bali') || str_contains($text, 'benoa') || str_contains($text, 'gilimanuk')) return 'Bali (Kodaeral V)';
        if (str_contains($text, 'ntb') || str_contains($text, 'lembar') || str_contains($text, 'mataram')) return 'NTB (Kodaeral V)';
        return 'Wilayah Kerja Kodaeral V';
    }
}
