<?php
// ============================================================
// index.php — Dhaka Model Agency | Grooming Registration
// ============================================================
require_once __DIR__ . '/../core/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Pull backend config
$pixel_id       = get_setting('fb_pixel_id', '');
$tt_pixel_id = get_setting('tiktok_pixel_id', '');
$campaign_name  = get_setting('campaign_name', 'Grooming Lab Season 6');
$is_active      = get_setting('campaign_active', '1') === '1';
$contact_phone  = get_setting('contact_phone', '+8801926960164');
$contact_email  = get_setting('contact_email', '');

// DB stats for social proof counter
$db    = db();
$count = (int)$db->query("SELECT COUNT(*) AS c FROM registrations")->fetch_assoc()['c'];
$max   = (int)get_setting('max_registrations', '500');
$slots = max(0, $max - $count);

// Flash errors from submit.php
$form_errors = $_SESSION['form_errors'] ?? [];
$form_old    = $_SESSION['form_old']    ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_old']);

// Query param errors
$query_error = $_GET['error'] ?? '';

// YouTube video ID
$youtube_id = '57Q9hgvXpHQ'; // ← Replace with your actual video ID

// ─────────────────────────────────────────────────────────────
// Curriculum — 6 Official Modules (verified from dhakamodel.agency/grooming-lab/2)
// ─────────────────────────────────────────────────────────────
$curriculum = [
  [
    'icon'  => 'walk',
    'title' => 'প্রফেশনাল রানওয়ে ও ক্যাটওয়াক',
    'desc'  => 'রানওয়ে ওয়াকিং, পোশ্চার, রিদম, আত্মবিশ্বাস এবং প্রফেশনাল ক্যাটওয়াক টেকনিক শেখানো হবে।',
  ],
  [
    'icon'  => 'acting',
    'title' => 'অভিনয় ও পারফরম্যান্স',
    'desc'  => 'এক্সপ্রেশন, বডি ল্যাঙ্গুয়েজ ও পারফরম্যান্স ট্রেনিং।',
  ],
  [
    'icon'  => 'communication',
    'title' => 'কর্পোরেট কমিউনিকেশন',
    'desc'  => 'প্রেজেন্টেশন, পাবলিক স্পিকিং ও আত্মবিশ্বাস উন্নয়ন।',
  ],
  [
    'icon'  => 'camera',
    'title' => 'ক্যামেরা কনফিডেন্স ও পোজিং',
    'desc'  => 'ফটোশুট ও ভিডিও সেশনে পোজিং, এক্সপ্রেশন এবং ক্যামেরার সামনে আত্মবিশ্বাস অর্জন।',
  ],
  [
    'icon'  => 'styling',
    'title' => 'ফ্যাশন স্টাইলিং ও পার্সোনাল গ্রুমিং',
    'desc'  => 'স্টাইলিং স্ট্যান্ডার্ড, গ্রুমিং এবং প্রফেশনাল পরিচ্ছন্ন উপস্থিতি তৈরির কৌশল।',
  ],
  [
    'icon'  => 'personality',
    'title' => 'পার্সোনালিটি ডেভেলপমেন্ট',
    'desc'  => 'আত্মবিশ্বাস, ব্যক্তিত্ব ও প্রফেশনাল উপস্থিতি গড়ে তোলা।',
  ],
];

// ─────────────────────────────────────────────────────────────
// Mentor — verified from dhakamodel.agency/grooming-lab/2
// ─────────────────────────────────────────────────────────────
$mentors = [
  [
    'name'  => 'Dilruba Doyel',
    'title' => 'Senior Grooming Expert',
    'exp'   => 'বাংলাদেশি অভিনেত্রী ও প্রাক্তন মডেল। Alpha (2019), The Tales of Chandrabati (2019), Call of the Red-Rooster (2021) এবং Song of the Soul (2022) চলচ্চিত্রে অভিনয়ের জন্য পরিচিত।',
    'img'   => 'assets/mentor-1.jpg',
  ],
];

// ─────────────────────────────────────────────────────────────
// FAQ — verified answers based on official course info
// ─────────────────────────────────────────────────────────────
$faqs = [
  [
    'q' => 'আগে কোনো অভিজ্ঞতা দরকার আছে কি?',
    'a' => 'না। নতুন এবং অভিজ্ঞ — উভয়েই এই প্রোগ্রামে অংশ নিতে পারবেন। Grooming Lab Season 6 সবার জন্য উন্মুক্ত।',
  ],
  [
    'q' => 'কোর্সের সময়সূচি কী?',
    'a' => 'প্রতি শুক্রবার, বিকাল ৩টা থেকে ৬টা। মোট ৮টি সেশন, ২ মাসের মধ্যে সম্পন্ন হবে।',
  ],
  [
    'q' => 'ক্লাস কোথায় অনুষ্ঠিত হবে?',
    'a' => 'ক্লাস হবে Flat-10A, Sonargaon Imtiaz Tower, 10/3-Box Culvert Road, Free School Street, Dhanmondi, Dhaka-1205-তে (কারওয়ান বাজার মেট্রো স্টেশনের দক্ষিণ পাশে, বাংলা ভিশন টিভির পাশে)। ভর্তির জন্য Mirpur-1 অফিসেও যোগাযোগ করা যাবে।',
  ],
  [
    'q' => 'কোর্স ফি কত এবং কীভাবে পরিশোধ করতে হবে?',
    'a' => 'কোর্স ফি ৳১২,০০০। ভর্তি নিশ্চিত হওয়ার পর পেমেন্ট করতে হবে।',
  ],
  [
    'q' => 'সার্টিফিকেট পাওয়া যাবে?',
    'a' => 'হ্যাঁ। প্রোগ্রাম সম্পন্ন করলে অফিশিয়াল পার্টিসিপেশন সার্টিফিকেট দেওয়া হবে।',
  ],
  [
    'q' => 'ফটোশুট কি প্রোগ্রামে অন্তর্ভুক্ত?',
    'a' => 'হ্যাঁ। প্রফেশনাল ব্র্যান্ড ফটোশুট এবং পোর্টফোলিও ডেভেলপমেন্টের সুযোগ প্রোগ্রামের অংশ।',
  ],
  [
    'q' => 'DMA মেম্বারশিপ কী?',
    'a' => 'প্রোগ্রাম শেষে Dhaka Model Agency-তে ১ বছরের বিনামূল্যে অফিশিয়াল কাস্টিং প্রোফাইল মেম্বারশিপ পাবেন।',
  ],
  [
    'q' => 'কীভাবে যোগাযোগ করব?',
    'a' => 'ফোন করুন: +880 1926-960164। অথবা উপরের ফর্ম পূরণ করুন — আমাদের টিম শীঘ্রই যোগাযোগ করবে।',
  ],
];

$districts = ['ঢাকা','চট্টগ্রাম','সিলেট','রাজশাহী','খুলনা','বরিশাল','রংপুর','ময়মনসিংহ',
              'গাজীপুর','নারায়ণগঞ্জ','কুমিল্লা','সাভার','টঙ্গী','অন্যান্য'];
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Grooming Lab Season 6 — ঢাকা মডেল এজেন্সি। ২ মাসের প্রফেশনাল গ্রুমিং প্রোগ্রাম। মোট ৮টি সেশন | প্রতি শুক্রবার | বিকাল ৩টা – ৬টা। শুরু ১ জুন ২০২৬।">
<meta property="og:title" content="Grooming Lab Season 6 — ঢাকা মডেল এজেন্সি">
<meta property="og:description" content="আপনার মডেলিং ক্যারিয়ার শুরু হোক সঠিক গ্রুমিং দিয়ে। Presented by Neo Classic Media। সীমিত আসন — এখনই রেজিস্ট্রেশন করুন।">
<title>Grooming Lab Season 6 — ঢাকা মডেল এজেন্সি</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700&family=Noto+Serif+Bengali:wght@400;600;700&display=swap" rel="stylesheet">

<?php if (!empty($pixel_id)): ?>
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}
(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '<?= $pixel_id ?>');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
  src="https://www.facebook.com/tr?id=<?= $pixel_id ?>&ev=PageView&noscript=1"/></noscript>
<?php endif; ?>

<?php if (!empty($tt_pixel_id)): ?>
<script>
!function (w, d, t) {
  w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];
  ttq.methods=["page","track","identify","instances","debug","on","off","once",
               "ready","alias","group","enableCookie","disableCookie",
               "holdConsent","revokeConsent","grantConsent"];
  ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};
  for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);
  ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)
    ttq.setAndDefer(e,ttq.methods[n]);return e};
  ttq.load=function(e,n){
    var r="https://analytics.tiktok.com/i18n/pixel/events.js";
    ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=r;
    ttq._t=ttq._t||{};ttq._t[e]=+new Date;
    ttq._o=ttq._o||{};ttq._o[e]=n||{};
    n=document.createElement("script");n.type="text/javascript";n.async=!0;
    n.src=r+"?sdkid="+e+"&lib="+t;
    e=document.getElementsByTagName("script")[0];e.parentNode.insertBefore(n,e)};
  ttq.load('<?= htmlspecialchars($tt_pixel_id, ENT_QUOTES) ?>');
  ttq.page();
}(window, document, 'ttq');
</script>
<?php endif; ?>

<style>
/* ═══════════════════════════════════════════════════════════
   DESIGN TOKENS (Light/Red Theme)
═══════════════════════════════════════════════════════════ */
:root {
  --primary:      #cc0000;
  --primary-dark: #a30000;
  --primary-light:#ffe5e5;
  --bg-main:      #ffffff;
  --bg-alt:       #f9f9fa;
  --text-dark:    #1a1a1a;
  --text-muted:   #666666;
  --border:       #e5e7eb;
  --radius:       8px;
  --radius-lg:    16px;

  --font-display: 'Noto Serif Bengali', serif;
  --font-body:    'Noto Sans Bengali', sans-serif;

  --section-pad: clamp(64px, 10vw, 120px);
  --ease:        cubic-bezier(0.16, 1, 0.3, 1);
  --shadow-sm:   0 4px 6px -1px rgba(0, 0, 0, 0.05);
  --shadow-md:   0 10px 30px rgba(0, 0, 0, 0.08);
}

/* ═══════════════════════════════════════════════════════════
   RESET & BASE
═══════════════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; font-size: 16px; }
body {
  background: var(--bg-main);
  color: var(--text-dark);
  font-family: var(--font-body);
  font-weight: 400;
  line-height: 1.7;
  overflow-x: hidden;
  -webkit-font-smoothing: antialiased;
}
img { max-width: 100%; display: block; }
a { color: inherit; text-decoration: none; }
button, input, select, textarea { font-family: inherit; }

/* ── Scroll animations ── */
.reveal {
  opacity: 0;
  transform: translateY(32px);
  transition: opacity 0.8s var(--ease), transform 0.8s var(--ease);
}
.reveal.visible {
  opacity: 1;
  transform: translateY(0);
}
.reveal-delay-1 { transition-delay: 0.1s; }
.reveal-delay-2 { transition-delay: 0.2s; }

/* ═══════════════════════════════════════════════════════════
   NAVIGATION
═══════════════════════════════════════════════════════════ */
.nav {
  position: fixed; top: 0; left: 0; right: 0;
  z-index: 500;
  max-width: 1140px; /* Added */
  margin: 0 auto;    /* Added */
  padding: 20px 40px;
  display: flex; align-items: center; justify-content: space-between;
  transition: all 0.4s var(--ease);
}
.nav.scrolled {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  padding: 14px 40px;
  border-bottom: 1px solid var(--border);
  box-shadow: var(--shadow-sm);
  border-bottom-left-radius: var(--radius);  /* Keeps corners smooth */
  border-bottom-right-radius: var(--radius); /* Keeps corners smooth */
}
.nav-logo {
  display: flex;
  align-items: center;
  gap: 12px; /* লোগো এবং নামের মাঝখানের স্পেস */
  text-decoration: none;
}

.nav-logo img {
  height: 40px; 
  width: auto;
  transition: transform 0.3s var(--ease);
}

.nav-logo:hover img {
  transform: scale(1.05);
}

.nav-logo .logo-text { 
  font-family: var(--font-display);
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--primary);
  line-height: 1;
  white-space: nowrap; /* নামটিকে এক লাইনে রাখবে */
}

/* Optional: মোবাইলের ছোট স্ক্রিনে জায়গা বাঁচাতে চাইলে নামটা লুকিয়ে শুধু লোগো দেখাতে পারেন */
@media (max-width: 480px) {
  .nav-logo .logo-text {
    display: none; /* সাইজ একটু ছোট হবে */
  }
}

/* Optional: Fallback styles if the image fails to load */
.nav-logo span { 
  font-family: var(--font-display);
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--primary);
  margin-left: 8px; /* Space between logo icon and text if you keep both */
}
.nav-cta {
  background: var(--primary);
  color: white;
  padding: 10px 24px;
  border-radius: var(--radius);
  font-weight: 600;
  font-size: 0.9rem;
  transition: all 0.3s var(--ease);
}
.nav-cta:hover { background: var(--primary-dark); transform: translateY(-1px); }

/* ═══════════════════════════════════════════════════════════
   HERO
═══════════════════════════════════════════════════════════ */
.hero {
  min-height: 100svh;
  display: flex; align-items: center; justify-content: center;
  position: relative;
  overflow: hidden;
  padding: 140px 40px 80px;
  background: radial-gradient(circle at top right, var(--primary-light), transparent 50%),
              radial-gradient(circle at bottom left, #f3f4f6, transparent 50%);
}

.hero-content {
  position: relative; z-index: 2;
  max-width: 800px;
  text-align: center;
}

.hero-eyebrow {
  display: inline-flex; align-items: center; gap: 10px;
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--primary);
  text-transform: uppercase;
  margin-bottom: 24px;
  opacity: 0;
  animation: fadeUp 0.8s 0.2s var(--ease) forwards;
}
.hero-eyebrow::before,
.hero-eyebrow::after {
  content: ''; width: 40px; height: 2px; background: var(--primary); opacity: 0.3;
}

.hero-headline {
  font-family: var(--font-display);
  font-size: clamp(2.4rem, 6vw, 4.5rem);
  font-weight: 700;
  line-height: 1.15;
  color: var(--text-dark);
  margin-bottom: 24px;
  opacity: 0;
  animation: fadeUp 0.9s 0.4s var(--ease) forwards;
}
.hero-headline em {
  font-style: normal;
  color: var(--primary);
}

.hero-sub {
  font-size: clamp(1rem, 2.5vw, 1.15rem);
  color: var(--text-muted);
  max-width: 600px;
  margin: 0 auto 40px;
  opacity: 0;
  animation: fadeUp 0.9s 0.6s var(--ease) forwards;
}

.hero-actions {
  display: flex; align-items: center; justify-content: center; gap: 16px;
  flex-wrap: wrap;
  opacity: 0;
  animation: fadeUp 0.9s 0.8s var(--ease) forwards;
}

.btn-primary {
  display: inline-flex; align-items: center; gap: 10px;
  background: var(--primary);
  color: white;
  padding: 16px 36px;
  border-radius: var(--radius);
  font-weight: 600;
  font-size: 1rem;
  transition: all 0.3s var(--ease);
  border: none; cursor: pointer;
  box-shadow: 0 4px 15px rgba(204, 0, 0, 0.2);
}
.btn-primary:hover { 
  background: var(--primary-dark); 
  transform: translateY(-2px); 
  box-shadow: 0 8px 25px rgba(204, 0, 0, 0.3); 
}

.btn-ghost {
  display: inline-flex; align-items: center; gap: 8px;
  color: var(--text-dark);
  padding: 16px 28px;
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  font-weight: 600;
  font-size: 1rem;
  transition: all 0.3s var(--ease);
  background: white;
  cursor: pointer;
}
.btn-ghost:hover { border-color: var(--primary); color: var(--primary); }

/* Slots indicator */
.hero-slots {
  margin-top: 30px;
  display: flex; align-items: center; justify-content: center; gap: 12px;
  opacity: 0;
  animation: fadeUp 0.9s 1s var(--ease) forwards;
}
.slots-bar-wrap {
  width: 160px; height: 6px;
  background: var(--border);
  border-radius: 4px; overflow: hidden;
}
.slots-bar {
  height: 100%;
  background: var(--primary);
  border-radius: 4px;
  transition: width 1.5s 1.5s var(--ease);
}
.slots-text {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-muted);
}
.slots-text strong { color: var(--primary); }

@keyframes fadeUp {
  from { opacity: 0; transform: translateY(24px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ═══════════════════════════════════════════════════════════
   SECTION COMMONS
═══════════════════════════════════════════════════════════ */
.section { padding: var(--section-pad) 40px; }
.section-alt { background: var(--bg-alt); }
.container { max-width: 1140px; margin: 0 auto; }

.section-label {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--primary);
  text-transform: uppercase;
  margin-bottom: 12px;
  display: flex; align-items: center; gap: 12px;
}
.section-label::before {
  content: ''; width: 32px; height: 2px; background: var(--primary);
}

.section-title {
  font-family: var(--font-display);
  font-size: clamp(2rem, 4.5vw, 3rem);
  font-weight: 700;
  line-height: 1.2;
  color: var(--text-dark);
  margin-bottom: 16px;
}

.section-subtitle {
  font-size: 1.1rem;
  color: var(--text-muted);
  max-width: 560px;
  margin-bottom: 48px;
}

/* Red divider line */
.primary-line {
  width: 64px; height: 3px;
  background: var(--primary);
  margin: 24px 0;
  border-radius: 2px;
}

/* ═══════════════════════════════════════════════════════════
   VIDEO SECTION
═══════════════════════════════════════════════════════════ */
.video-section {
  padding: var(--section-pad) 40px;
  background: var(--text-dark);
}
.video-section .section-label { color: var(--primary-light); }
.video-section .section-label::before { background: var(--primary-light); }
.video-section .section-title { color: white; }
.video-section .section-subtitle { color: #aaaaaa; }

.video-wrap {
  max-width: 840px; margin: 0 auto;
  border-radius: var(--radius-lg);
  overflow: hidden;
  box-shadow: 0 30px 60px rgba(0,0,0,0.4);
  position: relative;
}

.yt-facade {
  aspect-ratio: 16/9;
  position: relative;
  cursor: pointer;
  background: #000;
  display: flex; align-items: center; justify-content: center;
}
.yt-facade img {
  position: absolute; inset: 0; width: 100%; height: 100%;
  object-fit: cover; opacity: 0.8; transition: opacity 0.3s;
}
.yt-facade:hover img { opacity: 0.95; }

.yt-play-btn {
  position: relative; z-index: 2;
  width: 80px; height: 80px;
  background: var(--primary);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  transition: transform 0.3s var(--ease);
  box-shadow: 0 0 20px rgba(204, 0, 0, 0.5);
}
.yt-facade:hover .yt-play-btn { transform: scale(1.1); background: var(--primary-dark); }
.yt-play-btn svg { fill: white; margin-left: 6px; }
.yt-iframe { display: none; }
.yt-iframe iframe { width: 100%; aspect-ratio: 16/9; border: none; display: block; }

.video-caption { text-align: center; margin-top: 24px; font-size: 0.95rem; color: #888888; }

/* ═══════════════════════════════════════════════════════════
   CURRICULUM GRID
═══════════════════════════════════════════════════════════ */
.curriculum-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
}
.curriculum-card {
  background: var(--bg-main);
  padding: 40px 32px;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  position: relative;
  transition: transform 0.3s var(--ease), box-shadow 0.3s var(--ease);
}
.curriculum-card:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-md);
  border-color: rgba(204,0,0,0.2);
}

.curriculum-icon {
  width: 56px; height: 56px;
  background: var(--primary-light);
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 24px;
  color: var(--primary);
}
.curriculum-icon svg { width: 28px; height: 28px; stroke: currentColor; fill: none; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; }

.curriculum-title {
  font-family: var(--font-display);
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--text-dark);
  margin-bottom: 12px;
}
.curriculum-desc {
  font-size: 0.95rem;
  color: var(--text-muted);
  line-height: 1.6;
}
.curriculum-num {
  position: absolute; top: 24px; right: 24px;
  font-size: 2rem;
  font-weight: 700;
  color: var(--border);
  line-height: 1;
}

/* ═══════════════════════════════════════════════════════════
   MENTORS (Centered Single Layout)
═══════════════════════════════════════════════════════════ */
.mentors-wrap {
  display: flex; justify-content: center; /* Center the single mentor */
}
.mentor-card {
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  background: var(--bg-main);
  box-shadow: var(--shadow-sm);
  max-width: 400px;
  width: 100%;
}
.mentor-photo {
  aspect-ratio: 4/3;
  background: var(--bg-alt);
  overflow: hidden;
}
.mentor-photo img { width: 100%; height: 100%; object-fit: cover; object-position: top; }
.mentor-photo-placeholder {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  font-size: 4rem; color: var(--border);
}
.mentor-info { padding: 32px; text-align: center; }
.mentor-name {
  font-family: var(--font-display);
  font-size: 1.8rem;
  font-weight: 700;
  color: var(--text-dark);
  margin-bottom: 8px;
}
.mentor-title {
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--primary);
  margin-bottom: 16px;
}
.mentor-exp {
  display: inline-flex; align-items: center; gap: 8px;
  background: var(--bg-alt);
  border-radius: 999px;
  padding: 6px 16px;
  font-size: 0.9rem;
  color: var(--text-muted);
}

/* ═══════════════════════════════════════════════════════════
   STATS BAR
═══════════════════════════════════════════════════════════ */
.stats-bar {
  background: var(--primary);
  padding: 48px 40px;
  color: white;
}
.stats-bar-inner {
  max-width: 1140px; margin: 0 auto;
  display: grid; grid-template-columns: repeat(4, 1fr);
  gap: 24px;
  text-align: center;
}
.stat-num  { font-family: var(--font-display); font-size: clamp(2.5rem, 4vw, 3.5rem); font-weight: 700; line-height: 1; margin-bottom: 8px;}
.stat-lbl  { font-size: 1rem; font-weight: 500; opacity: 0.9; }

/* ═══════════════════════════════════════════════════════════
   GALLERY GRID
═══════════════════════════════════════════════════════════ */
.gallery-grid {
  display: grid;
  grid-template-columns: repeat(12, 1fr);
  grid-auto-rows: 240px;
  gap: 12px;
}

/* Desktop mosaic layout */
.gallery-item:nth-child(1)  { grid-column: span 5; grid-row: span 2; }
.gallery-item:nth-child(2)  { grid-column: span 4; grid-row: span 1; }
.gallery-item:nth-child(3)  { grid-column: span 3; grid-row: span 1; }
.gallery-item:nth-child(4)  { grid-column: span 4; grid-row: span 1; }
.gallery-item:nth-child(5)  { grid-column: span 3; grid-row: span 1; }
.gallery-item:nth-child(6)  { grid-column: span 3; grid-row: span 2; }
.gallery-item:nth-child(7)  { grid-column: span 4; grid-row: span 1; }
.gallery-item:nth-child(8)  { grid-column: span 5; grid-row: span 1; }
.gallery-item:nth-child(9)  { grid-column: span 4; grid-row: span 1; }
.gallery-item:nth-child(10) { grid-column: span 4; grid-row: span 1; }

.gallery-item {
  position: relative;
  background: var(--bg-alt);
  border-radius: var(--radius);
  overflow: hidden;
  cursor: pointer;
}

.gallery-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.45s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

/* Hover zoom */
.gallery-item:hover img {
  transform: scale(1.06);
}

/* Overlay on hover */
.gallery-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.38);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  opacity: 0;
  transition: opacity 0.3s ease;
  pointer-events: none;
}

.gallery-item:hover .gallery-overlay {
  opacity: 1;
}

/* Placeholder */
.gallery-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--border);
  color: var(--text-muted);
}

.gallery-placeholder-text {
  font-size: 0.8rem;
  text-align: center;
  padding: 20px;
  line-height: 1.6;
}

/* ═══════════════════════════════════════════════════════════
   LIGHTBOX MODAL
═══════════════════════════════════════════════════════════ */
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(0, 0, 0, 0.92);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;

  /* Hidden state */
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.3s ease, visibility 0.3s ease;
}

.modal-backdrop.is-open {
  opacity: 1;
  visibility: visible;
}

.modal-content {
  position: relative;
  max-width: min(90vw, 1100px);
  max-height: 88vh;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-img {
  display: block;
  max-width: 100%;
  max-height: 88vh;
  width: auto;
  height: auto;
  border-radius: 10px;
  object-fit: contain;
  box-shadow: 0 30px 80px rgba(0, 0, 0, 0.6);

  /* Entrance animation */
  transform: scale(0.94);
  opacity: 0;
  transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1),
              opacity 0.3s ease;
}

.modal-backdrop.is-open .modal-img {
  transform: scale(1);
  opacity: 1;
}

/* Close button */
.modal-close {
  position: fixed;
  top: 18px;
  right: 18px;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  border: none;
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s ease, transform 0.2s ease;
  z-index: 10;
  -webkit-tap-highlight-color: transparent;
}

.modal-close:hover {
  background: rgba(255, 255, 255, 0.25);
  transform: rotate(90deg);
}

/* Prev / Next nav */
.modal-nav {
  position: fixed;
  top: 50%;
  transform: translateY(-50%);
  width: 48px;
  height: 48px;
  border-radius: 50%;
  border: none;
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s ease, transform 0.2s ease;
  z-index: 10;
  -webkit-tap-highlight-color: transparent;
}

.modal-prev { left: 14px; }
.modal-next { right: 14px; }

.modal-nav:hover {
  background: rgba(255, 255, 255, 0.28);
}

.modal-prev:hover  { transform: translateY(-50%) translateX(-3px); }
.modal-next:hover  { transform: translateY(-50%) translateX(3px); }

/* Counter */
.modal-counter {
  position: fixed;
  bottom: 18px;
  left: 50%;
  transform: translateX(-50%);
  color: rgba(255, 255, 255, 0.65);
  font-size: 0.85rem;
  letter-spacing: 0.08em;
  pointer-events: none;
  user-select: none;
}

/* ═══════════════════════════════════════════════════════════
   RESPONSIVE — Tablet  (≤ 900px)
═══════════════════════════════════════════════════════════ */
@media (max-width: 900px) {
  .gallery-grid {
    grid-template-columns: repeat(4, 1fr);
    grid-auto-rows: 200px;
    gap: 10px;
  }

  .gallery-item:nth-child(1)  { grid-column: span 2; grid-row: span 2; }
  .gallery-item:nth-child(2)  { grid-column: span 2; grid-row: span 1; }
  .gallery-item:nth-child(3)  { grid-column: span 2; grid-row: span 1; }
  .gallery-item:nth-child(4)  { grid-column: span 2; grid-row: span 1; }
  .gallery-item:nth-child(5)  { grid-column: span 2; grid-row: span 1; }
  .gallery-item:nth-child(6)  { grid-column: span 2; grid-row: span 2; }
  .gallery-item:nth-child(7)  { grid-column: span 2; grid-row: span 1; }
  .gallery-item:nth-child(8)  { grid-column: span 2; grid-row: span 1; }
  .gallery-item:nth-child(9)  { grid-column: span 2; grid-row: span 1; }
  .gallery-item:nth-child(10) { grid-column: span 2; grid-row: span 1; }

  .modal-nav { display: none; } /* use swipe on tablet */
}

/* ═══════════════════════════════════════════════════════════
   RESPONSIVE — Mobile  (≤ 600px) — strict 2-column
═══════════════════════════════════════════════════════════ */
@media (max-width: 600px) {
  .gallery-grid {
    grid-template-columns: repeat(2, 1fr);
    grid-auto-rows: 160px;
    gap: 8px;
  }

  /* Reset ALL mosaic overrides → uniform 2-col tiles */
  .gallery-item:nth-child(n) {
    grid-column: span 1 !important;
    grid-row:    span 1 !important;
  }

  /* Give first item a bit more visual weight */
  .gallery-item:nth-child(1) {
    grid-column: span 2 !important;
    grid-row:    span 1 !important;
  }

  .modal-backdrop {
    padding: 0;
    align-items: center;
  }

  .modal-content {
    max-width: 100vw;
    max-height: 100dvh;
  }

  .modal-img {
    border-radius: 0;
    max-height: 100dvh;
  }

  .modal-close {
    top: 12px;
    right: 12px;
  }

  .modal-counter {
    bottom: 12px;
  }
}

/* ═══════════════════════════════════════════════════════════
   REGISTRATION FORM
═══════════════════════════════════════════════════════════ */
.form-section {
  padding: var(--section-pad) 40px;
  background: var(--bg-alt);
}
.form-layout {
  display: grid;
  grid-template-columns: 5fr 7fr;
  gap: 64px;
  align-items: start;
}
.form-pitch { position: sticky; top: 120px; }

.form-benefits {
  list-style: none;
  display: flex; flex-direction: column; gap: 16px;
  margin-top: 32px;
}
.form-benefits li {
  display: flex; align-items: flex-start; gap: 14px;
  font-size: 1rem; color: var(--text-dark); font-weight: 500;
}
.form-benefits li::before {
  content: '✓';
  flex-shrink: 0;
  width: 24px; height: 24px;
  background: var(--primary-light);
  color: var(--primary);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.8rem; font-weight: bold;
}

.price-box {
  margin-top: 48px;
  padding: 32px;
  background: white;
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  border-top: 4px solid var(--primary);
}
.price-label { font-size: 1rem; color: var(--text-muted); font-weight: 600; }
.price-amount {
  font-family: var(--font-display);
  font-size: 3.5rem; font-weight: 700;
  color: var(--primary);
  line-height: 1;
  margin: 8px 0;
}
.price-note { font-size: 0.9rem; color: var(--text-muted); }

/* Form card */
.form-card {
  background: white;
  border-radius: var(--radius-lg);
  padding: 48px;
  box-shadow: var(--shadow-md);
}
.form-card-title {
  font-family: var(--font-display);
  font-size: 2rem; font-weight: 700;
  color: var(--text-dark);
  margin-bottom: 8px;
}
.form-card-sub { font-size: 0.95rem; color: var(--text-muted); margin-bottom: 32px; }

/* Form alerts */
.form-alert {
  padding: 16px 20px;
  border-radius: var(--radius);
  margin-bottom: 24px;
  font-size: 0.95rem;
}
.form-alert-error { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
.form-alert-error ul { padding-left: 20px; margin-top: 8px; }

/* Form fields */
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.form-group { margin-bottom: 24px; }
.form-label {
  display: block;
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--text-dark);
  margin-bottom: 8px;
}
.form-label span { color: var(--primary); }

.form-input, .form-select, .form-textarea {
  width: 100%;
  background: var(--bg-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  color: var(--text-dark);
  font-size: 1rem;
  padding: 14px 16px;
  transition: border-color 0.2s, box-shadow 0.2s;
  outline: none;
}
.form-input:focus, .form-select:focus, .form-textarea:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(204, 0, 0, 0.1);
  background: white;
}
.form-input.invalid, .form-select.invalid { border-color: #ef4444; }

.form-hint { font-size: 0.8rem; color: var(--text-muted); margin-top: 6px; }
.form-error-text { font-size: 0.8rem; color: #ef4444; margin-top: 6px; display: none; }
.form-error-text.show { display: block; }

/* File upload */
.upload-zone {
  border: 2px dashed var(--border);
  border-radius: var(--radius);
  padding: 32px 20px;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s;
  background: var(--bg-alt);
  position: relative;
}
.upload-zone:hover { border-color: var(--primary); background: var(--primary-light); }
.upload-zone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
.upload-icon { font-size: 2.5rem; margin-bottom: 12px; }
.upload-text { font-size: 0.95rem; color: var(--text-muted); }
.upload-text strong { color: var(--primary); }
.upload-preview { display: none; margin-top: 12px; font-size: 0.9rem; color: var(--primary); font-weight: 600; justify-content: center;}
.upload-preview.show { display: flex; }

.form-submit {
  width: 100%;
  background: var(--primary);
  color: white;
  border: none;
  padding: 20px;
  border-radius: var(--radius);
  font-size: 1.1rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.3s var(--ease);
  margin-top: 16px;
}
.form-submit:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 10px 20px rgba(204,0,0,0.2); }

/* ═══════════════════════════════════════════════════════════
   FAQ
═══════════════════════════════════════════════════════════ */
.faq-list { display: flex; flex-direction: column; gap: 16px; max-width: 800px; margin: 0 auto; }
.faq-item {
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: white;
  overflow: hidden;
  transition: border-color 0.3s, box-shadow 0.3s;
}
.faq-item.open { border-color: var(--primary); box-shadow: var(--shadow-sm); }
.faq-q {
  width: 100%;
  display: flex; align-items: center; justify-content: space-between;
  padding: 24px;
  background: none; border: none; cursor: pointer;
  color: var(--text-dark);
  font-size: 1.1rem;
  font-weight: 600;
  text-align: left;
  gap: 16px;
}
.faq-item.open .faq-q { color: var(--primary); }
.faq-icon {
  flex-shrink: 0; width: 32px; height: 32px;
  background: var(--bg-alt);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: var(--text-muted);
  font-size: 1.2rem;
  transition: all 0.3s var(--ease);
}
.faq-item.open .faq-icon { transform: rotate(45deg); background: var(--primary-light); color: var(--primary); }
.faq-a { max-height: 0; overflow: hidden; transition: max-height 0.4s var(--ease); }
.faq-item.open .faq-a { max-height: 300px; }
.faq-a-inner { padding: 0 24px 24px; font-size: 1rem; color: var(--text-muted); }

/* ═══════════════════════════════════════════════════════════
   FOOTER
═══════════════════════════════════════════════════════════ */
.footer {
  background: var(--text-dark);
  color: white;
  padding: 60px 40px;
  text-align: center;
}
.footer-logo {
  font-family: var(--font-display);
  font-size: 1.8rem; font-weight: 700;
  color: white;
  margin-bottom: 16px;
}
.footer-contact { font-size: 1rem; color: #aaaaaa; margin-bottom: 24px; }
.footer-contact a { color: white; margin: 0 8px;}
.footer-copy { font-size: 0.9rem; color: #666666; }

/* ═══════════════════════════════════════════════════════════
   STICKY BOTTOM BAR
═══════════════════════════════════════════════════════════ */
.sticky-bar {
  position: fixed;
  bottom: 0; left: 0; right: 0;
  z-index: 400;
  max-width: 1140px; /* Added */
  margin: 0 auto;    /* Added */
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-top: 1px solid var(--border);
  border-left: 1px solid var(--border); /* Added for boxed look */
  border-right: 1px solid var(--border); /* Added for boxed look */
  border-top-left-radius: var(--radius);  /* Smooth top corners */
  border-top-right-radius: var(--radius); /* Smooth top corners */
  box-shadow: 0 -10px 30px rgba(0,0,0,0.05);
  padding: 16px 24px;
  display: flex; align-items: center; justify-content: space-between;
  gap: 20px;
  transform: translateY(100%);
  transition: transform 0.4s var(--ease);
}
.sticky-bar.visible { transform: translateY(0); }
.sticky-bar.hidden-near-form { transform: translateY(100%); }
.sticky-price-label { font-size: 0.8rem; color: var(--text-muted); font-weight: 600; }
.sticky-price {
  font-family: var(--font-display);
  font-size: 1.8rem; font-weight: 700;
  color: var(--primary);
  line-height: 1;
}
.sticky-cta {
  background: var(--primary);
  color: white;
  border: none; cursor: pointer;
  padding: 16px 36px;
  border-radius: var(--radius);
  font-size: 1rem;
  font-weight: 700;
  transition: all 0.3s var(--ease);
}
.sticky-cta:hover { background: var(--primary-dark); }
.sticky-slots-text { font-size: 0.85rem; color: var(--text-muted); margin-top: 4px; }

/* ═══════════════════════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════════════════════ */
@media (max-width: 1024px) {
  .curriculum-grid { grid-template-columns: repeat(2, 1fr); }
  .form-layout { grid-template-columns: 1fr; gap: 48px; }
  .form-pitch { position: static; text-align: center; }
  .form-benefits { align-items: center; }
  .gallery-grid { grid-template-columns: 1fr 1fr 1fr; grid-auto-rows: 200px; }
  .gallery-item { grid-column: span 1 !important; grid-row: span 1 !important; }
}
@media (max-width: 768px) {
  :root { --section-pad: 80px; }
  .section, .video-section, .form-section { padding: 60px 20px; }
  .nav { padding: 16px 20px; }
  .hero { padding: 120px 20px 80px; }
  .stats-bar { padding: 40px 20px; }
  .stats-bar-inner { grid-template-columns: repeat(2, 1fr); gap: 32px; }
  .curriculum-grid { grid-template-columns: 1fr; }
  .form-row { grid-template-columns: 1fr; }
  .form-card { padding: 32px 20px; }
  .gallery-grid { grid-template-columns: 1fr 1fr; grid-auto-rows: 160px; }
  .gallery-item:nth-child(1) { grid-column: span 2; }
  .sticky-bar { padding: 12px 16px; flex-direction: column; text-align: center; gap: 12px;}
  .sticky-cta { width: 100%; padding: 14px; }
}
@media (max-width: 480px) {
  .hero-headline { font-size: 2.2rem; }
  .btn-primary, .btn-ghost { width: 100%; justify-content: center; }
  .gallery-grid { grid-template-columns: 1fr; }
  .gallery-item { grid-column: span 1 !important; grid-row: span 1 !important; }
}
</style>
</head>
<body>

<nav class="nav" id="navbar">
  <a href="/" class="nav-logo">
    <img src="/assets/dma-logo.png" alt="Dhaka Model Agency Logo">
    <span class="logo-text">Dhaka Model Agency</span>
  </a>
  
  <?php if ($is_active): ?>
  <a href="#register" class="nav-cta">রেজিস্ট্রেশন করুন →</a>
  <?php endif; ?>
</nav>

<section class="hero" id="hero">
  <div class="hero-content">
    <div class="hero-eyebrow">ঢাকা মডেল এজেন্সি</div>
    <h1 class="hero-headline">
      আপনার মডেলিং ক্যারিয়ার<br>
      শুরু হোক <em>সঠিক গ্রুমিং</em> দিয়ে
    </h1>
    <p class="hero-sub">
      ২ মাসের প্রফেশনাল গ্রুমিং প্রোগ্রাম।
      মোট ৮টি সেশন | প্রতি শুক্রবার | বিকাল ৩টা – ৬টা
    </p>
    <?php if ($is_active): ?>
    <div class="hero-actions">
      <a href="#register" class="btn-primary">
        <span>এখনই রেজিস্ট্রেশন করুন</span>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </a>
      <a href="#video" class="btn-ghost">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>
        ভিডিও দেখুন
      </a>
    </div>
    <?php else: ?>
    <p style="color:var(--primary);font-size:1.1rem;margin-top:20px;font-weight:600;">রেজিস্ট্রেশন বর্তমানে বন্ধ আছে।</p>
    <?php endif; ?>
  </div>
</section>

<div class="stats-bar reveal">
  <div class="stats-bar-inner">
    <div class="stat-item">
      <div class="stat-num">২</div>
      <div class="stat-lbl">মাসের প্রোগ্রাম</div>
    </div>
    <div class="stat-item">
      <div class="stat-num">৮</div>
      <div class="stat-lbl">মোট সেশন</div>
    </div>
    <div class="stat-item">
      <div class="stat-num">১</div>
      <div class="stat-lbl">লিড ট্রেইনার</div>
    </div>
    <div class="stat-item">
      <div class="stat-num">৬</div>
      <div class="stat-lbl">প্রফেশনাল মডিউল</div>
    </div>
  </div>
</div>

<section class="video-section" id="video">
  <div class="container">
    <div style="text-align:center; margin-bottom:48px;" class="reveal">
      <div class="section-label" style="justify-content:center;">আমাদের কাজ</div>
      <h2 class="section-title">কেনো এই প্রোগ্রামে রেজিস্ট্রেশন করবেন?</h2>
      <p class="section-subtitle" style="margin:0 auto;">
        পেশাদার মডেলিং সেশনের পেছনের দৃশ্য, আমাদের ট্রেনিং পদ্ধতি এবং সফল মডেলদের যাত্রা।
      </p>
    </div>

    <div class="video-wrap reveal">
      <div class="yt-facade" id="ytFacade" data-ytid="<?= htmlspecialchars($youtube_id) ?>">
        <img src="https://i.ytimg.com/vi/<?= htmlspecialchars($youtube_id) ?>/sddefault.jpg"
             alt="Grooming Lab Season 6 — Behind The Scenes"
             loading="lazy"
             onerror="this.onerror=null; this.src='https://i.ytimg.com/vi/<?= htmlspecialchars($youtube_id) ?>/hqdefault.jpg'">
        <div class="yt-play-btn">
          <svg width="28" height="28" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        </div>
      </div>
      <div class="yt-iframe" id="ytIframe"></div>
    </div>
    <p class="video-caption reveal">Grooming Lab Season 6 — Behind The Scenes &amp; সফল অংশগ্রহণকারীদের অভিজ্ঞতা</p>
  </div>
</section>

<section class="section section-alt" id="curriculum">
  <div class="container">
    <div class="reveal">
      <div class="section-label">প্রোগ্রামের বিষয়বস্তু</div>
      <h2 class="section-title">আপনি যা শিখবেন</h2>
      <p class="section-subtitle">প্রতিটি মডিউল ইন্ডাস্ট্রির বাস্তব চাহিদা অনুযায়ী ডিজাইন করা হয়েছে।</p>
    </div>
    <div class="curriculum-grid reveal">
      <?php foreach ($curriculum as $i => $item): ?>
      <div class="curriculum-card">
        <div class="curriculum-num"><?= str_pad($i+1, 2, '0', STR_PAD_LEFT) ?></div>
        <div class="curriculum-icon">
          <?= get_curriculum_icon($item['icon']) ?>
        </div>
        <h3 class="curriculum-title"><?= $item['title'] ?></h3>
        <p class="curriculum-desc"><?= $item['desc'] ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php
/*
 * ─────────────────────────────────────────────────────────────
 *  CURRICULUM DATA — config.php অথবা $curriculum array-তে
 *  নিচের ৬টি official module সেট করুন:
 * ─────────────────────────────────────────────────────────────
 * $curriculum = [
 *   [
 *     'icon'  => 'runway',
 *     'title' => 'প্রফেশনাল রানওয়ে ও ক্যাটওয়াক',
 *     'desc'  => 'রানওয়ে ওয়াকিং, পোশ্চার, রিদম, আত্মবিশ্বাস এবং প্রফেশনাল ক্যাটওয়াক টেকনিক শেখানো হবে।',
 *   ],
 *   [
 *     'icon'  => 'acting',
 *     'title' => 'অভিনয় ও পারফরম্যান্স কৌশল',
 *     'desc'  => 'অভিনয়, ফেসিয়াল এক্সপ্রেশন, বডি ল্যাঙ্গুয়েজ ও পারফরম্যান্স কনফিডেন্স ডেভেলপমেন্ট।',
 *   ],
 *   [
 *     'icon'  => 'communication',
 *     'title' => 'কর্পোরেট প্রেজেন্টেশন ও কমিউনিকেশন',
 *     'desc'  => 'যোগাযোগ দক্ষতা, প্রেজেন্টেশন এবং পাবলিক স্পিকিং আত্মবিশ্বাস উন্নয়ন।',
 *   ],
 *   [
 *     'icon'  => 'camera',
 *     'title' => 'ক্যামেরা কনফিডেন্স ও পোজিং',
 *     'desc'  => 'ফটোশুট ও ভিডিও সেশনে পোজিং, এক্সপ্রেশন এবং ক্যামেরার সামনে আত্মবিশ্বাস অর্জন।',
 *   ],
 *   [
 *     'icon'  => 'styling',
 *     'title' => 'ফ্যাশন স্টাইলিং ও পার্সোনাল গ্রুমিং',
 *     'desc'  => 'স্টাইলিং স্ট্যান্ডার্ড, গ্রুমিং এবং প্রফেশনাল পরিচ্ছন্ন উপস্থিতি তৈরির কৌশল।',
 *   ],
 *   [
 *     'icon'  => 'personality',
 *     'title' => 'পার্সোনালিটি ডেভেলপমেন্ট ও পাবলিক স্পিকিং',
 *     'desc'  => 'আত্মবিশ্বাস, ব্যক্তিত্ব, লিডারশিপ উপস্থিতি এবং পাবলিক স্পিকিং দক্ষতা গড়ে তোলা।',
 *   ],
 * ];
 * ─────────────────────────────────────────────────────────────
 */
?>

<section class="section" id="mentors">
  <div class="container">
    <div class="reveal" style="text-align: center; display: flex; flex-direction: column; align-items: center;">
      <div class="section-label" style="justify-content: center;">আপনার মেন্টর</div>
      <h2 class="section-title">বিশেষজ্ঞ গাইডেন্স</h2>
      <p class="section-subtitle">বাংলাদেশের অভিজ্ঞ পেশাদারদের কাছ থেকে সরাসরি শিখুন।</p>
    </div>
    <div class="mentors-wrap reveal">

      <?php
      /*
       * ─────────────────────────────────────────────────────────
       *  MENTOR DATA — config.php বা $mentors array-তে সেট করুন:
       * ─────────────────────────────────────────────────────────
       * $mentors = [
       *   [
       *     'img'  => 'storage/trainers/01KQYGGW2GW680NKHB4ZT2YKK4.jpg',
       *     'name' => 'Dilruba Doyel',
       *     'title'=> 'Senior Grooming Expert',
       *     'exp'  => 'বাংলাদেশি অভিনেত্রী ও প্রাক্তন মডেল। Alpha (2019), The Tales of Chandrabati (2019), Call of the Red-Rooster (2021) এবং Song of the Soul (2022) চলচ্চিত্রে অভিনয়ের জন্য পরিচিত।',
       *   ],
       * ];
       * ─────────────────────────────────────────────────────────
       */
      ?>

      <?php foreach ($mentors as $i => $m): ?>
      <div class="mentor-card">
        <div class="mentor-photo">
          <?php if (file_exists(__DIR__ . '/' . $m['img'])): ?>
          <img src="/<?= $m['img'] ?>" alt="<?= htmlspecialchars($m['name']) ?>" loading="lazy">
          <?php else: ?>
          <div class="mentor-photo-placeholder">👤</div>
          <?php endif; ?>
        </div>
        <div class="mentor-info">
          <h3 class="mentor-name"><?= htmlspecialchars($m['name']) ?></h3>
          <div class="mentor-title"><?= htmlspecialchars($m['title']) ?></div>
          <div class="mentor-exp">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <?= htmlspecialchars($m['exp']) ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     GALLERY SECTION
═══════════════════════════════════════════════════════════ -->
<section class="section section-alt" id="gallery">
  <div class="container">
    <div style="text-align:center; margin-bottom:48px;" class="reveal">
      <div class="section-label" style="justify-content:center;">সফলতার গল্প</div>
      <h2 class="section-title">আমাদের মডেলরা</h2>
      <p class="section-subtitle" style="margin:0 auto;">
        আমাদের গ্রুমিং প্রোগ্রাম থেকে বেরিয়ে তারা এখন সফল ক্যারিয়ার গড়ে তুলেছেন।
      </p>
    </div>

    <div class="gallery-grid reveal">
      <?php for ($g = 1; $g <= 10; $g++): ?>
      <div class="gallery-item" data-index="<?= $g - 1 ?>" onclick="openModal(this)">
        <?php if (file_exists(__DIR__ . "/assets/gallery-{$g}.jpg")): ?>
          <img src="/assets/gallery-<?= $g ?>.jpg" alt="মডেল <?= $g ?>" loading="lazy">
        <?php else: ?>
          <div class="gallery-placeholder">
            <div class="gallery-placeholder-text">ছবি <?= $g ?><br>আপনার ছবি যোগ করুন<br>/assets/gallery-<?= $g ?>.jpg</div>
          </div>
        <?php endif; ?>
        <div class="gallery-overlay">
          <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            <line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/>
          </svg>
        </div>
      </div>
      <?php endfor; ?>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════════════════
     LIGHTBOX MODAL
═══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="galleryModal" onclick="handleBackdropClick(event)">
  <button class="modal-close" onclick="closeModal()" aria-label="বন্ধ করুন">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
    </svg>
  </button>

  <button class="modal-nav modal-prev" onclick="navigateModal(-1)" aria-label="আগের ছবি">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <polyline points="15 18 9 12 15 6"/>
    </svg>
  </button>

  <div class="modal-content" id="modalContent">
    <img class="modal-img" id="modalImg" src="" alt="" loading="lazy">
  </div>

  <button class="modal-nav modal-next" onclick="navigateModal(1)" aria-label="পরের ছবি">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <polyline points="9 18 15 12 9 6"/>
    </svg>
  </button>

  <div class="modal-counter" id="modalCounter"></div>
</div>

<?php if ($is_active): ?>
<section class="form-section" id="register">
  <div class="container">
    <div class="form-layout">

      <div class="form-pitch reveal">
        <div class="section-label">রেজিস্ট্রেশন</div>
        <h2 class="section-title">আজই আপনার<br>সিট বুক করুন</h2>
        <div class="primary-line"></div>
        <p style="color:var(--text-muted);font-size:1.05rem;margin-bottom:0; line-height: 1.6;">
          সীমিত আসন। প্রতিটি সিটের জন্য ব্যক্তিগত মনোযোগ নিশ্চিত করা হয়।
          আপনার তথ্য পূরণ করুন — আমাদের টিম শীঘ্রই আপনার সাথে যোগাযোগ করবে।
        </p>

        <ul class="form-benefits">
          <li>অফিশিয়াল গ্রুমিং ল্যাব ক্যাপ ও টি-শার্ট</li>
          <li>প্রফেশনাল ব্র্যান্ড ফটোশুট</li>
          <li>পোর্টফোলিও ডেভেলপমেন্টের সুযোগ</li>
          <li>DMA-তে ১ বছরের বিনামূল্যে মেম্বারশিপ</li>
          <li>অফিশিয়াল পার্টিসিপেশন সার্টিফিকেট</li>
          <li>মিডিয়া ও প্রমোশনাল কভারেজের সুযোগ</li>
        </ul>

        <div class="price-box">
          <div class="price-label">কোর্স ফি</div>
          <div class="price-amount">৳১২,০০০</div>
          <div class="price-note">শুরুর তারিখ: ১ জুন, ২০২৬ · প্রতি শুক্রবার | বিকাল ৩টা – ৬টা</div>
          <div class="price-note" style="margin-top:6px;">ভর্তির পর পেমেন্ট নিশ্চিত করতে হবে</div>
        </div>
      </div>

      <div class="reveal">
        <div class="form-card">
          <div class="form-card-title">রেজিস্ট্রেশন ফর্ম</div>
          <div class="form-card-sub">সমস্ত তথ্য সঠিকভাবে পূরণ করুন। <span style="color:var(--primary)">*</span> চিহ্নিত ঘর অবশ্যই পূরণ করতে হবে।</div>

          <?php if (!empty($form_errors) || $query_error === 'save_failed'): ?>
          <div class="form-alert form-alert-error">
            <strong>Something went wrong:</strong>
            <ul>
              <?php foreach ($form_errors as $err): ?>
              <li><?= htmlspecialchars($err) ?></li>
              <?php endforeach; ?>
              <?php if ($query_error === 'save_failed'): ?>
              <li>Something went wrong while saving your data. Please try again.</li>
              <?php endif; ?>
            </ul>
          </div>
          <?php endif; ?>
          <?php if ($query_error === 'quota_full'): ?>
          <div class="form-alert" style="background:var(--primary-light);border:1px solid var(--primary);color:var(--primary-dark);">
            <strong>রেজিস্ট্রেশন বন্ধ</strong>
            <p style="margin-top:8px;">দুঃখিত, আমাদের রেজিস্ট্রেশন কোটা পূর্ণ হয়ে গেছে। ভবিষ্যতে আরও প্রোগ্রাম আসবে, তাই আমাদের সাথে থাকুন!</p>
          </div>
          <?php endif; ?>

          <form method="POST" action="/submit.php" enctype="multipart/form-data" id="regForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="full_name">Full Name <span>*</span></label>
                <input class="form-input" type="text" id="full_name" name="full_name"
                       placeholder="Your full name (English)"
                       value="<?= htmlspecialchars($form_old['full_name'] ?? '') ?>"
                       required minlength="3">
                <div class="form-error-text" id="err_full_name">Name must be at least 3 characters long.</div>
              </div>
              <div class="form-group">
                <label class="form-label" for="phone">Mobile Number <span>*</span></label>
                <input class="form-input" type="tel" id="phone" name="phone"
                       placeholder="01XXXXXXXXX"
                       value="<?= htmlspecialchars($form_old['phone'] ?? '') ?>"
                       required>
                <div class="form-hint">Provide your WhatsApp number</div>
                <div class="form-error-text" id="err_phone">Please enter a valid Bangladeshi mobile number.</div>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="email">Email Address</label>
              <input class="form-input" type="email" id="email" name="email"
                     placeholder="Your email address (optional)"
                     value="<?= htmlspecialchars($form_old['email'] ?? '') ?>">
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="dob">Date of Birth <span>*</span></label>
                <input class="form-input" type="date" id="dob" name="dob"
                       value="<?= htmlspecialchars($form_old['dob'] ?? '') ?>"
                       required>
                <div class="form-error-text" id="err_dob">Please enter a valid date of birth.</div>
              </div>
              <div class="form-group">
                <label class="form-label" for="gender">Gender <span>*</span></label>
                <select class="form-select" id="gender" name="gender" required>
                  <option value="" disabled <?= empty($form_old['gender']) ? 'selected' : '' ?>>Select</option>
                  <option value="female" <?= ($form_old['gender']??'')==='female'?'selected':'' ?>>Female</option>
                  <option value="male"   <?= ($form_old['gender']??'')==='male'  ?'selected':'' ?>>Male</option>
                  <option value="other"  <?= ($form_old['gender']??'')==='other' ?'selected':'' ?>>Other</option>
                </select>
                <div class="form-error-text" id="err_gender">Please select your gender.</div>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="height_cm">Height <span>*</span></label>
                <select class="form-select" id="height_cm" name="height_cm" required>
                  <option value="" disabled <?= empty($form_old['height_cm']) ? 'selected' : '' ?>>Select Height</option>
                  <?php
                  for ($feet = 4; $feet <= 6; $feet++) {
                      for ($inches = 0; $inches <= 11; $inches++) {
                          if ($feet == 4 && $inches < 5) continue;
                          if ($feet == 6 && $inches > 8) continue;
                          $cm = round(($feet * 30.48) + ($inches * 2.54));
                          $label = "{$feet}' {$inches}\"  ({$cm} cm)";
                          $selected = (($form_old['height_cm']??'') == $cm) ? 'selected' : '';
                          echo "<option value=\"{$cm}\" {$selected}>{$label}</option>\n";
                      }
                  }
                  ?>
                </select>
                <div class="form-error-text" id="err_height">Please select a valid height.</div>
              </div>
              <div class="form-group">
                <label class="form-label" for="weight_kg">Weight (kg)</label>
                <input class="form-input" type="number" id="weight_kg" name="weight_kg"
                       placeholder="e.g., 55 (optional)"
                       min="30" max="150"
                       value="<?= htmlspecialchars($form_old['weight_kg'] ?? '') ?>">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="address">Current Address <span>*</span></label>
                <input class="form-input" type="text" id="address" name="address"
                      placeholder="e.g., District, Upazila, and Details..."
                      value="<?= htmlspecialchars($form_old['address'] ?? '') ?>"
                      required>
                <div class="form-error-text" id="err_address">Please enter your complete address.</div>
              </div>
              <div class="form-group">
                <label class="form-label" for="skin_tone">Skin Tone</label>
                <select class="form-select" id="skin_tone" name="skin_tone">
                  <option value="">Select (Optional)</option>
                  <option value="fair"     <?= ($form_old['skin_tone']??'')==='fair'    ?'selected':'' ?>>Fair</option>
                  <option value="wheatish" <?= ($form_old['skin_tone']??'')==='wheatish'?'selected':'' ?>>Wheatish</option>
                  <option value="dusky"    <?= ($form_old['skin_tone']??'')==='dusky'   ?'selected':'' ?>>Dusky</option>
                  <option value="dark"     <?= ($form_old['skin_tone']??'')==='dark'    ?'selected':'' ?>>Dark</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="experience">Previous Experience</label>
              <select class="form-select" id="experience" name="experience">
                <option value="none"         <?= ($form_old['experience']??'none')==='none'        ?'selected':'' ?>>No Experience</option>
                <option value="some"         <?= ($form_old['experience']??'')==='some'            ?'selected':'' ?>>Some Experience</option>
                <option value="professional" <?= ($form_old['experience']??'')==='professional'    ?'selected':'' ?>>Professional Experience</option>
              </select>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="fb_profile">Facebook Profile Link</label>
                <input class="form-input" type="url" id="fb_profile" name="fb_profile"
                       placeholder="facebook.com/yourname"
                       value="<?= htmlspecialchars($form_old['fb_profile'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="how_heard">How did you hear about us?</label>
                <select class="form-select" id="how_heard" name="how_heard">
                  <option value="facebook"  <?= ($form_old['how_heard']??'facebook')==='facebook' ?'selected':'' ?>>Facebook</option>
                  <option value="instagram" <?= ($form_old['how_heard']??'')==='instagram'        ?'selected':'' ?>>Instagram</option>
                  <option value="friend"    <?= ($form_old['how_heard']??'')==='friend'           ?'selected':'' ?>>Friend / Acquaintance</option>
                  <option value="poster"    <?= ($form_old['how_heard']??'')==='poster'           ?'selected':'' ?>>Poster / Banner</option>
                  <option value="other"     <?= ($form_old['how_heard']??'')==='other'            ?'selected':'' ?>>Other</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Upload Your Photo</label>
              <div class="upload-zone" id="uploadZone">
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
                <div class="upload-icon">📸</div>
                <div class="upload-text">
                  <strong>Click here</strong> or drag and drop<br>
                  JPG, PNG, WEBP — up to 5 MB
                </div>
                <div class="upload-preview" id="uploadPreview">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;"><polyline points="20 6 9 17 4 12"/></svg>
                  <span id="uploadFileName"></span>
                </div>
              </div>
            </div>

            <button type="submit" class="form-submit" id="submitBtn">
              Submit →
            </button>

            <p style="text-align:center;font-size:0.85rem;color:var(--text-muted);margin-top:16px;">
              Your information is completely secure and will not be shared with any third parties.
            </p>
          </form>
        </div>
      </div>

    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════
     VENUE SECTION
═══════════════════════════════════════════════════════════ -->
<section class="section section-alt" id="venue">
  <div class="container">
    <div style="text-align:center; margin-bottom:48px;" class="reveal">
      <div class="section-label" style="justify-content:center;">ঠিকানা</div>
      <h2 class="section-title">কোথায় হবে?</h2>
      <p class="section-subtitle" style="margin:0 auto;">
        ক্লাস এবং ভর্তির জন্য আলাদা ঠিকানা নিচে দেওয়া হলো।
      </p>
    </div>

    <div class="venue-grid reveal" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:24px;">

      <div class="venue-card" style="background:var(--card-bg, #fff); border:1px solid var(--border, #e5e7eb); border-radius:16px; padding:32px;">
        <div style="font-size:1.5rem; margin-bottom:12px;">🏛️</div>
        <h3 style="font-size:1.1rem; font-weight:700; margin-bottom:8px;">ক্লাসের ভেন্যু</h3>
        <p style="color:var(--text-muted); line-height:1.7; margin:0;">
          Flat-10A, Sonargaon Imtiaz Tower,<br>
          10/3-Box Culvert Road, Free School Street,<br>
          Dhanmondi, Dhaka-1205<br>
          <em style="font-size:0.88rem;">(কারওয়ান বাজার মেট্রো স্টেশনের দক্ষিণ পাশে, বাংলা ভিশন টিভির পাশে)</em>
        </p>
      </div>

      <div class="venue-card" style="background:var(--card-bg, #fff); border:1px solid var(--border, #e5e7eb); border-radius:16px; padding:32px;">
        <div style="font-size:1.5rem; margin-bottom:12px;">📋</div>
        <h3 style="font-size:1.1rem; font-weight:700; margin-bottom:8px;">ভর্তির ঠিকানা</h3>
        <p style="color:var(--text-muted); line-height:1.7; margin:0;">
          2nd Colony, Mazar Road,<br>
          Mirpur-1, Dhaka-1216
        </p>
        <div style="margin-top:16px;">
          <a href="tel:+8801926960164" style="display:inline-flex; align-items:center; gap:6px; color:var(--primary); font-weight:600; text-decoration:none; font-size:0.95rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.56 3.37a2 2 0 0 1 2-2.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 5.61 5.61l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            +880 1926-960164
          </a>
        </div>
      </div>

    </div>
  </div>
</section>

<section class="section" id="faq">
  <div class="container">
    <div style="text-align:center; margin-bottom:48px;" class="reveal">
      <div class="section-label" style="justify-content:center;">সাধারণ প্রশ্ন</div>
      <h2 class="section-title">আপনার মনে প্রশ্ন আছে?</h2>
    </div>

    <?php
    /*
     * ─────────────────────────────────────────────────────────
     *  FAQ DATA — config.php বা $faqs array-তে সেট করুন:
     * ─────────────────────────────────────────────────────────
     * $faqs = [
     *   [
     *     'q' => 'আগে কোনো অভিজ্ঞতা দরকার আছে কি?',
     *     'a' => 'না। নতুন এবং অভিজ্ঞ — উভয়েই এই প্রোগ্রামে অংশ নিতে পারবেন। Grooming Lab Season 6 সবার জন্য উন্মুক্ত।',
     *   ],
     *   [
     *     'q' => 'কোর্সের সময়সূচি কী?',
     *     'a' => 'প্রতি শুক্রবার, বিকাল ৩টা থেকে ৬টা। মোট ৮টি সেশন, ২ মাসের মধ্যে সম্পন্ন হবে।',
     *   ],
     *   [
     *     'q' => 'ক্লাস কোথায় অনুষ্ঠিত হবে?',
     *     'a' => 'ক্লাস হবে Flat-10A, Sonargaon Imtiaz Tower, 10/3-Box Culvert Road, Free School Street, Dhanmondi, Dhaka-1205-তে (কারওয়ান বাজার মেট্রো স্টেশনের দক্ষিণ পাশে, বাংলা ভিশন টিভির পাশে)। ভর্তির জন্য Mirpur-1 অফিসেও যোগাযোগ করা যাবে।',
     *   ],
     *   [
     *     'q' => 'কোর্স ফি কত এবং কীভাবে পরিশোধ করতে হবে?',
     *     'a' => 'কোর্স ফি ৳১২,০০০। ভর্তি নিশ্চিত হওয়ার পর পেমেন্ট করতে হবে।',
     *   ],
     *   [
     *     'q' => 'সার্টিফিকেট পাওয়া যাবে?',
     *     'a' => 'হ্যাঁ। প্রোগ্রাম সম্পন্ন করলে অফিশিয়াল পার্টিসিপেশন সার্টিফিকেট দেওয়া হবে।',
     *   ],
     *   [
     *     'q' => 'ফটোশুট কি প্রোগ্রামে অন্তর্ভুক্ত?',
     *     'a' => 'হ্যাঁ। প্রফেশনাল ব্র্যান্ড ফটোশুট এবং পোর্টফোলিও ডেভেলপমেন্টের সুযোগ প্রোগ্রামের অংশ।',
     *   ],
     *   [
     *     'q' => 'DMA মেম্বারশিপ কী?',
     *     'a' => 'প্রোগ্রাম শেষে Dhaka Model Agency-তে ১ বছরের বিনামূল্যে অফিশিয়াল কাস্টিং প্রোফাইল মেম্বারশিপ পাবেন।',
     *   ],
     *   [
     *     'q' => 'কীভাবে যোগাযোগ করব?',
     *     'a' => 'ফোন করুন: +880 1926-960164। অথবা উপরের ফর্ম পূরণ করুন — আমাদের টিম শীঘ্রই যোগাযোগ করবে।',
     *   ],
     * ];
     * ─────────────────────────────────────────────────────────
     */
    ?>

    <div class="faq-list reveal" id="faqList">
      <?php foreach ($faqs as $i => $faq): ?>
      <div class="faq-item" data-faq="<?= $i ?>">
        <button class="faq-q" aria-expanded="false">
          <span><?= htmlspecialchars($faq['q']) ?></span>
          <div class="faq-icon">+</div>
        </button>
        <div class="faq-a" role="region">
          <div class="faq-a-inner"><?= htmlspecialchars($faq['a']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<footer class="footer">
  <div class="footer-logo">ঢাকা মডেল এজেন্সি</div>
  <div class="footer-tagline" style="font-size:0.85rem; color:var(--text-muted); margin-bottom:8px;">
    Presented by Neo Classic Media &nbsp;·&nbsp; Associated with Ananda Binodon
  </div>
  <div class="footer-contact">
    <a href="tel:+8801926960164">+880 1926-960164</a>
    <?php if ($contact_email): ?>
    &nbsp;·&nbsp;
    <a href="mailto:<?= htmlspecialchars($contact_email) ?>"><?= htmlspecialchars($contact_email) ?></a>
    <?php endif; ?>
  </div>
  <div class="footer-copy">© <?= date('Y') ?> ঢাকা মডেল এজেন্সি। সর্বস্বত্ব সংরক্ষিত। &nbsp;·&nbsp; Lic. No: 03-090585 &nbsp;·&nbsp; ESTD-2012</div>
</footer>

<?php if ($is_active): ?>
<div class="sticky-bar" id="stickyBar">
  <div>
    <div class="sticky-price-label">কোর্স ফি</div>
    <div class="sticky-price">৳১২,০০০</div>
  </div>
  <button class="sticky-cta" onclick="document.getElementById('register').scrollIntoView({behavior:'smooth'})">
    রেজিস্ট্রেশন করুন →
  </button>
</div>
<?php endif; ?>

<script>
// ── Navbar scroll effect ─────────────────────────────────────
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
  navbar.classList.toggle('scrolled', window.scrollY > 60);
}, { passive: true });

// ── Scroll reveal ─────────────────────────────────────────────
const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('visible');
      revealObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));

// ── YouTube Facade ────────────────────────────────────────────
const ytFacade = document.getElementById('ytFacade');
const ytIframe = document.getElementById('ytIframe');
if (ytFacade) {
  ytFacade.addEventListener('click', () => {
    const id = ytFacade.dataset.ytid;
    ytFacade.style.display = 'none';
    ytIframe.style.display = 'block';
    ytIframe.innerHTML = `<iframe src="https://www.youtube.com/embed/${id}?autoplay=1&rel=0"
      allow="autoplay; encrypted-media; fullscreen" allowfullscreen loading="lazy"></iframe>`;

    <?php if (!empty($pixel_id)): ?>
    if (typeof fbq !== 'undefined') fbq('trackCustom', 'VideoPlay', { content_name: 'DMA Grooming Promo' });
    <?php endif; ?>
  });
}
// ── Scroll to form error on page load (PHP session errors) ────
document.addEventListener('DOMContentLoaded', () => {
  const formAlert = document.querySelector('.form-alert-error');
  if (formAlert) {
    // Small delay so layout is fully painted
    setTimeout(() => {
      formAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
      // Optional: briefly highlight it to draw the eye
      formAlert.style.transition = 'box-shadow 0.4s ease';
      formAlert.style.boxShadow = '0 0 0 3px rgba(204,0,0,0.25)';
      setTimeout(() => { formAlert.style.boxShadow = ''; }, 1800);
    }, 200);
  }
});
// ── FAQ Accordion ─────────────────────────────────────────────
document.querySelectorAll('.faq-q').forEach(btn => {
  btn.addEventListener('click', () => {
    const item    = btn.parentElement;
    const isOpen  = item.classList.contains('open');
    document.querySelectorAll('.faq-item.open').forEach(el => el.classList.remove('open'));
    if (!isOpen) item.classList.add('open');
    btn.setAttribute('aria-expanded', String(!isOpen));
  });
});

// ── Sticky bar visibility (hide near form) ────────────────────
const stickyBar = document.getElementById('stickyBar');
const formSection = document.getElementById('register');

if (stickyBar && formSection) {
  let stickyVisible = false;

  const updateSticky = () => {
    const scrollY = window.scrollY;
    const heroH   = document.getElementById('hero').offsetHeight;
    const formRect = formSection.getBoundingClientRect();
    const nearForm = formRect.top < window.innerHeight && formRect.bottom > 0;

    if (scrollY > heroH * 0.6 && !nearForm) {
      stickyBar.classList.add('visible');
      stickyBar.classList.remove('hidden-near-form');
    } else {
      stickyBar.classList.remove('visible');
    }
  };

  window.addEventListener('scroll', updateSticky, { passive: true });
  updateSticky();
}

// ── Photo upload UX ───────────────────────────────────────────
const photoInput   = document.getElementById('photo');
const uploadZone   = document.getElementById('uploadZone');
const uploadPreview = document.getElementById('uploadPreview');
const uploadFileName = document.getElementById('uploadFileName');

if (photoInput) {
  photoInput.addEventListener('change', () => {
    const file = photoInput.files[0];
    if (file) {
      uploadFileName.textContent = file.name;
      uploadPreview.classList.add('show');
    }
  });

  ['dragover','dragenter'].forEach(ev => {
    uploadZone.addEventListener(ev, e => { e.preventDefault(); uploadZone.style.borderColor = 'var(--primary)'; });
  });
  ['dragleave','drop'].forEach(ev => {
    uploadZone.addEventListener(ev, () => uploadZone.style.borderColor = '');
  });
}

// ── Form inline validation ────────────────────────────────────
const regForm = document.getElementById('regForm');
if (regForm) {
  const rules = {
    full_name: v => v.trim().length >= 3,
    phone:     v => /^(\+8801|01)[3-9]\d{8}$/.test(v.replace(/\s/g,'')),
    dob:       v => {
      if (!v) return false;
      const age = Math.floor((new Date() - new Date(v)) / (365.25*24*3600*1000));
      return age >= 16 && age <= 35;
    },
    gender:    v => ['male','female','other'].includes(v),
    height_cm: v => v !== '',
    address:   v => v.trim().length >= 5,
  };

  const validateField = (name, value) => {
    const errEl = document.getElementById('err_' + name);
    const input = document.getElementById(name);
    if (!rules[name] || !errEl || !input) return true;
    const valid = rules[name](value);
    input.classList.toggle('invalid', !valid);
    errEl.classList.toggle('show', !valid);
    return valid;
  };

  // Live validation on blur
  Object.keys(rules).forEach(name => {
    const el = document.getElementById(name);
    if (el) {
      el.addEventListener('blur', () => validateField(name, el.value));
      el.addEventListener('input', () => {
        if (el.classList.contains('invalid')) validateField(name, el.value);
      });
    }
  });

  // Pre-submit validation
  regForm.addEventListener('submit', e => {
    let allValid = true;
    Object.keys(rules).forEach(name => {
      const el = document.getElementById(name);
      if (el && !validateField(name, el.value)) allValid = false;
    });
    if (!allValid) {
      e.preventDefault();
      const firstErr = regForm.querySelector('.form-input.invalid, .form-select.invalid');
      if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
    // Fire pixel ViewContent on submit attempt
    <?php if (!empty($pixel_id)): ?>
    if (typeof fbq !== 'undefined') fbq('track', 'InitiateCheckout', { content_name: 'DMA Registration' });
    if (typeof ttq !== 'undefined') ttq.track('InitiateCheckout');
    <?php endif; ?>
    document.getElementById('submitBtn').disabled = true;
    document.getElementById('submitBtn').textContent = 'জমা দেওয়া হচ্ছে…';
  });
}
/* ═══════════════════════════════════════════════════════════
   GALLERY LIGHTBOX — vanilla JS
═══════════════════════════════════════════════════════════ */
(function () {
  const modal    = document.getElementById('galleryModal');
  const modalImg = document.getElementById('modalImg');
  const counter  = document.getElementById('modalCounter');

  let currentIndex = 0;
  let sources = [];        // [{src, alt}, …]
  let touchStartX = 0;

  /* ── Build sources list from rendered gallery items ── */
  function buildSources() {
    sources = [];
    document.querySelectorAll('.gallery-item').forEach(item => {
      const img = item.querySelector('img');
      if (img) {
        sources.push({ src: img.src, alt: img.alt });
      } else {
        sources.push(null);  // placeholder slot
      }
    });
  }

  /* ── Open ── */
  window.openModal = function (el) {
    buildSources();
    const idx = parseInt(el.dataset.index ?? 0, 10);
    showImage(idx);
    modal.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  };

  /* ── Close ── */
  window.closeModal = function () {
    modal.classList.remove('is-open');
    document.body.style.overflow = '';
    // Reset img so entrance animation replays next open
    setTimeout(() => { modalImg.src = ''; }, 300);
  };

  /* ── Backdrop click to close (not nav / content) ── */
  window.handleBackdropClick = function (e) {
    if (e.target === modal) closeModal();
  };

  /* ── Navigate ── */
  window.navigateModal = function (dir) {
    let next = currentIndex + dir;
    if (next < 0) next = sources.length - 1;
    if (next >= sources.length) next = 0;
    showImage(next);
  };

  function showImage(idx) {
    // Skip placeholder slots
    let attempts = 0;
    while (attempts < sources.length) {
      if (sources[idx] !== null) break;
      idx = (idx + 1) % sources.length;
      attempts++;
    }
    if (!sources[idx]) return;

    currentIndex = idx;

    /* Brief fade-out then swap src */
    modalImg.style.opacity = '0';
    modalImg.style.transform = 'scale(0.94)';

    setTimeout(() => {
      modalImg.src = sources[idx].src;
      modalImg.alt = sources[idx].alt;
      modalImg.onload = () => {
        modalImg.style.opacity = '1';
        modalImg.style.transform = 'scale(1)';
      };
    }, 150);

    counter.textContent = (idx + 1) + ' / ' + sources.filter(Boolean).length;
  }

  /* ── Keyboard ── */
  document.addEventListener('keydown', e => {
    if (!modal.classList.contains('is-open')) return;
    if (e.key === 'ArrowRight') navigateModal(1);
    if (e.key === 'ArrowLeft')  navigateModal(-1);
    if (e.key === 'Escape')     closeModal();
  });

  /* ── Touch swipe ── */
  modal.addEventListener('touchstart', e => {
    touchStartX = e.touches[0].clientX;
  }, { passive: true });

  modal.addEventListener('touchend', e => {
    const dx = e.changedTouches[0].clientX - touchStartX;
    if (Math.abs(dx) > 50) navigateModal(dx < 0 ? 1 : -1);
  }, { passive: true });
})();
</script>

</body>
</html>

<?php
// ── SVG Icon Helper (inline, no external lib) ──────────────
function get_curriculum_icon(string $name): string {
  $icons = [
    'walk' => '<svg viewBox="0 0 24 24"><path d="M13 4a1 1 0 1 0 2 0 1 1 0 0 0-2 0"/><path d="M7.5 17.5L9 14l3 2 2-5.5"/><path d="M17 8.5l-3.5.5-1 3 3 2-1 5"/></svg>',
    'camera' => '<svg viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>',
    'face' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>',
    'body' => '<svg viewBox="0 0 24 24"><path d="M18 3a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3"/><path d="M13 8.5c-2 0-4.5 1.5-5 4l-1 4.5 3 .5.5-3.5"/><path d="M16.5 8.5l1.5 4-3.5 2-.5 5h-2l.5-5-2.5-1.5"/></svg>',
    'style' => '<svg viewBox="0 0 24 24"><path d="M20.38 8.57l-1.23 1.85a8 8 0 0 1-.22 7.58H5.07A8 8 0 0 1 15.58 6.85l1.85-1.23A10 10 0 0 0 3.35 19a2 2 0 0 0 1.72 1h13.85a2 2 0 0 0 1.74-1 10 10 0 0 0-.27-10.44z"/><path d="M10.59 15.41a2 2 0 0 0 2.83 0l5.66-8.49-8.49 5.66a2 2 0 0 0 0 2.83z"/></svg>',
    'network' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="5" cy="19" r="2"/><circle cx="19" cy="19" r="2"/><path d="M12 7v4M5 17l5-4M19 17l-5-4"/></svg>',
  ];
  return $icons[$name] ?? $icons['camera'];
}
?>