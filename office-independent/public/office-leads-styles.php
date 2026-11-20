<?php
declare(strict_types=1);
header('Content-Type: text/css; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=300');
?>
:root{color-scheme:light dark}
body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#19293a;margin:0}
main{max-width:1200px;margin:32px auto;padding:20px}
section{background:#fff;color:#19293a;padding:24px;border-radius:12px;border:1px solid #dbe1e8}
section h2,section h3,section p,section small,section strong{color:inherit}
table{width:100%;border-collapse:collapse}
th,td{padding:12px;text-align:left;border-bottom:1px solid #dbe1e8}
a{color:#175a91}
.lead-funnel{max-width:860px;margin:22px auto;display:flex;flex-direction:column;gap:0}
.lead-funnel__step{min-height:86px;display:flex;align-items:center;justify-content:center;text-align:center;background:#245d99;color:#fff;text-decoration:none;box-sizing:border-box;margin:0}
.lead-funnel__step:nth-child(2){background:#2869a4}
.lead-funnel__step:nth-child(3){background:#2c779e}
.lead-funnel__step:nth-child(4){background:#27898f}
.lead-funnel__step--converted{background:#237a65}
.lead-funnel__step span{display:flex;flex-direction:column;gap:5px;padding:10px 24px}
.lead-funnel__step strong{font-size:1.05rem}
.lead-funnel__step small{font-size:.82rem;color:#eaf4ff}
.lead-funnel__step[aria-current=true]{filter:brightness(1.3)}
.lead-funnel__step:focus-visible{outline:3px solid #fbbf24;outline-offset:-3px}
.lead-funnel__step:nth-child(1){clip-path:polygon(0 0,100% 0,93% 100%,7% 100%)}
.lead-funnel__step:nth-child(2){clip-path:polygon(7% 0,93% 0,86% 100%,14% 100%)}
.lead-funnel__step:nth-child(3){clip-path:polygon(14% 0,86% 0,79% 100%,21% 100%)}
.lead-funnel__step:nth-child(4){clip-path:polygon(21% 0,79% 0,72% 100%,28% 100%)}
.lead-funnel__step--converted{clip-path:polygon(28% 0,72% 0,65% 100%,35% 100%)}
@media(prefers-color-scheme:dark){
body{background:#0d1420;color:#e9f1fa}
section{background:#182334;color:#e9f1fa;border-color:#35465d}
a{color:#83baff}
th,td{border-color:#35465d}
}
@media(max-width:640px){main{padding:12px;margin:12px auto}section{padding:14px}.lead-funnel__step{min-height:72px}.lead-funnel__step span{padding:8px 30px;font-size:.85rem}}
