<?php
/**
 * MYAVANA Mobile App — Executive APK Download & Testing Guide
 *
 * Dedicated mobile-first download portal for executive testing (Candace, Winston, QA).
 * Features real-time download telemetry, comprehensive feature walkthrough,
 * installation instructions, and testing accounts.
 *
 * @package Myavana\Next\Templates\Pages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Send strict no-cache headers to prevent Cloudflare/browser stale HTML caching
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("Surrogate-Control: no-store");
header("CDN-Cache-Control: no-store");
header("Cloudflare-CDN-Cache-Control: no-store");

$downloadEndpoint = home_url('/wp-json/myavana/v1/download/apk');
$statsEndpoint    = home_url('/wp-json/myavana/v1/download/stats');
$apkUrl           = home_url('/wp-content/uploads/apk/myavana-mya-preview-build29.apk');
$downloadCount    = (int) get_option('myavana_apk_download_count', 0);

$apkFsPath = ABSPATH . 'wp-content/uploads/apk/myavana-mya-preview-build29.apk';
$apkSizeStr = '292.4 MB';
$buildTimeStr = 'Sept 12, 2026 • 8:23 PM CEST';
if (file_exists($apkFsPath)) {
    $bytes = (int) filesize($apkFsPath);
    if ($bytes > 0) {
        $apkSizeStr = round($bytes / (1024 * 1024), 1) . ' MB';
    }
    $mtime = filemtime($apkFsPath);
    if ($mtime > 0) {
        $buildTimeStr = gmdate('M j, Y • g:i A', $mtime + 7200) . ' CEST';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#09090b">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>MYAVANA Mobile App • Executive Preview & APK Download</title>
    
    <!-- OpenGraph / Social Metadata -->
    <meta property="og:title" content="MYAVANA Mobile App — Native Mya Intelligence Preview">
    <meta property="og:description" content="Download the latest Android APK preview with native Mya streaming, hair journey context awareness, and strand diagnostics.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo esc_url(home_url('/download/')); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✨</text></svg>">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-base: #09090b;
            --bg-surface: #121217;
            --bg-elevated: #1a1a23;
            --bg-card: rgba(26, 26, 35, 0.75);
            --border-subtle: rgba(255, 255, 255, 0.08);
            --border-accent: rgba(201, 122, 99, 0.35);
            --coral-primary: #c97a63;
            --coral-light: #e09681;
            --coral-glow: rgba(201, 122, 99, 0.25);
            --gold-primary: #d4af37;
            --gold-light: #f3e5ab;
            --gold-glow: rgba(212, 175, 55, 0.2);
            --emerald-accent: #10b981;
            --emerald-glow: rgba(16, 185, 129, 0.2);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --font-display: 'Cinzel', serif;
            --font-sans: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background-color: var(--bg-base);
            color: var(--text-primary);
            font-family: var(--font-sans);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0;
            margin: 0;
            overflow-x: hidden;
            background-image: 
                radial-gradient(circle at 50% 0%, rgba(201, 122, 99, 0.15) 0%, transparent 60%),
                radial-gradient(circle at 10% 30%, rgba(212, 175, 55, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 90% 70%, rgba(201, 122, 99, 0.08) 0%, transparent 50%);
            background-attachment: fixed;
        }

        .container {
            width: 100%;
            max-width: 680px;
            padding: 24px 20px 80px;
            margin: 0 auto;
        }

        /* Top Header */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-subtle);
            margin-bottom: 28px;
        }

        .brand-block {
            display: flex;
            flex-direction: column;
        }

        .brand-name {
            font-family: var(--font-display);
            font-size: 1.35rem;
            letter-spacing: 0.28em;
            color: var(--text-primary);
            font-weight: 700;
            text-transform: uppercase;
        }

        .brand-tag {
            font-size: 0.72rem;
            letter-spacing: 0.18em;
            color: var(--coral-light);
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 2px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 9999px;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--emerald-accent);
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .status-dot {
            width: 7px;
            height: 7px;
            background-color: var(--emerald-accent);
            border-radius: 50%;
            box-shadow: 0 0 8px var(--emerald-accent);
            animation: pulse-dot 2s infinite ease-in-out;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
        }

        /* Hero Card */
        .hero-card {
            background: linear-gradient(180deg, rgba(26, 26, 35, 0.95) 0%, rgba(18, 18, 23, 0.9) 100%);
            border: 1px solid var(--border-accent);
            border-radius: 20px;
            padding: 32px 24px;
            text-align: center;
            box-shadow: 0 16px 40px -10px rgba(0, 0, 0, 0.6), 0 0 25px var(--coral-glow);
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
        }

        .hero-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--coral-primary), var(--gold-primary), var(--coral-primary));
        }

        .badge-pill {
            display: inline-block;
            padding: 4px 14px;
            background: rgba(201, 122, 99, 0.15);
            border: 1px solid var(--coral-primary);
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--coral-light);
            margin-bottom: 16px;
        }

        .hero-title {
            font-family: var(--font-display);
            font-size: 1.85rem;
            line-height: 1.25;
            color: #ffffff;
            font-weight: 700;
            margin-bottom: 12px;
            letter-spacing: 0.02em;
        }

        .hero-subtitle {
            font-size: 0.95rem;
            color: var(--text-secondary);
            max-width: 520px;
            margin: 0 auto 26px;
            line-height: 1.55;
        }

        /* Primary CTA Button */
        .btn-download-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            max-width: 440px;
            background: linear-gradient(135deg, #d98870 0%, #c97a63 50%, #b26852 100%);
            color: #ffffff;
            font-family: var(--font-sans);
            font-size: 1.05rem;
            font-weight: 700;
            padding: 18px 28px;
            border-radius: 14px;
            text-decoration: none;
            box-shadow: 0 8px 24px rgba(201, 122, 99, 0.4), 0 2px 6px rgba(0, 0, 0, 0.4);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            cursor: pointer;
        }

        .btn-download-primary:hover, .btn-download-primary:active {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(201, 122, 99, 0.6), 0 4px 10px rgba(0, 0, 0, 0.5);
            background: linear-gradient(135deg, #e09681 0%, #d4836d 50%, #be725c 100%);
        }

        .btn-icon {
            width: 24px;
            height: 24px;
            fill: currentColor;
            flex-shrink: 0;
        }

        .download-meta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            margin-top: 14px;
            flex-wrap: wrap;
            font-size: 0.78rem;
            color: var(--text-secondary);
        }

        .download-meta-item {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .download-meta-item strong {
            color: var(--text-primary);
        }

        .download-counter {
            margin-top: 18px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border-subtle);
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 0.78rem;
            color: var(--gold-light);
            font-weight: 600;
        }

        .counter-icon {
            color: var(--gold-primary);
        }

        /* Release Specs Strip */
        .specs-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 28px;
        }

        @media (min-width: 520px) {
            .specs-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        .spec-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            padding: 12px 14px;
            text-align: center;
        }

        .spec-label {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 4px;
        }

        .spec-value {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        /* Section Headings */
        .section-header {
            margin-bottom: 18px;
        }

        .section-tag {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: var(--coral-light);
            font-weight: 700;
            margin-bottom: 4px;
            display: block;
        }

        .section-title {
            font-family: var(--font-display);
            font-size: 1.35rem;
            color: #ffffff;
            font-weight: 600;
        }

        /* Letter to Candace / Exec Note */
        .note-card {
            background: linear-gradient(180deg, rgba(201, 122, 99, 0.06) 0%, rgba(26, 26, 35, 0.5) 100%);
            border-left: 3px solid var(--coral-primary);
            border-top: 1px solid var(--border-subtle);
            border-right: 1px solid var(--border-subtle);
            border-bottom: 1px solid var(--border-subtle);
            border-radius: 0 14px 14px 0;
            padding: 20px;
            margin-bottom: 28px;
        }

        .note-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .note-avatar {
            width: 32px;
            height: 32px;
            background: var(--coral-primary);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .note-author {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .note-role {
            font-size: 0.72rem;
            color: var(--text-muted);
        }

        .note-body {
            font-size: 0.9rem;
            color: #cbd5e1;
            line-height: 1.6;
        }

        .note-body p {
            margin-bottom: 8px;
        }

        .note-body p:last-child {
            margin-bottom: 0;
        }

        /* Features List */
        .features-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-bottom: 32px;
        }

        .feature-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            gap: 16px;
            align-items: flex-start;
            transition: border-color 0.2s ease, transform 0.2s ease;
        }

        .feature-card:hover {
            border-color: var(--border-accent);
            transform: translateY(-1px);
        }

        .feature-icon-wrapper {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(201, 122, 99, 0.12);
            border: 1px solid rgba(201, 122, 99, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        .feature-content {
            flex: 1;
        }

        .feature-title {
            font-size: 1rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .feature-badge {
            font-size: 0.65rem;
            padding: 2px 7px;
            border-radius: 9999px;
            background: rgba(212, 175, 55, 0.15);
            color: var(--gold-light);
            border: 1px solid rgba(212, 175, 55, 0.3);
            text-transform: uppercase;
            font-weight: 700;
        }

        .feature-desc {
            font-size: 0.86rem;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        /* Step-by-step Installation Guide */
        .install-guide {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: 16px;
            padding: 24px 20px;
            margin-bottom: 32px;
        }

        .step-item {
            display: flex;
            gap: 16px;
            margin-bottom: 20px;
            position: relative;
        }

        .step-item:last-child {
            margin-bottom: 0;
        }

        .step-item:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 17px;
            top: 38px;
            bottom: -12px;
            width: 2px;
            background: var(--border-subtle);
        }

        .step-number {
            width: 36px;
            height: 36px;
            background: var(--bg-elevated);
            border: 2px solid var(--coral-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.88rem;
            font-weight: 800;
            color: var(--coral-light);
            flex-shrink: 0;
            z-index: 2;
        }

        .step-content {
            flex: 1;
            padding-top: 4px;
        }

        .step-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .step-desc {
            font-size: 0.84rem;
            color: var(--text-secondary);
            line-height: 1.45;
        }

        .step-callout {
            margin-top: 8px;
            background: rgba(212, 175, 55, 0.08);
            border: 1px solid rgba(212, 175, 55, 0.2);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.78rem;
            color: var(--gold-light);
        }

        /* Test Accounts Card */
        .credentials-card {
            background: var(--bg-surface);
            border: 1px dashed var(--border-accent);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 32px;
        }

        .cred-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-subtle);
            font-size: 0.85rem;
        }

        .cred-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .cred-label {
            color: var(--text-muted);
            font-weight: 600;
        }

        .cred-value {
            font-family: monospace;
            color: var(--coral-light);
            background: rgba(255, 255, 255, 0.04);
            padding: 3px 8px;
            border-radius: 6px;
            user-select: all;
            font-size: 0.88rem;
        }

        /* iOS Notice */
        .ios-banner {
            background: rgba(148, 163, 184, 0.08);
            border: 1px solid rgba(148, 163, 184, 0.15);
            border-radius: 14px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 32px;
        }

        .ios-icon {
            font-size: 1.8rem;
        }

        .ios-text {
            font-size: 0.84rem;
            color: var(--text-secondary);
        }

        .ios-text strong {
            color: var(--text-primary);
        }

        /* Footer */
        .footer {
            text-align: center;
            padding-top: 24px;
            border-top: 1px solid var(--border-subtle);
            color: var(--text-muted);
            font-size: 0.78rem;
        }

        .footer a {
            color: var(--coral-light);
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        .direct-link-row {
            margin-top: 16px;
            font-size: 0.8rem;
        }

        .direct-link-row a {
            color: var(--text-muted);
            text-decoration: underline;
        }

        .direct-link-row a:hover {
            color: var(--coral-light);
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Top Header -->
    <header class="header">
        <div class="brand-block">
            <span class="brand-name">MYAVANA</span>
            <span class="brand-tag">HAIR JOURNEY • MOBILE PREVIEW</span>
        </div>
        <div class="status-badge">
            <span class="status-dot"></span>
            <span>BUILD ACTIVE</span>
        </div>
    </header>

    <!-- Main Hero Card -->
    <div class="hero-card">
        <span class="badge-pill">Internal Executive Preview</span>
        <h1 class="hero-title">Native Mya Intelligence</h1>
        <p class="hero-subtitle">
            Next-generation hair science experience on Android with streaming AI, real-time regimen personalization, and strand taxonomy.
        </p>

        <!-- Primary Download Button -->
        <a href="<?php echo esc_url($apkUrl); ?>" class="btn-download-primary" id="btn-download-apk" download>
            <svg class="btn-icon" viewBox="0 0 24 24">
                <path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM17 13l-5 5-5-5h3V9h4v4h3z"/>
            </svg>
            <span>Download APK (<?php echo esc_html($apkSizeStr); ?>)</span>
        </a>

        <!-- Specs Subtitle -->
        <div class="download-meta">
            <span class="download-meta-item">Version: <strong>v10.9 (Build 57)</strong></span>
            <span>•</span>
            <span class="download-meta-item">Build: <strong>#29</strong></span>
            <span>•</span>
            <span class="download-meta-item">Size: <strong><?php echo esc_html($apkSizeStr); ?></strong></span>
            <span>•</span>
            <span class="download-meta-item">Built: <strong><?php echo esc_html($buildTimeStr); ?></strong></span>
        </div>

        <!-- Live Download Counter -->
        <div class="download-counter">
            <span class="counter-icon">⚡</span>
            <span id="counter-text">Downloaded <strong id="download-count"><?php echo esc_html($downloadCount); ?></strong> times</span>
        </div>

        <div class="direct-link-row">
            <a href="<?php echo esc_url($apkUrl); ?>" download>Direct APK file mirror</a>
        </div>
    </div>

    <!-- Quick Hardware Specs Grid -->
    <div class="specs-grid">
        <div class="spec-card">
            <div class="spec-label">Package</div>
            <div class="spec-value">com.myavana.app</div>
        </div>
        <div class="spec-card">
            <div class="spec-label">Size</div>
            <div class="spec-value"><?php echo esc_html($apkSizeStr); ?></div>
        </div>
        <div class="spec-card">
            <div class="spec-label">Architecture</div>
            <div class="spec-value">Universal</div>
        </div>
        <div class="spec-card">
            <div class="spec-label">Build Time</div>
            <div class="spec-value"><?php echo esc_html($buildTimeStr); ?></div>
        </div>
    </div>

    <!-- Note for Candace & Executive Team -->
    <div class="note-card">
        <div class="note-header">
            <div class="note-avatar">WZ</div>
            <div>
                <div class="note-author">Winston Zulu • Lead Engineer</div>
                <div class="note-role">MYAVANA Product & Engineering</div>
            </div>
        </div>
        <div class="note-body">
            <p><strong>Hi Candace,</strong></p>
            <p>
                We have replaced the legacy Kommunicate third-party webview with our own bespoke, high-performance native React Native Mya interface and Cloud Run streaming engine.
            </p>
            <p>
                This preview build is completely connected to the live Hair Journey backend. When you chat with Mya, she greets you by name, recalls your hair profile and porosity, reviews your current active goals, and formats hair regimens cleanly without walls of text.
            </p>
        </div>
    </div>

    <!-- Feature Breakdown -->
    <div class="section-header">
        <span class="section-tag">Feature Highlights</span>
        <h2 class="section-title">What You Can Test Today</h2>
    </div>

    <div class="features-list">
        <!-- Feature 1 -->
        <div class="feature-card">
            <div class="feature-icon-wrapper">⚡</div>
            <div class="feature-content">
                <div class="feature-title">
                    <span>Instant Streaming Responses</span>
                    <span class="feature-badge">Zero Latency</span>
                </div>
                <div class="feature-desc">
                    Word-by-word real-time streaming via the Cloud Run NDJSON protocol. No 15-second loading spinners or frozen chat bubbles.
                </div>
            </div>
        </div>

        <!-- Feature 2 -->
        <div class="feature-card">
            <div class="feature-icon-wrapper">✨</div>
            <div class="feature-content">
                <div class="feature-title">
                    <span>Rich Formatted Prose</span>
                    <span class="feature-badge">Editorial UX</span>
                </div>
                <div class="feature-desc">
                    Goodbye wall-of-text: Mya structures responses with bold ingredient calls, numbered routine steps, and clean scientific strand paragraphs.
                </div>
            </div>
        </div>

        <!-- Feature 3 -->
        <div class="feature-card">
            <div class="feature-icon-wrapper">🧬</div>
            <div class="feature-content">
                <div class="feature-title">
                    <span>Hair Profile & Regimen Context</span>
                    <span class="feature-badge">Personalized</span>
                </div>
                <div class="feature-desc">
                    Mya knows your hair type, porosity, density, and active daily hair routines directly from the Hair Journey backend without needing re-prompting.
                </div>
            </div>
        </div>

        <!-- Feature 4 -->
        <div class="feature-card">
            <div class="feature-icon-wrapper">🎯</div>
            <div class="feature-content">
                <div class="feature-title">
                    <span>Screen-Aware Dynamic Prompts</span>
                    <span class="feature-badge">Smart Chips</span>
                </div>
                <div class="feature-desc">
                    Context chips change dynamically depending on where you tapped Mya — whether from the Routine Hub, Hair Goals, or the Journey Diary.
                </div>
            </div>
        </div>

        <!-- Feature 5 -->
        <div class="feature-card">
            <div class="feature-icon-wrapper">📸</div>
            <div class="feature-content">
                <div class="feature-title">
                    <span>Camera & Photo Strand Diagnostics</span>
                    <span class="feature-badge">Multi-Modal</span>
                </div>
                <div class="feature-desc">
                    Direct camera capture and gallery attachment to upload strand photos directly into Mya conversations for texture & health consultations.
                </div>
            </div>
        </div>

        <!-- Feature 6 -->
        <div class="feature-card">
            <div class="feature-icon-wrapper">🔬</div>
            <div class="feature-content">
                <div class="feature-title">
                    <span>35-Strand Scientific Catalog</span>
                    <span class="feature-badge">Science</span>
                </div>
                <div class="feature-desc">
                    Full integration with MYAVANA's scientific strand classification taxonomy and ingredient safety databases.
                </div>
            </div>
        </div>
    </div>

    <!-- Step-by-Step Installation Guide -->
    <div class="section-header">
        <span class="section-tag">Installation Guide</span>
        <h2 class="section-title">How to Install on Android</h2>
    </div>

    <div class="install-guide">
        <div class="step-item">
            <div class="step-number">1</div>
            <div class="step-content">
                <div class="step-title">Tap the Download APK Button</div>
                <div class="step-desc">Chrome will begin downloading the <?php echo esc_html($apkSizeStr); ?> preview build to your phone.</div>
            </div>
        </div>

        <div class="step-item">
            <div class="step-number">2</div>
            <div class="step-content">
                <div class="step-title">Tap "Download Anyway" if prompted</div>
                <div class="step-desc">Because this is an internal preview build not yet published to Google Play, Android displays a standard security notice. Tap <strong>Download anyway</strong>.</div>
                <div class="step-callout">🔒 This APK is compiled and signed directly by our internal development team.</div>
            </div>
        </div>

        <div class="step-item">
            <div class="step-number">3</div>
            <div class="step-content">
                <div class="step-title">Open the Downloaded APK</div>
                <div class="step-desc">Swipe down from your notification tray and tap <code>app-debug.apk</code> or locate it in your <strong>Files &gt; Downloads</strong> folder.</div>
            </div>
        </div>

        <div class="step-item">
            <div class="step-number">4</div>
            <div class="step-content">
                <div class="step-title">Allow Unknown Apps (if asked)</div>
                <div class="step-desc">If prompted "For your security, your phone is not allowed to install unknown apps", tap <strong>Settings</strong> and toggle on <strong>"Allow from this source"</strong>, then press Back.</div>
            </div>
        </div>

        <div class="step-item">
            <div class="step-number">5</div>
            <div class="step-content">
                <div class="step-title">Tap Install & Launch</div>
                <div class="step-desc">Tap <strong>Install</strong>. Once completed, tap <strong>Open</strong> to launch MYAVANA and begin testing!</div>
            </div>
        </div>
    </div>

    <!-- Testing Accounts -->
    <div class="section-header">
        <span class="section-tag">Account Access</span>
        <h2 class="section-title">Test Credentials</h2>
    </div>

    <div class="credentials-card">
        <div class="cred-row">
            <span class="cred-label">Pre-seeded Test User:</span>
            <span class="cred-value">creativesites263@gmail.com</span>
        </div>
        <div class="cred-row">
            <span class="cred-label">Backend Environment:</span>
            <span class="cred-value">myhairjourney.ai (Production)</span>
        </div>
        <div class="cred-row">
            <span class="cred-label">Or Create New:</span>
            <span class="cred-value">Tap "Sign Up" inside app</span>
        </div>
    </div>

    <!-- iOS TestFlight Notice -->
    <div class="ios-banner">
        <div class="ios-icon">🍎</div>
        <div class="ios-text">
            <strong>iOS User?</strong> An Apple TestFlight build is being assembled concurrently. TestFlight invitations will be sent directly to Candace and team via email.
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; <?php echo esc_html(date('Y')); ?> MYAVANA. All rights reserved. • Internal Preview Release</p>
        <p style="margin-top: 6px;">Questions or feedback? Reach out directly to <a href="mailto:winston@creativesites.io">winston@creativesites.io</a> or on Slack.</p>
    </footer>
</div>

<!-- Real-time Download Telemetry Script -->
<script>
(function() {
    var statsUrl = <?php echo json_encode($statsEndpoint); ?>;
    var trackUrl = <?php echo json_encode(home_url('/wp-json/myavana/v1/download/track')); ?>;
    var countEl = document.getElementById('download-count');
    var downloadBtn = document.getElementById('btn-download-apk');

    // Fetch latest live count on load
    function refreshStats() {
        if (!statsUrl || !countEl) return;
        var queryUrl = statsUrl + (statsUrl.indexOf('?') === -1 ? '?' : '&') + '_t=' + Date.now();
        fetch(queryUrl, { cache: 'no-store' })
            .then(function(res) { return res.json(); })
            .then(function(json) {
                if (json && json.success && json.data && typeof json.data.count === 'number') {
                    countEl.textContent = json.data.count;
                }
            })
            .catch(function(err) {
                // Silently keep rendered count
            });
    }

    refreshStats();

    // Optimistically bump count on button click
    if (downloadBtn) {
        downloadBtn.addEventListener('click', function() {
            var current = parseInt(countEl.textContent, 10) || 0;
            countEl.textContent = current + 1;

            // Send tracking beacon
            try {
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(trackUrl, JSON.stringify({ source: 'web_button' }));
                } else {
                    fetch(trackUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ source: 'web_button' }),
                        keepalive: true
                    });
                }
            } catch (e) {}

            setTimeout(refreshStats, 2000);
        });
    }
})();
</script>

</body>
</html>
