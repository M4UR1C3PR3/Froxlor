<?php

/**
 * This file is part of the Froxlor project.
 * Copyright (c) 2003-2009 the SysCP Team (see authors).
 * Copyright (c) 2010 the Froxlor Team (see authors).
 *
 * For the full copyright and license information, please view the COPYING
 * file that was distributed with this source code. You can also view the
 * COPYING file online at http://files.froxlor.org/misc/COPYING.txt
 *
 * @copyright  (c) the authors
 * @author     Florian Lippert <flo@syscp.org> (2003-2009)
 * @author     Froxlor team <team@froxlor.org> (2010-)
 * @license    GPLv2 http://files.froxlor.org/misc/COPYING.txt
 * @package    System
 *
 */

header("Content-Type: text/html; charset=UTF-8");

// prevent Froxlor pages from being cached
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");
header('Last-Modified: ' . gmdate( 'D, d M Y H:i:s \G\M\T', time()));
header('Expires: ' . gmdate( 'D, d M Y H:i:s \G\M\T', time()));

// Prevent inline - JS to be executed (i.e. XSS) in browsers which support this,
// Inline-JS is no longer allowed and used
// See: http://people.mozilla.org/~bsterne/content-security-policy/index.html
header("X-Content-Security-Policy: allow 'self'; frame-ancestors 'none'");

// Don't allow to load Froxlor in an iframe to prevent i.e. clickjacking
header('X-Frame-Options: DENY');

// If Froxlor was called via HTTPS -> enforce it for the next time
if (isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) != 'off')) {
	header('Strict-Transport-Security: max-age=500');
}

// Internet Explorer shall not guess the Content-Type, see:
// http://blogs.msdn.com/ie/archive/2008/07/02/ie8-security-part-v-comprehensive-protection.aspx
header('X-Content-Type-Options: nosniff' );

// ensure that default timezone is set
if (function_exists("date_default_timezone_set") && function_exists("date_default_timezone_get")) {
	@date_default_timezone_set(@date_default_timezone_get());
}

/**
 * Register Globals Security Fix
 * - unsetting every variable registered in $_REQUEST and as variable itself
 */
foreach ($_REQUEST as $key => $value) {
	if (isset($$key)) {
		unset($$key);
	}
}

unset($_);
unset($value);
unset($key);

$filename = basename($_SERVER['PHP_SELF']);

// keep this for compatibility reasons
$pathtophpfiles = dirname(dirname(__FILE__));

// define default theme for configurehint, etc.
$_deftheme = 'Sparkle';

define('FROXLOR_INSTALL_DIR', dirname(dirname(__FILE__)));

// check whether the userdata file exists
if (!file_exists(FROXLOR_INSTALL_DIR.'/lib/userdata.inc.php')) {
	$config_hint = file_get_contents(FROXLOR_INSTALL_DIR.'/templates/'.$_deftheme.'/misc/configurehint.tpl');
	die($config_hint);
}

// check whether we can read the userdata file
if (!is_readable(FROXLOR_INSTALL_DIR.'/lib/userdata.inc.php')) {
	// get possible owner
	$posixusername = posix_getpwuid(posix_getuid());
	$posixgroup = posix_getgrgid(posix_getgid());
	// get hint-template
	$owner_hint = file_get_contents(FROXLOR_INSTALL_DIR.'/templates/'.$_deftheme.'/misc/ownershiphint.tpl');
	// replace values
	$owner_hint = str_replace("<USER>", $posixusername['name'], $owner_hint);
	$owner_hint = str_replace("<GROUP>", $posixgroup['name'], $owner_hint);
	$owner_hint = str_replace("<FROXLOR_INSTALL_DIR>", FROXLOR_INSTALL_DIR, $owner_hint);
	// show
	die($owner_hint);
}

/**
 * Includes the Usersettings eg. MySQL-Username/Passwort etc.
 */
require (FROXLOR_INSTALL_DIR.'/lib/userdata.inc.php');

if (!isset($sql)
   || !is_array($sql)
) {
	$config_hint = file_get_contents(FROXLOR_INSTALL_DIR.'/templates/'.$_deftheme.'/misc/configurehint.tpl');
	die($config_hint);
}

// Legacy sql-root-information
if (isset($sql['root_user'])
	&& isset($sql['root_password'])
	&& (!isset($sql_root) || !is_array($sql_root))
) {
	$sql_root = array(0 => array('caption' => 'Default', 'host' => $sql['host'], 'user' => $sql['root_user'], 'password' => $sql['root_password']));
	unset($sql['root_user']);
	unset($sql['root_password']);
}

/**
 * Includes the Functions
 */
require (FROXLOR_INSTALL_DIR.'/lib/functions.php');

/**
 * Includes the MySQL-Tabledefinitions etc.
 */
require (FROXLOR_INSTALL_DIR.'/lib/tables.inc.php');

/**
 * Includes the MySQL-Connection-Class
 */
$db = new db($sql['host'], $sql['user'], $sql['password'], $sql['db']);
unset($sql['password']);

// we will try to unset most of the $sql information if they are not needed
// by the calling script.
if (!isset($need_db_sql_data) || $need_db_sql_data !== true) {
	unset($sql);
	$sql = array();
}

if (!isset($need_root_db_sql_data) || $need_root_db_sql_data !== true) {
	unset($sql_root);
	$sql_root = array();
}

/**
 * Create a new idna converter
 */
$idna_convert = new idna_convert_wrapper();

/**
 * disable magic_quotes_runtime if enabled
 */
if (get_magic_quotes_runtime()) {
	//Deactivate
	set_magic_quotes_runtime(false);
}

/**
 * Reverse magic_quotes_gpc=on to have clean GPC data again
 */
if (get_magic_quotes_gpc()) {
	$in = array(&$_GET, &$_POST, &$_COOKIE);

	while (list($k, $v) = each($in)) {
		foreach ($v as $key => $val) {
			if (!is_array($val)) {
				$in[$k][$key] = stripslashes($val);
				continue;
			}
			$in[] = & $in[$k][$key];
		}
	}
	unset($in);
}

/**
 * Selects settings from MySQL-Table
 */
$settings_data = loadConfigArrayDir('actions/admin/settings/');
$settings = loadSettings($settings_data);

/**
 * SESSION MANAGEMENT
 */
$remote_addr = $_SERVER['REMOTE_ADDR'];

if (empty($_SERVER['HTTP_USER_AGENT'])) {
	$http_user_agent = 'unknown';
} else {
	$http_user_agent = $_SERVER['HTTP_USER_AGENT'];
}
unset($userinfo);
unset($userid);
unset($customerid);
unset($adminid);
unset($s);

if (isset($_POST['s'])) {
	$s = $_POST['s'];
	$nosession = 0;
} elseif (isset($_GET['s'])) {
	$s = $_GET['s'];
	$nosession = 0;
} else {
	$s = '';
	$nosession = 1;
}

$timediff = time() - $settings['session']['sessiontimeout'];
$db->query('DELETE FROM `' . TABLE_PANEL_SESSIONS . '` WHERE `lastactivity` < "' . (int)$timediff . '"');
$userinfo = array();

if (isset($s)
   && $s != ""
   && $nosession != 1
) {
	ini_set("session.name", "s");
	ini_set("url_rewriter.tags", "");
	ini_set("session.use_cookies", false);
	session_id($s);
	session_start();
	$query = 'SELECT `s`.*, `u`.* FROM `' . TABLE_PANEL_SESSIONS . '` `s` LEFT JOIN `';

	if (AREA == 'admin') {
		$query.= TABLE_PANEL_ADMINS . '` `u` ON (`s`.`userid` = `u`.`adminid`)';
		$adminsession = '1';
	} else {
		$query.= TABLE_PANEL_CUSTOMERS . '` `u` ON (`s`.`userid` = `u`.`customerid`)';
		$adminsession = '0';
	}

	$query.= 'WHERE `s`.`hash`="' . $db->escape($s) . '" AND `s`.`ipaddress`="' . $db->escape($remote_addr) . '" AND `s`.`useragent`="' . $db->escape($http_user_agent) . '" AND `s`.`lastactivity` > "' . (int)$timediff . '" AND `s`.`adminsession` = "' . $db->escape($adminsession) . '"';
	$userinfo = $db->query_first($query);

	if ((($userinfo['adminsession'] == '1' && AREA == 'admin' && isset($userinfo['adminid'])) || ($userinfo['adminsession'] == '0' && (AREA == 'customer' || AREA == 'login') && isset($userinfo['customerid'])))
	   && (!isset($userinfo['deactivated']) || $userinfo['deactivated'] != '1')
	) {
		$userinfo['newformtoken'] = strtolower(md5(uniqid(microtime(), 1)));
		$query = 'UPDATE `' . TABLE_PANEL_SESSIONS . '` SET `lastactivity`="' . time() . '", `formtoken`="' . $userinfo['newformtoken'] . '" WHERE `hash`="' . $db->escape($s) . '" AND `adminsession` = "' . $db->escape($adminsession) . '"';
		$db->query($query);
		$nosession = 0;
	} else {
		$nosession = 1;
	}
} else {
	$nosession = 1;
}

/**
 * Language Managament
 */
$langs = array();
$languages = array();
$iso = array();

// query the whole table
$query = 'SELECT * FROM `' . TABLE_PANEL_LANGUAGE . '` ';
$result = $db->query($query);

// presort languages
while ($row = $db->fetch_array($result)) {
	$langs[$row['language']][] = $row;
	// check for row[iso] cause older froxlor
	// versions didn't have that and it will
	// lead to a lot of undfined variables
	// before the admin can even update
	if (isset($row['iso'])) { 
		$iso[$row['iso']] = $row['language'];
	}
}

// buildup $languages for the login screen
foreach ($langs as $key => $value) {
	$languages[$key] = $key;
}

// set default langauge before anything else to
// ensure that we can display messages
$language = $settings['panel']['standardlanguage'];

if (isset($userinfo['language']) && isset($languages[$userinfo['language']])) {
	// default: use language from session, #277
	$language = $userinfo['language'];
} else {
	if (!isset($userinfo['def_language'])
	   || !isset($languages[$userinfo['def_language']]) // this will always evaluat  true, since it is the above statement inverted. @todo remove
	) {
		if (isset($_GET['language'])
		   && isset($languages[$_GET['language']])
		) {
			$language = $_GET['language'];
		} else {
			if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
				$accept_langs = explode(',',$_SERVER['HTTP_ACCEPT_LANGUAGE']);
				for($i = 0; $i<count($accept_langs); $i++) {
				    // this only works for most common languages. some (uncommon) languages have a 3 letter iso-code.
				    // to be able to use these also, we would have to depend on the intl extension for php (using Locale::lookup or similar)
				    // as long as froxlor does not support any of these languages, we can leave it like that.
					if (isset($iso[substr($accept_langs[$i],0,2)])) {
						$language=$iso[substr($accept_langs[$i],0,2)];
						break;
					}
				}
				unset($iso);

				// if HTTP_ACCEPT_LANGUAGES has no valid langs, use default (very unlikely)
				if (!strlen($language)>0) {
					$language = $settings['panel']['standardlanguage'];
				}
			}
		}
	} else {
		$language = $userinfo['def_language'];
	}
}

// include every english language file we can get
foreach ($langs['English'] as $key => $value) {
	include_once makeSecurePath($value['file']);
}

// now include the selected language if its not english
if ($language != 'English') {
	foreach ($langs[$language] as $key => $value) {
		include_once makeSecurePath($value['file']);
	}
}

// last but not least include language references file
include_once makeSecurePath('lng/lng_references.php');

// Initialize our new link - class
$linker = new linker('index.php', $s);

/**
 * global Theme-variable
 */
$theme = isset($settings['panel']['default_theme']) ? $settings['panel']['default_theme'] : 'Froxlor';

/**
 * overwrite with customer/admin theme if defined
 */
if (isset($userinfo['theme']) && $userinfo['theme'] != $theme) {
	$theme = $userinfo['theme'];
}

// check for existence of the theme
if (!file_exists('templates/'.$theme.'/index.tpl')) {
	// Fallback
	$theme = 'Froxlor';
}

/*
 * check for custom header-graphic
 */
$hl_path = 'templates/'.$theme.'/assets/img';
$header_logo = $hl_path.'/logo.png';

if (file_exists($hl_path.'/logo_custom.png')) {
	$header_logo = $hl_path.'/logo_custom.png';
}

/**
 * Redirects to index.php (login page) if no session exists
 */
if ($nosession == 1 && AREA != 'login') {
	unset($userinfo);
	redirectTo('index.php');
	exit;
}

/**
 * Initialize Template Engine
 */
$templatecache = array();

/**
 * Logic moved out of lng-file
 */
if (isset($userinfo['loginname'])
   && $userinfo['loginname'] != ''
) {
	$lng['menue']['main']['username'].= $userinfo['loginname'];
	//Initialize logging
	$log = FroxlorLogger::getInstanceOf($userinfo, $settings);
}

/**
 * Fills variables for navigation, header and footer
 */
if (AREA == 'admin' || AREA == 'customer') {
	if (hasUpdates($version)) {
		/*
		 * if froxlor-files have been updated
		 * but not yet configured by the admin
		 * we only show logout and the update-page
		 */
		$navigation_data = array (
			'admin' => array (
				'index' => array (
					'url' => 'admin_index.php',
					'label' => $lng['admin']['overview'],
					'elements' => array (
						array (
							'label' => $lng['menue']['main']['username'],
						),
						array (
							'url' => 'admin_index.php?action=logout',
							'label' => $lng['login']['logout'],
						),
					),
				),
				'server' => array (
					'label' => $lng['admin']['server'],
					'required_resources' => 'change_serversettings',
					'elements' => array (
						array (
							'url' => 'admin_updates.php?page=overview',
							'label' => $lng['update']['update'],
							'required_resources' => 'change_serversettings',
						),
					),
				),
			),
		);
		$navigation = buildNavigation($navigation_data['admin'], $userinfo);
	} else {
		$navigation_data = loadConfigArrayDir('lib/navigation/');
		$navigation = buildNavigation($navigation_data[AREA], $userinfo);
	}
	unset($navigation_data);
}

/**
 * header information about open tickets (only if used)
 */
$awaitingtickets = 0;
$awaitingtickets_text = '';
if ($settings['ticket']['enabled'] == '1') {

	$opentickets = 0;

	if (AREA == 'admin' && isset($userinfo['adminid'])) {
		$opentickets = $db->query_first('
			SELECT COUNT(`id`) as `count` FROM `' . TABLE_PANEL_TICKETS . '`
			WHERE `answerto` = "0" AND (`status` = "0" OR `status` = "1")
			AND `lastreplier`="0" AND `adminid` = "' . $userinfo['adminid'] . '"
		');
		$awaitingtickets = $opentickets['count'];

		if ($opentickets > 0) {
			$awaitingtickets_text = strtr($lng['ticket']['awaitingticketreply'], array('%s' => '<a href="admin_tickets.php?page=tickets&amp;s=' . $s . '">' . $opentickets['count'] . '</a>'));
		}
	}
	elseif (AREA == 'customer' && isset($userinfo['customerid'])) {
		$opentickets = $db->query_first('
			SELECT COUNT(`id`) as `count` FROM `' . TABLE_PANEL_TICKETS . '`
			WHERE `answerto` = "0" AND (`status` = "0" OR `status` = "2")
			AND `lastreplier`="1" AND `customerid` = "' . $userinfo['customerid'] . '"
		');
		$awaitingtickets = $opentickets['count'];

		if ($opentickets > 0) {
			$awaitingtickets_text = strtr($lng['ticket']['awaitingticketreply'], array('%s' => '<a href="customer_tickets.php?page=tickets&amp;s=' . $s . '">' . $opentickets['count'] . '</a>'));
		}
	}
}

$webfont = str_replace('+', ' ', $settings['panel']['webfont']);
eval("\$header = \"" . getTemplate('header', '1') . "\";");

$current_year = date('Y', time());
eval("\$footer = \"" . getTemplate('footer', '1') . "\";");

if (isset($_POST['action'])) {
	$action = $_POST['action'];
} elseif(isset($_GET['action'])) {
	$action = $_GET['action'];
} else {
	$action = '';
	// clear request data
	if (isset($_SESSION)) {
		unset($_SESSION['requestData']);
	}
}

if (isset($_POST['page'])) {
	$page = $_POST['page'];
} elseif(isset($_GET['page'])) {
	$page = $_GET['page'];
} else {
	$page = '';
}

if ($page == '') {
	$page = 'overview';
}

/**
 * Initialize the mailingsystem
 */
$mail = new PHPMailer(true);
$mail->CharSet = "UTF-8";

if (PHPMailer::ValidateAddress($settings['panel']['adminmail']) !== false) {
	// set return-to address and custom sender-name, see #76
	$mail->SetFrom($settings['panel']['adminmail'], $settings['panel']['adminmail_defname']);
	if ($settings['panel']['adminmail_return'] != '') {
		$mail->AddReplyTo($settings['panel']['adminmail_return'], $settings['panel']['adminmail_defname']);
	}
}
