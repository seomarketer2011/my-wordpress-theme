<?php
/**
 * E3 Locksmith (Kadence Child) – Conversion UI
 *
 * - Conversion hero injected under the header (all pages)
 * - Mobile sticky call bar
 * - Per-page hero content (hardcoded for each service)
 * - Uses Magic Page variables (e.g. [location], [meta_telephone])
 */

/** ---------------------------------------------------------
 * Enqueue styles
 * -------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', 'e3_locksmith_enqueue_styles', 20 );
function e3_locksmith_enqueue_styles() {
	$child_uri  = get_stylesheet_directory_uri();
	$child_path = get_stylesheet_directory();

	// Parent + child
	wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css', array(), null );
	wp_enqueue_style( 'child-style', $child_uri . '/style.css', array( 'parent-style' ), null );

	// Conversion UI CSS
	$ui_css = $child_path . '/assets/css/e3-conversion-ui.css';
	if ( file_exists( $ui_css ) ) {
		wp_enqueue_style(
			'e3-conversion-ui',
			$child_uri . '/assets/css/e3-conversion-ui.css',
			array( 'child-style' ),
			filemtime( $ui_css )
		);
	}
}

/** ---------------------------------------------------------
 * Basic speed hygiene
 * -------------------------------------------------------- */
add_action( 'init', 'e3_speed_hygiene' );
function e3_speed_hygiene() {
	// Disable emoji assets
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

	// Remove WP embed script
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );
}

add_action( 'wp_enqueue_scripts', 'e3_dequeue_dashicons_for_visitors', 100 );
function e3_dequeue_dashicons_for_visitors() {
	if ( ! is_user_logged_in() ) {
		wp_deregister_style( 'dashicons' );
	}
}

/** ---------------------------------------------------------
 * Magic Page Helper Functions
 * -------------------------------------------------------- */
function e3_get_magicpage_term() {
	// Magic Page plugin provides the most reliable context for [meta_*] tokens.
	if ( function_exists( 'get_location_object' ) ) {
		$term = get_location_object();
		if ( is_array( $term ) && isset( $term[0] ) ) {
			$term = $term[0];
		}
		if ( is_object( $term ) && isset( $term->term_id ) ) {
			return $term;
		}
	}

	if ( function_exists( 'get_magicpage_location' ) ) {
		$term = get_magicpage_location();
		if ( is_array( $term ) && isset( $term[0] ) ) {
			$term = $term[0];
		}
		if ( is_object( $term ) && isset( $term->term_id ) ) {
			return $term;
		}
	}

	return null;
}

function e3_apply_magicpage_vars( $html ) {
	// Replace Magic Page tokens like [meta_telephone] in theme-injected markup.
	if ( function_exists( 'apply_variables' ) ) {
		$term = e3_get_magicpage_term();
		return $term ? apply_variables( $html, $term ) : apply_variables( $html );
	}
	return $html;
}

function e3_get_phone_display() {
	$phone = '';

	$term = e3_get_magicpage_term();
	if ( $term && isset( $term->term_id ) ) {
		$phone = (string) get_term_meta( $term->term_id, 'telephone', true );
	}

	if ( '' === trim( $phone ) ) {
		$maybe = e3_apply_magicpage_vars( '[meta_telephone]' );
		if ( is_string( $maybe ) && '[meta_telephone]' !== $maybe ) {
			$phone = $maybe;
		}
	}

	return trim( (string) $phone );
}

function e3_phone_to_tel_href( $phone ) {
	$raw = trim( (string) $phone );
	if ( '' === $raw ) {
		return '';
	}
	$clean = preg_replace( '/[^0-9+]/', '', $raw );
	return $clean ? 'tel:' . $clean : '';
}

/** ---------------------------------------------------------
 * Check if hero should be disabled for a page
 * -------------------------------------------------------- */
function e3_is_hero_enabled( $post_id = 0 ) {
	if ( ! $post_id ) {
		$post_id = get_queried_object_id();
	}

	// Check for _e3_hero_off meta field to disable hero on specific pages
	$disabled = $post_id ? get_post_meta( $post_id, '_e3_hero_off', true ) : '';
	return ( 'yes' !== $disabled && '1' !== $disabled );
}

/** ---------------------------------------------------------
 * Default Hero Content (fallback for pages without specific content)
 * -------------------------------------------------------- */
function e3_get_default_hero_content() {
	return array(
		'top_label'    => 'Call us 24/7',
		'top_bullet_1' => '30-min response',
		'top_bullet_2' => 'No call-out fee',
		'top_bullet_3' => 'DBS checked',
		'title'        => 'Professional Locksmith Services [location]',
		'subtitle'     => 'Fast, reliable locksmith service across [location] — call for immediate help or a free quote',
		'usp_1_title'  => 'Fast response times',
		'usp_1_desc'   => 'Rapid callout service when you need help with locks and security',
		'usp_2_title'  => 'Clear pricing',
		'usp_2_desc'   => 'Transparent quotes with no hidden charges',
		'usp_3_title'  => 'Professional service',
		'usp_3_desc'   => 'Expert locksmiths with full credentials and insurance',
		'usp_4_title'  => 'DBS-checked engineers',
		'usp_4_desc'   => 'Trusted, vetted locksmiths for your security needs',
		'cta_text'     => 'Call for Help',
		'microcopy'    => 'Professional service • Clear pricing • DBS checked',
		'badge_1'      => 'Fast response',
		'badge_2'      => 'Clear pricing',
		'badge_3'      => 'Professional',
		'badge_4'      => 'DBS checked',
		'sticky_cta'   => 'Call Now',
	);
}

/** ---------------------------------------------------------
 * Per-Page Hero Content (add your custom pages here)
 * -------------------------------------------------------- */
function e3_get_page_specific_hero_content( $post_id ) {
	if ( ! $post_id ) {
		return null;
	}

	// Get current page slug
	$post = get_post( $post_id );
	$slug = $post ? $post->post_name : '';

	// Page-specific hero content by slug or ID
	$page_content = array();

	// Commercial Locksmiths Page
	if ( 'commercial-locksmiths' === $slug || 'commercial-locksmith' === $slug ) {
		$page_content = array(
			'top_label'    => 'Call Now',
			'top_bullet_1' => 'Same Day Service',
			'top_bullet_2' => 'No call-out fee',
			'top_bullet_3' => 'Free Quotes',
			'title'        => 'Commercial Locksmith Services [location]',
			'subtitle'     => 'Locked out or need security upgrades? We serve businesses across [location] with rapid emergency response and professional master key systems',
			'usp_1_title'  => 'Rapid business lockout response',
			'usp_1_desc'   => '30-minute emergency callout to get your premises open and staff back to work',
			'usp_2_title'  => 'Zero call-out charges',
			'usp_2_desc'   => 'Speak to us first, get clear pricing, only pay for completed work',
			'usp_3_title'  => 'DBS-checked commercial engineers',
			'usp_3_desc'   => 'Trusted, vetted locksmiths for your business premises and security systems',
			'usp_4_title'  => 'Master key systems & rekeying',
			'usp_4_desc'   => 'Full key control for businesses – manage staff access and eliminate lost key risks',
			'cta_text'     => 'Call Now For Help & Free Quote',
			'microcopy'    => '30-min response • Master key systems • No call-out fees',
			'badge_1'      => 'DBS checked',
			'badge_2'      => 'Business focused',
			'badge_3'      => '30-min response',
			'badge_4'      => 'Key control experts',
			'sticky_cta'   => 'Call 24/7 For Help',
		);
	}

	// Security Grilles Page
	if ( 'security-grilles' === $slug || 'security-grille' === $slug || 'security-grille-lock-repairs' === $slug || 'security-grille-lock-repair' === $slug ) {
		$page_content = array(
			'top_label'    => 'Call Now',
			'top_bullet_1' => 'Same Day Service',
			'top_bullet_2' => 'No call-out fee',
			'top_bullet_3' => 'From £59',
			'title'        => 'Security Grille Lock Repairs [location]',
			'subtitle'     => 'Grille stuck or stiff? We repair jammed security grille locks with same-day service – get your mechanism working smoothly again',
			'usp_1_title'  => 'Same-day grille lock repair',
			'usp_1_desc'   => 'Same-day service available – we fix jammed, stiff, or binding grille mechanisms fast',
			'usp_2_title'  => 'Clear repair pricing',
			'usp_2_desc'   => 'Transparent pricing quoted upfront – parts charged separately only when needed',
			'usp_3_title'  => 'Repair-first specialists',
			'usp_3_desc'   => 'We restore existing lock function rather than replace – professional diagnosis and multi-cycle testing',
			'usp_4_title'  => 'DBS-checked engineers',
			'usp_4_desc'   => 'Trusted locksmiths for commercial security grille repairs with 24/7 availability',
			'cta_text'     => 'Call Now For Help & Free Quote',
			'microcopy'    => 'Same-day service • Repair-first approach • Fixed pricing',
			'badge_1'      => 'Same-day service',
			'badge_2'      => 'Repair-focused',
			'badge_3'      => 'From £59',
			'badge_4'      => 'DBS checked',
			'sticky_cta'   => 'Call 24/7 For Help',
		);
	}

	// Security Upgrades Page
	if ( 'security-upgrades' === $slug || 'security-upgrade' === $slug ) {
		$page_content = array(
			'top_label'    => 'Call Now',
			'top_bullet_1' => 'Same Day Service',
			'top_bullet_2' => 'No call-out fee',
			'top_bullet_3' => 'Security Survey £69',
			'title'        => 'Security Upgrades [location]',
			'subtitle'     => 'Professional security surveys and upgrades – from anti-snap locks to smart cameras, we\'ll secure your property to insurance standards',
			'usp_1_title'  => 'Professional security survey',
			'usp_1_desc'   => 'Comprehensive property assessment identifying vulnerabilities across doors, windows, and access points',
			'usp_2_title'  => 'Clear upgrade pricing',
			'usp_2_desc'   => 'Transparent quotes for all security improvements – from basic lock upgrades to full camera systems',
			'usp_3_title'  => 'Insurance-compliant installations',
			'usp_3_desc'   => 'BS3621 and PAS24 certified locks and security solutions meeting insurer requirements',
			'usp_4_title'  => 'Complete security solutions',
			'usp_4_desc'   => 'Anti-snap locks, smart cameras, door reinforcement, and 24/7 monitoring systems – all professionally installed',
			'cta_text'     => 'Call Now to Book Security Survey',
			'microcopy'    => 'Security surveys • Insurance-compliant • Professional installation',
			'badge_1'      => 'Insurance-approved',
			'badge_2'      => 'BS3621 certified',
			'badge_3'      => 'Smart security',
			'badge_4'      => 'DBS checked',
			'sticky_cta'   => 'Call To Book Security Survey',
		);
	}

	return ! empty( $page_content ) ? $page_content : null;
}

/** ---------------------------------------------------------
 * Remove Kadence default title output (prevents double H1)
 * -------------------------------------------------------- */
add_action( 'wp', 'e3_maybe_remove_kadence_default_titles' );
function e3_maybe_remove_kadence_default_titles() {
	if ( ! e3_is_hero_enabled() ) {
		return;
	}
	// Kadence adds Kadence\kadence_entry_header() to these actions
	remove_action( 'kadence_entry_header', 'Kadence\kadence_entry_header', 10 );
	remove_action( 'kadence_entry_hero', 'Kadence\kadence_entry_header', 10 );
}

/** ---------------------------------------------------------
 * Conversion Hero (under header, above content)
 * -------------------------------------------------------- */
add_action( 'kadence_after_header', 'e3_output_conversion_hero', 5 );
function e3_output_conversion_hero() {
	if ( is_admin() || is_feed() || is_robots() || is_attachment() ) {
		return;
	}

	$post_id = get_queried_object_id();

	if ( ! e3_is_hero_enabled( $post_id ) ) {
		return;
	}

	// Check for page-specific hero content first
	$page_specific_content = e3_get_page_specific_hero_content( $post_id );

	// Use page-specific content or fall back to default
	if ( null !== $page_specific_content ) {
		$content = $page_specific_content;
	} else {
		$content = e3_get_default_hero_content();
	}

	// Get all content fields
	$top_label = $content['top_label'];
	$title     = $content['title'];
	$sub       = $content['subtitle'];
	$cta       = $content['cta_text'];
	$micro     = $content['microcopy'];

	$phone_display = e3_get_phone_display();
	$phone_href    = e3_phone_to_tel_href( $phone_display );

	// Small inline SVG icons (fast, no external requests)
	$icon_clock = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20Zm0-18a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm.75 4.5a.75.75 0 0 0-1.5 0v3.72c0 .2.08.39.22.53l2.25 2.25a.75.75 0 1 0 1.06-1.06l-2.03-2.03V8.5Z"/></svg>';
	$icon_pound = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M14 21H7v-2h1v-5H7v-2h1V9a5 5 0 0 1 5-5h2v2h-2a3 3 0 0 0-3 3v3h4v2h-4v5h4v2Z"/></svg>';
	$icon_shield = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2 4 5v6c0 5 3.4 9.74 8 11 4.6-1.26 8-6 8-11V5l-8-3Zm0 18.2c-3.33-1.08-6-4.85-6-9.2V6.3L12 4l6 2.3V11c0 4.35-2.67 8.12-6 9.2Zm-1-5.7 5.3-5.3-1.4-1.4L11 11.7 9.1 9.8 7.7 11.2 11 14.5Z"/></svg>';
	$icon_pin = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 22s7-4.44 7-11a7 7 0 1 0-14 0c0 6.56 7 11 7 11Zm0-9.5A2.5 2.5 0 1 1 12 7a2.5 2.5 0 0 1 0 5Z"/></svg>';
	$icon_phone = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.07 21 3 13.93 3 5a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.24.2 2.45.57 3.57a1 1 0 0 1-.24 1.02l-2.21 2.2Z"/></svg>';

	$html  = '<section class="e3-hero">';
	$html .= '  <div class="e3-hero__wrap">';
	$html .= '    <div class="e3-topstrip">';
	$html .= '      <span class="e3-topstrip__label">' . esc_html( $top_label ) . '</span> ';
	$html .= '      <a class="e3-topstrip__phone" href="' . esc_attr( $phone_href ? $phone_href : 'tel:' ) . '">' . esc_html( $phone_display ) . '</a>';
	$html .= '      <span class="e3-topstrip__sep">•</span> <span>' . esc_html( $content['top_bullet_1'] ) . '</span>';
	$html .= '      <span class="e3-topstrip__sep">•</span> <span>' . esc_html( $content['top_bullet_2'] ) . '</span>';
	$html .= '      <span class="e3-topstrip__sep">•</span> <span>' . esc_html( $content['top_bullet_3'] ) . '</span>';
	$html .= '    </div>';

	$html .= '    <div class="e3-hero__grid">';
	$html .= '      <div class="e3-hero__main">';
	$html .= '        <h1 class="e3-hero__title">' . esc_html( $title ) . '</h1>';
	$html .= '        <p class="e3-hero__sub">' . esc_html( $sub ) . '</p>';

	$html .= '        <ul class="e3-usps">';
	$html .= '          <li>' . $icon_clock . '<div><strong>' . esc_html( $content['usp_1_title'] ) . '</strong><span>' . esc_html( $content['usp_1_desc'] ) . '</span></div></li>';
	$html .= '          <li>' . $icon_pound . '<div><strong>' . esc_html( $content['usp_2_title'] ) . '</strong><span>' . esc_html( $content['usp_2_desc'] ) . '</span></div></li>';
	$html .= '          <li>' . $icon_shield . '<div><strong>' . esc_html( $content['usp_3_title'] ) . '</strong><span>' . esc_html( $content['usp_3_desc'] ) . '</span></div></li>';
	$html .= '          <li>' . $icon_pin . '<div><strong>' . esc_html( $content['usp_4_title'] ) . '</strong><span>' . esc_html( $content['usp_4_desc'] ) . '</span></div></li>';
	$html .= '        </ul>';

	$html .= '        <div class="e3-cta">';
	$html .= '          <a class="e3-btn e3-btn--call" href="' . esc_attr( $phone_href ? $phone_href : 'tel:' ) . '">' . $icon_phone . esc_html( $cta ) . '</a>';
	$html .= '        </div>';
	$html .= '        <p class="e3-microcopy">' . esc_html( $micro ) . '</p>';

	$html .= '        <div class="e3-trust">';
	$html .= '          <div class="e3-trust__item">' . $icon_shield . esc_html( $content['badge_1'] ) . '</div>';
	$html .= '          <div class="e3-trust__item">' . $icon_pound . esc_html( $content['badge_2'] ) . '</div>';
	$html .= '          <div class="e3-trust__item">' . $icon_clock . esc_html( $content['badge_3'] ) . '</div>';
	$html .= '          <div class="e3-trust__item">' . $icon_pin . esc_html( $content['badge_4'] ) . '</div>';
	$html .= '        </div>';
	$html .= '      </div>';
	$html .= '      <div class="e3-hero__side" aria-hidden="true"></div>';
	$html .= '    </div>';
	$html .= '  </div>';
	$html .= '</section>';

	echo e3_apply_magicpage_vars( $html );
}

/** ---------------------------------------------------------
 * Mobile Sticky Call Bar (footer)
 * -------------------------------------------------------- */
add_filter( 'body_class', 'e3_body_class_sticky_call' );
function e3_body_class_sticky_call( $classes ) {
	if ( ! is_admin() && e3_is_hero_enabled() ) {
		$classes[] = 'e3-has-sticky-call';
	}
	return $classes;
}

add_action( 'wp_footer', 'e3_output_sticky_call_bar', 20 );
function e3_output_sticky_call_bar() {
	if ( is_admin() ) {
		return;
	}

	$post_id = get_queried_object_id();

	if ( ! e3_is_hero_enabled( $post_id ) ) {
		return;
	}

	// Check for page-specific hero content first
	$content = e3_get_page_specific_hero_content( $post_id );

	// Use page-specific content or fall back to default
	if ( null === $content ) {
		$content = e3_get_default_hero_content();
	}

	$cta = $content['sticky_cta'];
	$phone_display = e3_get_phone_display();
	$phone_href    = e3_phone_to_tel_href( $phone_display );

	$icon_phone = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.07 21 3 13.93 3 5a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.24.2 2.45.57 3.57a1 1 0 0 1-.24 1.02l-2.21 2.2Z"/></svg>';

	$html  = '<div class="e3-sticky-call" role="region" aria-label="Call">';
	$html .= '  <a class="e3-sticky-call__btn" href="' . esc_attr( $phone_href ? $phone_href : 'tel:' ) . '">' . $icon_phone . esc_html( $cta ) . '</a>';
	$html .= '</div>';

	echo e3_apply_magicpage_vars( $html );
}
