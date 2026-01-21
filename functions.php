<?php
/**
 * E3 Locksmith (Kadence Child) – Speed Tweaks
 */

/** ---------------------------------------------------------
 * Enqueue styles
 * -------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', 'e3_locksmith_enqueue_styles', 20 );
function e3_locksmith_enqueue_styles() {
	$child_uri  = get_stylesheet_directory_uri();

	// Parent + child
	wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css', array(), null );
	wp_enqueue_style( 'child-style', $child_uri . '/style.css', array( 'parent-style' ), null );
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
