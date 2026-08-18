<x-app-layout>
    <div class="flex h-[calc(100vh-65px)] bg-[#09090b] text-zinc-300"x-data="{ selectedSub: null, nextNumber: 'Pilih kategori...', isLoading: false }">
         
        
        <div class="w-80 bg-[#121214] border-r border-zinc-800 flex flex-col">
            <div class="p-6 border-b border-zinc-800">
                <h3 class="text-xs font-bold text-zinc-500 uppercase tracking-widest">Kategori Surat</h3>
            </div>
            <div class="flex-1 overflow-y-auto p-4 space-y-2">
                @foreach($subCategories as $sub)
                <button 
                    @click="selectedSub = {{ $sub->id }}; fetchNomor({{ $sub->id }})"
                    :class="selectedSub === {{ $sub->id }} ? 'bg-blue-600 text-white shadow-lg shadow-blue-900/20' : 'hover:bg-zinc-800 text-zinc-400'"class="w-full text-left px-4 py-4 rounded-xl transition-all duration-200 group flex justify-between items-center">
                    <div>
                        <div class="text-sm font-semibold group-hover:text-white">{{ $sub->name }}</div>
                        <div class="text-[10px] opacity-50">{{ $sub->category->name }}</div>
                    </div>
                    <span class="text-[10px] bg-black/30 px-2 py-1 rounded text-zinc-500">{{ $sub->sub_code }}</span>
                </button>
                @endforeach
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-8">
            <div class="max-w-6xl mx-auto grid grid-cols-12 gap-8">
                
                <div class="col-span-12 lg:col-span-5">
                    <div class="bg-[#121214] border border-zinc-800 rounded-3xl p-8 shadow-2xl sticky top-0">
                        <form action="{{ route('surat.store') }}" method="POST" class="space-y-6">
                            @csrf
                            <input type="hidden" name="sub_category_id" :value="selectedSub">
                            
                            <div>
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">Nomor Surat Tergenerate</label>
                                <div class="mt-2 p-5 bg-[#09090b] border border-zinc-800 rounded-2xl text-2xl font-mono text-blue-400 tracking-tighter"x-text="nextNumber">
                                </div>
                            </div>

                            <div>
                                <label class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">Perihal / Subjek</label>
                                <textarea name="subject" required 
                                    class="mt-2 w-full bg-[#09090b] border border-zinc-800 rounded-2xl text-zinc-200 focus:border-blue-500 focus:ring-0 placeholder-zinc-700"rows="4" placeholder="Ketik perihal surat di sini..."></textarea>
                            </div>

                            <button type="submit" :disabled="!selectedSub || isLoading"class="w-full py-5 bg-white text-black font-black rounded-2xl hover:bg-zinc-200 disabled:opacity-20 transition-all uppercase tracking-widest text-sm"> Simpan Arsip
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-7">
                    <div class="bg-[#121214] border border-zinc-800 rounded-3xl overflow-hidden">
                        <div class="p-6 border-b border-zinc-800 flex justify-between items-center bg-zinc-900/30">
                            <h3 class="text-sm font-bold tracking-tight">Riwayat Penomoran</h3>
                            <span class="text-[10px] bg-zinc-800 px-2 py-1 rounded text-zinc-500">10 Terakhir</span>
                        </div>
                        <div class="divide-y divide-zinc-800">
                            @foreach($letters as $l)
                            <div class="p-6 hover:bg-zinc-800/30 transition flex justify-between items-start group">
                                <div class="space-y-1">
                                    <div class="text-sm font-mono text-blue-400 group-hover:text-blue-300">{{ $l->letter_number }}</div>
                                    <div class="text-xs text-zinc-500 line-clamp-1">{{ $l->subject }}</div>
                                </div>
                                <div class="text-[10px] text-zinc-700 font-medium">
                                    {{ $l->created_at->diffForHumans() }}
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script> function fetchNomor(id) {
            fetch(`/get-nomor/${id}`)
                .then(res => res.json())
                .then(data => {
                    const el = document.querySelector('[x-data]');
                    el.__x.$data.nextNumber = data.full_number;
                });
        }
    </script>
</x-app-layout>