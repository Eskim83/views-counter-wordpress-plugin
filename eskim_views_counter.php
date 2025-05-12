<?php
/**
 * Plugin Name:       Prosty licznik odwiedzin od eskim.pl
 * Plugin URI:        https://eskim.pl/zapisywanie-w-bazie-danych-wordpressa/
 * Description:       Prosty licznik odwiedzin, zapisujący dane o użytkowniku w bazie danych WordPress.
 * Version:           1.2
 * Requires PHP:      8.2
 * Author:            Maciej Włodarczak
 * Author URI:        https://eskim.pl
 * License:           GPL v3 or later
 * Text Domain:       eskim_views_counter
 * Domain Path:       /languages
 */

/**
 * Blokada bezpośredniego wywołania pliku.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

// Rejestracja hooków aktywacyjnych i deinstalacyjnych.
register_activation_hook(__FILE__, 'eskim_views_counter_activation');

/**
 * Tworzenie wymaganych tabel przy aktywacji pluginu.
 */
function eskim_views_counter_activation() {
	global $wpdb;

	$table_name_countries = $wpdb->prefix . 'eskim_views_counter_countries';
	$table_name_referers = $wpdb->prefix . 'eskim_views_counter_referers';
	$table_name_visitors = $wpdb->prefix . 'eskim_views_counter_visitors';

	dbDelta("CREATE TABLE $table_name_countries (
		id INT NOT NULL AUTO_INCREMENT,
		name VARCHAR(50) UNIQUE,
		code VARCHAR(10),
		PRIMARY KEY (id)
	);");

	dbDelta("CREATE TABLE $table_name_referers (
		id INT NOT NULL AUTO_INCREMENT,
		url VARCHAR(255) UNIQUE,
		PRIMARY KEY (id)
	);");

	dbDelta("CREATE TABLE $table_name_visitors (
		ip BIGINT,
		post INT NULL,
		country INT NULL,
		referer INT NULL,
		created TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
		INDEX (ip),
		INDEX (post),
		FOREIGN KEY (referer) REFERENCES $table_name_referers (id) ON DELETE SET NULL,
		FOREIGN KEY (country) REFERENCES $table_name_countries (id) ON DELETE SET NULL
	);");
}

/**
 * Zapisuje wizytę w bazie danych.
 */
function eskim_views_counter_add_visitor($ip_raw, $post_id = null, $country_id = null, $referer_id = null): bool {
	global $wpdb;

	$ip = ip2long($ip_raw);
	if ($ip === false) return false;

	$table_name = $wpdb->prefix . 'eskim_views_counter_visitors';
	$result = $wpdb->insert($table_name, [
		'ip' => $ip,
		'post' => $post_id,
		'country' => $country_id,
		'referer' => $referer_id
	]);

	return $result !== false;
}

/**
 * Dodaje nowy kraj do bazy danych (jeśli go wcześniej nie było).
 */
function eskim_views_counter_add_country(string $name, string $code): int {
	global $wpdb;

	$table_name = $wpdb->prefix . 'eskim_views_counter_countries';
	$wpdb->insert($table_name, [ 'name' => $name, 'code' => $code ]);

	return $wpdb->insert_id ?: 0;
}

/**
 * Dodaje nowego referera do bazy danych.
 */
function eskim_views_counter_add_referer(string $url): int {
	global $wpdb;

	$table_name = $wpdb->prefix . 'eskim_views_counter_referers';
	$wpdb->insert($table_name, [ 'url' => $url ]);

	return $wpdb->insert_id ?: 0;
}

/**
 * Pobiera ID kraju na podstawie adresu IP.
 */
function eskim_views_counter_get_country_id_by_ip(string $ip_raw): ?int {
	global $wpdb;
	$ip = ip2long($ip_raw);
	if ($ip === false) return null;

	$table_visitors = $wpdb->prefix . 'eskim_views_counter_visitors';
	$table_countries = $wpdb->prefix . 'eskim_views_counter_countries';

	return $wpdb->get_var($wpdb->prepare("SELECT c.id FROM $table_visitors v JOIN $table_countries c ON v.country = c.id WHERE v.ip = %d", $ip));
}

/**
 * Pobiera ID referera (jeśli istnieje).
 */
function eskim_views_counter_get_referer_id_by_url(string $url): ?int {
	global $wpdb;
	$table_name = $wpdb->prefix . 'eskim_views_counter_referers';
	return $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE url = %s", $url));
}

/**
 * Pobiera informacje o kraju na podstawie zewnętrznego API (ip-api.com).
 */
function eskim_views_counter_get_country_from_api(string $ip_raw): ?array {
	$response = wp_remote_get("http://ip-api.com/json/$ip_raw");
	if (is_wp_error($response)) return null;

	$data = json_decode(wp_remote_retrieve_body($response), true);
	if (!is_array($data) || !isset($data['country'], $data['countryCode'])) return null;

	return [ 'name' => $data['country'], 'code' => $data['countryCode'] ];
}

/**
 * Główna funkcja zliczająca wizyty.
 */
function eskim_views_counter_track_visit(): void {
	if (is_admin()) return;

	$post_id = get_the_ID();
	if (!$post_id) return;

	$ip_raw = $_SERVER['REMOTE_ADDR'] ?? '';
	if (!$ip_raw) return;

	$country_id = eskim_views_counter_get_country_id_by_ip($ip_raw);
	if (!$country_id) {
		$country = eskim_views_counter_get_country_from_api($ip_raw);
		if ($country) {
			$country_id = eskim_views_counter_add_country($country['name'], $country['code']);
		}
	}

	$referer_id = null;
	$referer_raw = $_SERVER['HTTP_REFERER'] ?? '';
	if (!empty($referer_raw)) {
		$referer_id = eskim_views_counter_get_referer_id_by_url($referer_raw) ?? eskim_views_counter_add_referer($referer_raw);
	}

	eskim_views_counter_add_visitor($ip_raw, $post_id, $country_id, $referer_id);
}

/**
 * Pobiera liczbę unikalnych wizyt.
 */
function eskim_views_counter_get_unique_visitors_count(?string $dateFrom = null, ?string $dateTo = null): int {
	global $wpdb;
	$table = $wpdb->prefix . 'eskim_views_counter_visitors';

	$dateFrom = $dateFrom ?: date('Y-m-d', 0);
	$dateTo = $dateTo ?: date('Y-m-d', time() + 86400);

	return (int) $wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(DISTINCT ip) FROM $table WHERE created BETWEEN %s AND %s",
		$dateFrom, $dateTo
	));
}

/**
 * Zwraca liczbę unikalnych wizyt według kraju.
 */
function eskim_views_counter_get_unique_visitors_by_country(?string $dateFrom = null, ?string $dateTo = null): array {
	global $wpdb;
	$table_visitors = $wpdb->prefix . 'eskim_views_counter_visitors';
	$table_countries = $wpdb->prefix . 'eskim_views_counter_countries';

	$dateFrom = $dateFrom ?: date('Y-m-d', 0);
	$dateTo = $dateTo ?: date('Y-m-d', time() + 86400);

	return $wpdb->get_results($wpdb->prepare(
		"SELECT c.name, COUNT(DISTINCT v.ip) as count
		 FROM $table_visitors v
		 JOIN $table_countries c ON v.country = c.id
		 WHERE v.created BETWEEN %s AND %s
		 GROUP BY c.id",
		$dateFrom, $dateTo
	));
}

/**
 * Zwraca liczbę unikalnych wizyt według strony odsyłającej.
 */
function eskim_views_counter_get_unique_visitors_by_referer(?string $dateFrom = null, ?string $dateTo = null): array {
	global $wpdb;
	$table_visitors = $wpdb->prefix . 'eskim_views_counter_visitors';
	$table_referers = $wpdb->prefix . 'eskim_views_counter_referers';

	$dateFrom = $dateFrom ?: date('Y-m-d', 0);
	$dateTo = $dateTo ?: date('Y-m-d', time() + 86400);

	return $wpdb->get_results($wpdb->prepare(
		"SELECT r.url, COUNT(DISTINCT v.ip) as count
		 FROM $table_visitors v
		 JOIN $table_referers r ON v.referer = r.id
		 WHERE v.created BETWEEN %s AND %s
		 GROUP BY r.id",
		$dateFrom, $dateTo
	));
}

// Automatyczne śledzenie wizyty (jeśli użytkownik nie jest administratorem).
add_action('wp_footer', function () {
	if (!current_user_can('edit_others_posts')) {
		eskim_views_counter_track_visit();
	}
});

// Dodanie menu do Kokpitu WP
add_action('admin_menu', function () {
	add_menu_page(
		__('Licznik odwiedzin', 'eskim_views_counter'),
		__('Licznik odwiedzin', 'eskim_views_counter'),
		'manage_options',
		'eskim-views-counter',
		'eskim_views_counter_admin_page',
		'dashicons-chart-bar',
		20
	);
});

// Dashboard – widok w menu administratora
function eskim_views_counter_admin_page() {
	echo '<div class="wrap">';
	echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
	echo '<p><strong>Unikalne wizyty:</strong> ' . esc_html(eskim_views_counter_get_unique_visitors_count()) . '</p>';

	echo '<h2>Wizyty według kraju</h2>';
	echo '<ul>';
	foreach (eskim_views_counter_get_unique_visitors_by_country() as $row) {
		echo '<li>' . esc_html($row->name) . ': ' . esc_html($row->count) . '</li>';
	}
	echo '</ul>';

	echo '<h2>Wizyty według strony odsyłającej</h2>';
	echo '<ul>';
	foreach (eskim_views_counter_get_unique_visitors_by_referer() as $row) {
		echo '<li>' . esc_html($row->url) . ': ' . esc_html($row->count) . '</li>';
	}
	echo '</ul>';
	echo '</div>';
}
