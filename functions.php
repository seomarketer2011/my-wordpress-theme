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
			'usp_1_title'  => 'Rapid Business Lockout Response',
			'usp_1_desc'   => '30-minute emergency callout to get your premises open and staff back to work',
			'usp_2_title'  => 'Zero Call-Out Charges',
			'usp_2_desc'   => 'Speak to us first, get clear pricing, only pay for completed work',
			'usp_3_title'  => 'DBS-Checked Commercial Engineers',
			'usp_3_desc'   => 'Trusted, vetted locksmiths for your business premises and security systems',
			'usp_4_title'  => 'Serving Businesses Across [location]',
			'usp_4_desc'   => 'Master key systems and rekeying for full key control – manage staff access and eliminate lost key risks',
			'cta_text'     => 'Call Now For Help & Free Quote',
			'microcopy'    => '30-min response • Master key systems • No call-out fees',
			'badge_1'      => '30-min response',
			'badge_2'      => 'No call-out fee',
			'badge_3'      => 'DBS checked',
			'badge_4'      => '[location] coverage',
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
			'usp_1_title'  => 'Same-Day Grille Lock Repair',
			'usp_1_desc'   => 'Same-day service available – we fix jammed, stiff, or binding grille mechanisms fast',
			'usp_2_title'  => 'Clear Repair Pricing from £59',
			'usp_2_desc'   => 'Transparent pricing quoted upfront – parts charged separately only when needed',
			'usp_3_title'  => 'Repair-First Specialists',
			'usp_3_desc'   => 'We restore existing lock function rather than replace – professional diagnosis and multi-cycle testing',
			'usp_4_title'  => '24/7 Availability Across [location]',
			'usp_4_desc'   => 'DBS-checked engineers for commercial security grille repairs with round-the-clock callout service',
			'cta_text'     => 'Call Now For Help & Free Quote',
			'microcopy'    => 'Same-day service • Repair-first approach • Fixed pricing',
			'badge_1'      => 'Same-day service',
			'badge_2'      => 'From £59',
			'badge_3'      => 'Repair-focused',
			'badge_4'      => '24/7 availability',
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
			'usp_1_title'  => 'Same-Day Service Available',
			'usp_1_desc'   => 'Professional security survey with same-day availability – comprehensive property assessment identifying vulnerabilities',
			'usp_2_title'  => 'Security Survey from £69',
			'usp_2_desc'   => 'Transparent quotes for all security improvements – from basic lock upgrades to full camera systems',
			'usp_3_title'  => 'Insurance-Compliant Installations',
			'usp_3_desc'   => 'BS3621 and PAS24 certified locks and security solutions meeting insurer requirements',
			'usp_4_title'  => 'Complete Security Solutions Across [location]',
			'usp_4_desc'   => 'Anti-snap locks, smart cameras, door reinforcement, and 24/7 monitoring systems – all professionally installed',
			'cta_text'     => 'Call Now to Book Security Survey',
			'microcopy'    => 'Security surveys • Insurance-compliant • Professional installation',
			'badge_1'      => 'Same-day service',
			'badge_2'      => 'Survey from £69',
			'badge_3'      => 'Insurance-approved',
			'badge_4'      => '[location] coverage',
			'sticky_cta'   => 'Call To Book Security Survey',
		);
	}

	// Safe Opening Page
	if ( 'open-safe' === $slug || 'safe-opening' === $slug ) {
		$page_content = array(
			'top_label'    => 'Call Now',
			'top_bullet_1' => 'Same Day Service',
			'top_bullet_2' => 'No call-out fee',
			'top_bullet_3' => 'From £69',
			'title'        => 'Safe Opening Services [location]',
			'subtitle'     => 'Locked out of your safe? We open all safe types with non-destructive methods first – from forgotten codes to jammed mechanisms, we\'ll get you access',
			'usp_1_title'  => 'Same-Day Safe Opening',
			'usp_1_desc'   => 'Same-day service for safe lockouts – we\'ll assess and open your safe with professional methods',
			'usp_2_title'  => 'From £69 with Clear Pricing',
			'usp_2_desc'   => 'Transparent quotes upfront – we attempt non-destructive methods first to minimize costs and preserve your safe',
			'usp_3_title'  => 'DBS-Checked Safe Engineers',
			'usp_3_desc'   => 'Trusted locksmiths authorized to open all safe types – key locks, digital keypads, and mechanical dials',
			'usp_4_title'  => 'All Safe Types Across [location]',
			'usp_4_desc'   => 'We open home safes, commercial safes, wall safes, floor safes, and high-security models – 24/7 availability',
			'cta_text'     => 'Call Now for Safe Opening',
			'microcopy'    => 'Same-day service • Non-destructive first • All safe types',
			'badge_1'      => 'Same-day service',
			'badge_2'      => 'From £69',
			'badge_3'      => 'DBS checked',
			'badge_4'      => '24/7 availability',
			'sticky_cta'   => 'Call 24/7 to Unlock Your Safe',
		);
	}

	// Door Repair Page
	if ( 'door-repair' === $slug || 'door-repairs' === $slug ) {
		$page_content = array(
			'top_label'    => 'Call Now',
			'top_bullet_1' => 'Same Day Service',
			'top_bullet_2' => 'No call-out fee',
			'top_bullet_3' => 'From £65',
			'title'        => 'Door Repair Services [location]',
			'subtitle'     => 'Door sticking, sagging, or won\'t close properly? We repair all door issues with same-day service – from alignment to hinges and frames',
			'usp_1_title'  => 'Same-Day Door Repairs',
			'usp_1_desc'   => 'Fast response for door issues – we fix sticking doors, broken hinges, and alignment problems with professional service',
			'usp_2_title'  => 'Clear Pricing from £65',
			'usp_2_desc'   => 'Transparent quotes upfront – alignment adjustments from £65, with parts charged separately when needed',
			'usp_3_title'  => '6-Month Repair Guarantee',
			'usp_3_desc'   => 'All door repairs backed by our 6-month workmanship guarantee – DBS-checked engineers',
			'usp_4_title'  => 'All Door Types Across [location]',
			'usp_4_desc'   => 'We repair uPVC doors, composite doors, wooden doors, and fire doors – hinges, handles, frames, and alignment',
			'cta_text'     => 'Call to Book Same Day Door Repair',
			'microcopy'    => 'Same-day service • 6-month guarantee • All door types',
			'badge_1'      => 'Same-day service',
			'badge_2'      => 'From £65',
			'badge_3'      => '6-month guarantee',
			'badge_4'      => 'All door types',
			'sticky_cta'   => 'Call us Now For Door Repair Help',
		);
	}

	// Lock Repair Page
	if ( 'lock-repair' === $slug || 'lock-repairs' === $slug ) {
		$page_content = array(
			'top_label'    => 'Call Now',
			'top_bullet_1' => 'Same Day Service',
			'top_bullet_2' => 'No call-out fee',
			'top_bullet_3' => 'From £59',
			'title'        => 'Lock Repair Services [location]',
			'subtitle'     => 'Lock stiff, grinding, or key won\'t turn? We repair all lock types with same-day service – from uPVC multipoint locks to mortice and cylinder locks',
			'usp_1_title'  => 'Same-Day Lock Repairs',
			'usp_1_desc'   => 'Fast response for lock issues – we fix stiff locks, worn mechanisms, and misaligned multipoint systems with professional service',
			'usp_2_title'  => 'Clear Pricing from £59',
			'usp_2_desc'   => 'Transparent hourly rates – weekday repairs from £59/hr, evenings/weekends from £69/hr, parts quoted separately',
			'usp_3_title'  => 'Repair-First Specialists',
			'usp_3_desc'   => 'We diagnose and repair existing locks rather than automatic replacement – saving you money when repair is viable',
			'usp_4_title'  => 'All Lock Types Across [location]',
			'usp_4_desc'   => 'We repair euro cylinders, multipoint mechanisms, mortice locks, rim cylinders, and window locks – common parts carried',
			'cta_text'     => 'Call Now For Lock Repairs',
			'microcopy'    => 'Same-day service • Repair-first approach • All lock types',
			'badge_1'      => 'Same-day service',
			'badge_2'      => 'From £59',
			'badge_3'      => 'Repair-focused',
			'badge_4'      => 'All lock types',
			'sticky_cta'   => 'Call 24/7 for Lock Repair Help',
		);
	}

	// Door Lock Replacement Page
	if ( 'door-lock-replacement' === $slug || 'lock-replacement' === $slug ) {
		$page_content = array(
			'top_label'    => 'Call Now',
			'top_bullet_1' => 'Same Day Service',
			'top_bullet_2' => 'No call-out fee',
			'top_bullet_3' => 'From £65',
			'title'        => 'Door Lock Replacement [location]',
			'subtitle'     => 'Need locks replaced after a break-in or lost keys? We install all lock types with same-day service – from euro cylinders to multipoint and smart locks',
			'usp_1_title'  => 'Same-Day Lock Replacement',
			'usp_1_desc'   => 'Fast response for lock changes – we replace worn, damaged, or compromised locks with professional installation and testing',
			'usp_2_title'  => 'Clear Pricing from £65',
			'usp_2_desc'   => 'Transparent quotes upfront – euro cylinders from £65, standard locks from £75, with insurance-approved options available',
			'usp_3_title'  => 'Insurance-Approved Installations',
			'usp_3_desc'   => 'BS3621 certified locks and TS007 rated cylinders meeting insurer requirements – proper alignment and testing guaranteed',
			'usp_4_title'  => 'All Lock Types Across [location]',
			'usp_4_desc'   => 'We install mortice locks, euro cylinders, multipoint mechanisms, smart locks, and commercial maglocks – residential and business properties',
			'cta_text'     => 'Call Now for Lock Replacement',
			'microcopy'    => 'Same-day service • Insurance-approved • All lock types',
			'badge_1'      => 'Same-day service',
			'badge_2'      => 'From £65',
			'badge_3'      => 'Insurance-approved',
			'badge_4'      => 'All lock types',
			'sticky_cta'   => 'Call 24/7 For Lock Replacements',
		);
	}

	// Door Lock Installation Page
	if ( 'door-lock-installation' === $slug || 'lock-installation' === $slug ) {
		$page_content = array(
			'top_label'    => 'Call Now',
			'top_bullet_1' => 'Same Day Service',
			'top_bullet_2' => 'No call-out fee',
			'top_bullet_3' => 'From £85',
			'title'        => 'Door Lock Installation [location]',
			'subtitle'     => 'Need new locks installed or security upgraded? We fit all lock types with same-day service – from smart locks to BS3621 mortice deadlocks',
			'usp_1_title'  => 'Same-Day Lock Installation',
			'usp_1_desc'   => 'Fast response for new lock fitting – we install fresh locks, upgrade existing systems, and fit smart lock solutions professionally',
			'usp_2_title'  => 'Clear Pricing from £85',
			'usp_2_desc'   => 'Transparent quotes upfront – standard fitting from £85, fresh installations from £125, smart locks from £185, all VAT included',
			'usp_3_title'  => 'Insurance-Compliant Installations',
			'usp_3_desc'   => 'BS3621 5-lever mortice locks and TS007 cylinders meeting insurer requirements – professional installation guaranteed',
			'usp_4_title'  => 'All Lock Types Across [location]',
			'usp_4_desc'   => 'We install mortice deadlocks, smart locks, digital keypads, multipoint systems, and commercial maglocks – residential and business properties',
			'cta_text'     => 'Call now For Lock Installation',
			'microcopy'    => 'Same-day service • Insurance-compliant • All lock types',
			'badge_1'      => 'Same-day service',
			'badge_2'      => 'From £85',
			'badge_3'      => 'Insurance-compliant',
			'badge_4'      => 'All lock types',
			'sticky_cta'   => 'Call For Lock Installation Help',
		);
	}

	// Lock Change Page
	if ( 'lock-change' === $slug || 'lock-changes' === $slug ) {
		$page_content = array(
			'top_label'    => 'Call Now',
			'top_bullet_1' => 'Same Day Service',
			'top_bullet_2' => 'No call-out fee',
			'top_bullet_3' => 'Free Quotes',
			'title'        => 'Lock Change Services [location]',
			'subtitle'     => 'Moving home or lost your keys? We change all lock types with same-day service – new keys, fresh security, complete peace of mind',
			'usp_1_title'  => 'Same-Day Lock Changes',
			'usp_1_desc'   => 'Fast response for lock changes – we replace locks for new properties, lost keys, tenant changes, and security upgrades',
			'usp_2_title'  => 'Transparent Pricing',
			'usp_2_desc'   => 'Clear quotes upfront – we choose the correct lock type, fit it properly, and test for reliable operation',
			'usp_3_title'  => 'Security-Focused Service',
			'usp_3_desc'   => 'Professional lock changes with correct sizing, alignment testing, and security grade options – DBS-checked engineers',
			'usp_4_title'  => 'All Lock Types Across [location]',
			'usp_4_desc'   => 'We change euro cylinders, mortice locks, nightlatches, and multipoint systems – keyed-alike options available',
			'cta_text'     => 'Call for Free Lock Change Quote',
			'microcopy'    => 'Same-day service • Professional fitting • All lock types',
			'badge_1'      => 'Same-day service',
			'badge_2'      => 'Free quotes',
			'badge_3'      => 'Security-focused',
			'badge_4'      => 'All lock types',
			'sticky_cta'   => 'Call 24/7 for Lock Change Help',
		);
	}

	// Window Lock Repairs Page
	if ( 'window-lock-repairs' === $slug || 'window-lock-repair' === $slug ) {
		$page_content = array(
			'top_label'    => 'Call Now',
			'top_bullet_1' => 'Same Day Service',
			'top_bullet_2' => 'No call-out fee',
			'top_bullet_3' => 'Free Quotes',
			'title'        => 'Window Lock Repairs [location]',
			'subtitle'     => 'Window lock stiff, loose, or won\'t engage? We repair all window lock types with same-day service – from uPVC to casement and tilt & turn mechanisms',
			'usp_1_title'  => 'Same-Day Window Lock Repairs',
			'usp_1_desc'   => 'Fast response for window security issues – we fix stiff locks, loose handles, misaligned mechanisms, and broken window locks professionally',
			'usp_2_title'  => 'Transparent Pricing',
			'usp_2_desc'   => 'Clear quotes upfront – we diagnose the issue properly before recommending repair or replacement options',
			'usp_3_title'  => 'Security-Focused Repairs',
			'usp_3_desc'   => 'Professional window lock repairs restoring security and smooth operation – DBS-checked engineers for your peace of mind',
			'usp_4_title'  => 'All Window Types Across [location]',
			'usp_4_desc'   => 'We repair uPVC espagnolette locks, casement handles, cockspur mechanisms, tilt & turn locks, and timber window locks',
			'cta_text'     => 'Call for Window Lock Repair',
			'microcopy'    => 'Same-day service • All window types • Professional repairs',
			'badge_1'      => 'Same-day service',
			'badge_2'      => 'Free quotes',
			'badge_3'      => 'Security-focused',
			'badge_4'      => 'All window types',
			'sticky_cta'   => 'Call Now for Window Lock Repairs',
		);
	}

	// Roller Shutter Door Lock Repair Page
	if ( 'roller-shutter-door-lock-repair' === $slug || 'roller-shutter-lock-repair' === $slug || 'roller-shutter-repair' === $slug ) {
		$page_content = array(
			'top_label'    => 'Call Now',
			'top_bullet_1' => 'Same Day Service',
			'top_bullet_2' => 'No call-out fee',
			'top_bullet_3' => 'From £59',
			'title'        => 'Roller Shutter Door Lock Repair [location]',
			'subtitle'     => 'Roller shutter won\'t lock or key sticking? We repair all roller shutter lock types with same-day service – from jammed mechanisms to worn shoot bolts',
			'usp_1_title'  => 'Same-Day Shutter Lock Repairs',
			'usp_1_desc'   => 'Fast response for roller shutter lock issues – we fix jammed locks, stiff mechanisms, and locking points that won\'t engage properly',
			'usp_2_title'  => 'Transparent Pricing from £59',
			'usp_2_desc'   => 'Clear quotes upfront – we diagnose the root cause, repair alignment, clean and lubricate mechanisms for reliable operation',
			'usp_3_title'  => 'Professional Diagnosis & Repair',
			'usp_3_desc'   => 'No quick fixes – we test across multiple cycles ensuring consistent locking without tricks like pushing or slamming',
			'usp_4_title'  => 'All Shutter Types Across [location]',
			'usp_4_desc'   => 'We repair commercial and industrial roller shutter locks – shoot bolts, locking points, and key mechanisms with DBS-checked engineers',
			'cta_text'     => 'Call Now For Shutter Lock Repairs',
			'microcopy'    => 'Same-day service • Professional diagnosis • All shutter types',
			'badge_1'      => 'Same-day service',
			'badge_2'      => 'From £59',
			'badge_3'      => 'Professional repair',
			'badge_4'      => 'All shutter types',
			'sticky_cta'   => 'Help Available 24/7 Call Now',
		);
	}

	// Emergency Locksmith Page
	if ( 'emergency-locksmith' === $slug || 'emergency-locksmiths' === $slug ) {
		$page_content = array(
			'top_label'    => 'Call Now',
			'top_bullet_1' => 'Available 24/7',
			'top_bullet_2' => 'No Call-Out Fee',
			'top_bullet_3' => '30-min Response Time',
			'title'        => 'Emergency Locksmith [location]',
			'subtitle'     => 'Locked out? Key snapped? We provide fast emergency locksmith service with 30-minute response – from lockouts to jammed locks, we get you back in quickly with minimal damage',
			'usp_1_title'  => '30-Minute Emergency Response',
			'usp_1_desc'   => 'Fast response for most lockouts – we prioritize emergency calls and get to you fast when you\'re locked out of your home or business',
			'usp_2_title'  => 'Transparent Pricing',
			'usp_2_desc'   => 'Clear quotes before we start – no hidden charges or surprise fees, even for out-of-hours emergency callouts',
			'usp_3_title'  => 'Non-Destructive Entry First',
			'usp_3_desc'   => 'We aim for lock-picking and non-destructive methods first – saving your locks and avoiding costly replacements where possible',
			'usp_4_title'  => '24/7 Availability Across [location]',
			'usp_4_desc'   => 'Locked out at 3am? We\'re available 24/7 for all lockout emergencies – lost keys, keys inside, snapped keys, or jammed locks',
			'cta_text'     => 'Call Now For Emergency Locksmith',
			'microcopy'    => '30-min response • 24/7 availability • Non-destructive entry',
			'badge_1'      => '30-min response',
			'badge_2'      => 'No call-out fee',
			'badge_3'      => 'Non-destructive entry',
			'badge_4'      => '24/7 availability',
			'sticky_cta'   => 'Call Now For Emergency Help',
		);
	}

	// Burglary Repair Services Page
	if ( 'burglary-repair-services' === $slug || 'burglary-repair' === $slug ) {
		$page_content = array(
			'top_label'    => 'Call Now',
			'top_bullet_1' => 'Available 24/7',
			'top_bullet_2' => 'No Call-Out Fee',
			'top_bullet_3' => '30-min Response Time',
			'title'        => 'Burglary Repair Services [location]',
			'subtitle'     => 'Door kicked in or lock forced? We secure your property after break-ins with emergency repairs – from damaged frames to forced locks, we restore security and get you safe again',
			'usp_1_title'  => '30-Minute Emergency Response',
			'usp_1_desc'   => 'Rapid response for burglary damage – we prioritize break-in calls and secure your property fast when you need immediate help',
			'usp_2_title'  => 'Transparent Pricing',
			'usp_2_desc'   => 'Clear quotes before we start – no hidden charges or surprise fees, even during stressful post-break-in situations',
			'usp_3_title'  => 'Complete Damage Repair',
			'usp_3_desc'   => 'We repair forced doors, damaged frames, broken keeps, and misaligned locks – restoring full security rather than temporary fixes',
			'usp_4_title'  => '24/7 Availability Across [location]',
			'usp_4_desc'   => 'Break-in at 2am? We\'re available 24/7 for emergency burglary repairs – we\'ll secure your property and restore your peace of mind',
			'cta_text'     => 'Call Now For Emergency Burglary Repair',
			'microcopy'    => '24/7 availability • Complete security restoration • Frame repairs',
			'badge_1'      => '30-min response',
			'badge_2'      => 'No call-out fee',
			'badge_3'      => 'Frame repairs',
			'badge_4'      => '24/7 availability',
			'sticky_cta'   => 'Call Now For Emergency Help',
		);
	}

	// Emergency Boarding Up Page
	if ( 'emergency-boarding-up' === $slug || 'boarding-up' === $slug ) {
		$page_content = array(
			'top_label'    => 'Call Now',
			'top_bullet_1' => 'Available 24/7',
			'top_bullet_2' => 'No Call-Out Fee',
			'top_bullet_3' => '30-min Response Time',
			'title'        => 'Emergency Boarding Up [location]',
			'subtitle'     => 'Window smashed or door damaged? We secure your property with emergency boarding up – from broken windows to forced doors, we make your property safe and weatherproof fast',
			'usp_1_title'  => '30-Minute Emergency Response',
			'usp_1_desc'   => 'Rapid emergency boarding for property damage – we prioritize emergency boarding calls and secure your property fast when you need immediate protection',
			'usp_2_title'  => 'Transparent Pricing',
			'usp_2_desc'   => 'Clear quotes before we start – no hidden charges or surprise fees, with insurance documentation provided for your claims',
			'usp_3_title'  => 'Complete Property Protection',
			'usp_3_desc'   => 'We board broken windows, damaged doors, and forced frames – making safe dangerous glass and securing all openings against weather and intruders',
			'usp_4_title'  => '24/7 Availability Across [location]',
			'usp_4_desc'   => 'Break-in or vandalism at 3am? We\'re available 24/7 for emergency boarding up – residential and commercial properties secured professionally',
			'cta_text'     => 'Call Now For Emergency Boarding Up',
			'microcopy'    => '24/7 availability • Complete protection • Insurance documentation',
			'badge_1'      => '30-min response',
			'badge_2'      => 'No call-out fee',
			'badge_3'      => 'Insurance support',
			'badge_4'      => '24/7 availability',
			'sticky_cta'   => 'Call Now For Emergency Help',
		);
	}

	// Lock Out Services Page
	if ( 'lock-out' === $slug || 'lockout' === $slug ) {
		$page_content = array(
			'top_label'    => 'Call Now',
			'top_bullet_1' => 'Available 24/7',
			'top_bullet_2' => 'No Call-Out Fee',
			'top_bullet_3' => '30-min Response Time',
			'title'        => 'Lock Out Services [location]',
			'subtitle'     => 'Locked out of your home or business? We get you back in fast with 30-minute emergency response – from keys inside to seized locks, we use non-destructive entry where possible',
			'usp_1_title'  => '30-Minute Lockout Response',
			'usp_1_desc'   => 'Fast response for lockouts – we prioritize emergency calls and get you back inside fast when you\'re locked out of your property',
			'usp_2_title'  => 'From £63 for Non-Destructive Entry',
			'usp_2_desc'   => 'Clear quotes before we start – from £63 for non-destructive entry, with no hidden charges or surprise fees',
			'usp_3_title'  => 'Non-Destructive Entry First',
			'usp_3_desc'   => 'We aim for lock-picking and non-destructive methods first – saving your locks and avoiding costly replacements where possible',
			'usp_4_title'  => '24/7 Availability Across [location]',
			'usp_4_desc'   => 'Locked out at 2am? We\'re available 24/7 for all lockout emergencies – residential, commercial, and managed buildings covered',
			'cta_text'     => 'Call Now For Lockout Help',
			'microcopy'    => '30-min response • 24/7 availability • Non-destructive entry',
			'badge_1'      => '30-min response',
			'badge_2'      => 'From £63',
			'badge_3'      => 'Non-destructive entry',
			'badge_4'      => '24/7 availability',
			'sticky_cta'   => 'Call Now For Emergency Help',
		);
	}

	// Safe Installation Page
	if ( 'safe-installation' === $slug || 'safe-install' === $slug ) {
		$page_content = array(
			'top_label'    => 'Call Now',
			'top_bullet_1' => 'Insurance Compliant',
			'top_bullet_2' => 'Trusted Brands',
			'top_bullet_3' => 'Free Quotes',
			'title'        => 'Safe Installation Services [location]',
			'subtitle'     => 'Need a safe professionally installed? We supply and fit all safe types with insurance-compliant installation – from wall safes to fire-resistant models, we anchor securely to insurance standards',
			'usp_1_title'  => 'Flexible Scheduling Available',
			'usp_1_desc'   => 'Book at your convenience – we offer flexible installation appointments that fit around your schedule for professional safe fitting',
			'usp_2_title'  => 'Transparent Pricing with Free Quotes',
			'usp_2_desc'   => 'Clear quotes before we start – transparent pricing for supply and installation with no hidden charges or surprise fees',
			'usp_3_title'  => 'Insurance-Compliant Installations',
			'usp_3_desc'   => 'Professional anchoring to insurance standards – we install to BS7558 requirements ensuring full insurer compliance and proper security',
			'usp_4_title'  => 'All Safe Types Across [location]',
			'usp_4_desc'   => 'We supply and fit wall safes, floor safes, fire-resistant safes, gun cabinets, and high-security models from trusted brands like Chubbsafes, Phoenix, and Yale',
			'cta_text'     => 'Call For Free Safe Installation Quote',
			'microcopy'    => 'Free quotes • Insurance-compliant • Trusted brands',
			'badge_1'      => 'Flexible scheduling',
			'badge_2'      => 'Free quotes',
			'badge_3'      => 'Insurance-compliant',
			'badge_4'      => 'All safe types',
			'sticky_cta'   => 'Call For Safe Installation Quote',
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
