<?php
/**
 * E3 Locksmith (Kadence Child) – Conversion UI + Speed Tweaks
 *
 * - Conversion hero injected under the header (all pages)
 * - Mobile-only sticky call bar (intent-aware)
 * - No contact forms / no WhatsApp (phone only)
 * - Uses Magic Page variables (e.g. [meta_telephone]) via apply_variables() when available
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
 * Basic speed hygiene (safe)
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
 * Intent detection (emergency vs same_day vs planned)
 * - Order: emergency keywords win, same_day keywords next, planned keywords next, default emergency
 * -------------------------------------------------------- */
function e3_get_page_intent( $post_id = 0 ) {
	if ( ! $post_id ) {
		$post_id = get_queried_object_id();
	}

	// Per-page override (optional)
	$override = $post_id ? get_post_meta( $post_id, '_e3_intent', true ) : '';
	if ( in_array( $override, array( 'emergency', 'same_day', 'planned' ), true ) ) {
		return $override;
	}
	if ( 'off' === $override ) {
		return 'off';
	}

	$uri   = isset( $_SERVER['REQUEST_URI'] ) ? strtolower( (string) $_SERVER['REQUEST_URI'] ) : '';
	$title = $post_id ? strtolower( (string) get_the_title( $post_id ) ) : '';

	$haystack = $uri . ' ' . $title;

	$emergency = array(
		'locked-out', 'lockout', 'emergency', '24-hour', '24hour', 'burglary', 'break-in', 'breakin',
		'lost-keys', 'lost-key', 'snapped-key', 'broken-key', 'key-broken', 'key-extraction',
		'door-opening', 'gain-entry', 'upvc-repair'
	);

	foreach ( $emergency as $k ) {
		if ( false !== strpos( $haystack, $k ) ) {
			return 'emergency';
		}
	}

	$same_day = array(
		'same-day', 'sameday', 'today', 'urgent', 'fast', 'quick', 'rapid'
	);

	foreach ( $same_day as $k ) {
		if ( false !== strpos( $haystack, $k ) ) {
			return 'same_day';
		}
	}

	$planned = array(
		'lock-change', 'lock-replacement', 'replace-lock', 'new-lock', 'install', 'installation',
		'upgrade', 'security-upgrade', 'smart-lock', 'rekey', 'key-cut', 'spare-key', 'lock-fitting',
		'quote', 'pricing', 'price', 'cost'
	);

	foreach ( $planned as $k ) {
		if ( false !== strpos( $haystack, $k ) ) {
			return 'planned';
		}
	}

	// Default (safer for locksmith sites)
	return 'emergency';
}


/** ---------------------------------------------------------
 * Helper: per-page overrides (Custom Fields)
 * -------------------------------------------------------- */
function e3_get_page_override( $post_id, $key ) {
	if ( ! $post_id ) { $post_id = get_queried_object_id(); }
	if ( ! $post_id ) { return ''; }

	$val = get_post_meta( $post_id, $key, true );

	// WordPress treats meta keys starting with "_" as protected, and the built-in Custom Fields UI
	// often refuses to save them ("Sorry, you are not allowed to do that.").
	// To keep things newbie-friendly, we support BOTH forms:
	//   _e3_hero_title  (protected)  and  e3_hero_title (unprotected)
	if ( '' === $val || null === $val ) {
		$alt = $key;
		if ( is_string( $key ) && strlen( $key ) > 0 ) {
			$alt = ( '_' === $key[0] ) ? substr( $key, 1 ) : '_' . $key;
		}
		if ( $alt && $alt !== $key ) {
			$val = get_post_meta( $post_id, $alt, true );
		}
	}

	return is_string( $val ) ? trim( $val ) : '';
}

/** ---------------------------------------------------------
 * Helper: apply Magic Page variables to a string if available
 * -------------------------------------------------------- */
function e3_get_magicpage_term() {
	// Magic Page plugin provides the most reliable context for [meta_*] tokens.
	if ( function_exists( 'get_location_object' ) ) {
		$term = get_location_object();
		// Some plugin calls return an array with term at index 0.
		if ( is_array( $term ) && isset( $term[0] ) ) {
			$term = $term[0];
		}
		if ( is_object( $term ) && isset( $term->term_id ) ) {
			return $term;
		}
	}

	// Fallbacks (in case plugin functions differ between installs).
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
		// Pass $term so [meta_*] replacements work (they require term_id).
		return $term ? apply_variables( $html, $term ) : apply_variables( $html );
	}
	return $html;
}

function e3_get_phone_display() {
	$phone = '';

	$term = e3_get_magicpage_term();
	if ( $term && isset( $term->term_id ) ) {
		// Most installs store the token name as the term meta key (e.g. telephone).
		$phone = (string) get_term_meta( $term->term_id, 'telephone', true );
	}

	// If not present, try the token replacement.
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
	// Keep only digits and plus for a clean tel: link.
	$clean = preg_replace( '/[^0-9+]/', '', $raw );
	return $clean ? 'tel:' . $clean : '';
}


/** ---------------------------------------------------------
 * Hero Content Configuration
 * Edit these arrays to change the text for each hero variant
 * -------------------------------------------------------- */
function e3_get_hero_content() {
	return array(
		'emergency' => array(
			'top_label'    => 'Emergency 24/7:',
			'top_bullet_1' => '30-min response',
			'top_bullet_2' => 'No call-out fee',
			'top_bullet_3' => 'DBS checked',
			'title'        => 'Locked Out? We\'ll Get You Back In Fast',
			'subtitle'     => 'Emergency locksmith dispatch — we respond within 30 minutes across the UK',
			'usp_1_title'  => 'Rapid 30-minute response',
			'usp_1_desc'   => 'Emergency callout with immediate dispatch to your location',
			'usp_2_title'  => 'Zero call-out charges',
			'usp_2_desc'   => 'Talk to us first — only pay for work completed',
			'usp_3_title'  => 'Fully vetted locksmiths',
			'usp_3_desc'   => 'All engineers are DBS-checked and insured professionals',
			'usp_4_title'  => 'Local engineers nationwide',
			'usp_4_desc'   => 'Fast response from your nearest qualified locksmith',
			'cta_text'     => 'Call Emergency Line',
			'microcopy'    => '24/7 dispatch • No hidden fees • DBS-checked engineers',
			'badge_1'      => 'DBS checked',
			'badge_2'      => 'No call-out fee',
			'badge_3'      => '30-min response',
			'badge_4'      => 'Local coverage',
			'sticky_cta'   => 'Call Now',
		),
		'same_day' => array(
			'top_label'    => 'Same-Day Service:',
			'top_bullet_1' => 'Book today',
			'top_bullet_2' => 'Fixed pricing',
			'top_bullet_3' => 'No rush fees',
			'title'        => 'Same-Day Lock Changes & Security Repairs',
			'subtitle'     => 'Call before 2pm for same-day service — fixed prices with no surprise charges',
			'usp_1_title'  => 'Same-day availability',
			'usp_1_desc'   => 'Book today, sorted today — call before 2pm for guaranteed slots',
			'usp_2_title'  => 'Transparent fixed pricing',
			'usp_2_desc'   => 'Clear quotes over the phone with no hidden extras',
			'usp_3_title'  => 'Professional service',
			'usp_3_desc'   => 'Expert locksmiths with full credentials and insurance',
			'usp_4_title'  => 'No rush charges applied',
			'usp_4_desc'   => 'Same competitive rates whether it\'s urgent or planned',
			'cta_text'     => 'Call for Same-Day',
			'microcopy'    => 'Same-day slots • Clear pricing • Professional service',
			'badge_1'      => 'Same-day service',
			'badge_2'      => 'Fixed pricing',
			'badge_3'      => 'No rush fees',
			'badge_4'      => 'DBS checked',
			'sticky_cta'   => 'Book Same-Day',
		),
		'planned' => array(
			'top_label'    => 'Get Your Quote:',
			'top_bullet_1' => 'Free quotes',
			'top_bullet_2' => 'No pressure',
			'top_bullet_3' => 'Flexible times',
			'title'        => 'Professional Lock Installation & Security Upgrades',
			'subtitle'     => 'Get a free quote over the phone — we\'ll arrange a time that suits you',
			'usp_1_title'  => 'Free no-obligation quotes',
			'usp_1_desc'   => 'Speak to us for clear pricing before you commit',
			'usp_2_title'  => 'Flexible scheduling',
			'usp_2_desc'   => 'Book appointments that work around your availability',
			'usp_3_title'  => 'Expert recommendations',
			'usp_3_desc'   => 'Professional advice on the best security solutions for your property',
			'usp_4_title'  => 'Quality installations',
			'usp_4_desc'   => 'Certified locksmiths using premium locks and hardware',
			'cta_text'     => 'Call for a Quote',
			'microcopy'    => 'Free quotes • No pressure • Flexible appointments',
			'badge_1'      => 'Free quotes',
			'badge_2'      => 'Expert advice',
			'badge_3'      => 'Quality work',
			'badge_4'      => 'DBS checked',
			'sticky_cta'   => 'Get Quote',
		),
	);
}

/** ---------------------------------------------------------
 * Remove Kadence default title output (prevents double H1)
 * -------------------------------------------------------- */
add_action( 'wp', 'e3_maybe_remove_kadence_default_titles' );
function e3_maybe_remove_kadence_default_titles() {
	$intent = e3_get_page_intent();
	if ( 'off' === $intent ) {
		return;
	}
	// Kadence adds Kadence\kadence_entry_header() to these actions in inc/template-hooks.php
	remove_action( 'kadence_entry_header', 'Kadence\kadence_entry_header', 10 );
	remove_action( 'kadence_entry_hero', 'Kadence\kadence_entry_header', 10 );
}

/** ---------------------------------------------------------
 * Conversion Hero (under header, above content)
 * -------------------------------------------------------- */
add_action( 'kadence_after_header', 'e3_output_conversion_hero', 5 );
function e3_output_conversion_hero() {
	if ( is_admin() || is_feed() || is_robots() ) {
		return;
	}

	$post_id = get_queried_object_id();
	// Show on most front-end pages (including Magic Page CPTs)
	if ( is_attachment() ) {
		return;
	}

	$intent = e3_get_page_intent( $post_id );
	if ( 'off' === $intent ) {
		return;
	}

	// Get hero content configuration
	$hero_content = e3_get_hero_content();
	$content = isset( $hero_content[ $intent ] ) ? $hero_content[ $intent ] : $hero_content['emergency'];

	// Allow per-page overrides, but fallback to configured content
	$title = e3_get_page_override( $post_id, '_e3_hero_title' );
	if ( '' === $title ) { $title = $content['title']; }

	$sub = e3_get_page_override( $post_id, '_e3_hero_sub' );
	if ( '' === $sub ) { $sub = $content['subtitle']; }

	$cta = e3_get_page_override( $post_id, '_e3_hero_cta' );
	if ( '' === $cta ) { $cta = $content['cta_text']; }

	$micro = e3_get_page_override( $post_id, '_e3_hero_micro' );
	if ( '' === $micro ) { $micro = $content['microcopy']; }

	$top_label = e3_get_page_override( $post_id, '_e3_hero_toplabel' );
	if ( '' === $top_label ) { $top_label = $content['top_label']; }

	$bg_url = e3_get_page_override( $post_id, '_e3_hero_bg_url' );

	$phone_display = e3_get_phone_display();
	$phone_href    = e3_phone_to_tel_href( $phone_display );

	// Small inline SVG icons (fast, no external requests)
	$icon_clock = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20Zm0-18a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm.75 4.5a.75.75 0 0 0-1.5 0v3.72c0 .2.08.39.22.53l2.25 2.25a.75.75 0 1 0 1.06-1.06l-2.03-2.03V8.5Z"/></svg>';
	$icon_pound = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M14 21H7v-2h1v-5H7v-2h1V9a5 5 0 0 1 5-5h2v2h-2a3 3 0 0 0-3 3v3h4v2h-4v5h4v2Z"/></svg>';
	$icon_shield = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2 4 5v6c0 5 3.4 9.74 8 11 4.6-1.26 8-6 8-11V5l-8-3Zm0 18.2c-3.33-1.08-6-4.85-6-9.2V6.3L12 4l6 2.3V11c0 4.35-2.67 8.12-6 9.2Zm-1-5.7 5.3-5.3-1.4-1.4L11 11.7 9.1 9.8 7.7 11.2 11 14.5Z"/></svg>';
	$icon_pin = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 22s7-4.44 7-11a7 7 0 1 0-14 0c0 6.56 7 11 7 11Zm0-9.5A2.5 2.5 0 1 1 12 7a2.5 2.5 0 0 1 0 5Z"/></svg>';
	$icon_phone = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.07 21 3 13.93 3 5a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.24.2 2.45.57 3.57a1 1 0 0 1-.24 1.02l-2.21 2.2Z"/></svg>';


	$style_attr = '';
	if ( '' !== $bg_url ) {
		$style_attr = ' style="--e3-hero-bg:url(\'' . esc_url( $bg_url ) . '\')"';
	}
	$html  = '<section class="e3-hero e3-hero--' . esc_attr( $intent ) . '"' . $style_attr . '>';
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
	$html .= '    </div>'; // grid
	$html .= '  </div>';
	$html .= '</section>';

	echo e3_apply_magicpage_vars( $html );
}

/** ---------------------------------------------------------
 * Mobile Sticky Call Bar (footer)
 * -------------------------------------------------------- */
add_filter( 'body_class', 'e3_body_class_sticky_call' );
function e3_body_class_sticky_call( $classes ) {
	if ( is_admin() ) {
		return $classes;
	}
	$intent = e3_get_page_intent();
	if ( 'off' !== $intent ) {
		$classes[] = 'e3-has-sticky-call';
	}
	return $classes;
}

add_action( 'wp_footer', 'e3_output_sticky_call_bar', 20 );
function e3_output_sticky_call_bar() {
	if ( is_admin() ) {
		return;
	}

	$intent = e3_get_page_intent();
	if ( 'off' === $intent ) {
		return;
	}

	// Get hero content configuration for sticky CTA
	$hero_content = e3_get_hero_content();
	$content = isset( $hero_content[ $intent ] ) ? $hero_content[ $intent ] : $hero_content['emergency'];

	$cta = e3_get_page_override( get_queried_object_id(), '_e3_sticky_cta' );
	if ( '' === $cta ) { $cta = $content['sticky_cta']; }
	$phone_display = e3_get_phone_display();
	$phone_href    = e3_phone_to_tel_href( $phone_display );

	$icon_phone = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.07 21 3 13.93 3 5a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.24.2 2.45.57 3.57a1 1 0 0 1-.24 1.02l-2.21 2.2Z"/></svg>';

	$html  = '<div class="e3-sticky-call" role="region" aria-label="Call">';
	$html .= '  <a class="e3-sticky-call__btn" href="' . esc_attr( $phone_href ? $phone_href : 'tel:' ) . '">' . $icon_phone . esc_html( $cta ) . '</a>';
	$html .= '</div>';

	echo e3_apply_magicpage_vars( $html );
}