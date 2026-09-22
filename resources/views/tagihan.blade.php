<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Konfirmasi Pembayaran</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<style>
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;overflow-x:hidden}
.modal-content{max-height:100vh;overflow-y:auto}
.modal-content::-webkit-scrollbar{width:8px}
.modal-content::-webkit-scrollbar-track{background:#1e293b}
.modal-content::-webkit-scrollbar-thumb{background:#475569;border-radius:4px}
.modal-content::-webkit-scrollbar-thumb:hover{background:#64748b}
.light-mode .modal-content::-webkit-scrollbar-track{background:#f1f5f9}
.light-mode .modal-content::-webkit-scrollbar-thumb{background:#cbd5e1}
.light-mode .modal-content::-webkit-scrollbar-thumb:hover{background:#94a3b8}
</style>
</head>
<body class="bg-slate-800">
<div class="modal-content px-6 py-5">
<div class="bg-slate-700 rounded-lg shadow-lg p-5 mb-4 card-section">
<h2 class="text-lg font-bold text-white mb-4 section-title">Data Siswa</h2>
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
<div><p class="text-gray-400 text-xs mb-1 info-label">Nama</p><p class="text-white font-semibold text-sm info-value" id="siswa_nama">-</p></div>
<div><p class="text-gray-400 text-xs mb-1 info-label">Kelas</p><p class="text-white font-semibold text-sm info-value" id="siswa_kelas">-</p></div>
<div><p class="text-gray-400 text-xs mb-1 info-label">NIS</p><p class="text-white font-semibold text-sm info-value" id="siswa_va">-</p></div>
<div><p class="text-gray-400 text-xs mb-1 info-label">Tahun Akademik</p><p class="text-white font-semibold text-sm info-value" id="siswa_tahun">-</p></div>
</div></div>

<div class="bg-slate-700 rounded-lg shadow-lg p-5 mb-4 card-section">
<div class="flex items-center justify-between mb-4"><h2 class="text-lg font-bold text-white section-title">Tagihan Terpilih</h2><span class="bg-blue-500 text-white px-3 py-1.5 rounded-full text-xs font-semibold" id="item_count">0 Item</span></div>
<div class="overflow-hidden rounded-lg border border-slate-600 table-wrapper">
<table class="w-full border-collapse">
<thead><tr class="bg-slate-600 table-header">
<th class="px-4 py-3 text-left text-white font-semibold text-sm border-r border-slate-500 th-cell">NO</th>
<th class="px-4 py-3 text-left text-white font-semibold text-sm border-r border-slate-500 th-cell">NAMA TAGIHAN</th>
<th class="px-4 py-3 text-left text-white font-semibold text-sm border-r border-slate-500 th-cell">TAHUN AKADEMIK TAGIHAN</th>
<th class="px-4 py-3 text-left text-white font-semibold text-sm border-r border-slate-500 th-cell">PERIODE</th>
<th class="px-4 py-3 text-right text-white font-semibold text-sm th-cell">NOMINAL</th>
</tr></thead>
<tbody id="tagihan_tbody" class="bg-slate-800 table-body">
<tr><td colspan="5" class="px-4 py-6 text-center text-gray-400 text-sm loading-cell">Loading...</td></tr>
</tbody></table></div>
<div class="mt-4 bg-blue-600 rounded-lg p-4 flex justify-between items-center total-section">
<span class="text-white font-bold text-sm">TOTAL PEMBAYARAN</span>
<span class="text-white font-bold text-xl" id="total_bayar">Rp 0</span>
</div></div>

<div class="bg-slate-700 rounded-lg shadow-lg p-5 mb-4 card-section">
<label class="block text-white font-semibold mb-3 text-sm section-title">Verifikasi Keamanan</label>
<div id="turnstile-widget" class="flex justify-center"></div>
</div>

<div id="vaResult" class="mb-4"></div>

<div class="flex gap-3" id="actionButtons">
<button onclick="window.parent.closePaymentModal()" class="flex-1 bg-slate-600 hover:bg-slate-500 text-white py-3 rounded-lg font-semibold text-sm transition-colors btn-close">Tutup</button>
<button onclick="prosesPembayaran()" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg font-semibold text-sm transition-colors btn-submit" id="btnSubmit">Buat Nomor VA</button>
</div>
</div>

<script>
let turnstileToken = null;

function applyTheme(){
const t = (typeof localStorage !== 'undefined' ? localStorage.getItem('theme') : null) || 'dark';
const b = document.body;
if(t === 'light'){
b.classList.add('light-mode'); b.classList.remove('bg-slate-800'); b.classList.add('bg-gray-100');
document.querySelectorAll('.card-section').forEach(e=>{e.classList.remove('bg-slate-700'); e.classList.add('bg-white','border','border-gray-200')});
document.querySelectorAll('.section-title').forEach(e=>{e.classList.remove('text-white'); e.classList.add('text-gray-900')});
document.querySelectorAll('.info-label').forEach(e=>{e.classList.remove('text-gray-400'); e.classList.add('text-gray-600')});
document.querySelectorAll('.info-value').forEach(e=>{e.classList.remove('text-white'); e.classList.add('text-gray-900')});
document.querySelectorAll('.table-wrapper').forEach(e=>{e.classList.remove('border-slate-600'); e.classList.add('border-gray-300')});
document.querySelectorAll('.table-header').forEach(e=>{e.classList.remove('bg-slate-600'); e.classList.add('bg-gray-200')});
document.querySelectorAll('.th-cell').forEach(e=>{e.classList.remove('text-white','border-slate-500'); e.classList.add('text-gray-900','border-gray-300')});
document.querySelectorAll('.table-body').forEach(e=>{e.classList.remove('bg-slate-800'); e.classList.add('bg-white')});
document.querySelectorAll('.loading-cell').forEach(e=>{e.classList.remove('text-gray-400'); e.classList.add('text-gray-600')});
document.querySelectorAll('.btn-close').forEach(e=>{e.classList.remove('bg-slate-600'); e.classList.add('bg-gray-300','text-gray-900')});
}}

function hideSubmitButton(){
const actionDiv = document.getElementById('actionButtons');
const submitBtn = document.getElementById('btnSubmit');
if(submitBtn) submitBtn.style.display = 'none';
}

function initTurnstile(){
const p = (typeof localStorage !== 'undefined' ? localStorage.getItem('theme') : null) || 'dark';
const th = p === 'light' ? 'light' : 'dark';

const widget = document.getElementById('turnstile-widget');
widget.innerHTML = '';

if(typeof turnstile !== 'undefined'){
turnstile.render('#turnstile-widget', {
sitekey: @json(config('services.turnstile.site_key')),
theme: th,
callback: function(token){
turnstileToken = token;
},
'error-callback': function(){
turnstileToken = null;
}
});
} else {
setTimeout(initTurnstile, 100);
}
}

window.onload = function(){
applyTheme();

const s = JSON.parse((typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('siswa_data') : null) || '{}');
const t = JSON.parse((typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('selected_tagihan') : null) || '[]');

document.getElementById('siswa_nama').textContent = s.nama || '-';
document.getElementById('siswa_kelas').textContent = s.kelas || '-';
document.getElementById('siswa_va').textContent = s.va_number || '-';
document.getElementById('siswa_tahun').textContent = s.tahun_akademik || '-';

const d = document.getElementById('tagihan_tbody');
const c = document.getElementById('item_count');
const tb = document.getElementById('total_bayar');

if(t.length === 0){
d.innerHTML = '<tr><td colspan="5" class="px-4 py-6 text-center text-gray-400 text-sm loading-cell">Tidak ada tagihan yang dipilih</td></tr>';
c.textContent = '0 Item';
tb.textContent = 'Rp 0';
} else {
let total = 0, h = '';
t.forEach((i, x) => {
let n = typeof i.total_tagihan === 'string' ? parseInt(i.total_tagihan.replace(/\./g,'').replace(/,/g,'')) : parseInt(i.total_tagihan);
total += n;
const nm = i.nama_tagihan.toLowerCase().replace(/_/g,' ').replace(/\b\w/g, l => l.toUpperCase());
const th = i.tahun_akademik_tagihan || s.tahun_akademik || '-';
h += `<tr class="hover:bg-slate-700 border-t border-slate-600 row-item">
<td class="px-4 py-3 text-white text-sm border-r border-slate-600 td-cell">${x+1}</td>
<td class="px-4 py-3 text-white text-sm border-r border-slate-600 td-cell">${nm}</td>
<td class="px-4 py-3 text-white text-sm border-r border-slate-600 td-cell">${th}</td>
<td class="px-4 py-3 text-white text-sm border-r border-slate-600 td-cell">${i.periode || '-'}</td>
<td class="px-4 py-3 text-white text-sm text-right font-semibold td-cell">Rp ${n.toLocaleString('id-ID')}</td></tr>`;
});
d.innerHTML = h;
c.textContent = `${t.length} Item`;
tb.textContent = `Rp ${total.toLocaleString('id-ID')}`;
}

initTurnstile();
};

async function prosesPembayaran(){
const btn = document.getElementById('btnSubmit');
btn.disabled = true;
btn.textContent = 'Memproses...';

try {
if(!turnstileToken){
alert('Silakan selesaikan verifikasi keamanan terlebih dahulu!');
btn.disabled = false;
btn.textContent = 'Buat Nomor VA';
return;
}

const s = JSON.parse((typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('siswa_data') : null) || '{}');
const t = JSON.parse((typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('selected_tagihan') : null) || '[]');

if(t.length === 0){
alert('Tidak ada tagihan yang dipilih!');
return;
}

let total = 0, ids = [];
t.forEach(i => {
let n = typeof i.total_tagihan === 'string' ? parseInt(i.total_tagihan.replace(/\./g,'').replace(/,/g,'')) : parseInt(i.total_tagihan);
total += n;
ids.push(i.AA || 0);
});

const nc = (s.va_number || '').replace(/^797766/,'');

const csrfMeta = document.querySelector('meta[name="csrf-token"]');

const r = await fetch("{{ route('cek-tagihan') }}", {
method: "POST",
headers: { 
"Content-Type": "application/json",
"Accept": "application/json",
"X-CSRF-TOKEN": csrfMeta.getAttribute('content')
},
body: JSON.stringify({ va: nc, tahun_akademik: s.tahun_akademik })
});

const rd = await r.json();

if(!rd.status || !rd.data || !rd.data.id){
alert('Data siswa tidak ditemukan!');
return;
}

const cid = rd.data.id;
const ncst = rd.data.num2nd;

const payload = {
custid: cid,
nocust: ncst,
namacust: s.nama,
array_tagihan: ids.join(','),
total: total
};

const rr = await fetch("{{ route('generate-va') }}", {
method: "POST",
headers: { 
"Content-Type": "application/json",
"Accept": "application/json",
"X-CSRF-TOKEN": csrfMeta.getAttribute('content')
},
body: JSON.stringify(payload)
});

const d = await rr.json();

if(d.status){
hideSubmitButton();

const v = document.getElementById('vaResult');
const isDark = (typeof localStorage !== 'undefined' ? localStorage.getItem('theme') : null) || 'dark' === 'dark';

v.innerHTML = `<div class="${isDark ? 'bg-slate-700 border-slate-600' : 'bg-white border-gray-200'} rounded-lg shadow-lg p-6 text-center border">
<h2 class="text-xl font-bold ${isDark ? 'text-white' : 'text-gray-900'} mb-3">Nomor Virtual Account</h2>
<p class="text-3xl font-mono ${isDark ? 'text-green-400' : 'text-green-600'} mb-4 font-bold">${d.data.va_number}</p>
<div class="space-y-2 mb-4">
<p class="${isDark ? 'text-white' : 'text-gray-900'}">Bank: <span class="font-semibold">Muamalat</span></p>
<p class="${isDark ? 'text-white' : 'text-gray-900'}">Total: <span class="font-semibold">Rp ${total.toLocaleString('id-ID')}</span></p>
</div>
<button onclick="window.parent.closePaymentModal()" class="${isDark ? 'bg-slate-600 hover:bg-slate-500' : 'bg-gray-300 hover:bg-gray-400 text-gray-900'} text-white py-2 px-6 rounded-lg font-semibold text-sm transition-colors">Tutup</button>
</div>`;
} else {
alert('Gagal membuat Virtual Account! ' + (d.message || 'Silakan coba lagi.'));
}

} catch(e) {
console.error(e);
alert('Terjadi kesalahan saat memproses pembayaran.');
} finally {
btn.disabled = false;
btn.textContent = 'Buat Nomor VA';
}
}
</script>
</body>
</html>