<?php
class SystemComponent {

	Var $settings;
	Function getSettings(){
	$settings['siteDir']='<fake-path>';
	$settings['dbhost']='ip-of-host';
	$settings['dbuser']='root';
	$settings['dbpassword']='root_password';
	$settings['dbname']='db-name';
	$settings['hash']='sha1';
	return $settings;
	}
}
define('APP_PATH','<fake>/rsaportal/cpt/');
define('PPD_PATH', APP_PATH.'xml/ppd_data.xml');
define('PKD_PATH', APP_PATH.'xml/pkd.xml');
define('PPDT_PATH', APP_PATH.'xml/pkd_temp.xml');
define('OBJETIVES_PATH', APP_PATH.'xml/objetives.xml');
define('FERIADOS_PATH', APP_PATH.'xml/feriados.xml');
define('FILES_ADDR', 'files/');


?>
