<?php
/**
 * Shared legal page shortcodes.
 *
 * Ported from the legacy myavana-hair-journey-updated plugin during the
 * migration to myavana-hair-journey-next — the shortcodes registered here
 * ([myavana_privacy_policy], [myavana_terms]) are what the live /privacy
 * and /terms pages already reference; that plugin is no longer active, so
 * this content needs to live here instead.
 *
 * @package Myavana\Next\Templates\Pages
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('myavana_render_legal_page')) {
    function myavana_render_legal_page($config = []) {
        $asset_version = defined('WP_DEBUG') && WP_DEBUG ? (string) time() : MYAVANA_NEXT_VERSION;
        wp_enqueue_style(
            'myavana-legal-pages',
            MYAVANA_NEXT_URL . 'assets/css/legal-pages.css',
            [],
            $asset_version
        );

        $config = wp_parse_args($config, [
            'eyebrow' => 'MYAVANA Legal',
            'title' => 'Legal Notice',
            'summary' => '',
            'updated' => 'March 13, 2026',
            'sections' => [],
        ]);

        $site_name = get_bloginfo('name');
        if ($site_name === '') {
            $site_name = 'MYAVANA';
        }

        $support_email = apply_filters('myavana_legal_contact_email', 'support@myavana.com');
        $home_url = home_url('/');

        ob_start();
        ?>
        <div class="myavana-legal-page">
            <div class="myavana-legal-shell">
                <article class="myavana-legal-card">
                    <header class="myavana-legal-hero">
                        <div class="myavana-legal-eyebrow"><?php echo esc_html($config['eyebrow']); ?></div>
                        <h1 class="myavana-legal-title"><?php echo esc_html($config['title']); ?></h1>
                        <p class="myavana-legal-summary"><?php echo wp_kses_post(sprintf($config['summary'], esc_html($site_name))); ?></p>
                        <p class="myavana-legal-updated">Last updated: <?php echo esc_html($config['updated']); ?></p>
                    </header>

                    <div class="myavana-legal-content">
                        <?php foreach ($config['sections'] as $section) : ?>
                            <section class="myavana-legal-section">
                                <h2><?php echo esc_html($section['heading']); ?></h2>
                                <?php if (!empty($section['paragraphs']) && is_array($section['paragraphs'])) : ?>
                                    <?php foreach ($section['paragraphs'] as $paragraph) : ?>
                                        <p><?php echo wp_kses_post(sprintf($paragraph, esc_html($site_name), esc_html($support_email))); ?></p>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <?php if (!empty($section['items']) && is_array($section['items'])) : ?>
                                    <ul>
                                        <?php foreach ($section['items'] as $item) : ?>
                                            <li><?php echo wp_kses_post(sprintf($item, esc_html($site_name), esc_html($support_email))); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </section>
                        <?php endforeach; ?>

                        <div class="myavana-legal-contact">
                            <h3>Questions</h3>
                            <p>
                                Contact us at <a href="mailto:<?php echo antispambot(esc_attr($support_email)); ?>"><?php echo esc_html($support_email); ?></a>
                                if you need help with these terms, privacy requests, or account questions.
                            </p>
                        </div>

                        <footer class="myavana-legal-footer">
                            <a class="myavana-legal-back" href="<?php echo esc_url($home_url); ?>">Back to site</a>
                            <span><?php echo esc_html($site_name); ?> Hair Journey</span>
                        </footer>
                    </div>
                </article>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }
}

if (!function_exists('myavana_privacy_policy_shortcode')) {
    function myavana_privacy_policy_shortcode($atts = []) {
        unset($atts);

        return myavana_render_legal_page([
            'eyebrow' => 'MYAVANA Privacy',
            'title' => 'Privacy Policy',
            'summary' => '%1$s Hair Journey collects and uses account, profile, photo, routine, and community data to run the service, personalize recommendations, and keep the platform secure.',
            'updated' => 'March 13, 2026',
            'sections' => [
                [
                    'heading' => 'What We Collect',
                    'paragraphs' => [
                        'We collect the information you choose to provide when you create an account, complete your hair profile, upload photos, save routines or goals, request AI analysis, contact support, or participate in community features.',
                    ],
                    'items' => [
                        'Account information such as your name, email address, login credentials, and profile preferences.',
                        'Hair journey information such as hair type, goals, routine details, check-ins, journal notes, progress photos, and analysis history.',
                        'Community content such as posts, comments, likes, follows, and privacy settings you choose for shared content.',
                        'Technical and usage information such as IP address, browser and device details, referring pages, and feature activity logs.',
                    ],
                ],
                [
                    'heading' => 'How We Use Information',
                    'items' => [
                        'To create and maintain your account and deliver the features you request.',
                        'To personalize hair analysis, recommendations, reminders, and progress tracking.',
                        'To moderate community activity, prevent abuse, investigate fraud, and protect the service.',
                        'To troubleshoot bugs, measure product performance, and improve the site experience.',
                        'To comply with legal obligations and enforce our agreements when needed.',
                    ],
                ],
                [
                    'heading' => 'When We Share Information',
                    'paragraphs' => [
                        'We share information only when it is needed to operate the service, when you direct us to share it, or when the law requires it.',
                    ],
                    'items' => [
                        'With service providers that support hosting, analytics, authentication, email delivery, or other core operations.',
                        'With other users when you choose to publish content to the community or use a public or follower-visible privacy setting.',
                        'With professional advisers, regulators, law enforcement, or transaction counterparties when reasonably necessary for legal, security, or business transfer purposes.',
                    ],
                ],
                [
                    'heading' => 'Photos, AI Features, and Sensitive Content',
                    'paragraphs' => [
                        'Photos and hair analysis inputs can reveal personal characteristics. Please avoid uploading content you do not want associated with your account. AI-generated results are intended to support your hair journey and may not always be accurate or complete.',
                    ],
                    'items' => [
                        'Private hair journey entries stay private unless you choose to share them.',
                        'Community posts follow the audience setting you select at the time of posting.',
                        'AI outputs should not be treated as medical advice or a substitute for professional care.',
                    ],
                ],
                [
                    'heading' => 'Cookies, Analytics, and Retention',
                    'paragraphs' => [
                        'We use cookies or similar technologies for login sessions, security, and understanding how the site is used. We keep information for as long as needed to operate the service, resolve disputes, maintain backups, and meet legal requirements.',
                    ],
                ],
                [
                    'heading' => 'Your Choices',
                    'items' => [
                        'You can update profile details and adjust community privacy settings from your account tools.',
                        'You can stop providing optional information, but some features may not work without it.',
                        'You can contact us to request account assistance or deletion review at <a href="mailto:%2$s">%2$s</a>.',
                    ],
                ],
                [
                    'heading' => 'Children and Policy Updates',
                    'paragraphs' => [
                        '%1$s Hair Journey is not intended for children under 13. If you believe a child under 13 has provided personal information, contact us so we can review and remove it where appropriate.',
                        'We may update this Privacy Policy as the product, legal requirements, or data practices change. The current version will always appear on this page with its effective date.',
                    ],
                ],
            ],
        ]);
    }
}

if (!function_exists('myavana_terms_shortcode')) {
    function myavana_terms_shortcode($atts = []) {
        unset($atts);

        return myavana_render_legal_page([
            'eyebrow' => 'MYAVANA Terms',
            'title' => 'Terms of Service',
            'summary' => 'These Terms govern your use of %1$s Hair Journey, including your account, uploaded content, community participation, and AI-powered features.',
            'updated' => 'March 13, 2026',
            'sections' => [
                [
                    'heading' => 'Using the Service',
                    'paragraphs' => [
                        'By accessing or using %1$s Hair Journey, you agree to these Terms. If you do not agree, do not use the service.',
                    ],
                    'items' => [
                        'You must be at least 13 years old to create an account or use the member features.',
                        'You must provide accurate information and keep your login credentials secure.',
                        'You are responsible for activity that occurs through your account unless you report unauthorized use promptly.',
                    ],
                ],
                [
                    'heading' => 'Acceptable Use',
                    'items' => [
                        'Use the service only for lawful, personal, and authorized purposes.',
                        'Do not upload malicious code, interfere with site operations, scrape restricted data, or attempt unauthorized access.',
                        'Do not post content that is abusive, defamatory, infringing, deceptive, or violates another person’s privacy or rights.',
                    ],
                ],
                [
                    'heading' => 'Your Content',
                    'paragraphs' => [
                        'You keep ownership of the content you submit, but you grant us a non-exclusive license to host, process, display, and transmit that content as needed to operate and improve the service.',
                    ],
                    'items' => [
                        'You are responsible for the legality, accuracy, and permissions associated with the content you upload.',
                        'If you share to community features, your content may be visible to the audience you choose.',
                        'We may remove or restrict content that violates these Terms or creates risk for users or the service.',
                    ],
                ],
                [
                    'heading' => 'AI and Informational Features',
                    'paragraphs' => [
                        'Hair analysis, recommendations, and other AI-supported outputs are provided for informational purposes only. They may be incomplete, incorrect, or unavailable at times.',
                    ],
                    'items' => [
                        'AI output is not medical advice and should not replace professional diagnosis or treatment.',
                        'You should use judgment before relying on recommendations, especially for health, allergy, or product-safety decisions.',
                    ],
                ],
                [
                    'heading' => 'Third-Party Services',
                    'paragraphs' => [
                        'Some features may depend on third-party tools, platforms, or content. We are not responsible for third-party services, and your use of them may be subject to separate terms or privacy policies.',
                    ],
                ],
                [
                    'heading' => 'Suspension, Disclaimers, and Liability',
                    'items' => [
                        'We may suspend or terminate access if we believe your use violates these Terms, creates security risk, or exposes us or other users to harm.',
                        'The service is provided on an "as is" and "as available" basis to the fullest extent allowed by law.',
                        'To the fullest extent allowed by law, we are not liable for indirect, incidental, special, consequential, or punitive damages arising from your use of the service.',
                    ],
                ],
                [
                    'heading' => 'Changes and Contact',
                    'paragraphs' => [
                        'We may update these Terms from time to time. Continued use of the service after an update becomes effective means you accept the revised Terms.',
                        'For questions about these Terms, contact <a href="mailto:%2$s">%2$s</a>.',
                    ],
                ],
            ],
        ]);
    }
}
