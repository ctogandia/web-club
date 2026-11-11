<?php
/**
 * Ajustes según el entorno, a partir de WP_ENVIRONMENT_TYPE en wp-config.php.
 *
 * En todos los entornos:
 *   - XML-RPC desactivado.
 *   - Aviso de entorno en la barra de administración (panel y portada), con color
 *     propio para que se vea de un vistazo dónde estás:
 *       producción = rojo, pruebas = ámbar, local = azul.
 *
 * Fuera de producción (pruebas, local):
 *   - No se envía ningún correo; solo se anota en el log de PHP.
 *   - noindex en todas las páginas, robots.txt y cabeceras.
 *
 * El aviso solo cambia colores y textos: NO altera el comportamiento del sitio.
 * En producción los correos y la indexación siguen activos.
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

/**
 * Datos del aviso según el entorno: etiqueta, color de fondo y nota explicativa.
 *
 * @return array{etiqueta:string,color:string,nota:string}
 */
function ctogandia_entorno_aviso() {
	switch ( wp_get_environment_type() ) {
		case 'production':
			return array(
				'etiqueta' => 'PRODUCCIÓN · EN VIVO',
				'color'    => '#b32d2e',
				'nota'     => 'Estás en la web en vivo (ctogandia.com): los cambios son reales y los correos se envían.',
			);
		case 'staging':
			return array(
				'etiqueta' => 'WEB DE PRUEBAS',
				'color'    => '#b45309',
				'nota'     => 'Los cambios hechos aquí no se publican en ctogandia.com y los correos están desactivados.',
			);
		default:
			return array(
				'etiqueta' => 'ENTORNO LOCAL',
				'color'    => '#2271b1',
				'nota'     => 'Entorno local de desarrollo. No se envían correos.',
			);
	}
}

/*
 * Aviso en la barra de administración.
 */
add_action(
	'admin_bar_menu',
	static function ( $wp_admin_bar ) {
		$aviso = ctogandia_entorno_aviso();
		$wp_admin_bar->add_node(
			array(
				'id'    => 'ctogandia-entorno',
				'title' => '⚠ ' . esc_html( $aviso['etiqueta'] ),
				'meta'  => array( 'title' => $aviso['nota'] ),
			)
		);
	},
	1
);

/**
 * Color del aviso en la barra de administración (portada y panel).
 */
function ctogandia_entorno_estilos() {
	if ( ! is_admin_bar_showing() ) {
		return;
	}
	$aviso = ctogandia_entorno_aviso();
	printf(
		'<style>#wpadminbar{background:%1$s!important}#wp-admin-bar-ctogandia-entorno>.ab-item{font-weight:700!important;color:#fff!important}</style>',
		esc_attr( $aviso['color'] )
	);
}
add_action( 'wp_head', 'ctogandia_entorno_estilos' );
add_action( 'admin_head', 'ctogandia_entorno_estilos' );

/*
 * Aviso en el panel. Rojo (error) en producción, ámbar (warning) fuera.
 */
add_action(
	'admin_notices',
	static function () {
		$aviso = ctogandia_entorno_aviso();
		$clase = 'production' === wp_get_environment_type() ? 'notice-error' : 'notice-warning';
		printf(
			'<div class="notice %2$s"><p><strong>%1$s</strong> · %3$s</p></div>',
			esc_html( $aviso['etiqueta'] ),
			esc_attr( $clase ),
			esc_html( $aviso['nota'] )
		);
	}
);

/*
 * Solo fuera de producción
 * ------------------------------------------------------------------ */

if ( 'production' === wp_get_environment_type() ) {
	return;
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
