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

/* Dashboard surfaces inherit the active Office shell theme, not browser color preference. */
body:has(.leads-dashboard) .leads-dashboard{display:grid;gap:16px;max-width:100%;background:transparent!important;border:0!important;padding:0!important;color:inherit}
body:has(.leads-dashboard) .leads-dashboard>section{background:#172334!important;color:#f1f6ff!important;border:1px solid #35465d!important;border-radius:14px!important;padding:22px!important;box-shadow:none!important}
body:has(.leads-dashboard) .leads-dashboard>section h2,
body:has(.leads-dashboard) .leads-dashboard>section p,
body:has(.leads-dashboard) .leads-dashboard>section strong,
body:has(.leads-dashboard) .leads-dashboard>section small{color:inherit}
body:has(.leads-dashboard) .leads-dashboard>section h2{color:#f1f6ff!important;font-weight:750}
body:has(.leads-dashboard) .leads-dashboard>section p{color:#d4e2f3!important}
body:has(.leads-dashboard) .leads-dashboard>section a:not(.lead-funnel__step):not(.lead-source-card){color:#a5ceff!important}
body:has(.leads-dashboard) .lead-funnel{max-width:690px;margin:18px auto}
body:has(.leads-dashboard) .lead-funnel__step{min-height:68px;color:#fff!important}
body:has(.leads-dashboard) .lead-funnel__step strong{color:#fff!important}
body:has(.leads-dashboard) .lead-funnel__step small{color:#e9f4ff!important}
.lead-source-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
body:has(.leads-dashboard) .lead-source-card{display:block;background:#23354b!important;border:1px solid #47617c!important;border-radius:10px;padding:15px;color:#f1f6ff!important;text-decoration:none}
body:has(.leads-dashboard) .lead-source-card strong{color:#fff!important}
body:has(.leads-dashboard) .lead-source-card p{color:#fff!important}
body:has(.leads-dashboard) .lead-source-card small{color:#d4e2f3!important}
.lead-source-count{font-size:26px;margin:8px 0}
.lead-owner-grid{display:flex;flex-wrap:wrap;gap:10px}
.lead-owner-link{padding:12px;border:1px solid #47617c;border-radius:8px}
.lead-kanban{display:grid;grid-template-columns:repeat(auto-fit,minmax(235px,1fr));gap:12px;margin-top:16px}
.lead-kanban-card{background:#23354b;border:1px solid #47617c;padding:12px;border-radius:8px;margin:10px 0}
body:has(.leads-dashboard) .lead-kanban-card{color:#f1f6ff}
body:has(.leads-dashboard) .lead-kanban-card p,body:has(.leads-dashboard) .lead-kanban-card small{color:#d4e2f3}
body:has(.leads-dashboard) .lead-funnel__step:hover,body:has(.leads-dashboard) .lead-source-card:hover{filter:brightness(1.13)}
@media(max-width:640px){body:has(.leads-dashboard) .leads-dashboard>section{padding:14px!important}.lead-source-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.lead-funnel__step strong{font-size:.85rem}}
/* Office's explicit light mode wins over system dark mode. */
html[data-theme="light"] body:has(.leads-dashboard) .leads-dashboard>section,
body[data-theme="light"]:has(.leads-dashboard) .leads-dashboard>section{background:#fff!important;color:#182b40!important;border-color:#dbe1e8!important}
html[data-theme="light"] body:has(.leads-dashboard) .leads-dashboard>section h2,
body[data-theme="light"]:has(.leads-dashboard) .leads-dashboard>section h2{color:#182b40!important}
html[data-theme="light"] body:has(.leads-dashboard) .leads-dashboard>section p,
body[data-theme="light"]:has(.leads-dashboard) .leads-dashboard>section p{color:#334b63!important}
