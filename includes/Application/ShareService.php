<?php
/**
 * Sharing outside MYAVANA.
 *
 * Every shared thing gets its own public page (?mhj_share=…) carrying Open
 * Graph and Twitter Card tags, so Facebook, WhatsApp, iMessage, X, Slack and
 * LinkedIn show the photo, title and caption as a proper preview card.
 *
 * - Community posts: public posts only, by id.
 * - Journey entries: private by default, so a link exists only once its
 *   owner shares it (a random token stored on the entry).
 * - Monthly recaps: a link signed for that member and month.
 *
 * @package Myavana\Next\Application
 */

namespace Myavana\Next\Application;

use Myavana\Next\Core\MemberName;
use Myavana\Next\Domain\Journal\JournalRepository;

if (!defined('ABSPATH')) {
    exit;
}

class ShareService {
    public const PARAM = 'mhj_share';
    private const TOKEN_META = 'myavana_share_token';
    private const CAPTION_META = 'myavana_share_caption';

    public static function init(): void {
        add_action('template_redirect', [__CLASS__, 'maybeRender'], 0);
    }

    // ------------------------------------------------------------ Links

    public static function url(string $key): string {
        return add_query_arg(self::PARAM, rawurlencode($key), home_url('/'));
    }

    /** @return string|\WP_Error */
    public function postLink(int $postId) {
        $post = $this->communityPost($postId);
        if (!$post) {
            return new \WP_Error('not_found', __('That post can\'t be shared.', 'myavana-hair-journey-next'));
        }
        return self::url('p' . $postId);
    }

    /** @return string|\WP_Error */
    public function entryLink(int $userId, int $entryId, ?string $caption = null) {
        $entry = (new JournalRepository())->getById($entryId, $userId);
        if (!$entry) {
            return new \WP_Error('not_found', __('Entry not found.', 'myavana-hair-journey-next'));
        }
        $token = (string) get_post_meta($entryId, self::TOKEN_META, true);
        if (!preg_match('/^[a-z0-9]{16}$/', $token)) {
            $token = strtolower(wp_generate_password(16, false, false));
            update_post_meta($entryId, self::TOKEN_META, $token);
        }
        if ($caption !== null) {
            update_post_meta($entryId, self::CAPTION_META, sanitize_textarea_field(mb_substr($caption, 0, 600)));
        }
        return self::url('e' . $entryId . '.' . $token);
    }

    /** @return string|\WP_Error */
    public function recapLink(int $userId, string $month) {
        if (!(new RecapService())->forMonth($userId, $month)) {
            return new \WP_Error('empty_month', __('There is nothing logged for that month yet.', 'myavana-hair-journey-next'));
        }
        return self::url('r' . $userId . '.' . $month . '.' . self::sign("recap|{$userId}|{$month}"));
    }

    private static function sign(string $data): string {
        return substr(hash_hmac('sha256', $data, wp_salt('auth')), 0, 20);
    }

    // ------------------------------------------------------------ Resolve

    /**
     * @return array|null {title, text, image, video, author, date, cta, ctaUrl, kind}
     */
    public function resolve(string $key): ?array {
        if (preg_match('/^p(\d+)$/', $key, $m)) {
            $post = $this->communityPost((int) $m[1]);
            if (!$post) {
                return null;
            }
            $name = MemberName::displayFor((int) $post['user_id']) ?: __('A MYAVANA member', 'myavana-hair-journey-next');
            return [
                'kind' => __('Community', 'myavana-hair-journey-next'),
                'title' => wp_strip_all_tags((string) $post['title']) ?: sprintf(__('%s\'s hair journey', 'myavana-hair-journey-next'), $name),
                'text' => wp_strip_all_tags((string) $post['content']),
                'image' => $this->large((string) $post['image_url']),
                'video' => (string) ($post['video_url'] ?? ''),
                'author' => $name,
                'date' => (string) $post['created_at'],
                'cta' => __('See it in the Community', 'myavana-hair-journey-next'),
                'ctaUrl' => home_url('/#community'),
            ];
        }

        if (preg_match('/^e(\d+)\.([a-z0-9]{16})$/', $key, $m)) {
            $entryId = (int) $m[1];
            $stored = (string) get_post_meta($entryId, self::TOKEN_META, true);
            if ($stored === '' || !hash_equals($stored, $m[2])) {
                return null;
            }
            $post = get_post($entryId);
            if (!$post || $post->post_status !== 'publish') {
                return null;
            }
            $entry = (new JournalRepository())->getById($entryId, (int) $post->post_author);
            if (!$entry) {
                return null;
            }
            $caption = (string) get_post_meta($entryId, self::CAPTION_META, true);
            $name = MemberName::displayFor((int) $post->post_author) ?: __('A MYAVANA member', 'myavana-hair-journey-next');
            $video = $entry->videos[0] ?? null;
            return [
                'kind' => __('Hair journey', 'myavana-hair-journey-next'),
                'title' => $entry->title !== '' ? $entry->title : sprintf(__('%s\'s hair journey', 'myavana-hair-journey-next'), $name),
                'text' => $caption !== '' ? $caption : $entry->notes,
                'image' => $this->large($entry->photos[0] ?? ($entry->featuredImage ?: ($video['poster'] ?? ''))),
                'video' => (string) ($video['url'] ?? ''),
                'author' => $name,
                'date' => $entry->date,
                'cta' => __('Start your own hair journey', 'myavana-hair-journey-next'),
                'ctaUrl' => home_url('/'),
            ];
        }

        if (preg_match('/^r(\d+)\.(\d{4}-\d{2})\.([a-f0-9]{20})$/', $key, $m)) {
            $userId = (int) $m[1];
            if (!hash_equals(self::sign("recap|{$userId}|{$m[2]}"), $m[3])) {
                return null;
            }
            $recap = (new RecapService())->forMonth($userId, $m[2]);
            if (!$recap) {
                return null;
            }
            $name = MemberName::forUser($userId, false);
            return [
                'kind' => __('Monthly recap', 'myavana-hair-journey-next'),
                'title' => $name !== ''
                    ? sprintf(__('%1$s\'s %2$s in hair', 'myavana-hair-journey-next'), $name, $recap['label'])
                    : sprintf(__('My %s in hair', 'myavana-hair-journey-next'), $recap['label']),
                'text' => $recap['summary'],
                'image' => $this->large((string) ($recap['last']['url'] ?? ($recap['first']['url'] ?? ''))),
                'before' => $recap['first'] && $recap['last'] ? $this->large((string) $recap['first']['url']) : '',
                'video' => '',
                'author' => $name,
                'date' => $m[2] . '-01',
                'cta' => __('Start your own hair journey', 'myavana-hair-journey-next'),
                'ctaUrl' => home_url('/'),
            ];
        }
        return null;
    }

    private function communityPost(int $postId): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'myavana_community_posts';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $postId), ARRAY_A);
        if (!$row || ($row['privacy_level'] ?? 'public') !== 'public') {
            return null;
        }
        return $row;
    }

    private function large(string $url): string {
        if ($url === '') {
            return '';
        }
        $id = attachment_url_to_postid($url);
        if ($id) {
            $src = wp_get_attachment_image_src($id, 'large');
            if (!empty($src[0])) {
                return (string) $src[0];
            }
        }
        return $url;
    }

    // ------------------------------------------------------------ Page

    public static function maybeRender(): void {
        if (empty($_GET[self::PARAM])) {
            return;
        }
        $key = sanitize_text_field(wp_unslash($_GET[self::PARAM]));
        $share = (new self())->resolve($key);
        if (!$share) {
            wp_safe_redirect(home_url('/'));
            exit;
        }
        status_header(200);
        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        self::page($share, self::url($key));
        exit;
    }

    private static function page(array $s, string $url): void {
        $site = get_bloginfo('name') ?: 'MYAVANA';
        $desc = wp_trim_words($s['text'] !== '' ? $s['text'] : __('A moment from a MYAVANA hair journey.', 'myavana-hair-journey-next'), 40, '…');
        $logo = MYAVANA_NEXT_URL . 'assets/images/myavana-primary-logo.png';
        $image = $s['image'] ?: '';
        $date = $s['date'] ? date_i18n('F j, Y', strtotime($s['date'])) : '';
        ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html($s['title'] . ' · ' . $site); ?></title>
<meta name="description" content="<?php echo esc_attr($desc); ?>">
<link rel="canonical" href="<?php echo esc_url($url); ?>">
<meta property="og:type" content="article">
<meta property="og:site_name" content="<?php echo esc_attr($site); ?>">
<meta property="og:title" content="<?php echo esc_attr($s['title']); ?>">
<meta property="og:description" content="<?php echo esc_attr($desc); ?>">
<meta property="og:url" content="<?php echo esc_url($url); ?>">
<?php if ($image) : ?>
<meta property="og:image" content="<?php echo esc_url($image); ?>">
<meta property="og:image:alt" content="<?php echo esc_attr($s['title']); ?>">
<?php endif; ?>
<meta name="twitter:card" content="<?php echo $image ? 'summary_large_image' : 'summary'; ?>">
<meta name="twitter:title" content="<?php echo esc_attr($s['title']); ?>">
<meta name="twitter:description" content="<?php echo esc_attr($desc); ?>">
<?php if ($image) : ?><meta name="twitter:image" content="<?php echo esc_url($image); ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400;62..125,600;62..125,800&display=swap">
<style>
:root{--onyx:#222323;--coral:#e7a690;--rose:#9b5a49;--cream:#fdf8f5;--blush:#fce5d7;--line:#eadfd8}
*{box-sizing:border-box}
html,body{margin:0;background:var(--cream);color:var(--onyx);font-family:Archivo,"Helvetica Neue",Arial,sans-serif}
body{min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:28px 16px 48px;background:radial-gradient(60% 40% at 10% 0%,var(--blush),transparent 70%),radial-gradient(50% 40% at 95% 10%,rgba(231,166,144,.25),transparent 70%),var(--cream)}
header{width:min(560px,100%);display:flex;justify-content:center;margin-bottom:22px}
header img{height:30px;width:auto}
.card{width:min(560px,100%);background:#fff;border:1px solid var(--line);border-radius:28px;overflow:hidden;box-shadow:0 30px 80px rgba(80,40,30,.14)}
.media{position:relative;background:var(--blush)}
.media img,.media video{display:block;width:100%;max-height:78vh;object-fit:cover}
.pair{display:grid;grid-template-columns:1fr 1fr;gap:3px;background:#fff}
.pair figure{position:relative;margin:0}
.pair img{aspect-ratio:4/5;height:auto}
.pair figcaption{position:absolute;left:10px;bottom:10px;padding:4px 10px;border-radius:999px;background:rgba(34,35,35,.8);color:#fff;font-size:11px;font-weight:700;letter-spacing:.14em;text-transform:uppercase}
.body{padding:24px 26px 28px}
.kicker{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin:0 0 10px;color:#6b6b6b;font-size:13px}
.kicker span{padding:4px 10px;border-radius:999px;background:var(--onyx);color:#fff;font-size:10.5px;font-weight:700;letter-spacing:.14em;text-transform:uppercase}
h1{margin:0 0 10px;font-size:clamp(24px,5vw,32px);font-stretch:125%;font-weight:800;line-height:1.05;text-transform:uppercase}
p.text{margin:0 0 22px;color:#4d4747;font-size:16px;line-height:1.6;white-space:pre-line}
.by{margin:0 0 18px;color:var(--rose);font-weight:600;font-size:14px}
.cta{display:flex;flex-wrap:wrap;gap:10px}
.btn{display:inline-flex;align-items:center;justify-content:center;min-height:50px;padding:0 24px;border-radius:999px;background:var(--onyx);color:#fff;text-decoration:none;font-size:12.5px;font-weight:700;letter-spacing:.14em;text-transform:uppercase}
.btn.alt{background:#fff;color:var(--onyx);border:1px solid var(--line)}
footer{margin-top:22px;color:#8a807d;font-size:12.5px;text-align:center}
footer a{color:var(--rose)}
</style>
</head>
<body>
<header><a href="<?php echo esc_url(home_url('/')); ?>"><img src="<?php echo esc_url($logo); ?>" alt="MYAVANA"></a></header>
<article class="card">
    <?php if (!empty($s['before']) && $image) : ?>
    <div class="media pair">
        <figure><img src="<?php echo esc_url($s['before']); ?>" alt=""><figcaption><?php esc_html_e('Start', 'myavana-hair-journey-next'); ?></figcaption></figure>
        <figure><img src="<?php echo esc_url($image); ?>" alt=""><figcaption><?php esc_html_e('Now', 'myavana-hair-journey-next'); ?></figcaption></figure>
    </div>
    <?php elseif ($s['video']) : ?>
    <div class="media"><video src="<?php echo esc_url($s['video']); ?>" <?php echo $image ? 'poster="' . esc_url($image) . '"' : ''; ?> controls playsinline preload="metadata"></video></div>
    <?php elseif ($image) : ?>
    <div class="media"><img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($s['title']); ?>"></div>
    <?php endif; ?>
    <div class="body">
        <p class="kicker"><span><?php echo esc_html($s['kind']); ?></span><?php echo esc_html($date); ?></p>
        <h1><?php echo esc_html($s['title']); ?></h1>
        <?php if ($s['author']) : ?><p class="by"><?php echo esc_html(sprintf(__('Shared by %s', 'myavana-hair-journey-next'), $s['author'])); ?></p><?php endif; ?>
        <?php if ($s['text'] !== '') : ?><p class="text"><?php echo esc_html(wp_trim_words($s['text'], 90, '…')); ?></p><?php endif; ?>
        <div class="cta">
            <a class="btn" href="<?php echo esc_url($s['ctaUrl']); ?>"><?php echo esc_html($s['cta']); ?></a>
            <a class="btn alt" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('What is MYAVANA?', 'myavana-hair-journey-next'); ?></a>
        </div>
    </div>
</article>
<footer><?php esc_html_e('Track your hair journey with photos, goals and a supportive community.', 'myavana-hair-journey-next'); ?> <a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html((string) wp_parse_url(home_url('/'), PHP_URL_HOST)); ?></a></footer>
</body>
</html>
        <?php
    }
}
