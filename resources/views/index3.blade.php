<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('partials.brand-head')
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}&r={{ time() }}">
<script>
(function(){
  try {
    if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
  } catch (e) {}
})();
window.__pwaDeferredPrompt = null;
window.__pwaInstallReady = false;
window.addEventListener('beforeinstallprompt', function (e) {
  e.preventDefault();
  window.__pwaDeferredPrompt = e;
  window.__pwaInstallReady = true;
  window.dispatchEvent(new Event('pwa-install-ready'));
});
if (window.__BRAND__ && window.__BRAND__.pwa && 'serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {});
}
</script>
@if(config('services.turnstile.enabled'))
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<script>
function notify(message, type) {
  type = type || 'info';
  var dark = document.documentElement.classList.contains('dark');
  var b = window.__BRAND__ || {};
  var bg = {
    success: dark ? (b.primaryDark || '#5ecf84') : (b.primary || '#1b6b3a'),
    error: '#c62828',
    warning: '#b45309',
    info: dark ? (b.surfaceDark || '#18241e') : '#1a2420'
  };
  var color = (type === 'success' && dark) ? '#0a1a10' : '#fff';
  if (typeof Toastify === 'undefined') {
    console[(type === 'error') ? 'error' : 'log'](message);
    return;
  }
  Toastify({
    text: String(message || ''),
    duration: type === 'error' ? 4800 : 3400,
    gravity: 'top',
    position: 'right',
    close: true,
    stopOnFocus: true,
    escapeMarkup: false,
    style: {
      background: bg[type] || bg.info,
      color: color,
      borderRadius: '4px',
      boxShadow: '0 2px 10px rgba(0,0,0,.18)',
      fontFamily: "'Plus Jakarta Sans', 'Segoe UI', Tahoma, sans-serif",
      fontSize: '13px',
      maxWidth: 'min(420px, 92vw)',
      padding: '10px 14px'
    }
  }).showToast();
}
function notifyTitle(title, text, type) {
  var msg = text ? ('<b>' + title + '</b><br>' + text) : ('<b>' + title + '</b>');
  notify(msg, type || 'info');
}
function notifyWarn(title, text) { notifyTitle(title, text, 'warning'); }
function notifyError(title, text) { notifyTitle(title, text, 'error'); }
function notifyOk(title, text) { notifyTitle(title, text, 'success'); }
</script>
</head>
<body>
@php
  $brand = config('brand');
  $brandFooter = $brand['footer'] ?: ('Copyright ' . date('Y') . ' ' . $brand['name']);
  $showGuide = !empty($brand['show_guide']);
@endphp
<div class="page-bg"></div>
<div class="app-shell {{ $showGuide ? 'has-guide' : 'no-guide' }}">
  <div class="topbar">
    <div class="brand-wrap">
      <img src="{{ asset($brand['logo']) }}" alt="{{ $brand['name'] }}" class="brand-logo">
      <div class="brand-text">
        <h1 class="brand-name">{{ $brand['name'] }}</h1>
        <p class="brand-tagline">{{ $brand['tagline'] }}</p>
      </div>
    </div>
    <div class="topbar-actions">
      <button class="icon-btn" onclick="toggleTheme()" id="themeBtn" type="button" aria-label="Ubah tema">
        <svg id="themeIcon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
        <span class="theme-label" id="themeLabel">Mode gelap</span>
      </button>
      <button class="icon-btn accent" id="installBtn" type="button" aria-label="Install aplikasi" title="Install aplikasi">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        <span class="theme-label">Install</span>
      </button>
    </div>
  </div>

  <div class="app-body">
  <div class="app-main">
  <div class="card" id="akunCard" @if(isset($result) && !empty($result['status'])) hidden @endif>
    <form method="POST" action="/" id="billForm">
      @csrf
      <div class="section-title">Informasi akun</div>
      <div class="form-grid">
        <div class="field">
          <label>Userlogin <em>*</em></label>
          <input type="text" name="no_cust" id="noCust" inputmode="numeric" autocomplete="username" placeholder="Masukkan userlogin" value="{{ old('no_cust', $va ?? '') }}" required>
        </div>
        <div class="field">
          <label>Password <em>*</em></label>
          <div class="pw-wrap">
            <input type="password" name="password" id="password" autocomplete="current-password" placeholder="Masukkan password" required>
            <button type="button" class="pw-toggle" id="togglePassword" aria-label="Tampilkan password">
              <svg id="iconEye" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              <svg id="iconEyeOff" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" hidden><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a10.05 10.05 0 012.223-3.444M6.18 6.18A9.966 9.966 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.974 9.974 0 01-4.245 5.253M3 3l18 18"/></svg>
            </button>
          </div>
        </div>
      </div>
      <input type="hidden" name="academic_year" id="academic_year" value="all">
      @if(config('services.turnstile.enabled'))
      <div class="field">
        <label>Verifikasi keamanan <em>*</em></label>
        <div class="turnstile-area">
          <div id="turnstile-widget"></div>
          <input type="hidden" name="cf_turnstile_response" id="cfToken">
        </div>
      </div>
      @endif
      <button type="submit" name="submit" class="submit-btn">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Cek tagihan
      </button>
      @if($showGuide)
      <div class="mobile-guide" style="display:none;margin-top:.85rem;padding-top:.85rem;border-top:1px solid var(--border)">
        <div class="aside-links">
          @if(!empty($brand['guide_pdf']) && file_exists(public_path($brand['guide_pdf'])))
          <a class="aside-link" href="{{ asset($brand['guide_pdf']) }}" target="_blank" rel="noopener">Unduh booklet pembayaran</a>
          @endif
          @if(!empty($brand['guide_image']) && file_exists(public_path($brand['guide_image'])))
          <a class="aside-link" href="{{ asset($brand['guide_image']) }}" target="_blank" rel="noopener">Lihat gambar panduan</a>
          @endif
        </div>
      </div>
      @endif
    </form>

    @if(session('error'))
    <script>
    document.addEventListener('DOMContentLoaded', function () {
      notifyTitle('Gagal', @json(session('error')), 'error');
    });
    </script>
    @endif
    @if(session('success'))
    <script>
    document.addEventListener('DOMContentLoaded', function () {
      notifyTitle('Berhasil', @json(session('success')), 'success');
    });
    </script>
    @endif
  </div>

  @if(isset($result))
    @if($result['status'])
      <div class="card" id="resultSection">
        <div class="siswa-head">
          <div class="section-title">Data siswa</div>
          <div class="tbl-controls">
            <button type="button" class="btn-logout" id="btnLogout" onclick="logoutAkun()">Logout</button>
            <button type="button" class="btn-showall" id="btnMultiAkun" onclick="openMultiAkunModal()">Multi akun</button>
          </div>
        </div>
        <div class="student-grid">
          <div class="sf"><label>Nama</label><p>{{ $result['data']['nama'] ?? '-' }}</p></div>
          <div class="sf"><label>Kelas</label><p>{{ $result['data']['kelas'] ?? '-' }}</p></div>
          <div class="sf"><label>Angkatan</label><p>{{ ($academic_year ?? 'all') === 'all' ? 'Semua' : $academic_year }}</p></div>
          <div class="sf"><label>Saldo VA</label><p>Rp {{ number_format($result['data']['saldo'] ?? 0, 0, ',', '.') }}</p></div>
          <div class="sf"><label>NOVA</label><p class="sf-nova" title="{{ $result['data']['va_number'] ?? '-' }}">{{ $result['data']['va_number'] ?? '-' }}</p></div>
          <div class="sf"><label>Jenjang</label><p>{{ $result['data']['jenjang'] ?? '-' }}</p></div>
        </div>

        <div class="divider"></div>

        <div class="tbl-bar">
          <div class="tbl-title">Tagihan aktif - {{ ($academic_year ?? 'all') === 'all' ? 'Semua tahun akademik' : $academic_year }}</div>
          <div class="tbl-controls">
            <select id="tagihanPerPage" onchange="changeTagihanPerPage()" aria-label="Jumlah data">
              <option value="10">10</option>
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
            <button class="btn-showall" type="button" onclick="showAllTagihan()">Tampilkan semua</button>
          </div>
        </div>
        <div id="tagihanInfo" class="tbl-info" style="margin-bottom:.75rem"></div>
        <div class="tbl-wrap excel-wrap">
          <table class="excel-tbl">
            <thead>
              <tr>
                <th class="sticky-l sticky-l0"><input type="checkbox" class="chk" id="selectAll" onclick="toggleSelectAll(this)" aria-label="Pilih semua"></th>
                <th class="sticky-l sticky-l1">No</th>
                <th class="sticky-l sticky-l2">Tagihan</th>
                <th>Periode</th>
                <th>Nominal</th>
                <th>Sisa</th>
                <th>Dibayar</th>
                <th>Cicil</th>
                <th class="sticky-r">Bayar</th>
                <th>Exp Date</th>
              </tr>
            </thead>
            <tbody id="tagihanTableBody">
              @forelse($result['data']['tagihan'] as $i => $tagih)
              @php
                $bolehCicil = (int)($tagih['isINSTALLABLE'] ?? $tagih['isinstallable'] ?? $tagih['is_installment'] ?? 0) === 1;
                $sudahBayar = (int)($tagih['sudah_dibayar'] ?? 0);
                $totalTagih = (int)($tagih['total_tagihan'] ?? 0);
                $sisaTagih = (int)($tagih['sisa_tagihan'] ?? max(0, $totalTagih - $sudahBayar));
                $expRaw = $tagih['ExpDate'] ?? $tagih['expdate'] ?? null;
                $expLabel = (!empty($expRaw) && !str_starts_with((string) $expRaw, '0000-00-00'))
                  ? \Carbon\Carbon::parse($expRaw)->format('Y-m-d')
                  : '-';
              @endphp
              <tr data-index="{{ $i }}">
                <td class="sticky-l sticky-l0">
                  <input type="checkbox" class="chk tagihan-checkbox" value="{{ $tagih['AA'] ?? '' }}" data-index="{{ $i }}" {{ $sisaTagih <= 0 ? 'disabled' : '' }} aria-label="Pilih tagihan">
                </td>
                <td class="col-no sticky-l sticky-l1">{{ $i+1 }}</td>
                <td class="sticky-l sticky-l2">{{ ucwords(str_replace('_', ' ', strtolower($tagih['nama_tagihan']))) }}</td>
                <td>{{ $tagih['periode'] ?: '-' }}</td>
                <td class="money">Rp&nbsp;{{ number_format($totalTagih, 0, ',', '.') }}</td>
                <td class="money">Rp&nbsp;{{ number_format($sisaTagih, 0, ',', '.') }}</td>
                <td class="money">Rp&nbsp;{{ number_format($sudahBayar, 0, ',', '.') }}</td>
                <td class="col-cicil">
                  @if($bolehCicil)
                    <span class="badge badge-cicil">Ya</span>
                  @else
                    <span class="badge badge-no-cicil">Tidak</span>
                  @endif
                </td>
                <td class="sticky-r">
                  <input type="number" class="pay-input bayar-input" data-index="{{ $i }}" min="1" max="{{ $sisaTagih }}" value="0" disabled inputmode="numeric" aria-label="Nominal bayar">
                </td>
                <td>{{ $expLabel }}</td>
              </tr>
              @empty
              <tr><td colspan="10" class="empty-note">Tidak ada data tersedia</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <label class="select-all-mobile">
          <input type="checkbox" class="chk" id="selectAllMobile" onclick="toggleSelectAll(this)"> Pilih semua
        </label>
        <div class="card-list" id="tagihanCardList">
          @forelse($result['data']['tagihan'] as $i => $tagih)
          @php
            $bolehCicil = (int)($tagih['isINSTALLABLE'] ?? $tagih['isinstallable'] ?? $tagih['is_installment'] ?? 0) === 1;
            $sudahBayar = (int)($tagih['sudah_dibayar'] ?? 0);
            $totalTagih = (int)($tagih['total_tagihan'] ?? 0);
            $sisaTagih = (int)($tagih['sisa_tagihan'] ?? max(0, $totalTagih - $sudahBayar));
            $expRaw = $tagih['ExpDate'] ?? $tagih['expdate'] ?? null;
            $expLabel = (!empty($expRaw) && !str_starts_with((string) $expRaw, '0000-00-00'))
              ? \Carbon\Carbon::parse($expRaw)->format('Y-m-d')
              : '-';
          @endphp
          <article class="bill-card" data-index="{{ $i }}">
            <div class="bill-card-top">
              <label class="bill-check">
                <input type="checkbox" class="chk tagihan-checkbox" value="{{ $tagih['AA'] ?? '' }}" data-index="{{ $i }}" {{ $sisaTagih <= 0 ? 'disabled' : '' }}>
                <span>Pilih</span>
              </label>
              @if($bolehCicil)
                <span class="badge badge-cicil">Bisa dicicil</span>
              @else
                <span class="badge badge-no-cicil">Tidak dicicil</span>
              @endif
            </div>
            <div class="bill-card-main">
              <h3>{{ ucwords(str_replace('_', ' ', strtolower($tagih['nama_tagihan']))) }}</h3>
              <p class="bill-amount">Rp {{ number_format($totalTagih, 0, ',', '.') }}</p>
            </div>
            <div class="bill-facts">
              <div class="bill-fact"><span>Periode</span><b>{{ $tagih['periode'] ?: '-' }}</b></div>
              <div class="bill-fact"><span>Exp</span><b>{{ $expLabel }}</b></div>
              <div class="bill-fact"><span>Sisa</span><b>Rp {{ number_format($sisaTagih, 0, ',', '.') }}</b></div>
              <div class="bill-fact"><span>Dibayar</span><b>Rp {{ number_format($sudahBayar, 0, ',', '.') }}</b></div>
            </div>
            <div class="bill-pay-row">
              <label class="bill-pay-label" for="bayarCard{{ $i }}">Nominal bayar</label>
              <input id="bayarCard{{ $i }}" type="number" class="pay-input bayar-input" data-index="{{ $i }}" min="1" max="{{ $sisaTagih }}" value="0" disabled inputmode="numeric" aria-label="Nominal bayar">
            </div>
          </article>
          @empty
          <div class="empty-note">Tidak ada data tersedia</div>
          @endforelse
        </div>
        <div id="tagihanPagination" class="pagination"></div>

        @if(!empty($result['data']['tagihan']))
        <p class="pay-note">*Pilih tagihan yang akan dibayar. Yang tidak bisa dicicil harus dibayar sesuai sisa. Yang bisa dicicil, nominal bayar tidak boleh melebihi sisa tagihan.</p>
        <div class="pay-summary" id="paySummary">
          <span id="paySummaryText">0 tagihan dipilih</span>
          <b id="paySummaryTotal">Rp 0</b>
        </div>
        <button type="button" class="pay-btn" id="btnBayar" onclick="showPaymentModal()">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
          Bayar tagihan
        </button>
        @endif

        <div class="divider"></div>

        <div class="tbl-bar">
          <div class="tbl-title">Tagihan lunas - {{ ($academic_year ?? 'all') === 'all' ? 'Semua tahun akademik' : $academic_year }}</div>
          <div class="tbl-controls">
            <select id="lunasPerPage" onchange="changeLunasPerPage()" aria-label="Jumlah data">
              <option value="10">10</option>
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
            <button class="btn-showall" type="button" onclick="showAllLunas()">Tampilkan semua</button>
          </div>
        </div>
        <div id="lunasInfo" class="tbl-info" style="margin-bottom:.75rem"></div>
        <div class="tbl-wrap">
          <table>
            <thead>
              <tr>
                <th>No</th>
                <th>Urutan</th>
                <th>Tagihan</th>
                <th>Periode</th>
                <th>Nominal</th>
                <th>Tgl bayar</th>
                <th>Detail</th>
              </tr>
            </thead>
            <tbody id="lunasTableBody">
              @forelse($result['data']['tagihan_lunas'] ?? [] as $i => $tagih)
              <tr data-index="{{ $i }}">
                <td>{{ $i+1 }}</td>
                <td>{{ $tagih['FURUTAN'] ?? '-' }}</td>
                <td>{{ ucwords(str_replace('_', ' ', strtolower($tagih['nama_tagihan']))) }}</td>
                <td>{{ $tagih['periode'] ?: '-' }}</td>
                <td class="money">Rp&nbsp;{{ number_format($tagih['total_tagihan'], 0, ',', '.') }}</td>
                <td>{{ !empty($tagih['PAIDDT']) ? \Carbon\Carbon::parse($tagih['PAIDDT'])->format('Y-m-d') : '-' }}</td>
                <td><button type="button" class="btn-detail" onclick="showLunasDetail({{ $i }})">Lihat</button></td>
              </tr>
              @empty
              <tr><td colspan="7" class="empty-note">Tidak ada tagihan lunas</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="card-list" id="lunasCardList">
          @forelse($result['data']['tagihan_lunas'] ?? [] as $i => $tagih)
          <article class="bill-card bill-card-lunas" data-index="{{ $i }}">
            <div class="bill-card-top">
              <span class="badge badge-paid">Lunas</span>
              <button type="button" class="btn-detail" onclick="showLunasDetail({{ $i }})">Detail</button>
            </div>
            <div class="bill-card-main">
              <h3>{{ ucwords(str_replace('_', ' ', strtolower($tagih['nama_tagihan']))) }}</h3>
              <p class="bill-amount">Rp {{ number_format($tagih['total_tagihan'], 0, ',', '.') }}</p>
            </div>
            <div class="bill-facts">
              <div class="bill-fact"><span>Periode</span><b>{{ $tagih['periode'] ?: '-' }}</b></div>
              <div class="bill-fact"><span>Urutan</span><b>{{ $tagih['FURUTAN'] ?? '-' }}</b></div>
              <div class="bill-fact bill-fact-wide"><span>Tgl bayar</span><b>{{ !empty($tagih['PAIDDT']) ? \Carbon\Carbon::parse($tagih['PAIDDT'])->format('d M Y') : '-' }}</b></div>
            </div>
          </article>
          @empty
          <div class="empty-note">Tidak ada tagihan lunas</div>
          @endforelse
        </div>
        <div id="lunasPagination" class="pagination"></div>
      </div>
      <script>setTimeout(()=>{document.getElementById('resultSection').scrollIntoView({behavior:'smooth',block:'start'})},100);</script>
    @else
      <div class="error-box">
        <p>{{ $result['message'] ?? 'Data tidak ditemukan' }}</p>
      </div>
    @endif
  @endif

    <div class="footer">{{ $brandFooter }}</div>
  </div>{{-- /app-main --}}

  @if($showGuide)
  <aside class="app-aside" aria-label="Panduan">
    <div class="card aside-card">
      <div class="aside-body">
        <h2>Panduan pembayaran</h2>
        <p>Ikuti langkah di gambar, atau buka booklet PDF untuk petunjuk lengkap.</p>
        @if(!empty($brand['guide_image']) && file_exists(public_path($brand['guide_image'])))
        <a class="aside-guide" href="{{ asset($brand['guide_image']) }}" target="_blank" rel="noopener">
          <img src="{{ asset($brand['guide_image']) }}" alt="Panduan pembayaran">
        </a>
        @endif
        <div class="aside-links">
          @if(!empty($brand['guide_pdf']) && file_exists(public_path($brand['guide_pdf'])))
          <a class="aside-link" href="{{ asset($brand['guide_pdf']) }}" target="_blank" rel="noopener">Unduh booklet PDF</a>
          @endif
          @if(!empty($brand['guide_image']) && file_exists(public_path($brand['guide_image'])))
          <a class="aside-link" href="{{ asset($brand['guide_image']) }}" target="_blank" rel="noopener">Lihat gambar panduan</a>
          @endif
        </div>
        <div class="aside-meta">
          <strong>Tips</strong>
          Simpan VA pembayaran sampai status tagihan berubah menjadi lunas. Install aplikasi untuk akses lebih cepat di HP.
        </div>
      </div>
    </div>
  </aside>
  @endif
  </div>{{-- /app-body --}}
</div>{{-- /app-shell --}}

<div id="detailModal" class="modal-bg">
  <div class="modal-box">
    <div class="modal-head">
      <h3>Detail tagihan</h3>
      <button class="modal-x" onclick="closeDetailModal()" aria-label="Tutup" type="button">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <div class="modal-row"><span class="modal-row-lbl">Nama tagihan</span><span class="modal-row-val" id="mNama"></span></div>
      <div class="modal-row" id="mTahunRow" hidden><span class="modal-row-lbl">Tahun akademik</span><span class="modal-row-val" id="mTahun"></span></div>
      <div class="modal-row"><span class="modal-row-lbl">Periode</span><span class="modal-row-val" id="mPeriode"></span></div>
      <div class="modal-row"><span class="modal-row-lbl">Tgl bayar</span><span class="modal-row-val" id="mPaidDt"></span></div>
      <div class="modal-row"><span class="modal-row-lbl">Exp Date</span><span class="modal-row-val" id="mExpDate"></span></div>
      <div id="mDetailTable"></div>
    </div>
    <div class="modal-foot">
      <button class="btn-close-full" onclick="closeDetailModal()" type="button">Tutup</button>
    </div>
  </div>
</div>

<div id="paymentModal" class="modal-bg">
  <div class="modal-box pay-modal">
    <div class="modal-head">
      <h3>Bayar Tagihan</h3>
      <button class="modal-x" onclick="closePaymentModal()" aria-label="Tutup" type="button">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body" id="paymentBody"></div>
    <div class="modal-foot" id="paymentFoot"></div>
  </div>
</div>

<div id="installModal" class="modal-bg">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-head">
      <h3>Install aplikasi</h3>
      <button class="modal-x" onclick="closeInstallModal()" aria-label="Tutup" type="button">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <div class="install-hero">
        <img src="{{ asset(config('brand.logo')) }}" alt="{{ config('brand.short_name') }}">
        <h4>{{ config('brand.short_name') }}</h4>
        <p id="installModalText">Pasang aplikasi ke perangkat Anda agar lebih cepat dibuka dan mudah diakses.</p>
      </div>
    </div>
    <div class="modal-foot" style="display:flex;flex-direction:column;gap:8px">
      <button type="button" class="btn-install-now" id="btnInstallNow">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        Install sekarang
      </button>
      <button class="btn-close-full" onclick="closeInstallModal()" type="button">Nanti saja</button>
    </div>
  </div>
</div>

@if(isset($result) && !empty($result['status']))
@php
  $activeNoCust = \App\Http\Controllers\TagihanController::normalizeVa($result['data']['no_cust'] ?? ($va ?? ''));
  $multiAccounts = collect($multiAccounts ?? []);
  if ($multiAccounts->isEmpty()) {
    $multiAccounts = collect([[
      'no_cust' => $activeNoCust,
      'va_display' => $va ?? ($result['data']['va_number'] ?? $activeNoCust),
      'nama' => $result['data']['nama'] ?? '-',
      'kelas' => $result['data']['kelas'] ?? '-',
      'jenjang' => $result['data']['jenjang'] ?? '-',
      'is_active' => true,
    ]]);
  }
@endphp
<div id="multiAkunModal" class="modal-bg">
  <div class="modal-box modal-sm">
    <div class="modal-head">
      <h3>Multi akun</h3>
      <button class="modal-x" onclick="closeMultiAkunModal()" aria-label="Tutup" type="button">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p class="ma-section-label">Daftar akun</p>
      <div id="maError" class="ma-error"></div>
      <div id="maSuccess" class="ma-success"></div>
      <div class="ma-list" id="maList">
        @foreach($multiAccounts as $acc)
          @php $isActive = !empty($acc['is_active']); @endphp
          <div
            class="ma-item {{ $isActive ? 'is-active' : '' }}"
            data-no-cust="{{ $acc['no_cust'] ?? '' }}"
            @if($isActive) aria-current="true" @endif
          >
            <div class="ma-item-main">
              <p class="ma-item-name">{{ $acc['nama'] ?? '-' }}</p>
              <p class="ma-item-meta">{{ $acc['kelas'] ?? '-' }} · VA {{ $acc['va_display'] ?? ($acc['no_cust'] ?? '-') }}</p>
            </div>
            <div class="ma-item-right">
              <span class="badge {{ $isActive ? 'badge-active' : 'badge-inactive' }}">{{ $isActive ? 'Aktif' : 'Nonaktif' }}</span>
              @if(count($multiAccounts) > 1)
              <button type="button" class="ma-del" data-hapus-no-cust="{{ $acc['no_cust'] ?? '' }}" title="Hapus dari multi akun" aria-label="Hapus dari multi akun">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              </button>
              @endif
            </div>
          </div>
        @endforeach
      </div>

      <div class="divider" style="margin:1rem 0"></div>
      <p class="ma-section-label">Tambah akun</p>
      <form id="multiAkunForm" onsubmit="return submitTambahMultiAkun(event)">
        @csrf
        <div class="field">
          <label>Userlogin <em>*</em></label>
          <input type="text" name="no_cust" id="maNoCust" placeholder="Masukkan userlogin" required autocomplete="username">
        </div>
        <div class="field">
          <label>Password <em>*</em></label>
          <div class="pw-wrap">
            <input type="password" name="password" id="maPassword" placeholder="Masukkan password" required autocomplete="current-password">
            <button type="button" class="pw-toggle" id="maTogglePassword" aria-label="Tampilkan password">
              <svg id="maIconEye" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              <svg id="maIconEyeOff" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" hidden><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
            </button>
          </div>
        </div>
        <input type="hidden" name="academic_year" id="maAcademicYear" value="all">
        <button type="submit" class="submit-btn" id="maSubmitBtn">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
          Tambah akun
        </button>
      </form>
    </div>
    <div class="modal-foot">
      <button class="btn-close-full" onclick="closeMultiAkunModal()" type="button">Tutup</button>
    </div>
  </div>
</div>

<form id="multiAkunSwitchForm" method="POST" action="{{ route('multi-akun.switch') }}" style="display:none">
  @csrf
  <input type="hidden" name="no_cust" id="maSwitchNoCust">
  <input type="hidden" name="academic_year" id="maSwitchYear" value="all">
</form>
@endif

<form id="logoutForm" method="POST" action="{{ route('logout') }}" style="display:none">
  @csrf
</form>

<button class="scroll-top" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Scroll ke atas" type="button">
  <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
</button>

<script>
let currentTheme = (typeof localStorage !== 'undefined' && localStorage.getItem('theme') === 'dark') ? 'dark' : 'light';
let turnstileEnabled = @json((bool) config('services.turnstile.enabled'));
let turnstileToken = turnstileEnabled ? null : 'bypass';
let tagihanPage = 1, tagihanPerPageVal = 10, tagihanAll = false;
let lunasPage = 1, lunasPerPageVal = 10, lunasAll = false;

const ICON_MOON = '<path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>';
const ICON_SUN = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>';

function syncThemeUI(theme) {
  currentTheme = theme;
  const lbl = document.getElementById('themeLabel');
  const icon = document.getElementById('themeIcon');
  const meta = document.getElementById('metaThemeColor');
  const b = window.__BRAND__ || {};
  if (theme === 'dark') {
    lbl.textContent = 'Mode terang';
    icon.innerHTML = ICON_SUN;
    if (meta) meta.setAttribute('content', b.theme || '#14532d');
  } else {
    lbl.textContent = 'Mode gelap';
    icon.innerHTML = ICON_MOON;
    if (meta) meta.setAttribute('content', b.theme || '#14532d');
  }
}

function applyTheme(theme, resetWidget) {
  document.documentElement.classList.toggle('dark', theme === 'dark');
  localStorage.setItem('theme', theme);
  syncThemeUI(theme);
  if (resetWidget) resetTurnstile();
}

function toggleTheme() {
  applyTheme(currentTheme === 'light' ? 'dark' : 'light', true);
}

function initTurnstile() {
  if (!turnstileEnabled) return;
  const w = document.getElementById('turnstile-widget');
  if (!w) return;
  w.innerHTML = '';
  turnstileToken = null;
  const tokenEl = document.getElementById('cfToken');
  if (tokenEl) tokenEl.value = '';
  if (typeof turnstile === 'undefined') { setTimeout(initTurnstile, 150); return; }
  turnstile.render('#turnstile-widget', {
    sitekey: @json(config('services.turnstile.site_key')),
    theme: currentTheme === 'dark' ? 'dark' : 'light',
    size: window.innerWidth < 420 ? 'compact' : 'normal',
    retry: 'auto',
    callback: t => { turnstileToken = t; const el = document.getElementById('cfToken'); if (el) el.value = t; },
    'error-callback': () => { turnstileToken = null; const el = document.getElementById('cfToken'); if (el) el.value = ''; },
    'expired-callback': () => { turnstileToken = null; const el = document.getElementById('cfToken'); if (el) el.value = ''; }
  });
}

function resetTurnstile() {
  if (!turnstileEnabled) return;
  if (typeof turnstile !== 'undefined') {
    const w = document.getElementById('turnstile-widget');
    if (w) w.innerHTML = '';
    setTimeout(initTurnstile, 100);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  syncThemeUI(currentTheme);
  document.documentElement.classList.toggle('dark', currentTheme === 'dark');

  const togglePw = document.getElementById('togglePassword');
  if (togglePw) {
    togglePw.addEventListener('click', () => {
      const field = document.getElementById('password');
      const eye = document.getElementById('iconEye');
      const eyeOff = document.getElementById('iconEyeOff');
      const show = field.getAttribute('type') === 'password';
      field.setAttribute('type', show ? 'text' : 'password');
      togglePw.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
      if (eye) eye.hidden = show;
      if (eyeOff) eyeOff.hidden = !show;
    });
  }

  const s = document.getElementById('academic_year');
  if (s && !s.value) s.value = 'all';
  initTagihanPagination();
  initLunasPagination();
});

window.onload = initTurnstile;

function getRows(id) {
  const el = document.getElementById(id);
  return el ? Array.from(el.querySelectorAll('tr[data-index]')) : [];
}

function syncCards(tableBodyId, rows) {
  const cardList = document.getElementById(tableBodyId.replace('TableBody', 'CardList'));
  if (!cardList) return;
  const vis = {};
  rows.forEach(r => { vis[r.dataset.index] = r.style.display; });
  cardList.querySelectorAll('[data-index]').forEach(c => {
    c.style.display = Object.prototype.hasOwnProperty.call(vis, c.dataset.index) ? vis[c.dataset.index] : 'none';
  });
}

function paginateRows(rows, page, perPage, showAll, infoId, paginationFn, tableBodyId) {
  const total = rows.length;
  rows.forEach(r => r.style.display = 'none');
  if (showAll) { rows.forEach(r => r.style.display = ''); }
  else {
    const start = (page - 1) * perPage;
    for (let i = start; i < Math.min(start + perPage, total); i++) rows[i].style.display = '';
  }
  if (tableBodyId) syncCards(tableBodyId, rows);
  const s2 = showAll ? 1 : (page - 1) * perPage + 1;
  const e2 = showAll ? total : Math.min(page * perPage, total);
  const inf = document.getElementById(infoId);
  if (inf) inf.textContent = `Menampilkan ${s2} - ${e2} dari ${total} data`;
  paginationFn(showAll ? 1 : Math.ceil(total / perPage), total);
}

function renderPagination(id, totalPages, totalRows, showAll, getCurrent, goTo) {
  const el = document.getElementById(id);
  if (!el) return;
  if (showAll || totalRows === 0) { el.innerHTML = ''; return; }
  const cur = getCurrent();
  let h = '';
  if (cur > 1) h += `<button class="pg-btn" type="button" onclick="${goTo}(${cur-1})"><</button>`;
  const max = 5;
  let sp = Math.max(1, cur - Math.floor(max/2));
  let ep = Math.min(totalPages, sp + max - 1);
  if (ep - sp < max - 1) sp = Math.max(1, ep - max + 1);
  if (sp > 1) { h += `<button class="pg-btn" type="button" onclick="${goTo}(1)">1</button>`; if (sp > 2) h += `<span style="padding:5px 4px;color:var(--text3)">...</span>`; }
  for (let i = sp; i <= ep; i++) h += `<button class="pg-btn${i === cur ? ' pg-active' : ''}" type="button" onclick="${goTo}(${i})">${i}</button>`;
  if (ep < totalPages) { if (ep < totalPages - 1) h += `<span style="padding:5px 4px;color:var(--text3)">...</span>`; h += `<button class="pg-btn" type="button" onclick="${goTo}(${totalPages})">${totalPages}</button>`; }
  if (cur < totalPages) h += `<button class="pg-btn" type="button" onclick="${goTo}(${cur+1})">&gt;</button>`;
  el.innerHTML = h;
}

function initTagihanPagination() {
  const rows = getRows('tagihanTableBody');
  if (rows.length) paginateRows(rows, tagihanPage, tagihanPerPageVal, tagihanAll, 'tagihanInfo', (tp, tr) => renderPagination('tagihanPagination', tp, tr, tagihanAll, () => tagihanPage, 'goToTagihanPage'), 'tagihanTableBody');
}

function initLunasPagination() {
  const rows = getRows('lunasTableBody');
  if (rows.length) paginateRows(rows, lunasPage, lunasPerPageVal, lunasAll, 'lunasInfo', (tp, tr) => renderPagination('lunasPagination', tp, tr, lunasAll, () => lunasPage, 'goToLunasPage'), 'lunasTableBody');
}

function goToTagihanPage(p) { tagihanPage = p; paginateRows(getRows('tagihanTableBody'), tagihanPage, tagihanPerPageVal, tagihanAll, 'tagihanInfo', (tp, tr) => renderPagination('tagihanPagination', tp, tr, tagihanAll, () => tagihanPage, 'goToTagihanPage'), 'tagihanTableBody'); }
function goToLunasPage(p) { lunasPage = p; paginateRows(getRows('lunasTableBody'), lunasPage, lunasPerPageVal, lunasAll, 'lunasInfo', (tp, tr) => renderPagination('lunasPagination', tp, tr, lunasAll, () => lunasPage, 'goToLunasPage'), 'lunasTableBody'); }

function changeTagihanPerPage() { tagihanPerPageVal = parseInt(document.getElementById('tagihanPerPage').value); tagihanPage = 1; tagihanAll = false; initTagihanPagination(); }
function changeLunasPerPage() { lunasPerPageVal = parseInt(document.getElementById('lunasPerPage').value); lunasPage = 1; lunasAll = false; initLunasPagination(); }
function showAllTagihan() { tagihanAll = true; initTagihanPagination(); }
function showAllLunas() { lunasAll = true; initLunasPagination(); }

function formatPeriode(value) {
  const raw = String(value || '').trim();
  if (!raw || raw === '-') return '-';
  const m = raw.match(/^(\d{4})[-]?(\d{2})/);
  if (m) {
    const names = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    const bulan = parseInt(m[2], 10);
    if (bulan >= 1 && bulan <= 12) return names[bulan - 1] + ' ' + m[1];
  }
  return raw;
}

function formatExpDate(value) {
  if (!value || String(value).indexOf('0000-00-00') === 0) return '-';
  const d = new Date(value);
  if (isNaN(d.getTime())) {
    const raw = String(value);
    return raw.length >= 10 ? raw.slice(0, 10) : raw;
  }
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return y + '-' + m + '-' + day;
}

function expDateOf(item) {
  return item?.ExpDate || item?.exp_date || item?.expdate || null;
}

function earliestExpDate(items) {
  const dates = (items || []).map(expDateOf).filter(v => v && String(v).indexOf('0000-00-00') !== 0);
  if (!dates.length) return null;
  return dates.slice().sort()[0];
}

function showLunasDetail(index) {
  const tahunRow = document.getElementById('mTahunRow');
  if (tahunRow) tahunRow.hidden = true;
  showDetailModal(tagihanLunas[index] || {});
}

function showDetailModal(tagihan) {
  document.getElementById('mNama').textContent = tagihan.nama_tagihan ? tagihan.nama_tagihan.toLowerCase().replace(/_/g,' ').replace(/\b\w/g, l => l.toUpperCase()) : '-';
  document.getElementById('mTahun').textContent = tagihan.tahun_akademik_tagihan || '-';
  const periodeEl = document.getElementById('mPeriode');
  if (periodeEl) periodeEl.textContent = tagihan.periode || '-';
  const paidEl = document.getElementById('mPaidDt');
  if (paidEl) paidEl.textContent = formatExpDate(tagihan.PAIDDT || tagihan.paiddt || null);
  const expEl = document.getElementById('mExpDate');
  if (expEl) expEl.textContent = formatExpDate(expDateOf(tagihan));
  let details = Array.isArray(tagihan.detail) ? tagihan.detail.slice() : [];
  if (!details.length && (tagihan.PAIDDT || tagihan.PAIDST === '1' || tagihan.paidst === '1')) {
    details = [{
      sumber: 'tran',
      trxdate: tagihan.PAIDDT || tagihan.paiddt || '-',
      metode: 'Lunas',
      noreff: tagihan.periode || '-',
      akun_detail: tagihan.nama_tagihan || 'Pembayaran',
      nominal_detail: tagihan.total_tagihan || tagihan.sudah_dibayar || 0
    }];
  }
  const isTran = details.some(d => d.sumber === 'tran' || d.trxdate || d.TRXDATE);
  const th = 'padding:9px 12px;text-align:left;font-size:11px;font-weight:700;letter-spacing:.05em;color:var(--text3);text-transform:uppercase;border-bottom:1px solid var(--border)';
  const td = 'padding:9px 12px;border-bottom:1px solid var(--border);font-size:13px;color:var(--text)';
  let t = '<table style="width:100%;border-collapse:collapse;margin-top:.75rem"><thead><tr style="background:var(--surface2)">';
  if (isTran) {
    t += `<th style="${th}">Tanggal</th><th style="${th}">Metode</th><th style="${th}">Ref</th><th style="${th};text-align:right">Nominal</th></tr></thead><tbody>`;
    if (details.length) {
      details.forEach(d => {
        t += `<tr>
          <td style="${td}">${esc(formatExpDate(d.trxdate || d.TRXDATE))}</td>
          <td style="${td}">${esc(d.metode || d.akun_detail || '-')}</td>
          <td style="${td}">${esc(d.noreff || d.transno || '-')}</td>
          <td style="${td};text-align:right;font-weight:650">Rp ${parseInt(d.nominal_detail||0).toLocaleString('id-ID')}</td>
        </tr>`;
      });
    } else {
      t += `<tr><td colspan="4" style="padding:1.5rem;text-align:center;color:var(--text3);font-size:13px">Tidak ada rincian</td></tr>`;
    }
  } else {
    t += `<th style="${th}">Komponen</th><th style="${th};text-align:right">Nominal</th></tr></thead><tbody>`;
    if (details.length) {
      details.forEach(d => {
        t += `<tr><td style="${td}">${esc(d.akun_detail||'-')}</td><td style="${td};text-align:right;font-weight:650">Rp ${parseInt(d.nominal_detail||0).toLocaleString('id-ID')}</td></tr>`;
      });
    } else {
      t += `<tr><td colspan="2" style="padding:1.5rem;text-align:center;color:var(--text3);font-size:13px">Tidak ada rincian</td></tr>`;
    }
  }
  t += '</tbody></table>';
  document.getElementById('mDetailTable').innerHTML = t;
  document.getElementById('detailModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeDetailModal() {
  document.getElementById('detailModal').classList.remove('open');
  document.body.style.overflow = '';
}

window.addEventListener('click', e => {
  if (e.target.id === 'detailModal') closeDetailModal();
  if (e.target.id === 'paymentModal') closePaymentModal();
  if (e.target.id === 'multiAkunModal') closeMultiAkunModal();
  if (e.target.id === 'installModal') closeInstallModal();
});

document.getElementById('billForm').addEventListener('submit', e => {
  if (turnstileEnabled && !turnstileToken) { e.preventDefault(); notifyWarn('Verifikasi', 'Selesaikan verifikasi keamanan terlebih dahulu!'); }
});

const tagihanAktif = @json(isset($result['data']['tagihan']) ? $result['data']['tagihan'] : []);
const tagihanLunas = @json(isset($result['data']['tagihan_lunas']) ? $result['data']['tagihan_lunas'] : []);
const siswaBayar = {
  id: @json($result['data']['id'] ?? null),
  nama: @json($result['data']['nama'] ?? ''),
  kelas: @json($result['data']['kelas'] ?? ''),
  no_cust: @json($result['data']['no_cust'] ?? ''),
  num2nd: @json($result['data']['num2nd'] ?? ''),
  va_number: @json($result['data']['va_number'] ?? ''),
  tahun_akademik: @json($academic_year ?? ''),
  jenjang: @json($result['data']['jenjang'] ?? ''),
  saldo: @json($result['data']['saldo'] ?? 0)
};
const generateVaUrl = @json(route('generate-va'));
const generateQrisUrl = @json(route('generate-qris'));
const paymentQrisEnabled = !!(window.__BRAND__ && window.__BRAND__.paymentQris);
const multiAkunTambahUrl = @json(route('multi-akun.tambah'));
const multiAkunHapusUrl = @json(route('multi-akun.hapus'));
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
let multiAkunAccounts = @json(isset($result) && !empty($result['status']) ? ($multiAccounts ?? []) : []);

function openMultiAkunModal() {
  const modal = document.getElementById('multiAkunModal');
  if (!modal) return;
  clearMaMessages();
  modal.classList.add('open');
  document.body.style.overflow = 'hidden';
  const yearEl = document.getElementById('maAcademicYear');
  if (yearEl) yearEl.value = 'all';
}

function closeMultiAkunModal() {
  const modal = document.getElementById('multiAkunModal');
  if (!modal) return;
  modal.classList.remove('open');
  document.body.style.overflow = '';
}

function clearMaMessages() {
  const err = document.getElementById('maError');
  const ok = document.getElementById('maSuccess');
  if (err) { err.textContent = ''; err.classList.remove('open'); }
  if (ok) { ok.textContent = ''; ok.classList.remove('open'); }
}

function showMaError(msg) {
  const err = document.getElementById('maError');
  const ok = document.getElementById('maSuccess');
  if (ok) ok.classList.remove('open');
  if (!err) return;
  err.textContent = msg || 'Terjadi kesalahan';
  err.classList.add('open');
  notify(msg || 'Terjadi kesalahan', 'error');
}

function showMaSuccess(msg) {
  const err = document.getElementById('maError');
  const ok = document.getElementById('maSuccess');
  if (err) err.classList.remove('open');
  if (!ok) return;
  ok.textContent = msg || 'Berhasil';
  ok.classList.add('open');
  notify(msg || 'Berhasil', 'success');
}

function renderMultiAkunList(accounts) {
  const list = document.getElementById('maList');
  if (!list) return;
  multiAkunAccounts = Array.isArray(accounts) ? accounts : [];
  if (!multiAkunAccounts.length) {
    list.innerHTML = '<div class="empty-note">Belum ada akun terhubung</div>';
    return;
  }
  const showDelete = multiAkunAccounts.length > 1;
  list.innerHTML = multiAkunAccounts.map(acc => {
    const active = !!acc.is_active;
    const nama = esc(acc.nama || '-');
    const kelas = esc(acc.kelas || '-');
    const va = esc(acc.va_display || acc.no_cust || '-');
    const noCust = esc(String(acc.no_cust || ''));
    const delBtn = showDelete
      ? `<button type="button" class="ma-del" data-hapus-no-cust="${noCust}" title="Hapus dari multi akun" aria-label="Hapus dari multi akun">
          <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </button>`
      : '';
    return `
      <div class="ma-item ${active ? 'is-active' : ''}" data-no-cust="${noCust}" ${active ? 'aria-current="true"' : ''}>
        <div class="ma-item-main">
          <p class="ma-item-name">${nama}</p>
          <p class="ma-item-meta">${kelas} · VA ${va}</p>
        </div>
        <div class="ma-item-right">
          <span class="badge ${active ? 'badge-active' : 'badge-inactive'}">${active ? 'Aktif' : 'Nonaktif'}</span>
          ${delBtn}
        </div>
      </div>`;
  }).join('');
}

function logoutAkun() {
  try { sessionStorage.clear(); } catch (e) {}
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const form = document.getElementById('logoutForm');
  const goHome = () => window.location.replace('/');
  fetch(@json(url('/logout')), {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': token,
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'text/html'
    },
    credentials: 'same-origin',
    redirect: 'follow'
  }).then(goHome).catch(() => {
    if (form) form.submit();
    else goHome();
  });
}

function switchMultiAkun(noCust) {
  const form = document.getElementById('multiAkunSwitchForm');
  const input = document.getElementById('maSwitchNoCust');
  const year = document.getElementById('maSwitchYear');
  if (!form || !input || !noCust) {
    showMaError('Form pindah akun tidak tersedia. Silakan cek tagihan ulang.');
    return;
  }
  input.value = noCust;
  if (year) year.value = 'all';
  notify('Beralih akun...', 'info');
  form.submit();
}

async function hapusMultiAkun(noCust, namaHint) {
  if (!noCust) return;
  clearMaMessages();
  const label = namaHint || noCust;
  if (!confirm('Hapus akun "' + label + '" dari multi akun?')) return;

  try {
    const res = await fetch(multiAkunHapusUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ no_cust: noCust })
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.status) {
      showMaError(data.message || 'Gagal menghapus akun');
      return;
    }
    renderMultiAkunList(data.data?.accounts || []);
    showMaSuccess(data.message || 'Akun berhasil dihapus dari multi akun');
  } catch (err) {
    showMaError('Terjadi kesalahan jaringan');
  }
}

document.addEventListener('click', (e) => {
  const del = e.target.closest('#maList .ma-del');
  if (del) {
    e.preventDefault();
    e.stopPropagation();
    const noCust = del.getAttribute('data-hapus-no-cust');
    const row = del.closest('.ma-item');
    const nama = row?.querySelector('.ma-item-name')?.textContent || noCust;
    hapusMultiAkun(noCust, nama);
    return;
  }

  const item = e.target.closest('#maList .ma-item');
  if (!item || item.classList.contains('is-active')) return;
  const noCust = item.getAttribute('data-no-cust');
  if (!noCust) return;
  e.preventDefault();
  switchMultiAkun(noCust);
});

async function submitTambahMultiAkun(e) {
  e.preventDefault();
  clearMaMessages();
  const btn = document.getElementById('maSubmitBtn');
  const noCust = document.getElementById('maNoCust')?.value?.trim();
  const password = document.getElementById('maPassword')?.value || '';
  const academicYear = 'all';
  if (!noCust || !password) {
    showMaError('Lengkapi VA dan password');
    return false;
  }
  if (btn) {
    btn.disabled = true;
    btn.style.opacity = '.7';
  }
  try {
    const res = await fetch(multiAkunTambahUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({
        no_cust: noCust,
        password: password,
        academic_year: academicYear
      })
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.status) {
      showMaError(data.message || 'Gagal menambahkan akun');
      return false;
    }
    renderMultiAkunList(data.data?.accounts || []);
    document.getElementById('maNoCust').value = '';
    document.getElementById('maPassword').value = '';
    showMaSuccess(data.message || 'Akun berhasil ditambahkan');
  } catch (err) {
    showMaError('Terjadi kesalahan jaringan');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.style.opacity = '';
    }
  }
  return false;
}

document.addEventListener('DOMContentLoaded', () => {
  const toggleMaPw = document.getElementById('maTogglePassword');
  if (toggleMaPw) {
    toggleMaPw.addEventListener('click', () => {
      const field = document.getElementById('maPassword');
      const eye = document.getElementById('maIconEye');
      const eyeOff = document.getElementById('maIconEyeOff');
      if (!field) return;
      const show = field.getAttribute('type') === 'password';
      field.setAttribute('type', show ? 'text' : 'password');
      toggleMaPw.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
      if (eye) eye.hidden = show;
      if (eyeOff) eyeOff.hidden = !show;
    });
  }
});

function formatRp(n) {
  return 'Rp\u00a0' + (parseInt(n, 10) || 0).toLocaleString('id-ID');
}

function formatNovaDisplay(va) {
  let n = String(va ?? '').replace(/\s+/g, '');
  if (!n || n === '-') return '-';
  n = n.replace(/^(757777|797766|751000)/, '');
  n = n.replace(/^0+/, '') || n;
  return n ? ('757777' + n) : '-';
}

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function billName(name) {
  return esc((name || '-').toLowerCase().replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()));
}

function isCicil(item) {
  return String(item?.isINSTALLABLE ?? item?.isinstallable ?? item?.is_installment ?? 0) === '1';
}

function sudahBayarOf(item) {
  return parseInt(item?.sudah_dibayar, 10) || 0;
}

function sisaTagihan(item) {
  if (item?.sisa_tagihan != null && item.sisa_tagihan !== '') {
    return Math.max(0, parseInt(item.sisa_tagihan, 10) || 0);
  }
  return Math.max(0, (parseInt(item?.total_tagihan, 10) || 0) - sudahBayarOf(item));
}

function setBayarForIndex(idx, value, enabled) {
  document.querySelectorAll('.bayar-input[data-index="' + idx + '"]').forEach(inp => {
    const item = tagihanAktif[idx];
    const max = sisaTagihan(item);
    const cicil = isCicil(item);
    inp.max = max;
    if (enabled) {
      inp.disabled = !cicil;
      inp.readOnly = !cicil;
      inp.value = Math.min(max, Math.max(0, value));
    } else {
      inp.disabled = true;
      inp.readOnly = true;
      inp.value = 0;
    }
  });
}

function getBayarAmount(idx) {
  const inp = document.querySelector('.bayar-input[data-index="' + idx + '"]');
  const item = tagihanAktif[idx];
  const max = sisaTagihan(item);
  let n = inp ? (parseInt(String(inp.value).replace(/\D/g, ''), 10) || 0) : 0;
  if (n < 0) n = 0;
  if (n > max) n = max;
  if (item && !isCicil(item) && n > 0) n = max;
  return n;
}

function clampBayarInput(inp) {
  const idx = inp.dataset.index;
  const item = tagihanAktif[idx];
  const max = sisaTagihan(item);
  let n = parseInt(String(inp.value).replace(/\D/g, ''), 10) || 0;
  if (n < 1) n = 1;
  if (n > max) n = max;
  if (!isCicil(item)) n = max;
  document.querySelectorAll('.bayar-input[data-index="' + idx + '"]').forEach(el => { el.value = n; });
  updatePaySummary();
}

function getSelectedTagihan() {
  const seen = new Set();
  const selected = [];
  document.querySelectorAll('.tagihan-checkbox:checked').forEach(cb => {
    const idx = String(cb.dataset.index);
    if (seen.has(idx) || cb.disabled) return;
    seen.add(idx);
    const item = tagihanAktif[idx];
    if (!item) return;
    selected.push({ ...item, _idx: idx, bayar: getBayarAmount(idx) });
  });
  return selected;
}

function syncCheckboxPair(source) {
  document.querySelectorAll('.tagihan-checkbox[data-index="' + source.dataset.index + '"]').forEach(cb => {
    cb.checked = source.checked;
  });
  const idx = source.dataset.index;
  const item = tagihanAktif[idx];
  setBayarForIndex(idx, source.checked ? sisaTagihan(item) : 0, source.checked);
  const all = Array.from(document.querySelectorAll('#tagihanTableBody .tagihan-checkbox')).filter(cb => !cb.disabled);
  const checked = all.filter(cb => cb.checked);
  const allOn = all.length > 0 && all.length === checked.length;
  ['selectAll', 'selectAllMobile'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.checked = allOn;
  });
}

function toggleSelectAll(master) {
  document.querySelectorAll('.tagihan-checkbox').forEach(cb => {
    if (cb.disabled) return;
    cb.checked = master.checked;
    const idx = cb.dataset.index;
    const item = tagihanAktif[idx];
    setBayarForIndex(idx, master.checked ? sisaTagihan(item) : 0, master.checked);
  });
  ['selectAll', 'selectAllMobile'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.checked = master.checked;
  });
  updatePaySummary();
}

function updatePaySummary() {
  const selected = getSelectedTagihan();
  const total = selected.reduce((s, i) => s + (parseInt(i.bayar, 10) || 0), 0);
  const box = document.getElementById('paySummary');
  const text = document.getElementById('paySummaryText');
  const tot = document.getElementById('paySummaryTotal');
  if (!box) return;
  if (!selected.length) {
    box.classList.remove('open');
    return;
  }
  box.classList.add('open');
  text.textContent = selected.length + ' tagihan dipilih';
  tot.textContent = formatRp(total);
}

document.addEventListener('change', e => {
  if (e.target.classList.contains('tagihan-checkbox')) {
    syncCheckboxPair(e.target);
    updatePaySummary();
  }
});

document.addEventListener('input', e => {
  if (e.target.classList.contains('bayar-input')) clampBayarInput(e.target);
});

function showPaymentModal() {
  const selected = getSelectedTagihan();
  if (!selected.length) {
    notifyWarn('Belum ada tagihan', 'Pilih minimal satu tagihan untuk dibayar.');
    return;
  }

  const missingAa = selected.some(i => !i.AA);
  if (missingAa || !siswaBayar.id) {
    notifyError('Data tidak lengkap', 'Data tagihan tidak bisa diproses. Silakan cek ulang tagihan.');
    return;
  }

  for (const i of selected) {
    const sisa = sisaTagihan(i);
    const bayar = parseInt(i.bayar, 10) || 0;
    if (bayar <= 0) {
      notifyWarn('Nominal belum diisi', 'Isi nominal bayar untuk setiap tagihan yang dipilih.');
      return;
    }
    if (bayar > sisa) {
      notifyWarn('Nominal melebihi sisa', 'Nominal bayar tidak boleh lebih dari sisa tagihan.');
      return;
    }
    if (!isCicil(i) && bayar !== sisa) {
      notifyWarn('Tidak bisa dicicil', 'Tagihan yang tidak bisa dicicil harus dibayar sesuai sisa tagihan.');
      return;
    }
  }

  const total = selected.reduce((s, i) => s + (parseInt(i.bayar, 10) || 0), 0);
  const minExp = earliestExpDate(selected);
  let rows = '';
  selected.forEach(i => {
    const cicil = isCicil(i);
    const sisa = sisaTagihan(i);
    const sudah = sudahBayarOf(i);
    const readonly = cicil ? '' : 'readonly';
    rows += `<tr>
      <td>${billName(i.nama_tagihan)}</td>
      <td>${esc(i.tahun_akademik_tagihan || siswaBayar.tahun_akademik || '-')}</td>
      <td>${esc(formatPeriode(i.periode))}</td>
      <td class="cicil"><span class="badge ${cicil ? 'badge-cicil' : 'badge-no-cicil'}">${cicil ? 'Ya' : 'Tidak'}</span></td>
      <td class="num">${formatRp(i.total_tagihan)}</td>
      <td class="num">${formatRp(sudah)}</td>
      <td class="bayar-col"><input type="number" class="pay-cell bayar-input modal-bayar-input" data-index="${i._idx}" min="1" max="${sisa}" value="${i.bayar}" ${readonly} inputmode="numeric"></td>
    </tr>`;
  });

  document.getElementById('paymentBody').innerHTML = `
    <div class="pay-info">
      <div><span class="pi-lbl">Nama</span><div class="pi-val">${esc(siswaBayar.nama) || '-'}</div></div>
      <div><span class="pi-lbl">Kelas</span><div class="pi-val">${esc(siswaBayar.kelas) || '-'}</div></div>
      <div><span class="pi-lbl">NIS</span><div class="pi-val">${esc(siswaBayar.no_cust || siswaBayar.num2nd) || '-'}</div></div>
      <div>
        <span class="pi-lbl">Nomor VA</span>
        <div class="pi-val pi-val-va">
          <span id="payInfoVaText">${esc(formatNovaDisplay(siswaBayar.va_number || siswaBayar.no_cust)) || '-'}</span>
          <button type="button" class="btn-copy-mini" onclick="copyTextEl('payInfoVaText')" title="Salin VA" aria-label="Salin VA">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="1"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
            Salin
          </button>
        </div>
      </div>
      <div><span class="pi-lbl">Exp Date VA</span><div class="pi-val">${esc(formatExpDate(minExp))}</div></div>
    </div>
    <div class="pay-tbl-wrap">
      <table class="pay-tbl">
        <thead>
          <tr>
            <th>Tagihan</th>
            <th>Tahun aka</th>
            <th>Periode</th>
            <th>Cicil</th>
            <th>Nominal</th>
            <th>Dibayar</th>
            <th>Bayar</th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    </div>
    <div class="pay-total"><span>Total pembayaran</span><span id="modalPayTotal">${formatRp(total)}</span></div>
    ${paymentQrisEnabled ? `
    <div class="pay-method" id="payMethodBox">
      <span class="pi-lbl">Metode pembayaran</span>
      <div class="pay-method-opts">
        <label class="pay-method-opt"><input type="radio" name="payMethod" value="va" checked> Virtual Account</label>
        <label class="pay-method-opt"><input type="radio" name="payMethod" value="qris"> QRIS</label>
      </div>
    </div>` : ''}
    <div id="vaResult"></div>`;

  const confirmLabel = paymentQrisEnabled ? 'Lanjutkan pembayaran' : '+ Buat Nomor VA';
  document.getElementById('paymentFoot').innerHTML = `
    <div class="pay-actions" id="payActions">
      <button type="button" class="btn-ghost" onclick="closePaymentModal()">Tutup</button>
      <button type="button" class="btn-pay-confirm" id="btnBuatVa" onclick="prosesPembayaran()">${confirmLabel}</button>
    </div>`;

  document.querySelectorAll('.modal-bayar-input').forEach(inp => {
    inp.addEventListener('input', () => {
      clampBayarInput(inp);
      const tot = getSelectedTagihan().reduce((s, i) => s + (parseInt(i.bayar, 10) || 0), 0);
      const el = document.getElementById('modalPayTotal');
      if (el) el.textContent = formatRp(tot);
    });
  });

  document.getElementById('paymentModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closePaymentModal() {
  document.getElementById('paymentModal').classList.remove('open');
  document.body.style.overflow = '';
  const head = document.querySelector('#paymentModal .modal-head h3');
  if (head) head.textContent = 'Bayar Tagihan';
}

async function prosesPembayaran() {
  const selected = getSelectedTagihan();
  const btn = document.getElementById('btnBuatVa');
  if (!selected.length) {
    notifyWarn('Belum ada tagihan', 'Pilih minimal satu tagihan untuk dibayar.');
    return;
  }

  for (const i of selected) {
    const sisa = sisaTagihan(i);
    const bayar = parseInt(i.bayar, 10) || 0;
    if (bayar <= 0 || bayar > sisa || (!isCicil(i) && bayar !== sisa)) {
      notifyWarn('Nominal tidak valid', 'Periksa nominal bayar. Yang tidak bisa dicicil harus lunas sisa tagihan, yang bisa dicicil tidak boleh melebihi sisa.');
      return;
    }
  }

  const methodEl = document.querySelector('input[name="payMethod"]:checked');
  const method = (paymentQrisEnabled && methodEl) ? methodEl.value : 'va';

  const items = selected.map(i => ({
    AA: i.AA,
    amount: parseInt(i.bayar, 10),
    is_cicil: isCicil(i) ? 1 : 0,
    sisa_sebelum: sisaTagihan(i),
    nama_tagihan: i.nama_tagihan || '',
    billcd: i.BILLCD || i.billcd || ''
  }));
  const ids = items.map(i => i.AA);
  const amounts = items.map(i => i.amount);
  const total = amounts.reduce((s, n) => s + n, 0);
  const nocust = siswaBayar.no_cust || siswaBayar.num2nd || '';

  if (btn) { btn.disabled = true; btn.textContent = method === 'qris' ? 'Membuat QRIS...' : 'Memproses...'; }

  const payload = {
    custid: siswaBayar.id,
    nocust: nocust,
    namacust: siswaBayar.nama,
    array_tagihan: ids.join(','),
    billam: amounts.join(','),
    total: total,
    items: items,
    method: method
  };

  try {
    if (method === 'qris') {
      await prosesGenerateQris(payload, total, btn);
    } else {
      await prosesGenerateVa(payload, total, nocust, btn);
    }
  } catch (err) {
    notifyError('Terjadi kesalahan', 'Gagal memproses pembayaran. Coba beberapa saat lagi.');
    if (btn) {
      btn.disabled = false;
      btn.textContent = paymentQrisEnabled ? 'Lanjutkan pembayaran' : '+ Buat Nomor VA';
    }
  }
}

async function prosesGenerateVa(payload, total, nocust, btn) {
  const res = await fetch(generateVaUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': csrfToken
    },
    body: JSON.stringify(payload)
  });

  const data = await res.json();
  const rawVa = data?.data?.va_number ?? data?.va_number;
  const vaOk = rawVa !== false && rawVa !== null && rawVa !== undefined && String(rawVa) !== 'false' && String(rawVa).trim() !== '';
  const va = (data?.status && vaOk) ? formatNovaDisplay(nocust || rawVa) : '';

  if (data?.status && va && va !== '-') {
    document.getElementById('vaResult').innerHTML = `
      <div class="va-box">
        <h4>Nomor Virtual Account</h4>
        <div class="va-number" id="vaNumberText">${esc(va)}</div>
        <button type="button" class="btn-copy" onclick="copyVa()">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="1"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
          Salin nomor VA
        </button>
        <p class="va-meta">Total: <b>${formatRp(total)}</b></p>
        <p class="va-help">Bayar ke nomor VA di atas (kode bank 757777).</p>
      </div>`;
    const methodBox = document.getElementById('payMethodBox');
    if (methodBox) methodBox.style.display = 'none';
    const actions = document.getElementById('payActions');
    if (actions) {
      actions.innerHTML = `<button type="button" class="btn-ghost" onclick="closePaymentModal()">Tutup</button>`;
    }
  } else {
    notifyError('Gagal membuat VA', data?.message || 'Silakan coba lagi.');
    if (btn) {
      btn.disabled = false;
      btn.textContent = paymentQrisEnabled ? 'Lanjutkan pembayaran' : '+ Buat Nomor VA';
    }
  }
}

async function prosesGenerateQris(payload, total, btn) {
  const res = await fetch(generateQrisUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': csrfToken
    },
    body: JSON.stringify(payload)
  });

  const data = await res.json();
  const d = data?.data || data || {};
  const rawQr = d.rawQrData || d.qris_content || '';
  const ok = !!(data?.status && rawQr);

  if (ok) {
    const qrImg = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=8&data=' + encodeURIComponent(rawQr);
    const exp = d.expiredTime || d.expired_time || '-';
    const trx = d.transaction_id || d.transactionId || d.qris_id || '-';
    const status = String(d.status || 'pending');
    const head = document.querySelector('#paymentModal .modal-head h3');
    if (head) head.textContent = 'Pembayaran QRIS';

    document.getElementById('paymentBody').innerHTML = `
      <div class="qris-result">
        <div class="qris-result-head">
          <p class="qris-result-kicker">Total pembayaran</p>
          <p class="qris-result-amount">${formatRp(total)}</p>
        </div>
        <div class="qris-result-frame">
          <div class="qris-result-frame-inner">
            <img src="${qrImg}" alt="Kode QRIS" width="240" height="240">
          </div>
        </div>
        <div class="qris-result-meta">
          <div class="qris-result-row"><span>Berlaku sampai</span><b>${esc(exp)}</b></div>
          <div class="qris-result-row"><span>ID transaksi</span><b>${esc(String(trx))}</b></div>
          <div class="qris-result-row"><span>Status</span><b><span class="qris-badge qris-badge-pending">${esc(status)}</span></b></div>
        </div>
        <p class="qris-result-help">Scan QR dengan aplikasi bank atau e-wallet sebelum waktu kedaluwarsa. Jangan tutup halaman sampai pembayaran selesai.</p>
      </div>`;

    document.getElementById('paymentFoot').innerHTML = `
      <div class="pay-actions" id="payActions">
        <button type="button" class="btn-ghost" onclick="closePaymentModal()" style="flex:1">Tutup</button>
      </div>`;
    notifyOk('QRIS siap', 'Silakan scan QR untuk membayar.');
  } else {
    notifyError('Gagal membuat QRIS', data?.message || 'Silakan coba lagi.');
    if (btn) {
      btn.disabled = false;
      btn.textContent = 'Lanjutkan pembayaran';
    }
  }
}

function copyVa() {
  const va = (document.getElementById('vaNumberText')?.textContent || '').trim();
  if (!va) return;
  copyPlain(va);
}

function copyTextEl(id) {
  const va = (document.getElementById(id)?.textContent || '').trim();
  if (!va || va === '-') return;
  copyPlain(va);
}

function copyPlain(va) {
  const done = () => notifyOk('Tersalin', 'Nomor VA sudah disalin.');
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(va).then(done).catch(() => fallbackCopy(va, done));
  } else {
    fallbackCopy(va, done);
  }
}

function fallbackCopy(va, done) {
  const t = document.createElement('textarea');
  t.value = va;
  document.body.appendChild(t);
  t.select();
  document.execCommand('copy');
  t.remove();
  done();
}

/* PWA */
const installBtn = document.getElementById('installBtn');
const btnInstallNow = document.getElementById('btnInstallNow');
const installModalText = document.getElementById('installModalText');
const PWA_INSTALLED_KEY = 'tagihan_rj_pwa_installed';

function isStandalonePwa() {
  return window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;
}

function markPwaInstalled() {
  try { localStorage.setItem(PWA_INSTALLED_KEY, '1'); } catch (e) {}
  window.__pwaAlreadyInstalled = true;
}

function isPwaMarkedInstalled() {
  if (window.__pwaAlreadyInstalled) return true;
  try { return localStorage.getItem(PWA_INSTALLED_KEY) === '1'; } catch (e) { return false; }
}

function setInstallBtnInstalledUi() {
  if (!installBtn) return;
  installBtn.classList.remove('is-hidden');
  installBtn.innerHTML = '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg><span class="theme-label">Buka app</span>';
  installBtn.title = 'Aplikasi sudah terpasang';
  installBtn.setAttribute('aria-label', 'Buka aplikasi');
  installBtn.dataset.state = 'installed';
}

function setInstallBtnDefaultUi() {
  if (!installBtn) return;
  installBtn.innerHTML = '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg><span class="theme-label">Install</span>';
  installBtn.title = 'Install aplikasi';
  installBtn.setAttribute('aria-label', 'Install aplikasi');
  installBtn.dataset.state = 'install';
}

function showAlreadyInstalledModal() {
  if (installModalText) {
    installModalText.innerHTML = 'Aplikasi <b>sudah terpasang</b>. Di Chrome, klik tombol biru <b>Buka di aplikasi</b> di bilah alamat (kanan atas) untuk membukanya.';
  }
  if (btnInstallNow) {
    btnInstallNow.disabled = false;
    btnInstallNow.dataset.mode = 'close';
    btnInstallNow.innerHTML = 'Mengerti';
    btnInstallNow.style.color = '';
    btnInstallNow.style.background = '';
  }
}

function openInstallModal() {
  const modal = document.getElementById('installModal');
  if (!modal) return;
  syncInstallModalState();
  modal.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeInstallModal() {
  const modal = document.getElementById('installModal');
  if (!modal) return;
  modal.classList.remove('open');
  document.body.style.overflow = '';
}

function syncInstallModalState() {
  if (!btnInstallNow) return;
  btnInstallNow.dataset.mode = 'install';
  btnInstallNow.style.color = '';
  btnInstallNow.style.background = '';
  if (isStandalonePwa()) {
    markPwaInstalled();
    if (installModalText) installModalText.textContent = 'Anda sudah membuka aplikasi dalam mode terpasang.';
    btnInstallNow.disabled = false;
    btnInstallNow.dataset.mode = 'close';
    btnInstallNow.innerHTML = 'Tutup';
    return;
  }
  if (isPwaMarkedInstalled() && !window.__pwaDeferredPrompt) {
    showAlreadyInstalledModal();
    setInstallBtnInstalledUi();
    return;
  }
  if (window.__pwaDeferredPrompt || window.__pwaInstallReady) {
    if (installModalText) installModalText.textContent = 'Pasang aplikasi ke perangkat Anda agar lebih cepat dibuka dan mudah diakses.';
    btnInstallNow.disabled = false;
    btnInstallNow.innerHTML = '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg> Install sekarang';
  } else {
    if (installModalText) installModalText.textContent = 'Menyiapkan installer...';
    btnInstallNow.disabled = true;
    btnInstallNow.innerHTML = 'Menyiapkan...';
  }
}

async function runPwaInstall() {
  const promptEvent = window.__pwaDeferredPrompt;
  if (!promptEvent) {
    syncInstallModalState();
    return false;
  }
  if (btnInstallNow) {
    btnInstallNow.disabled = true;
    btnInstallNow.textContent = 'Menginstall...';
  }
  try {
    promptEvent.prompt();
    const choice = await promptEvent.userChoice;
    window.__pwaDeferredPrompt = null;
    window.__pwaInstallReady = false;
    if (choice && choice.outcome === 'accepted') {
      markPwaInstalled();
      closeInstallModal();
      setInstallBtnInstalledUi();
      notifyOk('Berhasil', 'Aplikasi sedang diinstall.');
      return true;
    }
    syncInstallModalState();
    return false;
  } catch (err) {
    syncInstallModalState();
    return false;
  }
}

window.addEventListener('pwa-install-ready', () => {
  try { localStorage.removeItem(PWA_INSTALLED_KEY); } catch (e) {}
  window.__pwaAlreadyInstalled = false;
  setInstallBtnDefaultUi();
  syncInstallModalState();
});

window.addEventListener('appinstalled', () => {
  window.__pwaDeferredPrompt = null;
  window.__pwaInstallReady = false;
  markPwaInstalled();
  setInstallBtnInstalledUi();
  closeInstallModal();
});

if (installBtn) {
  installBtn.addEventListener('click', () => {
    openInstallModal();

    // Sudah terpasang: cukup arahkan ke tombol Chrome "Buka di aplikasi"
    if (isPwaMarkedInstalled() && !window.__pwaDeferredPrompt) {
      showAlreadyInstalledModal();
      return;
    }

    if (window.__pwaDeferredPrompt) {
      syncInstallModalState();
      return;
    }

    let tries = 0;
    const timer = setInterval(() => {
      tries += 1;
      if (window.__pwaDeferredPrompt) {
        syncInstallModalState();
        clearInterval(timer);
        return;
      }
      if (tries >= 16) {
        clearInterval(timer);
        // Tidak ada event install = biasanya app sudah terpasang (Chrome tampil "Buka di aplikasi")
        markPwaInstalled();
        setInstallBtnInstalledUi();
        showAlreadyInstalledModal();
      } else {
        syncInstallModalState();
      }
    }, 250);
  });
}

if (btnInstallNow) {
  btnInstallNow.addEventListener('click', () => {
    if (btnInstallNow.dataset.mode === 'close') {
      closeInstallModal();
      return;
    }
    if (btnInstallNow.dataset.mode === 'reload') {
      location.reload();
      return;
    }
    runPwaInstall();
  });
}

if (isStandalonePwa()) {
  markPwaInstalled();
  setInstallBtnInstalledUi();
}
if (isPwaMarkedInstalled() && !window.__pwaDeferredPrompt) {
  setInstallBtnInstalledUi();
}
if (navigator.getInstalledRelatedApps) {
  navigator.getInstalledRelatedApps().then((apps) => {
    if (apps && apps.length) {
      markPwaInstalled();
      setInstallBtnInstalledUi();
    }
  }).catch(() => {});
}
syncInstallModalState();
</script>
</body>
</html>
