<?php
/*
Plugin Name: SpamCaptcher
Plugin URI: http://www.spamcaptcher.com/documentation/wordPress.jsp
Description: Integrates SpamCaptcher anti-spam solutions with wordpress
Version: 1.2.1
Author: SpamCaptcher
Email: support@spamcaptcher.com
Author URI: http://www.spamcaptcher.com
*/

// this is the 'driver' file that instantiates the objects and registers every hook

define('ALLOW_INCLUDE', true);

require_once('spamcaptcher.php');

$spamcaptcher = new SpamCaptcherPlugin('spamcaptcher_options');

?>