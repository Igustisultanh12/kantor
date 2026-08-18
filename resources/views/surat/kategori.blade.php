<x-app-layout>
    <div class="flex h-[calc(100vh-65px)] bg-[#09090b] text-zinc-300">
        
        <div class="w-64 bg-[#121214] border-r border-zinc-800 p-6 flex flex-col">
            <h3 class="text-[10px] font-bold text-zinc-500 uppercase tracking-[0.2em] mb-8">Admin Panel</h3>
            <nav class="space-y-4">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 text-sm text-zinc-500 hover:text-white transition">
                    <span></span> Dashboard POS
                </a>
                <a href="{{ route('kategori.index') }}" class="flex items-center gap-3 text-sm text-white font-semibold">
                    <span class="p-1.5 bg-blue-600 rounded-md text-xs"></span> Pengaturan Kategori
                </a>
            </nav>
        </div>

        <div class="flex-1 p-10 overflow-y-auto">
            <div class="max-w-6xl mx-auto space-y-10">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-[#121214] border border-zinc-800 p-6 rounded-3xl">
                        <p class="text-xs text-zinc-500 font-medium">Total Sub-Kategori</p>
                        <h4 class="text-3xl font-bold mt-2 text-white">{{ $totalSub }}</h4>
                    </div>
                    <div class="bg-[#121214] border border-zinc-800 p-6 rounded-3xl col-span-2 flex justify-between items-center">
                        <div>
                            <p class="text-xs text-zinc-500 font-medium">Update Terakhir</p>
                            <h4 class="text-xl font-semibold mt-1 text-zinc-300">{{ $lastUpdate ? $lastUpdate->diffForHumans() : '-' }}</h4>
                        </div>
                        <button onclick="document.getElementById('modalAdd').showModal()" class="bg-white text-black px-6 py-3 rounded-2xl font-bold text-sm hover:bg-zinc-200 transition">
                            + Tambah Sub
                        </button>
                    </div>
                </div>

                <div class="bg-[#121214] border border-zinc-800 rounded-3xl overflow-hidden shadow-2xl">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-zinc-900/50 border-b border-zinc-800">
                                <th class="px-8 py-5 text-[10px] font-bold text-zinc-500 uppercase tracking-widest">Kategori Utama</th>
                                <th class="px-8 py-5 text-[10px] font-bold text-zinc-500 uppercase tracking-widest">Nama Sub</th>
                                <th class="px-8 py-5 text-[10px] font-bold text-zinc-500 uppercase tracking-widest">Kode Unik</th>
                                <th class="px-8 py-5 text-[10px] font-bold text-zinc-500 uppercase tracking-widest text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800/50">
                            @foreach($subCategories as $sub)
                            <tr class="group hover:bg-zinc-800/20 transition-all">
                                <td class="px-8 py-6">
                                    <span class="text-xs bg-zinc-900 text-zinc-400 px-3 py-1 rounded-full border border-zinc-800">{{ $sub->category->name }}</span>
                                </td>
                                <td class="px-8 py-6 font-medium text-zinc-200">{{ $sub->name }}</td>
                                <td class="px-8 py-6 font-mono text-blue-400 text-sm">{{ $sub->sub_code }}</td>
                                <td class="px-8 py-6 text-right">
                                    <form action="{{ route('kategori.destroy', $sub->id) }}" method="POST" onsubmit="return confirm('Hapus kategori ini?')">
                                        @csrf @method('DELETE')
                                        <button class="text-zinc-600 hover:text-red-500 transition"></button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

    <dialog id="modalAdd" class="bg-[#121214] border border-zinc-800 rounded-3xl p-8 text-zinc-300 backdrop:backdrop-blur-sm w-full max-w-md">
        <h3 class="text-xl font-bold mb-6">Tambah Sub Kategori</h3>
        <form action="{{ route('kategori.store') }}" method="POST" class="space-y-5">
            @csrf
            <div>
                <label class="text-[10px] font-bold text-zinc-500 uppercase">Kategori Utama</label>
                <select name="letter_category_id" class="w-full mt-2 bg-[#09090b] border-zinc-800 rounded-xl focus:ring-blue-500">
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-[10px] font-bold text-zinc-500 uppercase">Nama Sub Kategori</label>
                <input type="text" name="name" required class="w-full mt-2 bg-[#09090b] border-zinc-800 rounded-xl focus:ring-blue-500" placeholder="Contoh: Keuangan">
            </div>
            <div>
                <label class="text-[10px] font-bold text-zinc-500 uppercase">Kode Unik (Sub-Code)</label>
                <input type="text" name="sub_code" required class="w-full mt-2 bg-[#09090b] border-zinc-800 rounded-xl focus:ring-blue-500" placeholder="Contoh: KEU">
            </div>
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="document.getElementById('modalAdd').close()" class="flex-1 py-3 border border-zinc-800 rounded-xl hover:bg-zinc-800 transition">Batal</button>
                <button type="submit" class="flex-1 py-3 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-500 transition">Simpan</button>
            </div>
        </form>
    </dialog>
</x-app-layout>