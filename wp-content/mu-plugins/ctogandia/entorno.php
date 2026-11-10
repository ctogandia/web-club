<?php
/**
 * Ajustes según el entorno, a partir de WP_ENVIRONMENT_TYPE en wp-config.php.
 *
 * En todos los entornos:
 *   - XML-RPC desactivado.
 *
 * Fuera de producción (pruebas, local):
 *   - No se envía ningún correo; solo se anota en el log de PHP.
 *   - noindex en todas las páginas, robots.txt y cabeceras.
 *   - Aviso visible en la barra de administración y en el panel.
 *
 * Como está en un fichero y no en la base de datos, se mantiene aunque
 * se copie la base de datos de producción a pruebas.
 *
 * @package ctogandia
 */

defined( 'ABSPATH' ) || exit;

/*
 * Todos los entornos
 * ------------------------------------------------------------------ */

add_filter( 'xmlrpc_enabled', '__return_false' );

add_filter(
	'wp_headers',
	static function ( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
);

/*
 * Solo fuera de producción
 * ------------------------------------------------------------------ */

if ( 'production' === wp_get_environment_type() ) {
	return;
}

/**
 * Texto del aviso según el entorno.
 *
 * @return string
 */
function ctogandia_entorno_etiqueta() {
	return 'staging' === wp_get_environment_type() ? 'WEB DE PRUEBAS' : 'ENTORNO LOCAL';
}

/*
 * 1. Correo bloqueado.
 *
 * wp_mail() es una función "pluggable": se usa la primera definición que se carga.
 * Los mu-plugins se cargan antes que los plugins, así que esta versión tiene prioridad
 * también sobre FluentSMTP y otros plugins de correo.
 */
if ( ! function_exists( 'wp_mail' ) ) {
	/**
	 * Sustituye a wp_mail(): no envía nada y lo anota en el log.
	 *
	 * @param string|string[] $to          Destinatarios.
	 * @param string          $subject     Asunto.
	 * @param string          $message     Cuerpo (no se usa).
	 * @param string|string[] $headers     Cabeceras (no se usan).
	 * @param string|string[] $attachments Adjuntos (no se usan).
	 * @return bool Siempre true, para que los formularios funcionen igual que en producción.
	 */
	function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$to = is_array( $to ) ? implode( ', ', $to ) : $to;
		error_log( sprintf( '[ctogandia] Correo NO enviado (%s) → %s | %s', wp_get_environment_type(), $to, $subject ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		return true;
	}
}

/*
 * 2. Sin indexación.
 */
add_filter( 'pre_option_blog_public', '__return_zero' );
add_filter( 'wp_robots', 'wp_robots_no_robots' );
add_action(
	'send_headers',
	static function () {
		header( 'X-Robots-Tag: noindex, nofollow', true );
	}
);

/*
 * 3. Aviso visible.
 */
add_action(
	'admin_bar_menu',
	static function ( $wp_admin_bar ) {
		$wp_admin_bar->add_node(
			array(
				'id'    => 'ctogandia-entorno',
				'title' => '⚠ ' . ctogandia_entorno_etiqueta(),
				'meta'  => array( 'title' => 'Los cambios hechos aquí no se publican en ctogandia.com' ),
			)
		);
	},
	1
);

/**
 * Estilo del aviso en la barra de administración (portada y panel).
 */
function ctogandia_entorno_estilos() {
	if ( ! is_admin_bar_showing() ) {
		return;
	}
	echo '<style>#wpadminbar{background:#b32d2e!important}#wp-admin-bar-ctogandia-entorno>.ab-item{font-weight:700!important;color:#fff!important}</style>';
}
add_action( 'wp_head', 'ctogandia_entorno_estilos' );
add_action( 'admin_head', 'ctogandia_entorno_estilos' );

add_action(
	'admin_notices',
	static function () {
		printf(
			'<div class="notice notice-error"><p><strong>%s</strong> · Los cambios hechos aquí <strong>no</strong> se publican en ctogandia.com y los correos están desactivados.</p></div>',
			esc_html( ctogandia_entorno_etiqueta() )
		);
	}
);
