<?php
/**
 * MagIRC - Let the magirc begin!
 * Admin panel
 *
 * @author      Sebastian Vassiliou <hal9000@denorastats.org>
 * @copyright   2012 - 2015 Sebastian Vassiliou
 * @link        http://www.magirc.org/
 * @license     GNU GPL Version 3, see http://www.gnu.org/licenses/gpl-3.0-standalone.html
 * @version     1.0.2
 */

ini_set('display_errors','on');
error_reporting(E_ALL);
ini_set('default_charset','UTF-8');
date_default_timezone_set('UTC');

if (version_compare(PHP_VERSION, '5.3.0', '<') || !extension_loaded('pdo') || !in_array('mysql', PDO::getAvailableDrivers()) || !extension_loaded('gettext') || !extension_loaded('mcrypt') || get_magic_quotes_gpc()) die('ERROR: System requirements not met. Please run Setup.');
if (!file_exists('../conf/magirc.cfg.php')) die('ERROR: MagIRC is not configured. Please run Setup.');
if (!is_writable('../tmp/')) die('ERROR: Unable to write temporary files. Please run Setup.');

session_start();

include_once('../lib/magirc/version.inc.php');
if (file_exists('../vendor/autoload.php')) {
	require '../vendor/autoload.php';
} else {
	die('Please run the `composer install` or `php composer.phar install` command. See README for more information');
}
require_once('../lib/magirc/DB.class.php');
require_once('../lib/magirc/Config.class.php');
include_once('../lib/ckeditor/ckeditor.php');
require_once('lib/Admin.class.php');

$admin = new Admin();

try {
	date_default_timezone_set($admin->cfg->timezone);
	define('DEBUG', $admin->cfg->debug_mode);
	define('BASE_URL', $admin->cfg->base_url . basename(__DIR__) . '/');
	$admin->tpl->assign('cfg', $admin->cfg);
	if ($admin->cfg->db_version < DB_VERSION) die('SQL Config Table is missing or out of date!<br />Please run the <em>MagIRC Installer</em>');
	if ($admin->cfg->debug_mode < 1) {
		ini_set('display_errors','off');
		error_reporting(E_ERROR);
	} else {
		$admin->tpl->force_compile = true;
		/*if ($admin->cfg->debug_mode') > 1) {
			$admin->tpl->debugging = true;
		}*/
	}

	$admin->slim->notFound(function () use ($admin) {
		$admin->tpl->assign('err_code', 404);
		$admin->tpl->display('error.tpl');
	});

	// Handle POST login/logout
	$admin->slim->post('/login', function() use ($admin) {
		if ($admin->login($_POST['username'], $_POST['password'])) {
			$admin->slim->redirect(BASE_URL.'index.php/overview');
		} else {
			$admin->slim->redirect(BASE_URL);
		}
	});
	$admin->slim->post('/ajaxlogin', function() use ($admin) {
		$admin->slim->contentType('application/json');
		echo json_encode($admin->login($_POST['username'], $_POST['password']));
	});
	$admin->slim->post('/logout', function() use ($admin) {
		// Unset session variables
		if (isset($_SESSION["username"])) unset($_SESSION["username"]);
		// Delete the session cookie
		if (ini_get("session.use_cookies")) {
			$params = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000,
				$params["path"], $params["domain"],
				$params["secure"], $params["httponly"]
			);
		}
		// Destroy the session
		session_destroy();
		// Redirect to login screen
		$admin->slim->redirect(BASE_URL);
	});

	$admin->slim->get('/(overview)', function() use ($admin) {
		if (!$admin->sessionStatus()) { $admin->tpl->display('login.tpl'); exit; }
		$admin->tpl->assign('section', 'overview');
		$admin->tpl->assign('setup', file_exists('../setup/'));
		$admin->tpl->assign('version', array('php' => phpversion(), 'slim' => '2.5.0'));
		$admin->tpl->display('overview.tpl');
	});
	$admin->slim->get('/configuration/welcome', function() use ($admin) {
		if (!$admin->sessionStatus()) { $admin->slim->halt(403, "HTTP 403 Access Denied"); }
		$admin->tpl->assign('editor', $admin->ckeditor->editor('content_welcome', $admin->getContent('welcome')));
		$admin->tpl->display('configuration_welcome.tpl');
	});
	$admin->slim->get('/configuration/interface', function() use ($admin) {
		if (!$admin->sessionStatus()) { $admin->slim->halt(403, "HTTP 403 Access Denied"); }
		$locales = array();
		foreach (glob("../locale/*") as $filename) {
			if (is_dir($filename)) $locales[] = basename($filename);
		}
		$admin->tpl->assign('locales', $locales);
		$themes = array();
		foreach (glob("../theme/*") as $filename) {
			$themes[] = basename($filename);
		}
		$admin->tpl->assign('themes', $themes);
		$admin->tpl->assign('timezones', DateTimeZone::listIdentifiers());
		$admin->tpl->display('configuration_interface.tpl');
	});
	$admin->slim->get('/configuration/network', function() use ($admin) {
		if (!$admin->sessionStatus()) { $admin->slim->halt(403, "HTTP 403 Access Denied"); }
		$ircds = array();
		foreach (glob("../lib/magirc/ircds/*") as $filename) {
			if ($filename != "../lib/magirc/ircds/index.php") {
				$ircdlist = explode("/", $filename);
				$ircdlist = explode(".", $ircdlist[4]);
				$ircds[] = $ircdlist[0];
			}
		}
		$admin->tpl->assign('ircds', $ircds);
		$admin->tpl->display('configuration_network.tpl');
	});
	$admin->slim->get('/configuration/service/:service', function($service) use ($admin) {
		if (!$admin->sessionStatus()) { $admin->slim->halt(403, "HTTP 403 Access Denied"); }
		$db_config_file = "../conf/{$service}.cfg.php";
		$db = array();
		if (file_exists($db_config_file)) {
			include($db_config_file);
		} else {
			@touch($db_config_file);
		}
		if (!$db) {
			$db = array('username' => $service, 'password' => $service, 'database' => $service, 'prefix' => ($service == "anope") ? "anope_" : null, 'hostname' => 'localhost');
		}
		$admin->tpl->assign('db_config_file', $db_config_file);
		$admin->tpl->assign('writable', is_writable($db_config_file));
		$admin->tpl->assign('db', $db);
		$admin->tpl->assign('service', $service);
		$admin->tpl->display('configuration_service.tpl');
	});
	$admin->slim->post('/content', function() use ($admin) {
		if (!$admin->sessionStatus()) { $admin->slim->halt(403, "HTTP 403 Access Denied"); }
		$admin->slim->contentType('application/json');
		foreach ($_POST as $key => $val) {
			$admin->saveContent($key, $val);
		}
		echo json_encode(true);
	});
	$admin->slim->post('/configuration', function() use ($admin) {
		if (!$admin->sessionStatus()) { $admin->slim->halt(403, "HTTP 403 Access Denied"); }
		$admin->slim->contentType('application/json');
		foreach ($_POST as $key => $val) {
			$admin->saveConfig($key, $val);
		}
		echo json_encode(true);
	});
	$admin->slim->post('/configuration/:service/database', function($service) use ($admin) {
		if (!$admin->sessionStatus()) { $admin->slim->halt(403, "HTTP 403 Access Denied"); }
		$admin->slim->contentType('application/json');
		$db_config_file = "../conf/$service.cfg.php";
		$db = array();
		if (file_exists($db_config_file)) {
			include($db_config_file);
		} else {
			@touch($db_config_file);
		}
		if (!$db) {
			$db = array('username' => $service, 'password' => $service, 'database' => $service, 'prefix' => null, 'hostname' => 'localhost', 'port' => 3306, 'ssl' => false, 'ssl_key' => null, 'ssl_cert' => null, 'ssl_ca' => null);
			if ($service == "anope") {
				$db['prefix'] = "anope_";
			}
		}
		if (isset($_POST['database'])) {
			$fields = array('username', 'password', 'database', 'hostname', 'port', 'ssl', 'ssl_key', 'ssl_cert', 'ssl_ca');
			if ($service == "anope") {
				$fields[] = 'prefix';
			} elseif ($service == "denora") {
				$fields = array_merge($fields, array('current', 'maxvalues', 'user', 'server', 'stats', 'channelstats', 'serverstats', 'ustats', 'cstats', 'chan', 'ison', 'aliases'));
			}
			foreach ($fields as $field) {
				$db[$field] = (isset($_POST[$field])) ? addslashes(trim($_POST[$field])) : $db[$field];
			}
			$db['ssl'] = isset($_POST['ssl']) ? 'true' : 'false';

			$db_buffer = "<?php\n\$db = array(\n";
			foreach ($db as $key => $val) {
				$db_buffer .= "    '{$key}' => '{$val}',\n";
			}
			$db_buffer .= ");\n";

			if (is_writable($db_config_file)) {
				$writefile = fopen($db_config_file,"w");
				fwrite($writefile,$db_buffer);
				fclose($writefile);
				die($db_config_file);
				echo json_encode(true);
				exit;
			}
		}
		echo json_encode(false);
	});
	$admin->slim->get('/support/register', function() use ($admin) {
		if (!$admin->sessionStatus()) { $admin->slim->halt(403, "HTTP 403 Access Denied"); }
		$magirc_url = (@$_SERVER['HTTPS'] ? 'https' : 'http') .'://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$magirc_url = explode("admin/",$magirc_url);
		$admin->tpl->assign('magirc_url', $magirc_url[0]);
		$admin->tpl->display('support_register.tpl');
	});
	$admin->slim->get('/support/doc/:file', function($file) use ($admin) {
		if (!$admin->sessionStatus()) { $admin->slim->halt(403, "HTTP 403 Access Denied"); }
		$path = ($file == 'readme') ? '../README.md' : '../doc/'.basename($file).'.md';
		if (is_file($path)) {
			$text = file_get_contents($path);
			$admin->tpl->assign('text', $text);
		} else {
			$admin->tpl->assign('text', "ERROR: Specified documentation file not found");
		}
		$admin->tpl->display('support_markdown.tpl');
	});
	$admin->slim->get('/admin/list', function() use ($admin) {
		if (!$admin->sessionStatus()) { $admin->slim->halt(403, "HTTP 403 Access Denied"); }
		$admins = $admin->db->query("SELECT username, realname, email FROM magirc_admin", SQL_ALL, SQL_ASSOC);
		echo json_encode(array('aaData' => $admin->db->record));
	});
	$admin->slim->get('/:section(/:action)', function($section, $action = 'main') use ($admin) {
		if (!$admin->sessionStatus()) { $admin->tpl->display('login.tpl'); exit; }
		$tpl_file = basename($section) . '_' . basename($action) . '.tpl';
		$tpl_path = 'tpl/' . $tpl_file;
		if (file_exists($tpl_path)) {
			$admin->tpl->assign('section', $section);
			$admin->tpl->display($tpl_file);
		} else {
			$admin->slim->notFound();
		}
	});

	$admin->slim->run();

} catch (Exception $e) {
	$admin->tpl->assign('err_msg', $e->getMessage());
	$admin->tpl->assign('err_extra', $e->getTraceAsString());
	$admin->tpl->display('error_fatal.tpl');
}

?>