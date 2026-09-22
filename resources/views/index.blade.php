<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Tagihan dan Pembayaran</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.7);
        }
      
        .modal.active {
            display: flex !important;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: #1e293b;
            margin: auto;
            padding: 0;
            border-radius: 0.75rem;
            width: 95%;
            max-width: 950px;
            max-height: 90vh;
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .modal-content::-webkit-scrollbar {
            width: 8px;
        }

        .modal-content::-webkit-scrollbar-track {
            background: #1e293b;
            border-radius: 0 0.75rem 0.75rem 0;
        }

        .modal-content::-webkit-scrollbar-thumb {
            background: #475569;
            border-radius: 4px;
        }

        .modal-content::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }

        .modal-content iframe {
            width: 100%;
            height: auto;
            min-height: 600px;
            border: none;
            display: block;
        }
        
        .light-mode .modal-content {
            background-color: #ffffff;
        }
        
        .light-mode .modal-content::-webkit-scrollbar-track {
            background: #ffffff;
        }
        
        .light-mode .modal-content::-webkit-scrollbar-thumb {
            background: #cbd5e1;
        }
        
        .light-mode .modal-content::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        .light-mode .modal-header {
            background-color: #f1f5f9 !important;
        }
        
        .light-mode .modal-content .modal-title {
            color: #1f2937 !important;
        }
        
        .light-mode .modal-content .modal-close {
            color: #1f2937 !important;
        }
        
        .light-mode .modal-content .modal-label {
            color: #6b7280 !important;
        }
        
        .light-mode .modal-content .modal-value {
            color: #1f2937 !important;
        }
        
        .light-mode .modal-content .modal-border {
            border-color: #e5e7eb !important;
        }
        
        .light-mode .modal-content .modal-btn {
            background-color: #e5e7eb !important;
            color: #1f2937 !important;
        }
        
        .light-mode .modal-content .modal-payment-text {
            color: #1f2937 !important;
        }
    </style>
</head>
<body class="bg-slate-900 transition-colors duration-300">
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <div class="flex justify-between items-start mb-8">
            <div>
                <h1 class="text-2xl font-bold text-white main-text mb-1">CEK TAGIHAN DAN PEMBAYARAN</h1>
                <h2 class="text-xl text-white main-text">DEMO CEK TAGIHAN ICT</h2>
            </div>
            <button onclick="toggleTheme()" class="flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-700 text-white hover:opacity-80 transition-opacity">
                <svg id="sunIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <svg id="moonIcon" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                </svg>
                <span id="themeText">Mode Terang</span>
            </button>
        </div>
        <div class="bg-slate-800 rounded-lg shadow-lg p-8 main-card">
            <form method="POST" action="{{ route('tagihan.cek') }}" id="billForm">
                @csrf
                <div class="mb-6">
                    <label class="block text-white label-text mb-2">Nomor Virtual Account <span class="text-red-500">*</span></label>
                    <input type="text" name="no_cust" id="noCust" placeholder="797766xxx" class="w-full px-4 py-3 rounded-lg border bg-slate-700 text-white border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500 input-field" required/>
                </div>
               <div class="mb-6">
    <label class="block text-white label-text mb-2">
        Tahun Akademik <span class="text-red-500">*</span>
    </label>

    <select id="academic_year" name="academic_year"
        class="w-full px-4 py-3 rounded-lg border bg-slate-700 text-white border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500 input-field">
        <option value="">Loading...</option>
    </select>
</div>
                <div class="mb-6">
                    <label class="block text-white label-text mb-3">Verifikasi Keamanan <span class="text-red-500">*</span></label>
                    <div id="turnstile-widget" class="flex justify-center mb-2"></div>
                    <input type="hidden" name="cf_turnstile_response" id="cfTurnstileResponse">
                </div>
                <button type="submit" name="submit" class="w-full bg-blue-600 text-white py-4 rounded-lg font-semibold hover:bg-blue-700 transition-colors flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Cek Tagihan
                </button>
            </form>
            <div class="mt-8 pt-8 border-t border-slate-600 border-section">
                <h3 class="text-center text-gray-400 secondary-text mb-6 uppercase text-sm font-semibold">PANDUAN</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <a href="{{url('/Gambar_Panduan_Bayar.jpeg')}}" target="_blank"
       class="bg-purple-600 hover:bg-purple-700 text-white py-4 px-6 rounded-lg font-semibold transition-colors flex items-center justify-center gap-2 shadow-sm">
        <svg class="w-5 h-5 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
        Panduan Pembayaran JPG
    </a>

    <a href="{{url('/Booklet_Panduan_Bayar.pdf')}}" target="_blank"
       class="bg-red-600 hover:bg-red-700 text-white py-4 px-6 rounded-lg font-semibold transition-colors flex items-center justify-center gap-2 shadow-sm">
        <svg class="w-5 h-5 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        Panduan Pembayaran PDF
    </a>
</div>

            </div>
        </div>

        @if(isset($result))
            @if($result['status'])
                <div class="mt-8 bg-slate-800 rounded-lg shadow-lg p-8 result-card">
                    <h2 class="text-2xl font-bold text-white main-text mb-6">Data Siswa</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                        <div>
                            <p class="text-gray-400 text-sm secondary-text">Nama</p>
                            <p class="text-white font-semibold text-lg main-text">{{ $result['data']['nama'] ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-sm secondary-text">Kelas</p>
                            <p class="text-white font-semibold text-lg main-text">{{ $result['data']['kelas'] ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-sm secondary-text">Angkatan</p>
<p class="text-white font-semibold text-lg main-text">{{ $academic_year ?: '-' }}</p>

                        </div>
                        <div>
                            <p class="text-gray-400 text-sm secondary-text">Saldo VA</p>
                            <p class="text-white font-semibold text-lg main-text">Rp. {{ number_format($result['data']['saldo'] ?? 0, 0, ',', '.') }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-sm secondary-text">NOVA</p>
                            <p class="text-white font-semibold text-lg main-text">{{ $result['data']['va_number'] ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-sm secondary-text">Jenjang</p>
                            <p class="text-white font-semibold text-lg main-text">{{ $result['data']['jenjang'] ?? '-' }}</p>
                        </div>
                    </div>
                    <div class="border-t border-slate-600 pt-6 border-section">
                        <h3 class="text-xl font-bold text-white main-text mb-4">Tagihan - {{ $academic_year ?: '-' }}</h3>
                        <div class="flex justify-between items-center mb-4 flex-wrap gap-4">
                            <div class="flex items-center gap-2">
                                <label class="text-white label-text text-sm">Tampilkan:</label>
                                <select id="tagihanPerPage" onchange="changeTagihanPerPage()" class="px-3 py-2 rounded-lg border bg-slate-700 text-white border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500 input-field text-sm">
                                    <option value="10">10</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <button onclick="showAllTagihan()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-sm font-semibold">
                                    Tampilkan Semua
                                </button>
                            </div>
                            <div id="tagihanInfo" class="text-sm text-gray-400 secondary-text"></div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="bg-slate-700 table-header">
                                        <th class="border border-slate-600 table-border px-4 py-3 text-center text-white main-text">
                                            <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)" class="w-4 h-4 cursor-pointer">
                                        </th>
                                        <th class="border border-slate-600 table-border px-4 py-3 text-left text-white main-text">NO</th>
                                        <th class="border border-slate-600 table-border px-4 py-3 text-left text-white main-text">NAMA TAGIHAN</th>
                                        <th class="border border-slate-600 table-border px-4 py-3 text-left text-white main-text">NOMINAL</th>
                                        <th class="border border-slate-600 table-border px-4 py-3 text-left text-white main-text">STATUS BAYAR</th>
                                        <th class="border border-slate-600 table-border px-4 py-3 text-left text-white main-text">TANGGAL BAYAR</th>
                                        <th class="border border-slate-600 table-border px-4 py-3 text-left text-white main-text">DETAIL</th>
                                    </tr>
                                </thead>
                                <tbody id="tagihanTableBody">
                                    @forelse($result['data']['tagihan'] as $i => $tagih)
                                        <tr data-index="{{ $i }}">
                                            <td class="border border-slate-600 table-border px-4 py-3 text-center">
                                                <input type="checkbox" name="selected_tagihan[]" value="{{ $tagih['id'] ?? $i }}" class="tagihan-checkbox w-4 h-4 cursor-pointer">
                                            </td>
                                            <td class="border border-slate-600 table-border px-4 py-3 text-white table-text">{{ $i+1 }}</td>
                                            <td class="border border-slate-600 table-border px-4 py-3 text-white table-text">{{ ucwords(str_replace('_', ' ', strtolower($tagih['nama_tagihan']))) }}</td>
                                            <td class="border border-slate-600 table-border px-4 py-3 text-white table-text">Rp. {{ number_format($tagih['total_tagihan'], 0, ',', '.') }}</td>
                                            <td class="border border-slate-600 table-border px-4 py-3 text-white table-text">{{ $tagih['PAIDST'] == '1' ? 'Lunas' : 'Belum Lunas' }}</td>
                                            <td class="border border-slate-600 table-border px-4 py-3 text-white table-text">
                                                {{ $tagih['PAIDDT'] ? \Carbon\Carbon::parse($tagih['PAIDDT'])->format('Y-m-d') : '-' }}
                                            </td>
                                            <td class="border border-slate-600 table-border px-4 py-3 text-center">
                                                <button type="button" onclick='showDetailModal(@json($tagih))' class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors text-sm">
                                                    Lihat
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="border border-slate-600 table-border px-4 py-8 text-center text-gray-400 secondary-text">Tidak ada data tersedia</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div id="tagihanPagination" class="flex justify-center gap-2 mt-4 flex-wrap"></div>
    </div>
                        <div class="border-t border-slate-600 pt-6 mt-8 border-section">
    <h3 class="text-xl font-bold text-white main-text mb-4">Tagihan Lunas - {{ $academic_year ?: '-' }}</h3>
    <div class="flex justify-between items-center mb-4 flex-wrap gap-4">
        <div class="flex items-center gap-2">
            <label class="text-white label-text text-sm">Tampilkan:</label>
            <select id="lunasPerPage" onchange="changeLunasPerPage()" class="px-3 py-2 rounded-lg border bg-slate-700 text-white border-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-500 input-field text-sm">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
            <button onclick="showAllLunas()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-sm font-semibold">
                Tampilkan Semua
            </button>
        </div>
        <div id="lunasInfo" class="text-sm text-gray-400 secondary-text"></div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full border-collapse">
            <thead>
                <tr class="bg-slate-700 table-header">
                    <th class="border border-slate-600 table-border px-4 py-3 text-left text-white main-text">NO</th>
                    <th class="border border-slate-600 table-border px-4 py-3 text-left text-white main-text">NAMA TAGIHAN</th>
                    <th class="border border-slate-600 table-border px-4 py-3 text-left text-white main-text">NOMINAL</th>
                    <th class="border border-slate-600 table-border px-4 py-3 text-left text-white main-text">TANGGAL BAYAR</th>
                    <th class="border border-slate-600 table-border px-4 py-3 text-left text-white main-text">DETAIL</th>
                </tr>
            </thead>
            <tbody id="lunasTableBody">
                @forelse($result['data']['tagihan_lunas'] ?? [] as $i => $tagih)
                    <tr data-index="{{ $i }}">
                        <td class="border border-slate-600 table-border px-4 py-3 text-white table-text text-center">{{ $i+1 }}</td>
                        <td class="border border-slate-600 table-border px-4 py-3 text-white table-text">{{ ucwords(str_replace('_',' ', strtolower($tagih['nama_tagihan']))) }}</td>
                        <td class="border border-slate-600 table-border px-4 py-3 text-white table-text">Rp. {{ number_format($tagih['total_tagihan'], 0, ',', '.') }}</td>
                        <td class="border border-slate-600 table-border px-4 py-3 text-white table-text">{{ $tagih['PAIDDT'] ? \Carbon\Carbon::parse($tagih['PAIDDT'])->format('Y-m-d') : '-' }}</td>
                        <td class="border border-slate-600 table-border px-4 py-3 text-center">
                            <button type="button" onclick='showDetailModal(@json($tagih))' class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors text-sm">
                                Lihat
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="border border-slate-600 table-border px-4 py-8 text-center text-gray-400 secondary-text">Tidak ada tagihan lunas</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div id="lunasPagination" class="flex justify-center gap-2 mt-4 flex-wrap"></div>
</div>

                        
                        <div class="mt-4 text-sm text-red-400">*Silahkan pilih salah satu atau beberapa tagihan untuk dibayarkan!</div>
                        <button onclick="showPaymentModal()" type="button" class="w-full mt-6 bg-green-600 text-white py-4 rounded-lg font-semibold hover:bg-green-700 transition-colors flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                            </svg>
                            Bayar Tagihan
                        </button>
                    </div>
                </div>
                <script>
                    setTimeout(() => {
                        window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
                    }, 100);
                </script>
            @else
                <div class="mt-8 bg-red-900 border border-red-700 rounded-lg p-4">
                    <p class="text-red-200">{{ $result['message'] ?? 'Data tidak ditemukan' }}</p>
                </div>
            @endif
        @endif

        <div class="text-center mt-8 text-gray-400 text-sm secondary-text">Copyright © 2024 PT. Inovasi Cipta Teknologi. All rights reserved.</div>
    </div>

    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="bg-slate-700 modal-header px-6 py-4 flex justify-between items-center rounded-t-lg">
                <h3 class="text-xl font-bold text-white modal-title">Detail Tagihan</h3>
                <button onclick="closeDetailModal()" class="text-white hover:text-gray-300 modal-close">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="p-6">
                <div class="mb-4">
                    <p class="text-gray-400 text-sm mb-1 modal-label">Nama Tagihan</p>
                    <p id="modalNamaTagihan" class="text-white font-semibold text-lg modal-value"></p>
                </div>
                <div class="mb-4">
                    <p class="text-gray-400 text-sm mb-1 modal-label">Tahun Akademik</p>
                    <p id="modalTahunAkademik" class="text-white font-semibold text-lg modal-value"></p>
                </div>
                <div id="modalDetailTable" class="overflow-x-auto">
                </div>
                <div class="mt-6 pt-4 border-t border-slate-600 modal-border">
                    <button onclick="closeDetailModal()" class="w-full bg-slate-600 text-white py-3 rounded-lg font-semibold hover:bg-slate-500 transition-colors modal-btn">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="bg-slate-700 modal-header px-6 py-4 flex justify-between items-center rounded-t-lg">
                <h3 class="text-xl font-bold text-white modal-title">Konfirmasi Pembayaran</h3>
                <button onclick="closePaymentModal()" class="text-white hover:text-gray-300 modal-close">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="p-6">
                <div id="paymentContent" class="modal-payment-content">
                </div>
            </div>
        </div>
    </div>

    <button onclick="window.scrollTo({ top: 0, behavior: 'smooth' })" class="fixed bottom-8 right-8 bg-blue-600 text-white p-4 rounded-full shadow-lg hover:bg-blue-700 transition-colors">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
        </svg>
    </button>
    
    <script>
        let currentTheme = 'dark';
        let turnstileToken = null;
        let tagihanCurrentPage = 1;
        let tagihanPerPage = 10;
        let tagihanShowAll = false;
        let lunasCurrentPage = 1;
        let lunasPerPage = 10;
        let lunasShowAll = false;

    document.addEventListener("DOMContentLoaded", function() {
    const select = document.getElementById("academic_year");

  fetch("/list-tahun-akademik")
        .then(response => response.json())
        .then(data => {
            select.innerHTML = ""; 

            const defaultOption = document.createElement("option");
            defaultOption.value = "all";
            defaultOption.textContent = "Semua Tahun Akademik";
            select.appendChild(defaultOption);

            if (data.status && data.data.length > 0) {
                data.data.forEach(item => {
                    const option = document.createElement("option");
                    option.value = item.thn_aka;
                    option.textContent = item.thn_aka;
                    select.appendChild(option);
                });
            } else {
                const option = document.createElement("option");
                option.textContent = "Tidak ada data tahun akademik";
                select.appendChild(option);
            }
        })
        .catch(error => {
            console.error("Gagal memuat data tahun akademik:", error);
            select.innerHTML = "<option>Error memuat data</option>";
        });

        initTagihanPagination();
        initLunasPagination();
});

        function initTurnstile() {
            const theme = currentTheme === 'light' ? 'light' : 'dark';
            const widget = document.getElementById('turnstile-widget');
            
            if (widget.innerHTML !== '') {
                return;
            }
            
            if (typeof turnstile !== 'undefined') {
                turnstile.render('#turnstile-widget', {
                    sitekey: @json(config('services.turnstile.site_key')),
                    theme: theme,
                    callback: function(token) {
                        turnstileToken = token;
                        document.getElementById('cfTurnstileResponse').value = token;
                    },
                    'error-callback': function() {
                        turnstileToken = null;
                        document.getElementById('cfTurnstileResponse').value = '';
                        alert('Verifikasi gagal! Silakan refresh halaman.');
                    },
                    'expired-callback': function() {
                        turnstileToken = null;
                        document.getElementById('cfTurnstileResponse').value = '';
                    }
                });
            } else {
                setTimeout(initTurnstile, 100);
            }
        }

        function initTagihanPagination() {
            const tbody = document.getElementById('tagihanTableBody');
            if (!tbody) return;
            
            const allRows = Array.from(tbody.querySelectorAll('tr[data-index]'));
            if (allRows.length === 0) return;

            paginateTagihan(allRows);
        }

        function initLunasPagination() {
            const tbody = document.getElementById('lunasTableBody');
            if (!tbody) return;
            
            const allRows = Array.from(tbody.querySelectorAll('tr[data-index]'));
            if (allRows.length === 0) return;

            paginateLunas(allRows);
        }

        function paginateTagihan(allRows) {
            const totalRows = allRows.length;
            const totalPages = tagihanShowAll ? 1 : Math.ceil(totalRows / tagihanPerPage);
            
            allRows.forEach(row => row.style.display = 'none');
            
            if (tagihanShowAll) {
                allRows.forEach(row => row.style.display = '');
            } else {
                const startIndex = (tagihanCurrentPage - 1) * tagihanPerPage;
                const endIndex = Math.min(startIndex + tagihanPerPage, totalRows);
                
                for (let i = startIndex; i < endIndex; i++) {
                    allRows[i].style.display = '';
                }
            }
            
            const startItem = tagihanShowAll ? 1 : (tagihanCurrentPage - 1) * tagihanPerPage + 1;
            const endItem = tagihanShowAll ? totalRows : Math.min(tagihanCurrentPage * tagihanPerPage, totalRows);
            
            const infoEl = document.getElementById('tagihanInfo');
            if (infoEl) {
                infoEl.textContent = `Menampilkan ${startItem} - ${endItem} dari ${totalRows} data`;
            }
            
            renderTagihanPagination(totalPages, totalRows);
        }

        function paginateLunas(allRows) {
            const totalRows = allRows.length;
            const totalPages = lunasShowAll ? 1 : Math.ceil(totalRows / lunasPerPage);
            
            allRows.forEach(row => row.style.display = 'none');
            
            if (lunasShowAll) {
                allRows.forEach(row => row.style.display = '');
            } else {
                const startIndex = (lunasCurrentPage - 1) * lunasPerPage;
                const endIndex = Math.min(startIndex + lunasPerPage, totalRows);
                
                for (let i = startIndex; i < endIndex; i++) {
                    allRows[i].style.display = '';
                }
            }
            
            const startItem = lunasShowAll ? 1 : (lunasCurrentPage - 1) * lunasPerPage + 1;
            const endItem = lunasShowAll ? totalRows : Math.min(lunasCurrentPage * lunasPerPage, totalRows);
            
            const infoEl = document.getElementById('lunasInfo');
            if (infoEl) {
                infoEl.textContent = `Menampilkan ${startItem} - ${endItem} dari ${totalRows} data`;
            }
            
            renderLunasPagination(totalPages, totalRows);
        }

        function renderTagihanPagination(totalPages, totalRows) {
            const paginationEl = document.getElementById('tagihanPagination');
            if (!paginationEl) return;
            
            if (tagihanShowAll || totalRows === 0) {
                paginationEl.innerHTML = '';
                return;
            }
            
            let html = '';
            
            if (tagihanCurrentPage > 1) {
                html += `<button onclick="goToTagihanPage(${tagihanCurrentPage - 1})" class="px-3 py-2 rounded-lg bg-slate-700 text-white hover:bg-slate-600 transition-colors text-sm">Prev</button>`;
            }
            
            const maxButtons = 5;
            let startPage = Math.max(1, tagihanCurrentPage - Math.floor(maxButtons / 2));
            let endPage = Math.min(totalPages, startPage + maxButtons - 1);
            
            if (endPage - startPage < maxButtons - 1) {
                startPage = Math.max(1, endPage - maxButtons + 1);
            }
            
            if (startPage > 1) {
                html += `<button onclick="goToTagihanPage(1)" class="px-3 py-2 rounded-lg bg-slate-700 text-white hover:bg-slate-600 transition-colors text-sm">1</button>`;
                if (startPage > 2) {
                    html += `<span class="px-3 py-2 text-white">...</span>`;
                }
            }
            
            for (let i = startPage; i <= endPage; i++) {
                const activeClass = i === tagihanCurrentPage ? 'bg-blue-600' : 'bg-slate-700';
                html += `<button onclick="goToTagihanPage(${i})" class="px-3 py-2 rounded-lg ${activeClass} text-white hover:bg-blue-500 transition-colors text-sm">${i}</button>`;
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    html += `<span class="px-3 py-2 text-white">...</span>`;
                }
                html += `<button onclick="goToTagihanPage(${totalPages})" class="px-3 py-2 rounded-lg bg-slate-700 text-white hover:bg-slate-600 transition-colors text-sm">${totalPages}</button>`;
            }
            
            if (tagihanCurrentPage < totalPages) {
                html += `<button onclick="goToTagihanPage(${tagihanCurrentPage + 1})" class="px-3 py-2 rounded-lg bg-slate-700 text-white hover:bg-slate-600 transition-colors text-sm">Next</button>`;
            }
            
            paginationEl.innerHTML = html;
        }

        function renderLunasPagination(totalPages, totalRows) {
            const paginationEl = document.getElementById('lunasPagination');
            if (!paginationEl) return;
            
            if (lunasShowAll || totalRows === 0) {
                paginationEl.innerHTML = '';
                return;
            }
            
            let html = '';
            
            if (lunasCurrentPage > 1) {
                html += `<button onclick="goToLunasPage(${lunasCurrentPage - 1})" class="px-3 py-2 rounded-lg bg-slate-700 text-white hover:bg-slate-600 transition-colors text-sm">Prev</button>`;
            }
            
            const maxButtons = 5;
            let startPage = Math.max(1, lunasCurrentPage - Math.floor(maxButtons / 2));
            let endPage = Math.min(totalPages, startPage + maxButtons - 1);
            
            if (endPage - startPage < maxButtons - 1) {
                startPage = Math.max(1, endPage - maxButtons + 1);
            }
            
            if (startPage > 1) {
                html += `<button onclick="goToLunasPage(1)" class="px-3 py-2 rounded-lg bg-slate-700 text-white hover:bg-slate-600 transition-colors text-sm">1</button>`;
                if (startPage > 2) {
                    html += `<span class="px-3 py-2 text-white">...</span>`;
                }
            }
            
            for (let i = startPage; i <= endPage; i++) {
                const activeClass = i === lunasCurrentPage ? 'bg-blue-600' : 'bg-slate-700';
                html += `<button onclick="goToLunasPage(${i})" class="px-3 py-2 rounded-lg ${activeClass} text-white hover:bg-blue-500 transition-colors text-sm">${i}</button>`;
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    html += `<span class="px-3 py-2 text-white">...</span>`;
                }
                html += `<button onclick="goToLunasPage(${totalPages})" class="px-3 py-2 rounded-lg bg-slate-700 text-white hover:bg-slate-600 transition-colors text-sm">${totalPages}</button>`;
            }
            
            if (lunasCurrentPage < totalPages) {
                html += `<button onclick="goToLunasPage(${lunasCurrentPage + 1})" class="px-3 py-2 rounded-lg bg-slate-700 text-white hover:bg-slate-600 transition-colors text-sm">Next</button>`;
            }
            
            paginationEl.innerHTML = html;
        }

        function goToTagihanPage(page) {
            tagihanCurrentPage = page;
            const tbody = document.getElementById('tagihanTableBody');
            const allRows = Array.from(tbody.querySelectorAll('tr[data-index]'));
            paginateTagihan(allRows);
        }

        function goToLunasPage(page) {
            lunasCurrentPage = page;
            const tbody = document.getElementById('lunasTableBody');
            const allRows = Array.from(tbody.querySelectorAll('tr[data-index]'));
            paginateLunas(allRows);
        }

        function changeTagihanPerPage() {
            const selectEl = document.getElementById('tagihanPerPage');
            tagihanPerPage = parseInt(selectEl.value);
            tagihanCurrentPage = 1;
            tagihanShowAll = false;
            const tbody = document.getElementById('tagihanTableBody');
            const allRows = Array.from(tbody.querySelectorAll('tr[data-index]'));
            paginateTagihan(allRows);
        }

        function changeLunasPerPage() {
            const selectEl = document.getElementById('lunasPerPage');
            lunasPerPage = parseInt(selectEl.value);
            lunasCurrentPage = 1;
            lunasShowAll = false;
            const tbody = document.getElementById('lunasTableBody');
            const allRows = Array.from(tbody.querySelectorAll('tr[data-index]'));
            paginateLunas(allRows);
        }

        function showAllTagihan() {
            tagihanShowAll = true;
            const tbody = document.getElementById('tagihanTableBody');
            const allRows = Array.from(tbody.querySelectorAll('tr[data-index]'));
            paginateTagihan(allRows);
        }

        function showAllLunas() {
            lunasShowAll = true;
            const tbody = document.getElementById('lunasTableBody');
            const allRows = Array.from(tbody.querySelectorAll('tr[data-index]'));
            paginateLunas(allRows);
        }

        function toggleTheme() {
            const body = document.body;
            const sunIcon = document.getElementById('sunIcon');
            const moonIcon = document.getElementById('moonIcon');
            const themeText = document.getElementById('themeText');
            
            if (currentTheme === 'dark') {
                currentTheme = 'light';
                body.classList.add('light-mode');
                body.classList.remove('bg-slate-900');
                body.classList.add('bg-gray-100');
                sunIcon.classList.add('hidden');
                moonIcon.classList.remove('hidden');
                themeText.textContent = 'Mode Gelap';
                
                document.querySelectorAll('.main-card, .result-card').forEach(el => {
                    el.classList.remove('bg-slate-800');
                    el.classList.add('bg-white');
                });
                
                document.querySelectorAll('.input-field').forEach(el => {
                    el.classList.remove('bg-slate-700', 'text-white', 'border-slate-600');
                    el.classList.add('bg-white', 'text-gray-900', 'border-gray-300');
                });
                
                document.querySelectorAll('.label-text').forEach(el => {
                    el.classList.remove('text-white');
                    el.classList.add('text-gray-900');
                });
                
                document.querySelectorAll('.main-text').forEach(el => {
                    el.classList.remove('text-white');
                    el.classList.add('text-gray-900');
                });
                
                document.querySelectorAll('.secondary-text').forEach(el => {
                    el.classList.remove('text-gray-400');
                    el.classList.add('text-gray-600');
                });
                
                document.querySelectorAll('.border-section').forEach(el => {
                    el.classList.remove('border-slate-600');
                    el.classList.add('border-gray-300');
                });
                
                document.querySelectorAll('.table-header').forEach(el => {
                    el.classList.remove('bg-slate-700');
                    el.classList.add('bg-gray-200');
                });
                
                document.querySelectorAll('.table-border').forEach(el => {
                    el.classList.remove('border-slate-600');
                    el.classList.add('border-gray-300');
                });
                
                document.querySelectorAll('.table-text').forEach(el => {
                    el.classList.remove('text-white');
                    el.classList.add('text-gray-900');
                });
                
                saveThemePreference();
                resetTurnstile();
            } else {
                currentTheme = 'dark';
                body.classList.remove('light-mode');
                body.classList.remove('bg-gray-100');
                body.classList.add('bg-slate-900');
                sunIcon.classList.remove('hidden');
                moonIcon.classList.add('hidden');
                themeText.textContent = 'Mode Terang';
                
                document.querySelectorAll('.main-card, .result-card').forEach(el => {
                    el.classList.remove('bg-white');
                    el.classList.add('bg-slate-800');
                });
                
                document.querySelectorAll('.input-field').forEach(el => {
                    el.classList.remove('bg-white', 'text-gray-900', 'border-gray-300');
                    el.classList.add('bg-slate-700', 'text-white', 'border-slate-600');
                });
                
                document.querySelectorAll('.label-text').forEach(el => {
                    el.classList.remove('text-gray-900');
                    el.classList.add('text-white');
                });
                
                document.querySelectorAll('.main-text').forEach(el => {
                    el.classList.remove('text-gray-900');
                    el.classList.add('text-white');
                });
                
                document.querySelectorAll('.secondary-text').forEach(el => {
                    el.classList.remove('text-gray-600');
                    el.classList.add('text-gray-400');
                });
                
                document.querySelectorAll('.border-section').forEach(el => {
                    el.classList.remove('border-gray-300');
                    el.classList.add('border-slate-600');
                });
                
                document.querySelectorAll('.table-header').forEach(el => {
                    el.classList.remove('bg-gray-200');
                    el.classList.add('bg-slate-700');
                });
                
                document.querySelectorAll('.table-border').forEach(el => {
                    el.classList.remove('border-gray-300');
                    el.classList.add('border-slate-600');
                });
                
                document.querySelectorAll('.table-text').forEach(el => {
                    el.classList.remove('text-gray-900');
                    el.classList.add('text-white');
                });
                
                saveThemePreference();
                resetTurnstile();
            }
        }

        function resetTurnstile() {
            if (typeof turnstile !== 'undefined') {
                const widget = document.getElementById('turnstile-widget');
                widget.innerHTML = '';
                turnstileToken = null;
                document.getElementById('cfTurnstileResponse').value = '';
                setTimeout(() => {
                    initTurnstile();
                }, 100);
            }
        }

        function toggleSelectAll(checkbox) {
            const tbody = document.getElementById('tagihanTableBody');
            const visibleCheckboxes = Array.from(tbody.querySelectorAll('tr[data-index]'))
                .filter(row => row.style.display !== 'none')
                .map(row => row.querySelector('.tagihan-checkbox'));
            
            visibleCheckboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
        }

       function showDetailModal(tagihan) {
    const modal = document.getElementById('detailModal');
    const namaTagihan = document.getElementById('modalNamaTagihan');
    const tahunAkademik = document.getElementById('modalTahunAkademik');
    const detailTable = document.getElementById('modalDetailTable');
    const formattedName = tagihan.nama_tagihan ? tagihan.nama_tagihan.toLowerCase().replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : '-';
    namaTagihan.textContent = formattedName;
    tahunAkademik.textContent = tagihan.tahun_akademik_tagihan || '-';
    let tableHTML = '<table class="w-full border-collapse mt-4">';
    tableHTML += '<thead><tr class="modal-table-header">';
    tableHTML += '<th class="border modal-table-border px-4 py-2 text-left modal-table-text">Detail</th>';
    tableHTML += '<th class="border modal-table-border px-4 py-2 text-left modal-table-text">Nominal</th>';
    tableHTML += '</tr></thead><tbody>';
    if (Array.isArray(tagihan.detail) && tagihan.detail.length > 0) {
        tagihan.detail.forEach(d => {
            tableHTML += '<tr>';
            tableHTML += `<td class="border modal-table-border px-4 py-2 modal-table-cell">${d.akun_detail || '-'}</td>`;
            tableHTML += `<td class="border modal-table-border px-4 py-2 modal-table-cell">Rp. ${parseInt(d.nominal_detail || 0).toLocaleString('id-ID')}</td>`;
            tableHTML += '</tr>';
        });
    } else {
        tableHTML += '<tr><td colspan="2" class="border modal-table-border px-4 py-2 text-center modal-table-cell text-gray-400">Tidak ada detail tagihan</td></tr>';
    }
    tableHTML += '</tbody></table>';
    detailTable.innerHTML = tableHTML;
    const isLight = typeof currentTheme !== 'undefined' && currentTheme === 'light';
    const headerClass = isLight ? 'bg-gray-200' : 'bg-slate-700';
    const borderClass = isLight ? 'border-gray-300' : 'border-slate-600';
    const textClass = isLight ? 'text-gray-900' : 'text-white';
    document.querySelectorAll('.modal-table-header').forEach(el => el.classList.add(headerClass));
    document.querySelectorAll('.modal-table-border').forEach(el => el.classList.add(borderClass));
    document.querySelectorAll('.modal-table-text').forEach(el => el.classList.add(textClass));
    document.querySelectorAll('.modal-table-cell').forEach(el => el.classList.add(textClass));
    modal.classList.add('active');
}

        function closeDetailModal() {
            const modal = document.getElementById('detailModal');
            modal.classList.remove('active');
        }
function showPaymentModal() {
    const tbody = document.getElementById('tagihanTableBody');
    const visibleRows = Array.from(tbody.querySelectorAll('tr[data-index]')).filter(row => row.style.display !== 'none');
    const checkboxes = visibleRows.map(row => row.querySelector('.tagihan-checkbox:checked')).filter(cb => cb);
    
    if (checkboxes.length === 0) {
        alert('Silakan pilih minimal satu tagihan untuk dibayar!');
        return;
    }

    const selectedTagihan = [];
    const allTagihanData = @json($result['data']['tagihan'] ?? []);

    checkboxes.forEach(checkbox => {
        const tagihanId = checkbox.value;
        const fullData = allTagihanData.find((item, idx) => {
            return (item.id && item.id == tagihanId) || idx == tagihanId;
        });

        if (fullData) {
            selectedTagihan.push({
                id: fullData.id || tagihanId,
                AA: fullData.AA || 0,
                nama_tagihan: fullData.nama_tagihan,
                total_tagihan: fullData.total_tagihan,
                tahun_akademik_tagihan: fullData.tahun_akademik_tagihan || '{{ $result["data"]["tahun_akademik"] ?? "" }}',
                periode: fullData.periode || fullData.PERIOD || '',
                status: fullData.PAIDST == '1' ? 'Lunas' : 'Belum Bayar',
                tanggal_bayar: fullData.PAIDDT || '-',
                akun: fullData.akun || '-',
                detail_amount: fullData.detail_amount || fullData.total_tagihan
            });
        }
    });

    const siswaData = {
    nama: '{{ $result["data"]["nama"] ?? "" }}',
    kelas: '{{ $result["data"]["kelas"] ?? "" }}',
    va_number: '{{ $result["data"]["va_number"] ?? "" }}'.replace(/^797766/, ''),
    tahun_akademik: '{{ $result["data"]["tahun_akademik"] ?? "" }}',
    jenjang: '{{ $result["data"]["jenjang"] ?? "" }}',
    saldo: '{{ $result["data"]["saldo"] ?? 0 }}',
    nis: '{{ $result["data"]["nis"] ?? "" }}'
};


    sessionStorage.setItem('siswa_data', JSON.stringify(siswaData));
    sessionStorage.setItem('selected_tagihan', JSON.stringify(selectedTagihan));

    const modal = document.getElementById('paymentModal');
    const content = document.getElementById('paymentContent');

    content.innerHTML = '<p class="text-center modal-payment-text">Loading view pembayaran...</p>';

    if (currentTheme === 'light') {
        document.querySelectorAll('.modal-payment-text').forEach(el => {
            el.classList.add('text-gray-900');
        });
    } else {
        document.querySelectorAll('.modal-payment-text').forEach(el => {
            el.classList.add('text-white');
        });
    }

    modal.classList.add('active');
setTimeout(() => {
    content.innerHTML = '<iframe src="{{url('/tagihan/view')}}" style="width:100%; height:85vh; border:none; border-radius:0.5rem;"></iframe>';
}, 500);
}


        function closePaymentModal() {
            const modal = document.getElementById('paymentModal');
            modal.classList.remove('active');
        }

        window.onclick = function(event) {
            const detailModal = document.getElementById('detailModal');
            const paymentModal = document.getElementById('paymentModal');
            if (event.target == detailModal) {
                closeDetailModal();
            }
            if (event.target == paymentModal) {
                closePaymentModal();
            }
        }

        document.getElementById('billForm').addEventListener('submit', function(e) {
            if (!turnstileToken) {
                e.preventDefault();
                alert('Silakan selesaikan verifikasi keamanan terlebih dahulu!');
                return false;
            }
        });

        function loadThemePreference() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'light') {
                toggleTheme();
            }
        }

        function saveThemePreference() {
            localStorage.setItem('theme', currentTheme);
        }

        window.onload = function() {
            loadThemePreference();
            initTurnstile();
        };
    </script>
</body>
</html>