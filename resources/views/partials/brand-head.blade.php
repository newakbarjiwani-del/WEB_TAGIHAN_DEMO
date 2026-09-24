@php
  $brand = config('brand');
  $c = $brand['colors'];
  $r = max(0, (int) $brand['radius']);
  $radiusSm = max(0, $r - 1);
  $brandJs = [
    'name' => $brand['name'],
    'short_name' => $brand['short_name'],
    'primary' => $c['primary'],
    'primaryDark' => $c['dark_primary'],
    'surface' => $c['surface'],
    'surfaceDark' => $c['dark_surface'],
    'text' => $c['text'],
    'textDark' => $c['dark_text'],
    'theme' => $c['theme'],
    'logo' => asset($brand['logo']),
    'icon192' => asset($brand['icon_192']),
    'pwa' => (bool) ($brand['pwa']['enabled'] ?? true),
    'showGuide' => (bool) ($brand['show_guide'] ?? true),
    'paymentQris' => (bool) ($brand['payment_qris'] ?? false),
  ];
@endphp
<title>{{ $brand['tagline'] }} | {{ $brand['name'] }}</title>
<meta name="description" content="{{ $brand['description'] }}">
<meta name="theme-color" content="{{ $c['theme'] }}" id="metaThemeColor">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ $brand['short_name'] }}">
<link rel="manifest" href="{{ url('/manifest.webmanifest') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset($brand['icon_192']) }}">
<link rel="icon" href="{{ asset($brand['favicon']) }}">
<link rel="apple-touch-icon" href="{{ asset($brand['icon_192']) }}">
@if(!empty($brand['font_url']))
<link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
<link href="{{ $brand['font_url'] }}" rel="stylesheet">
@endif
{{-- Fallback jika CDN config gagal --}}
<link rel="stylesheet" href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap">
<style>
:root{
  --brand-primary:{{ $c['primary'] ?: '#0B7EB8' }};
  --brand-primary-h:{{ $c['primary_hover'] ?: '#086693' }};
  --brand-primary-soft:{{ $c['primary_soft'] ?: '#D6EEF8' }};
  --brand-theme:{{ $c['theme'] ?: '#0B7EB8' }};
  --brand-highlight:{{ $c['highlight'] ?? '#E89B0C' }};
  --brand-highlight-soft:{{ $c['highlight_soft'] ?? '#FFF3D1' }};
  --brand-logo-cyan:#3AB4F2;
  --bg:#e4e9ee;
  --bg-deep:#d5dde5;
  --surface:#ffffff;
  --surface2:#eef2f6;
  --border:#b7c4d0;
  --border2:#8fa3b5;
  --text:#0a1f2e;
  --text2:#334b5c;
  --text3:#5a7386;
  --accent:var(--brand-primary, #0B7EB8);
  --accent-h:var(--brand-primary-h, #086693);
  --accent-soft:var(--brand-primary-soft, #D6EEF8);
  --highlight:var(--brand-highlight, #E89B0C);
  --highlight-soft:var(--brand-highlight-soft, #FFF3D1);
  --danger:#b71c1c;
  --radius:{{ $r }}px;
  --radius-sm:{{ $radiusSm }}px;
  --font:'Plus Jakarta Sans', system-ui, sans-serif;
  --font-display:'Plus Jakarta Sans', system-ui, sans-serif;
  --shadow:0 1px 2px rgba(10,31,46,.06), 0 10px 28px rgba(10,31,46,.08);
  --shadow-sm:0 1px 2px rgba(10,31,46,.06);
  --table-head:#dceaf3;
  --table-head-text:#0a4f73;
  --btn-face:#ffffff;
  color-scheme:light;
}
html,body,button,input,select,textarea,table,th,td,.btn,.tbl-title,.brand-name,.brand-tagline,.aside-card,.sf,.modal,.bill-card{
  font-family:var(--font) !important;
}
html.dark{
  --bg:#0a1620;
  --bg-deep:#071018;
  --surface:{{ $c['dark_surface'] }};
  --surface2:#1a2e3d;
  --border:#2a4558;
  --border2:#3d6078;
  --text:{{ $c['dark_text'] }};
  --text2:#b8d0de;
  --text3:#8aabbc;
  --accent:{{ $c['dark_primary'] }};
  --accent-h:{{ $c['dark_primary_hover'] }};
  --accent-soft:{{ $c['dark_primary_soft'] }};
  --highlight:#F9A825;
  --highlight-soft:#3a2f14;
  --danger:#ef5350;
  --shadow:0 1px 2px rgba(0,0,0,.2), 0 10px 28px rgba(0,0,0,.28);
  --shadow-sm:0 1px 2px rgba(0,0,0,.25);
  --table-head:#143044;
  --table-head-text:#d5ebf5;
  --btn-face:#1a2e3d;
  color-scheme:dark;
}
</style>
<script>
window.__BRAND__ = @json($brandJs);
</script>
