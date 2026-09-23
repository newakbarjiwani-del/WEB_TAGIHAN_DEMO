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
  --brand-primary:{{ $c['primary'] }};
  --brand-primary-h:{{ $c['primary_hover'] }};
  --brand-primary-soft:{{ $c['primary_soft'] }};
  --brand-theme:{{ $c['theme'] }};
  --bg:#ecefed;
  --surface:#ffffff;
  --surface2:#f4f6f5;
  --border:#c5cec8;
  --border2:#8a968e;
  --text:#1a1f1c;
  --text2:#3d4741;
  --text3:#5c6861;
  --accent:var(--brand-primary);
  --accent-h:var(--brand-primary-h);
  --accent-soft:var(--brand-primary-soft);
  --danger:#b71c1c;
  --radius:{{ $r }}px;
  --radius-sm:{{ $radiusSm }}px;
  --font:'Plus Jakarta Sans', system-ui, sans-serif;
  --font-display:'Plus Jakarta Sans', system-ui, sans-serif;
  --shadow:none;
  --table-head:#14532d;
  --btn-face:#ffffff;
  color-scheme:light;
}
html,body,button,input,select,textarea,table,th,td,.btn,.tbl-title,.brand-name,.brand-tagline,.aside-card,.sf,.modal,.bill-card{
  font-family:var(--font) !important;
}
html.dark{
  --bg:{{ $c['dark_bg'] }};
  --surface:{{ $c['dark_surface'] }};
  --surface2:#1f2e27;
  --border:#3d5246;
  --border2:#5a7163;
  --text:{{ $c['dark_text'] }};
  --text2:#c5d4cb;
  --text3:#9aafa2;
  --accent:{{ $c['dark_primary'] }};
  --accent-h:{{ $c['dark_primary_hover'] }};
  --accent-soft:{{ $c['dark_primary_soft'] }};
  --danger:#ef5350;
  --shadow:none;
  --table-head:#166534;
  --btn-face:#1f2e27;
  color-scheme:dark;
}
</style>
<script>
window.__BRAND__ = @json($brandJs);
</script>
