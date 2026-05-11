<?php
// ============================================================
// thank-you.php  — Post-Registration Confirmation + Pixel Fire
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../core/includes/functions.php';

// Guard — only accessible after a successful registration
if (empty($_SESSION['reg_code'])) {
    redirect('/index.php');
}

$reg_code    = htmlspecialchars($_SESSION['reg_code'], ENT_QUOTES, 'UTF-8');
$applicant   = htmlspecialchars($_SESSION['applicant'], ENT_QUOTES, 'UTF-8');
$fb_event_id = htmlspecialchars($_SESSION['fb_event_id'] ?? '', ENT_QUOTES, 'UTF-8'); // ADD THIS
$pixel_id    = get_setting('fb_pixel_id', '');
$tiktok_pixel_id = get_setting('tiktok_pixel_id', '');
$tt_event_id     = htmlspecialchars($_SESSION['tt_event_id'] ?? '', ENT_QUOTES, 'UTF-8');

// Consume session values
unset($_SESSION['reg_code'], $_SESSION['applicant'], $_SESSION['fb_event_id'], $_SESSION['tt_event_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Confirmed – Dhaka Model Agency</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;1,600&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-red: #cc0000; /* Matched to your website's primary red */
            --text-dark: #222222;
            --text-muted: #666666;
            --bg-light: #f9f9fa;
            --card-bg: #ffffff;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Montserrat', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .thank-you-container {
            background-color: var(--card-bg);
            max-width: 600px;
            width: 90%;
            padding: 50px 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            text-align: center;
            border-top: 5px solid var(--primary-red);
        }

        .icon-wrapper {
            width: 80px;
            height: 80px;
            background-color: rgba(204, 0, 0, 0.1);
            color: var(--primary-red);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }

        .icon-wrapper svg {
            width: 40px;
            height: 40px;
        }

        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            color: var(--text-dark);
            margin-top: 0;
            margin-bottom: 15px;
        }

        .greeting {
            font-size: 1.1rem;
            color: var(--text-muted);
            margin-bottom: 30px;
        }

        .reg-code-box {
            background-color: var(--bg-light);
            border: 1px dashed #ccc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .reg-code-box p {
            margin: 0 0 10px 0;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
        }

        .reg-code {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-red);
            letter-spacing: 2px;
            margin: 0;
        }

        .details {
            font-size: 0.95rem;
            line-height: 1.6;
            color: var(--text-muted);
            margin-bottom: 40px;
        }

        .btn-primary {
            display: inline-block;
            background-color: var(--primary-red);
            color: #ffffff;
            text-decoration: none;
            padding: 15px 35px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-radius: 4px;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .btn-primary:hover {
            background-color: #a30000;
            transform: translateY(-2px);
        }

        .footer-links {
            margin-top: 30px;
        }

        .footer-links a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--primary-red);
        }

        @media (max-width: 480px) {
            .thank-you-container {
                padding: 40px 20px;
            }
            h1 {
                font-size: 1.8rem;
            }
            .reg-code {
                font-size: 1.5rem;
            }
        }
    </style>

    <?php if (!empty($pixel_id)) : ?>
    <script>
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
    n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}
    (window,document,'script','https://connect.facebook.net/en_US/fbevents.js');

    fbq('init', '<?= $pixel_id ?>');
    fbq('track', 'PageView');

    // ★ LEAD EVENT — fires on thank-you page only
    fbq('track', 'Lead', {
        content_name:     'DMA Grooming Registration',
        content_category: 'ModelAgency',
        value:            0,
        currency:         'BDT'
    }, {
        eventID: '<?= $fb_event_id ?>'  // ADD THIS LINE
    });
    </script>
    <noscript>
      <img height="1" width="1" style="display:none"
        src="https://www.facebook.com/tr?id=<?= $pixel_id ?>&ev=Lead&noscript=1"/>
    </noscript>
    <?php endif; ?>
    <?php if (!empty($tiktok_pixel_id)) : ?>
    <script>
    !function (w, d, t) {
    w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];
    ttq.methods=["page","track","identify","instances","debug","on","off","once",
                "ready","alias","group","enableCookie","disableCookie",
                "holdConsent","revokeConsent","grantConsent"];  // ← updated
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
    ttq.load('<?= htmlspecialchars($tiktok_pixel_id, ENT_QUOTES) ?>');  // ← added htmlspecialchars
    ttq.page();

    // ★ SUBMITFORM EVENT — fires on thank-you page only
    ttq.track('SubmitForm', {
        value:    0,
        currency: 'BDT'
    }, {
        event_id: '<?= $tt_event_id ?>'
    });
    }(window, document, 'ttq');
    </script>
    <?php endif; ?>

</head>
<body>

<div class="thank-you-container">
    <div class="icon-wrapper">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
    </div>

    <h1>Application Received</h1>
    
    <div class="greeting">
        Thank you, <strong><?= $applicant ?></strong>. Your profile has been submitted successfully.
    </div>

    <div class="reg-code-box">
        <p>Your Registration Code</p>
        <div class="reg-code"><?= $reg_code ?></div>
    </div>

    <div class="details">
        <?= htmlspecialchars(get_setting('registration_note'), ENT_QUOTES, 'UTF-8') ?><br><br>
        <strong>Need assistance?</strong> Contact us at <?= htmlspecialchars(get_setting('contact_phone'), ENT_QUOTES, 'UTF-8') ?>
    </div>

    <a href="https://dhakamodel.agency/" class="btn-primary">Visit Our Website</a>

    <div class="footer-links">
        <a href="/index.php">← Submit another application</a>
    </div>
</div>

</body>
</html>