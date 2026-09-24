<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'خرید پکیج') — پلتفرم زبان کنکور ارشد</title>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  :root{--ink:#16191d;--ink-2:#5b6167;--ink-3:#8b9198;--paper:#fff;--canvas:#f5f4f2;--line:#e6e3dd;
        --gold:#a8842c;--gold-soft:#fdf8ec;--gold-line:#e2c98b;--ok:#1d6b58;--ok-soft:#e6f2ee;
        --danger:#b5451d;--danger-soft:#fbeee8}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:Vazirmatn,system-ui,sans-serif;background:var(--canvas);color:var(--ink);line-height:1.9;
       min-height:100vh;display:flex;justify-content:center;padding:40px 18px}
  .wrap{max-width:520px;width:100%}
  .brand{color:var(--gold);font-weight:700;font-size:15px;text-decoration:none;display:inline-block;margin-bottom:14px}
  .card{background:var(--paper);border:1px solid var(--line);border-radius:16px;padding:28px 26px}
  h1{font-size:20px;font-weight:700;margin-bottom:4px}
  .sub{color:var(--ink-2);font-size:13.5px;margin-bottom:20px}
  .err{background:var(--danger-soft);color:var(--danger);border-radius:10px;padding:10px 14px;font-size:14px;margin-bottom:16px}
  .ok{background:var(--ok-soft);color:var(--ok);border-radius:10px;padding:10px 14px;font-size:14px;margin-bottom:16px}
  .btn{display:block;width:100%;text-align:center;background:var(--ink);color:#fff;border:0;border-radius:10px;
       padding:12px 16px;font:inherit;font-weight:500;font-size:15px;cursor:pointer;text-decoration:none}
  .btn:hover{background:#000}
  .btn[disabled]{background:#b9bec4;cursor:not-allowed}
  .btn.ghost{background:transparent;color:var(--ink);border:1px solid var(--line)}
  .btn:focus-visible{outline:2px solid var(--gold);outline-offset:3px}
  .note{color:var(--ink-3);font-size:12.5px;margin-top:12px;text-align:center}
  .dev{background:#fff4d6;border:1px dashed var(--gold-line);border-radius:10px;padding:8px 12px;font-size:12.5px;
       color:#7d6320;margin-bottom:16px}
  .num{font-variant-numeric:tabular-nums}
</style>
@stack('head')
</head>
<body>
  <div class="wrap">
    <a class="brand" href="/zaban">پلتفرم زبان کنکور ارشد</a>
    <div class="card">@yield('body')</div>
  </div>
</body>
</html>
