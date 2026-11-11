<?php
/**
 * Fuerza 200 en la página "próximamente" de LightStart.
 *
 * LightStart (WP Maintenance Mode) devuelve 503 también en modo "Coming soon",
 * y eso le dice a Google "vuelve más tarde". Mientras la web está en construcción
 * queremos que la página provisional sea indexable (200). El único punto de cambio
 * es el filtro wpmm_status_code; ningún ajuste del panel lo toca.
 *
 * Es inocuo donde LightStart no esté activo (pruebas, local): el filtro no se dispara.
 *
 * TEMPORAL: borra este fichero el día del lanzamiento, junto con LightStart.
 *
 * @package ctogandia
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'wpmm_status_code',
	static function () {
		return 200;
	}
);
