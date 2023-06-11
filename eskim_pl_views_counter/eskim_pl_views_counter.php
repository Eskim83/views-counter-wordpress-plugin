<?php

/**
 * Plugin Name:       Prosty licznik odwiedzin od eskim.pl
 * Plugin URI:        https://eskim.pl/zapisywanie-w-bazie-danych-wordpressa/
 * Description:       Prosty licznik odwiedzin na podstawie kursu https://eskim.pl/zapisywanie-w-bazie-danych-wordpressa/
 * Version:           1.0
 * Requires at least: 5.2
 * Requires PHP:      5.6
 * Author:            Maciej Włodarczak
 * Author URI:        https://eskim.pl
 * License:           GPL v3 or later
 * Text Domain:       eskim_pl_views_counter
 * Domain Path:       /languages
 */
 
/*
Poniższy kod jest częścią artykułu https://eskim.pl/zapisywanie-w-bazie-danych-wordpressa/
*/ 

if ( !function_exists( 'add_action' ) ) {

	echo 'Zapraszam do artykułu <a href="https://eskim.pl/zapisywanie-w-bazie-danych-wordpressa/">Zapisywanie w bazie danych WordPressa</a>';
	exit;
}

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

$path = dirname( __FILE__ );
 
register_activation_hook(__FILE__, 'eskim_pl_views_counter_activation');


/** TWORZENIE TABEL **/


/**** Tworzenie tabel w WordPress ****/

if ( !function_exists('eskim_pl_views_counter_activation') ) :
function eskim_pl_views_counter_activation() {

	global $wpdb;

	$table_name_countries = $wpdb->prefix . 'eskim_pl_views_counter_countries';

	$sql = "CREATE TABLE IF NOT EXISTS $table_name_countries (
			id INT NOT NULL AUTO_INCREMENT,
			name VARCHAR (50) UNIQUE,
			code VARCHAR (10),
			PRIMARY KEY (id)
		);";
	
	dbDelta( $sql );


	$table_name_referers = $wpdb->prefix . 'eskim_pl_views_counter_referers';

	$sql = "CREATE TABLE IF NOT EXISTS $table_name_referers (
			id INT NOT NULL AUTO_INCREMENT,
			url VARCHAR (255) UNIQUE,
			PRIMARY KEY (id)
		);";

	dbDelta( $sql );

	$table_name_visitors = $wpdb->prefix . 'eskim_pl_views_counter_visitors';

	$sql = "CREATE TABLE IF NOT EXISTS $table_name_visitors (
			ip BIGINT,
			post INT NULL,
			country INT NULL,
			referer INT NULL,
			created TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
			index (ip),
			index (post),
			FOREIGN KEY (referer) REFERENCES $table_name_referers (id),
			FOREIGN KEY (country) REFERENCES $table_name_countries (id)
		);";

	dbDelta( $sql );
}
endif;
 

/** POBIERANIE I ZAPISYWANIE DANYCH **/


/**** Dodawanie rekordów ****/
 
if ( !function_exists('eskim_pl_views_counter_visitor_add') ) :
function eskim_pl_views_counter_visitor_add ($ip, $post = null, $country = null, $referer = null) {

	global $wpdb;

	$table_name = $wpdb->prefix . 'eskim_pl_views_counter_visitors';

	$result = $wpdb->insert( $table_name,
	[
		'ip' => $ip,
		'post' => $post,
		'country' => $country,
		'referer' => $referer
	]);
	
	return $wpdb->last_error;
}
endif;


if ( !function_exists('eskim_pl_views_counter_country_add') ) :
function eskim_pl_views_counter_country_add ($name, $code) {

	global $wpdb;

	$table_name = $wpdb->prefix . 'eskim_pl_views_counter_countries';

	$wpdb->insert( $table_name,
	[
		'name' => $name,
		'code' => $code
	]);
	
	return $wpdb->insert_id;
}
endif;


if ( !function_exists('eskim_pl_views_counter_referer_add') ) :
function eskim_pl_views_counter_referer_add ($url) {

	global $wpdb;

	$table_name = $wpdb->prefix . 'eskim_pl_views_counter_referers';

	$wpdb->insert( $table_name,
	[
		'url' => $url
	]);
	
	return $wpdb->insert_id;
}
endif;


/**** Pobieranie id kraju oraz strony odsyłającej ****/

if ( !function_exists('eskim_pl_country_get_by_ip') ) :
function eskim_pl_country_get_by_ip ($ip) {	

	global $wpdb;

	$table_name_vistors = $wpdb->prefix . 'eskim_pl_views_counter_visitors';
	$table_name_countries = $wpdb->prefix . 'eskim_pl_views_counter_countries';

	$query = $wpdb->prepare ("
		SELECT $table_name_countries.id
		FROM $table_name_vistors 
		JOIN $table_name_countries ON $table_name_vistors.country = $table_name_countries.id
		WHERE ip = %d", 
		$ip
	  );

	return $wpdb->get_var ($query);  
}
endif;

if ( !function_exists('eskim_pl_country_get_referer_by_url') ) :
function eskim_pl_country_get_referer_by_url ($url) {	

	global $wpdb;

	$table_name = $wpdb->prefix . 'eskim_pl_views_counter_referers';

	$query = $wpdb->prepare ("
		SELECT id
		FROM $table_name
		WHERE url = %s", 
		$url
	  );

	return $wpdb->get_var ($query);  
	
}
endif;


/**** Pobieranie danych o kraju z API ****/

if ( !function_exists('eskim_pl_country_api') ) :
function eskim_pl_country_api ($ip) {	

	global $wpdb;

	$data = file_get_contents ( "http://ip-api.com/json/$ip" );
	return json_decode ($data, true);
}
endif;


/**** Pobieranie informacji o użytkowniku ****/

if ( !function_exists('eskim_pl_count_visitors') ) :
function eskim_pl_count_visitors () {

	$postId = get_the_ID();
	if ($postId == 0) return false;

	$ip = ip2long ( $_SERVER['REMOTE_ADDR'] );

	$countryId = eskim_pl_country_get_by_ip ($ip);
	if ($countryId === null) {
		
		$country = eskim_pl_country_api ( $_SERVER['REMOTE_ADDR'] );
		$countryId = eskim_pl_views_counter_country_add ($country['country'], $country['countryCode']);
	}

	$refererId = null;
	if (isset ($_SERVER['HTTP_REFERER']) && !empty ($_SERVER['HTTP_REFERER']) ) {
		$refererId = eskim_pl_country_get_referer_by_url ($_SERVER['HTTP_REFERER']);
		if ($refererId === null) {
			
			$refererId = eskim_pl_views_counter_referer_add ($_SERVER['HTTP_REFERER']);
		}
	}

	return eskim_pl_views_counter_visitor_add ($ip, $postId, $countryId, $refererId);
}
endif;


/**** Zmiana adresu IP w bazie ****/

if ( !function_exists('eskim_pl_views_counter_visitor_update_ip') ) :
function eskim_pl_views_counter_visitor_update_ip ($oldip, $ip) {

  if ($oldip == $ip) return false;

  global $wpdb;

  $table_name = $wpdb->prefix . 'eskim_pl_views_counter_visitors';

	return $wpdb->update( $table_name,
		[ 'ip' => $oldip ],
		[' ip' => $ip ],
		[' %d '],
		[' %d ']
	);
}
endif;


/**** Utworzenie i odczyt ciasteczka ****/

if ( !function_exists('eskim_pl_views_counter_set_cookie') ) :
function eskim_pl_views_counter_set_cookie ($ip) {

	setcookie ( 'eskim_pl_visit', $ip, time() + (86400 * 400) );
}
endif;

if ( !function_exists('eskim_pl_views_counter_get_cookie') ) :
function eskim_pl_views_counter_get_cookie () {

	if ( !isset($_COOKIE['eskim_pl_visit']) ) return null;
	return $_COOKIE['eskim_pl_visit'];
}
endif;


/** ANALIZA DANYCH **/
 
if ( !function_exists('eskim_pl_views_counter_get_unique_visitors_count') ) :
function eskim_pl_views_counter_get_unique_visitors_count ($dateFrom = null, $dateTo = null) {
	
	global $wpdb;
	
	$table_name = $wpdb->prefix . 'eskim_pl_views_counter_visitors';
	
	if ($dateFrom === null) $dateFrom = date('Y-m-d',0);
	if ($dateTo === null) $dateTo = date('Y-m-d',time()+86400);
	
	$query = $wpdb->prepare ("
	
			SELECT COUNT( DISTINCT ip) AS count
			FROM $table_name 
			WHERE created BETWEEN %s AND %s",
			$dateFrom,
			$dateTo
		);	  
			  
	return $wpdb->get_var($query);
	
}
endif;

if ( !function_exists('eskim_pl_views_counter_get_unique_visitors_by_country') ) :
function eskim_pl_views_counter_get_unique_visitors_by_country ($dateFrom = null, $dateTo = null) {
	
	global $wpdb;
	
	if ($dateFrom === null) $dateFrom = date('Y-m-d',0);
	if ($dateTo === null) $dateTo = date('Y-m-d',time()+86400);
	
	$table_name_vistors = $wpdb->prefix . 'eskim_pl_views_counter_visitors';
	$table_name_countries = $wpdb->prefix . 'eskim_pl_views_counter_countries';
	
	$query = $wpdb->prepare ("
			SELECT name, COUNT( DISTINCT ip) AS count 
			FROM $table_name_vistors
			JOIN $table_name_countries
			ON $table_name_vistors.country = $table_name_countries.id
			WHERE created BETWEEN %s AND %s
			GROUP BY id",
			$dateFrom,
			$dateTo
		);	    
	return $wpdb->get_results($query);
	
}
endif;

if ( !function_exists('eskim_pl_views_counter_get_unique_visitors_by_referers') ) :
function eskim_pl_views_counter_get_unique_visitors_by_referers ($dateFrom = null, $dateTo = null) {
	
	global $wpdb;
	
	if ($dateFrom == null) $dateFrom = date('Y-m-d',0);
	if ($dateTo == null) $dateTo = date('Y-m-d',time()+86400);
	
	$table_name_vistors = $wpdb->prefix . 'eskim_pl_views_counter_visitors';
	$table_name_referers = $wpdb->prefix . 'eskim_pl_views_counter_referers';
	
	$query = $wpdb->prepare ("
			SELECT url, COUNT( DISTINCT ip) AS count
			FROM $table_name_vistors
			JOIN $table_name_referers
			ON $table_name_vistors.referer = $table_name_referers.id
			WHERE created BETWEEN %s AND %s
			GROUP BY id",
			$dateFrom,
			$dateTo
		);	  
			  
	return $wpdb->get_results($query);
	
}
endif;
 
/** MENU **/

if ( !function_exists('eskim_pl_views_counter_menu_main') ) :
function eskim_pl_views_counter_menu_main () {

    ?>
    <div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html(get_admin_page_title()); ?></h1>
    <hr class="wp-header-end">

	<h1>Prosty licznik odwiedzin</h1>
	<p>Autor: Maciej Włodarczak (<a href="https://eskim.pl">https://eskim.pl</a>)</p>

<p>Wersja: 1.0</p>

<p>Na podstawie artykułu: <a href="https://eskim.pl/zapisywanie-w-bazie-danych-wordpressa/">Zapisywanie w bazie danych WordPressa</a></p>

<p>***</p>
<p>Jeżeli przydał Ci się skrypt lub masz jakiekolwiek uwagi wejdź na stronę i zostaw komentarz (nie trzeba się rejestrować). Będzie mi bardzo miło.</p>
<p>Będzie mi jeszcze milej, jeżeli zostawisz link do powyższej strony lub artykułu.</p>
<p>***</p>

<p>Skrypt zlicza odwiedziny na stronie:</p>
<ul>
<li>-> tworzy tabele, zapisuje i odczytuje dane do bazy danych WordPressa</li>
<li>-> dodaje menu</li>
<li>-> wyświetla informacje o unikatowych odwiedzinach</li>
<li>-> sprawdza kraj odwiedzającego</li>
<li>-> sprawdza pochodzenie kupującego (z jakiej strony przyszedł)</li>
</ul>

<h2>-- INSTALACJA --</h2>

<p>Należy przenieść wtyczkę do katalogu z pluginami w WordPress i uruchomić z poziomu pluginów w WordPress.</p>


<h2>-- DEINSTALACJA --</h2>

<p>Należy usunąć wtyczkę z poziomu menu wtyczki - przycisk "Usuń tabele i wyłącz wtyczkę". To spowodouje usunięcie tabel w bazie danych.</p>

<h2>-- ODPOWIEDZIALNOŚĆ --</h2>

<p>Autor nie ponosi żadnej odpowiedzialności z tytułu błędów w skrypcie, albo niewłaściwego jego wykorzystania.</p>

<p>Korzystasz na własną odpowiedzialność.
	
    </div>
    <?php
	
}
endif;

if ( !function_exists('eskim_pl_views_counter_menu_statistics') ) :
function eskim_pl_views_counter_menu_statistics () {

    ?>
    <div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html(get_admin_page_title()); ?></h1>
    <hr class="wp-header-end">
    <p>
	<?php 
	$admin_url = admin_url('admin-post.php');

	echo '<form action="' . $admin_url . '" method="post">';
	echo '<input type="hidden" name="action" value="eskim_pl_views_counter_delete" />';
	wp_nonce_field('eskim_pl_views_counter_delete_nonce');
	submit_button ('Usuń tabele i wyłącz wtyczkę');
	echo '</form>';
	
	echo '<h2> unikatowe wizyty: '. eskim_pl_views_counter_get_unique_visitors_count() . '</h2>';
	echo '<h2> unikatowe wizyty po kraju</h2>';
	foreach (eskim_pl_views_counter_get_unique_visitors_by_country() as $country) {
		echo $country->name.': '.$country->count . '<br />';
	}
	echo '<h2> unikatowe wizyty po stronie odsyłającej</h2>';
	foreach (eskim_pl_views_counter_get_unique_visitors_by_referers() as $referer) {
		echo $referer->url.': '.$referer->count . '<br />';
	}
	
	?>
	
	
	</p>
    </div>
    <?php
	
}
endif;

add_action('admin_menu', function () {

    add_menu_page(
		__('Views counter','views-counter'), // tytuł strony
		__('Views counter','views-counter'), // tytuł w menu
		'manage_options',  // widoczne tylko osobom, które mogą zmieniać ustawienia
		'menu-views-counter', // identyfikator
		'eskim_pl_views_counter_menu_main', // funkcja, którą zostanie wywołana po kliknięciu
		'',               // link do ikony
		20                // pozycja w menu
    );

	add_submenu_page(
		'menu-views-counter',          // menu do którego się podpinasz
		__('Chars counter','views-counter').' - '.__('statistics','views-counter'), // tytuł strony
		__('Counter table','views-counter'),  // tytuł w menu
		'manage_options',               // widoczne tylko osobom, które mogą zmieniać ustawienia
		'submenu-views-counter-statistics', // identyfikator
		'eskim_pl_views_counter_menu_statistics', // funkcja, którą zostanie wywołana po kliknięciu
	);

});

	
add_action('wp_footer', function () {
		
	if ( !current_user_can('edit_others_posts') )
	eskim_pl_count_visitors ();
});


if ( !function_exists('eskim_pl_views_counter_delete') ) :
function eskim_pl_views_counter_delete () {

	check_admin_referer( 'eskim_pl_views_counter_delete_nonce' );

	global $wpdb;

	$table_name = $wpdb->prefix . 'eskim_pl_views_counter_visitors';
	$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

	$table_name = $wpdb->prefix . 'eskim_pl_views_counter_countries';
	$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

	$table_name = $wpdb->prefix . 'eskim_pl_views_counter_referers';
	$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

	deactivate_plugins('eskim_pl_views_counter/eskim_pl_views_counter.php');
	
	wp_redirect(admin_url('plugins.php'));
}
endif;


add_action('admin_post_eskim_pl_views_counter_delete', 'eskim_pl_views_counter_delete');

 ?>