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
 *   - No se envían correos (se anotan en el log). Se puede permitir el envío real
 *     temporalmente (1 hora) con un botón en la barra de administración, para probar SMTP.
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
				'color'    => '#c2691c',
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
 * 1. Correo bloqueado, con permiso temporal desde la barra de administración.
 *
 * Cortamos el envío con el filtro pre_wp_mail en lugar de redefinir wp_mail(). Así
 * wp_mail() sigue siendo la de WordPress y plugins como FluentSMTP (que necesitan la
 * wp_mail original) se pueden configurar sin conflicto; el correo simplemente no sale
 * y se anota en el log.
 *
 * Para probar envíos reales (p. ej. FluentSMTP), hay un botón en la barra de admin que
 * permite el envío durante 1 hora y se apaga solo (se guarda en un transient, así que
 * es un clic: sin tocar ficheros ni desplegar). "Cortar" lo desactiva al instante. El
 * filtro consulta el estado en cada envío, así que el cambio es inmediato en ambos
 * sentidos. También se puede forzar desde wp-config.php con
 *     define( 'CTOGANDIA_ALLOW_MAIL', true );
 * (útil en local o CI). Cuidado: pruebas puede tener copias de datos reales de socios.
 */

/**
 * ¿Está permitido el envío de correos ahora mismo en este entorno?
 *
 * @return bool
 */
function ctogandia_correo_permitido() {
	if ( defined( 'CTOGANDIA_ALLOW_MAIL' ) && CTOGANDIA_ALLOW_MAIL ) {
		return true;
	}
	return (int) get_transient( 'ctogandia_allow_mail' ) > time();
}

add_filter(
	'pre_wp_mail',
	static function ( $short_circuit, $atts ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ctogandia_correo_permitido() ) {
			return $short_circuit; // Deja pasar: wp_mail() envía de verdad.
		}
		$to = isset( $atts['to'] ) ? $atts['to'] : '';
		$to = is_array( $to ) ? implode( ', ', $to ) : $to;
		$subject = isset( $atts['subject'] ) ? $atts['subject'] : '';
		error_log( sprintf( '[ctogandia] Correo NO enviado (%s) → %s | %s', wp_get_environment_type(), $to, $subject ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		return true; // Corta wp_mail(): no envía y devuelve true, como si hubiera salido.
	},
	10,
	2
);

/**
 * Activa (1 hora) o corta al instante el envío de correos. Lo llama el botón de la barra.
 */
function ctogandia_correo_toggle() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'No tienes permiso para esto.' );
	}
	check_admin_referer( 'ctogandia_correo_toggle' );

	if ( ctogandia_correo_permitido() ) {
		delete_transient( 'ctogandia_allow_mail' );
	} else {
		set_transient( 'ctogandia_allow_mail', time() + HOUR_IN_SECONDS, HOUR_IN_SECONDS );
	}

	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
	exit;
}
add_action( 'admin_post_ctogandia_correo_toggle', 'ctogandia_correo_toggle' );

/*
 * Botón en la barra de administración (solo para quien administre): estado + permitir/cortar.
 */
add_action(
	'admin_bar_menu',
	static function ( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$expira   = (int) get_transient( 'ctogandia_allow_mail' );
		$activo   = $expira > time();
		$restante = $activo ? (int) ceil( ( $expira - time() ) / MINUTE_IN_SECONDS ) : 0;

		$titulo = $activo
			? sprintf( '✉ Correos ON (%d min) · Cortar', $restante )
			: '✉ Correos OFF · Permitir 1h';

		$wp_admin_bar->add_node(
			array(
				'id'    => 'ctogandia-correo',
				'title' => esc_html( $titulo ),
				'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=ctogandia_correo_toggle' ), 'ctogandia_correo_toggle' ),
				'meta'  => array( 'title' => $activo ? 'Pulsa para cortar el envío de correos al instante' : 'Pulsa para permitir el envío real durante 1 hora' ),
			)
		);
	},
	90
);

/*
 * Aviso mientras el envío esté activado, para no dejarlo encendido por olvido.
 */
add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$expira = (int) get_transient( 'ctogandia_allow_mail' );
		if ( $expira <= time() ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p><strong>Envío de correos ACTIVADO</strong> en este entorno (%d min restantes). Los correos salen de verdad.</p></div>',
			(int) ceil( ( $expira - time() ) / MINUTE_IN_SECONDS )
		);
	}
);

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
