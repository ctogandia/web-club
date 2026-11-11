<?php
/**
 * Funciones del tema hijo de Kadence para el Club de Tiro Olímpico Gandia.
 *
 * Mantén aquí solo lo que sea presentación del tema. La funcionalidad de
 * competiciones vive en el plugin tiro-competiciones, y los ajustes de
 * entorno en los mu-plugins de este mismo repositorio.
 *
 * @package kadence-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Carga la hoja de estilos del tema hijo después de la del padre (Kadence).
 *
 * Kadence encola su propio CSS automáticamente; aquí solo añadimos el del hijo,
 * versionado con la versión del tema para romper la caché en cada release.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style(
			'kadence-child',
			get_stylesheet_uri(),
			array(),
			wp_get_theme()->get( 'Version' )
		);
	}
);
