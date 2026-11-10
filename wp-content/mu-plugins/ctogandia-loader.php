<?php
/**
 * Plugin Name: CTO Gandia – cargador de mu-plugins
 * Description: Carga los mu-plugins del club desde wp-content/mu-plugins/ctogandia/. Se despliega desde el repositorio web-club.
 * Author:      Club de Tiro Olímpico Gandia
 * License:     GPL-2.0-or-later
 *
 * WordPress solo carga automáticamente los .php sueltos en mu-plugins/, no los de subcarpetas.
 * Este cargador incluye todo lo que haya en mu-plugins/ctogandia/, por orden alfabético.
 * Así el despliegue puede sustituir esa carpeta entera de una vez, sin tocar mu-plugins
 * que hayan puesto otros plugins.
 */

defined( 'ABSPATH' ) || exit;

$ctogandia_mu_files = glob( __DIR__ . '/ctogandia/*.php' );

if ( $ctogandia_mu_files ) {
	sort( $ctogandia_mu_files );
	foreach ( $ctogandia_mu_files as $ctogandia_mu_file ) {
		require_once $ctogandia_mu_file;
	}
}

unset( $ctogandia_mu_files, $ctogandia_mu_file );
