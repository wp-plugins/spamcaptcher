<?php

require_once('wp-plugin.php');

if (!class_exists('SpamCaptcherPlugin')) {
    class SpamCaptcherPlugin extends SC_WPPlugin {
        // member variables
        private $saved_error;
        
        // php 4 constructor
		// is this necessary as PHP4 isn't supported by the lib file?
        function SpamCaptcherPlugin($options_name) {
            $args = func_get_args();
            call_user_func_array(array(&$this, "__construct"), $args);
        }
        
        // php 5 constructor
        function __construct($options_name) {
            parent::__construct($options_name);
            
            $this->register_default_options();
            
            // require the spamcaptcher library
            $this->require_library();
            
            // register the hooks
            $this->register_actions();
            $this->register_filters();
        }
        
        function register_actions() {
            // styling
            add_action('wp_head', array(&$this, 'register_stylesheets')); 
            add_action('admin_head', array(&$this, 'register_stylesheets')); 
            
            if ($this->options['show_in_registration'])
                add_action('login_head', array(&$this, 'registration_style')); // make unnecessary: instead use jQuery and add to the footer?

            // options
            register_activation_hook(SC_WPPlugin::path_to_plugin_directory() . '/wp-spamcaptcher.php', array(&$this, 'register_default_options')); 
            add_action('admin_init', array(&$this, 'register_settings_group'));

            // only register the hooks if the user wants spamcaptcher on the registration page
            if ($this->options['show_in_registration']) {
                // spamcaptcher form display
                if ($this->is_multi_blog())
                    add_action('signup_extra_fields', array(&$this, 'show_spamcaptcher_in_registration'));
                else
                    add_action('register_form', array(&$this, 'show_spamcaptcher_in_registration'));
            }

            // only register the hooks if the user wants spamcaptcher on the comments page
            if ($this->options['show_in_comments']) {
                add_action('comment_form', array(&$this, 'show_spamcaptcher_in_comments'));
                add_action('wp_footer', array(&$this, 'save_comment_script')); // preserve the comment that was entered
				
				//add spamcaptcher session id to the comment's metadata
				add_action ('comment_post', array(&$this, 'add_spamcaptcher_to_comment_meta'));
				
				// flag the session based on the spamcaptcher session id when the comment is marked as spam
				add_action ('spammed_comment', array(&$this, 'flag_comment_for_spam'));
				
                // spamcaptcher comment processing (look into doing all of this with AJAX, optionally)
                add_action('wp_head', array(&$this, 'saved_comment'), 0);
                add_action('preprocess_comment', array(&$this, 'check_comment'), 0);
                add_action('comment_post_redirect', array(&$this, 'relative_redirect'), 0, 2);
            }
			
			// only register the hook if the user wants spamcaptcher on the password reset page
			if ($this->options['show_in_password_reset']){
				add_action('lostpassword_form', array(&$this, 'show_spamcaptcher_in_password_reset'));
			}

            // administration (menus, pages, notifications, etc.)
            add_filter("plugin_action_links", array(&$this, 'show_settings_link'), 10, 2);

            add_action('admin_menu', array(&$this, 'add_settings_page'));
            
            // admin notices
            add_action('admin_notices', array(&$this, 'missing_keys_notice'));
			
			// gravity forms
			add_action("gform_field_standard_settings", array(&$this, "gform_standard_settings"), 10, 2);
			
			add_action("gform_field_advanced_settings", array(&$this, "gform_advanced_settings"), 10, 2);
			
			add_action("gform_editor_js", array(&$this, "gform_editor_script"));

			add_action("gform_field_input", array(&$this, "gform_add_spamcaptcher_to_form"), 10, 5);
			
			//end gravity forms
        }
        
        function register_filters() {
            // only register the hooks if the user wants spamcaptcher on the registration page
            if ($this->options['show_in_registration']) {
                // spamcaptcher validation
                if ($this->is_multi_blog())
                    add_filter('wpmu_validate_user_signup', array(&$this, 'validate_spamcaptcher_registration_wpmu'));
                else
                    add_filter('registration_errors', array(&$this, 'validate_spamcaptcher_registration'));
            }
			
			// only register the hook if the user wants spamcaptcher on the password reset page
			if ($this->options['show_in_password_reset']){
				add_filter ( 'allow_password_reset',  array(&$this, 'validate_spamcaptcher_password_reset') );
				//add_filter('login_errors', array(&$this, "show_password_reset_error_message"));
			}
			
			// gravity forms
			add_filter('gform_tooltips', array(&$this, "gform_tooltips"));
			add_filter("gform_add_field_buttons", array(&$this,"gform_add_spamcaptcher_button"));
			add_filter("gform_validation", array(&$this, "gform_validate_spamcaptcher_response"));
			add_filter("gform_pre_render", array(&$this, "gform_init_defaults"));
			add_filter("gform_admin_pre_render", array(&$this, "gform_init_defaults"));
			// end gravity forms
        }
        
        function load_textdomain() {
            load_plugin_textdomain('spamcaptcher', false, 'languages');
        }
        
        // set the default options
        function register_default_options() {
			if ($this->options)
               return;
			   
            $option_defaults = array();
           
            $old_options = SC_WPPlugin::retrieve_options("spamcaptcher");
           
            if ($old_options) {
               $option_defaults['account_id'] = $old_options['account_id']; // the public key for spamcaptcher
               $option_defaults['account_password'] = $old_options['account_password']; // the private key for spamcaptcher

               // placement
               $option_defaults['show_in_comments'] = $old_options['sc_comments']; // whether or not to show spamcaptcher on the comment post
               $option_defaults['show_in_registration'] = $old_options['sc_registration']; // whether or not to show spamcaptcher on the registration page
			   $option_defaults['show_in_password_reset'] = $old_options['show_in_password_reset']; //whether or not to show spamcaptcher on the password reset page
			   
			   // Spam-Free Account
			   $option_defaults['comments_force_sfa'] = $old_options['comments_force_sfa'];
			   $option_defaults['registration_force_sfa'] = $old_options['registration_force_sfa'];
			   
               // bypass levels
               $option_defaults['bypass_for_registered_users'] = ($old_options['sc_bypass'] == "on") ? 1 : 0; // whether to skip spamcaptchers for registered users
               $option_defaults['minimum_bypass_level'] = $old_options['sc_bypasslevel']; // who doesn't have to do the spamcaptcher (should be a valid WordPress capability slug)

               if ($option_defaults['minimum_bypass_level'] == "level_10") {
                  $option_defaults['minimum_bypass_level'] = "activate_plugins";
               }

               // error handling
               $option_defaults['incorrect_response_error'] = $old_options['error_incorrect']; // message for incorrect CAPTCHA response
			   
			   // misc
			   $options_defaults['bind_to_form'] = $old_options['bind_to_form'];
			   $options_defaults['toggle_opacity'] = $old_options['toggle_opacity'];
			   $option_defaults['min_moderation_score'] = $old_options['min_moderation_score'];
			   $option_defaults['max_moderation_score'] = $old_options['max_moderation_score'];
            }
           
            else {
               // keys
               $option_defaults['account_id'] = ''; // the account's id for spamcaptcher
               $option_defaults['account_password'] = ''; // the account's password for spamcaptcher

               // placement
               $option_defaults['show_in_comments'] = 1; // whether or not to show spamcaptcher on the comment post
               $option_defaults['show_in_registration'] = 1; // whether or not to show spamcaptcher on the registration page
			   $option_defaults['show_in_password_reset'] = 1; //whether or not to show spamcaptcher on the password reset page

               // bypass levels
               $option_defaults['bypass_for_registered_users'] = 1; // whether to skip spamcaptchers for registered users
               $option_defaults['minimum_bypass_level'] = 'read'; // who doesn't have to do the spamcaptcher (should be a valid WordPress capability slug)

               // error handling
               $option_defaults['incorrect_response_error'] = '<span style=\'color:red\'><strong>ERROR</strong>: Incorrect SpamCaptcher response. Please try again.</span>'; 
			   
			   // misc
			   $options_defaults['bind_to_form'] = 0;
			   $options_defaults['toggle_opacity'] = 0;
			   $option_defaults['min_moderation_score'] = 35;
			   $option_defaults['max_moderation_score'] = 99;
			   
			   // Spam-Free Account
			   $option_defaults['comments_force_sfa'] = 0;
			   $option_defaults['registration_force_sfa'] = 0;
            }
            
            // add the option based on what environment we're in
            SC_WPPlugin::add_options($this->options_name, $option_defaults);
        }
        
        // require the spamcaptcher library
        function require_library() {
			require_once($this->path_to_plugin_directory() . '/spamcaptcherlib_wordpress.php');
        }
        
        // register the settings
        function register_settings_group() {
            register_setting("spamcaptcher_options_group", 'spamcaptcher_options', array(&$this, 'validate_options'));
        }
        
        function register_stylesheets() {
            $path = SC_WPPlugin::url_to_plugin_directory() . '/spamcaptcher.css';
                
            echo '<link rel="stylesheet" type="text/css" href="' . $path . '" />';
        }
        
        function registration_style() {
            
            echo <<<REGISTRATION
                <script type="text/javascript">
                window.onload = function() {
                    document.getElementById('login').style.width = '360px';
                    document.getElementById('reg_passmail').style.marginTop = '10px';
                    document.getElementById('spamcaptcher_widget_div').style.marginBottom = '10px';
                };
                </script>
REGISTRATION;
        }
        
        function spamcaptcher_enabled() {
            return ($this->options['show_in_comments'] || $this->options['show_in_registration'] || $this->options['show_in_password_reset']);
        }
        
        function keys_missing() {
            return (empty($this->options['account_id']) || empty($this->options['account_password']));
        }
        
        function create_error_notice($message, $anchor = '') {
            $options_url = admin_url('options-general.php?page=wp-spamcaptcher/spamcaptcher.php') . $anchor;
            $error_message = sprintf(__($message . ' <a href="%s" title="WP-SpamCaptcher Options">Fix this</a>', 'spamcaptcher'), $options_url);
            
            echo '<div class="error"><p><strong>' . $error_message . '</strong></p></div>';
        }
        
        function missing_keys_notice() {
            if ($this->spamcaptcher_enabled() && $this->keys_missing()) {
                $this->create_error_notice('You enabled SpamCaptcher, but some of the SpamCaptcher API Keys seem to be missing.');
            }
        }
        
        function validate_dropdown($array, $key, $value) {
            // make sure that the capability that was supplied is a valid capability from the drop-down list
            if (in_array($value, $array))
                return $value;
            else // if not, load the old value
                return $this->options[$key];
        }
        
        function validate_options($input) {

            $validated['account_id'] = trim($input['account_id']);
            $validated['account_password'] = trim($input['account_password']);
            
            $validated['show_in_comments'] = ($input['show_in_comments'] == 1 ? 1 : 0);
            $validated['bypass_for_registered_users'] = ($input['bypass_for_registered_users'] == 1 ? 1: 0);
            
            $capabilities = array ('read', 'edit_posts', 'publish_posts', 'moderate_comments', 'activate_plugins');
            
            $validated['minimum_bypass_level'] = $this->validate_dropdown($capabilities, 'minimum_bypass_level', $input['minimum_bypass_level']);
      
            $validated['show_in_registration'] = ($input['show_in_registration'] == 1 ? 1 : 0);
			
			$validated['show_in_password_reset'] = $input['show_in_password_reset'];
            
            $validated['incorrect_response_error'] = $input['incorrect_response_error'];
			
			$validated['toggle_opacity'] = $input['toggle_opacity'];
			$validated['bind_to_form'] = $input['bind_to_form'];
			
			$validated['registration_force_sfa'] = $input['registration_force_sfa'];
			$validated['comments_force_sfa'] = $input['comments_force_sfa'];
			
			$minVal = (int) $input['min_moderation_score'];
			$maxVal = (int) $input['max_moderation_score'];
			if (is_int($minVal) && is_int($maxVal) && $minVal >= 0 && $maxVal >= 0 && $minVal <= 100 && $maxVal <= 100 && $maxVal >= $minVal){
				$validated['min_moderation_score'] = $minVal;
				$validated['max_moderation_score'] = $maxVal;
			}else{
				$validated['min_moderation_score'] = 35;
				$validated['max_moderation_score'] = 99;
			}
            
            return $validated;
        }
        
        // display spamcaptcher
        function show_spamcaptcher_in_registration($errors) {
			echo $this->show_spamcaptcher_captcha($this->options['registration_force_sfa']);
        }
		
		function show_spamcaptcher_in_password_reset($errors = null){
			echo $this->show_spamcaptcher_captcha(false, false) . "<br />";
		}
		
		function validate_spamcaptcher_password_reset(){
			if (empty($_POST['spamCaptcherSessionID']) || $_POST['spamCaptcherSessionID'] == '') {
                return false;
            }
			$sc_obj = $this->check_spamcaptcher_answer(null, false, false);
			$recommendation = $sc_obj->getRecommendedAction();
			if($recommendation == SpamCaptcher::$SHOULD_PASS){
				return true;
			}
			return false;
		}
        
        function validate_spamcaptcher_registration($errors) {
			$sc_obj = $this->check_spamcaptcher_answer(null, $this->options['registration_force_sfa'], true);
			$recommendation = $sc_obj->getRecommendedAction();
			if (!$sc_obj->getIsValid()){
				$errors->add('captcha_wrong', $this->options['incorrect_response_error']);
				 echo '<div class="error">' . $this->options['incorrect_response_error'] . '</div>';
			}elseif(!$recommendation == SpamCaptcher::$SHOULD_PASS){
				$errors->add('spam_score_high', '<strong>Spam Score is too high</strong>'); //TODO handle moderation
			}
			
           return $errors;
        }
        
        function validate_spamcaptcher_registration_wpmu($result) {
            
            if (!$this->is_authority()) {
                // blogname in 2.6, blog_id prior to that
                // todo: why is this done?
                if (isset($_POST['blog_id']) || isset($_POST['blogname']))
                    return $result;
                    
				$sc_obj = $this->check_spamcaptcher_answer(null, $this->options['registration_force_sfa'], true);
				$recommendation = $sc_obj->getRecommendedAction();
				if (!$sc_obj->getIsValid()){
					$errors->add('captcha_wrong', $this->options['incorrect_response_error']);
					 echo '<div class="error">' . $this->options['incorrect_response_error'] . '</div>';
				}elseif(!$recommendation == SpamCaptcher::$SHOULD_PASS){
					$errors->add('spam_score_high', '<strong>Spam Score is too high</strong>'); //TODO handle moderation
				}
						
					return $result;
				}
        }
		
		function check_spamcaptcher_answer($csessID = null, $forceSpamFreeAccount = false, $allowSpamFreeAccount = true){
			$result = SpamCaptcher::$SHOULD_DELETE;
			if (empty($_POST['spamCaptcherSessionID']) || $_POST['spamCaptcherSessionID'] == '') {
				$sc_obj = new SpamCaptcher($this->options['account_id'],$this->options['account_password']);
				return $sc_obj;
			}
			$sessionID = null;
			$answer = null;
			if (isset($_POST["spamCaptcherSessionID"]) && $_POST["spamCaptcherSessionID"]){
				$sessionID = $_POST["spamCaptcherSessionID"];
				$answer = $_POST["spamCaptcherAnswer"];
			}elseif (isset($_GET["spamCaptcherSessionID"]) && $_GET["spamCaptcherSessionID"]){
				$sessionID = $_GET["spamCaptcherSessionID"];
				$answer = $_GET["spamCaptcherAnswer"];
			}
			$args = array (
				'ip' => $_SERVER['REMOTE_ADDR'],
				'id' => $sessionID,
				'fsfa' => ($forceSpamFreeAccount ? "1" : "0"),
				'asfa' => ($allowSpamFreeAccount ? "1" : "0"),
				'spamCaptcherAnswer' => $answer
			);
			$sc_obj = new SpamCaptcher($this->options['account_id'],$this->options['account_password']);
			$sc_obj->setCustomerSessionID($csessID);
			$sc_obj->setMinModerationScore($this->options['min_moderation_score']);
			$sc_obj->setMaxModerationScore($this->options['max_moderation_score']);
			$sc_obj->validate($args);
			return $sc_obj;
		}
        
        // utility methods
        function hash_comment($id) {
            define ("spamcaptcher_WP_HASH_SALT", "b7e0638d85f5d7f3694f68e944136d62"); //TODO: change the salt
            
            if (function_exists('wp_hash'))
                return wp_hash(spamcaptcher_WP_HASH_SALT . $id);
            else
                return md5(spamcaptcher_WP_HASH_SALT . $this->options['private_key'] . $id);
        }
        
		function show_spamcaptcher_captcha($forceSFA = false, $allowSFA = true){
			return $this->show_spamcaptcher_captcha_all_options($forceSFA, $allowSFA, $this->options['toggle_opacity'], $this->options['bind_to_form']);
		}
		
		function show_spamcaptcher_captcha_all_options($forceSFA = false, $allowSFA = true, $toggleOpacity = false, $bindToForm = false){
			$sc_obj = new SpamCaptcher($this->options['account_id'],$this->options['account_password']);
			$strForceSFA = "false";
			$strToggleOpacity = "false";
			$strBindToForm = "false";
			$strAllowSFA = "false";
			if ($forceSFA){
				$strForceSFA = "true";
			}
			if ($allowSFA){
				$strAllowSFA = "true";
			}
			if ($toggleOpacity){
				$strToggleOpacity = "true";
			}
			if ($bindToForm){
				$strBindToForm = "true";
			}
			$sc_obj->setSettings("{theme: '" . $this->options['registration_theme'] . "',forceSpamFreeAccount:$strForceSFA,allowSpamFreeAccount:$strAllowSFA,toggleOpacity:$strToggleOpacity,bindToForm:$strBindToForm}");
			return $sc_obj->getCaptcha();
		}
		
        function show_spamcaptcher_in_comments() {
            global $user_ID;

            // set the minimum capability needed to skip the captcha if there is one
            if (isset($this->options['bypass_for_registered_users']) && $this->options['bypass_for_registered_users'] && $this->options['minimum_bypass_level'])
                $needed_capability = $this->options['minimum_bypass_level'];

            // skip the SpamCaptcher display if the minimum capability is met
            if ((isset($needed_capability) && $needed_capability && current_user_can($needed_capability)) || !$this->options['show_in_comments'])
                return;

            else {
                // If user failed the CAPTCHA show an error message
                if ((isset($_GET['scerror']) && $_GET['scerror'] == 'captcha-fail'))
                    echo '<p class="spamcaptcher-error">' . $this->options['incorrect_response_error'] . "</p>";
				
				$comment_string = <<<COMMENT_FORM
					<div id="spamcaptcher-submit-btn-area">&nbsp;</div>
					<noscript>
					 <style type='text/css'>#submit {display:none;}</style>
					 <input name="submit" type="submit" id="submit-alt" value="Submit Comment"/> 
					</noscript>
COMMENT_FORM;
				
                echo $this->show_spamcaptcher_captcha($this->options['comments_force_sfa']) . $comment_string;
           }
        }
		
        // this is what does the submit-button re-ordering
        function save_comment_script() {
            $javascript = <<<JS
                <script type="text/javascript">
                var sub = document.getElementById('submit');
                document.getElementById('spamcaptcher-submit-btn-area').appendChild (sub);
                if ( typeof _spamcaptcher_wordpress_savedcomment != 'undefined') {
                        document.getElementById('comment').value = _spamcaptcher_wordpress_savedcomment;
                }
                //document.getElementById('spamcaptcher_table').style.direction = 'ltr';
                </script>
JS;
            echo $javascript;
        }
        
        // todo: this doesn't seem necessary
        function show_captcha_for_comment() {
            global $user_ID;
            return true;
        }
        
        function check_comment($comment_data) {
            global $user_ID;
            
            if ($this->options['bypass_for_registered_users'] && $this->options['minimum_bypass_level'])
                $needed_capability = $this->options['minimum_bypass_level'];
            
            if (($needed_capability && current_user_can($needed_capability)) || !$this->options['show_in_comments'])
                return $comment_data;
            
            if ($this->show_captcha_for_comment()) {
                // do not check trackbacks/pingbacks
                if ($comment_data['comment_type'] == '') {
                    
                    $sc_obj = $this->check_spamcaptcher_answer(null, $this->options['comments_force_sfa'], true);
					$response = $sc_obj->getRecommendedAction();
                    
                    if ($response == SpamCaptcher::$SHOULD_PASS){
						return $comment_data;
                    }elseif ($response == SpamCaptcher::$SHOULD_MODERATE) {
						add_filter('pre_comment_approved', create_function('$a', 'return 0;'));
					}else{
                        if ($sc_obj->getIsValid()){
							$this->saved_error = "spam";
							add_filter('pre_comment_approved', create_function('$a', 'return \'delete\';'));
						}else{
							$this->saved_error = "captcha-fail";
							add_filter('pre_comment_approved', create_function('$a', 'return \'spam\';'));
						}
                        
                        return $comment_data;
                    }
                }
            }
            
            return $comment_data;
        }
        
		function add_spamcaptcher_to_comment_meta($comment_id){
			add_comment_meta($comment_id, 'spamcaptcher_session_id', $_POST['spamCaptcherSessionID'], true);
		}
		
		function flag_comment_for_spam($comment_id){
			$sc_sess_id = get_comment_meta($comment_id, 'spamcaptcher_session_id', true);
			if ($sc_sess_id){
				$sc_obj = new SpamCaptcher($this->options['account_id'],$this->options['account_password']);
				$sc_obj->flag($sc_sess_id, null, 3);
			}
			return true; //for right now just let things go regardless if we didn't successfully flag the session
		}
		
        function relative_redirect($location, $comment) {
            if ($this->saved_error != '') {
                
                $location = substr($location, 0, strpos($location, '#')) .
                    ((strpos($location, "?") === false) ? "?" : "&") .
                    'sccommentid=' . $comment->comment_ID .
                    '&scerror=' . $this->saved_error .
                    '&schash=' . $this->hash_comment($comment->comment_ID) .
                    '#commentform';
            }
            
            return $location;
        }
        
        function saved_comment() {
            if (!is_single() && !is_page())
                return;
            
            $comment_id = $_REQUEST['sccommentid'];
            $comment_hash = $_REQUEST['schash'];
            
            if (empty($comment_id) || empty($comment_hash))
               return;
            
            if ($comment_hash == $this->hash_comment($comment_id)) {
               $comment = get_comment($comment_id);

               // todo: removed double quote from list of 'dangerous characters'
               $com = preg_replace('/([\\/\(\)\+\;\'])/e','\'%\'.dechex(ord(\'$1\'))', $comment->comment_content);
                
               $com = preg_replace('/\\r\\n/m', '\\\n', $com);
                
               echo "
                <script type='text/javascript'>
                var _spamcaptcher_wordpress_savedcomment =  '" . $com  ."';
                _spamcaptcher_wordpress_savedcomment = unescape(_spamcaptcher_wordpress_savedcomment);
                </script>
                ";

                wp_delete_comment($comment->comment_ID);
            }
        }
        
        // todo: is this still needed?
        // this is used for the api keys url in the administration interface
        function blog_domain() {
            $uri = parse_url(get_option('siteurl'));
            return $uri['host'];
        }
        
        // add a settings link to the plugin in the plugin list
        function show_settings_link($links, $file) {
            if ($file == plugin_basename($this->path_to_plugin_directory() . '/wp-spamcaptcher.php')) {
               $settings_title = __('Settings for this Plugin', 'spamcaptcher');
               $settings = __('Settings', 'spamcaptcher');
               $settings_link = '<a href="options-general.php?page=wp-spamcaptcher/spamcaptcher.php" title="' . $settings_title . '">' . $settings . '</a>';
               array_unshift($links, $settings_link);
            }
            
            return $links;
        }
        
        // add the settings page
        function add_settings_page() {
            // add the options page
            if ($this->environment == Environment::WordPressMU && $this->is_authority())
                add_submenu_page('wpmu-admin.php', 'WP-SpamCaptcher', 'WP-SpamCaptcher', 'manage_options', __FILE__, array(&$this, 'show_settings_page'));

            if ($this->environment == Environment::WordPressMS && $this->is_authority())
                add_submenu_page('ms-admin.php', 'WP-SpamCaptcher', 'WP-SpamCaptcher', 'manage_options', __FILE__, array(&$this, 'show_settings_page'));
            
            add_options_page('WP-SpamCaptcher', 'WP-SpamCaptcher', 'manage_options', __FILE__, array(&$this, 'show_settings_page'));
        }
        
        function show_settings_page() {
            include("settings.php");
        }
        
        function build_dropdown($name, $keyvalue, $checked_value) {
            echo '<select name="' . $name . '" id="' . $name . '">' . "\n";
            
            foreach ($keyvalue as $key => $value) {
                $checked = ($value == $checked_value) ? ' selected="selected" ' : '';
                
                echo '\t <option value="' . $value . '"' . $checked . ">$key</option> \n";
                $checked = NULL;
            }
            
            echo "</select> \n";
        }
        
        function capabilities_dropdown() {
            // define choices: Display text => permission slug
            $capabilities = array (
                __('all registered users', 'spamcaptcher') => 'read',
                __('edit posts', 'spamcaptcher') => 'edit_posts',
                __('publish posts', 'spamcaptcher') => 'publish_posts',
                __('moderate comments', 'spamcaptcher') => 'moderate_comments',
                __('activate plugins', 'spamcaptcher') => 'activate_plugins'
            );
            
            $this->build_dropdown('spamcaptcher_options[minimum_bypass_level]', $capabilities, $this->options['minimum_bypass_level']);
        }
		
		// gravity forms functions
		
		function gform_standard_settings($position, $form_id){

			//create settings on position 25 (right after Field Label)
			if($position == 25){
				?>
				<li class="spamcaptcher field_setting">
					<label for="field_label">
						<?php _e("Field Label", "gravityforms"); ?>
						<?php gform_tooltip("spamcaptcher_field_label") ?>
					</label>
					<input type="text" id="field_label" size="35" onkeyup="SetFieldLabel(this.value)" />
				</li>
				<li class="spamcaptcher field_setting">
					<label for="field_admin_label">
						<?php _e("Spam-Free Account Settings", "gravityforms"); ?>
					</label>
					<input type="checkbox" id="allow_spam_free_account" onclick="SetFieldProperty('allowSpamFreeAccount', this.checked);spamcaptcher_allow_sfa_checkbox_clicked(this.checked);" /> Allow Spam-Free Account
					<?php gform_tooltip("allow_spam_free_account") ?>
					<br />
					<input type="checkbox" id="force_spam_free_account" onclick="SetFieldProperty('forceSpamFreeAccount', this.checked);" /> Force Spam-Free Account
					<?php gform_tooltip("force_spam_free_account") ?>
				</li>
				<?php
			}
		}
		
		function gform_advanced_settings($position, $form_id){

			//create settings on position 50 (right after Admin Label)
			if($position == 50){
				?>
				<li class="spamcaptcher field_setting">
					<label for="field_admin_label">
						<?php _e("Miscellaneous", "gravityforms"); ?>
					</label>
					<input type="checkbox" id="spamcaptcher_bind_to_form" onclick="SetFieldProperty('spamcaptcher_bindtoform', this.checked);" /> Bind To Form
					<?php gform_tooltip("spamcaptcher_bind_to_form") ?>
					<br />
					<input type="checkbox" id="spamcaptcher_toggle_opacity" onclick="SetFieldProperty('spamcaptcher_toggleopacity', this.checked);" /> Toggle Opacity
					<?php gform_tooltip("spamcaptcher_toggle_opacity") ?>
					<br />
				</li>
				<?php
			}
		}
		
		function gform_init_defaults($form){
			foreach($form["fields"] as &$field){
				if($field["type"] == "spamcaptcher"){
					if (!isset($field["allowSpamFreeAccount"])){
						$field["allowSpamFreeAccount"] = true;
						$field["forceSpamFreeAccount"] = false;
					}
					break;
				}
			}
			return $form;
		}
		
		function gform_editor_script(){
			?>
			<script type='text/javascript'>
				//adding setting to fields of type "text"
				fieldSettings["spamcaptcher"] += ", .spamcaptcher";
				
				function spamcaptcher_allow_sfa_checkbox_clicked(isChecked){
					jQuery("#force_spam_free_account").attr("disabled", !isChecked);
					if (!isChecked){
						jQuery("#force_spam_free_account").attr("checked", false);
					}
				}
				
				//binding to the load field settings event to initialize the fields
				jQuery(document).bind("gform_load_field_settings", function(event, field, form){
					jQuery("#allow_spam_free_account").attr("checked", field["allowSpamFreeAccount"] == true || typeof field["allowSpamFreeAccount"] != "boolean");
					jQuery("#force_spam_free_account").attr("checked", field["forceSpamFreeAccount"] == true);
					spamcaptcher_allow_sfa_checkbox_clicked(jQuery("#allow_spam_free_account").attr("checked"));
				});
				
			</script>
			<?php
		}
		
		function gform_tooltips($tooltips){
		   $tooltips["allow_spam_free_account"] = "<h6>Allow Spam-Free Account</h6>Check this box to allow a user to authenticate the CAPTCHA session with a Spam-Free Account.";
		   $tooltips["force_spam_free_account"] = "<h6>Force Spam-Free Account</h6>Check this box to force a user to authenticate the CAPTCHA session with a Spam-Free Account.";
		   $tooltips["spamcaptcher_bind_to_form"] = "<h6>Bind To Form</h6>Check this box to have the CAPTCHA auto verified client side prior to allowing the form to submit. Please note that server side validation will still occur.";
		   $tooltips["spamcaptcher_toggle_opacity"] = "<h6>Toggle Opacity</h6>Check this box to make the CAPTCHA box become somewhat transparent when the user's mouse cursor leaves the box.";
		   $tooltips["spamcaptcher_field_label"] = "<h6>Field Label</h6>The field title the user will see for the SpamCaptcher CAPTCHA.";
		   return $tooltips;
		}
		
		function gform_add_spamcaptcher_button($field_groups){
		 
			foreach($field_groups as &$group){
				if($group["name"] == "advanced_fields"){
					$group["fields"][] = array("class"=>"button", "value" => __("SpamCaptcher", "gravityforms"), "onclick" => "StartAddField('spamcaptcher');");
					break;
				}
			}
			return $field_groups;
		}
		
		function gform_add_spamcaptcher_to_form($input, $field, $value, $lead_id, $form_id){
			if ($field["type"] == "spamcaptcher"){
				$input = "";
				if (IS_ADMIN){
					if ($this->keys_missing()){
						$input = "To use SpamCaptcher you must enter your Account Keys on the SpamCaptcher Plugin Settings Page.";
					}
				}else{
					$input = $this->show_spamcaptcher_captcha_all_options($field["forceSpamFreeAccount"],$field["allowSpamFreeAccount"],$field["spamcaptcher_toggleopacity"],$field["spamcaptcher_bindtoform"]);
				}
				return $input;
			}
		}
		
		function gform_validate_spamcaptcher_response($validation_result){
			if($validation_result["is_valid"]){
				foreach($validation_result["form"]["fields"] as &$field){
					if($field["type"] == "spamcaptcher"){
						$sc_obj = $this->check_spamcaptcher_answer(null, $field["forceSpamFreeAccount"],$field["allowSpamFreeAccount"]);
						$recommendation = $sc_obj->getRecommendedAction();
						if($recommendation != SpamCaptcher::$SHOULD_PASS){
							$validation_result["is_valid"] = false;
							$field["failed_validation"] = true;
							$field["validation_message"] = "Incorrect SpamCaptcher response, please try again.";
						}
						break;
					}
				}
			}
			return $validation_result;
		}
        
    } 
} 

?>
