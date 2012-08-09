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
			
			// only register the hook if the user wants spamcaptcher on the login page
			if ($this->options['show_in_account_login']){
				add_action('wp_login_failed', array(&$this, 'update_user_invalid_login_info'));
				add_action('wp_login', array(&$this, 'reset_user_invalid_login_count'), 0, 2);
				add_action('login_form', array(&$this, 'show_spamcaptcher_in_login_if_necessary'));
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
			
			add_action('gform_post_submission', array(&$this, "gform_update_entry_meta"));
			
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
			
			// only register the hook if the user wants spamcaptcher on the login page
			if ($this->options['show_in_account_login']){
				add_filter( 'authenticate',  array(&$this, 'validate_spamcaptcher_in_login_if_necessary'), 9, 3);
			}
			
			// only register the hook if the user wants spamcaptcher on the password reset page
			if ($this->options['show_in_password_reset']){
				add_filter ( 'allow_password_reset',  array(&$this, 'validate_spamcaptcher_password_reset') );
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
               $option_defaults['account_private_key'] = $old_options['account_private_key']; // the private key for spamcaptcher

               // placement
               $option_defaults['show_in_comments'] = $old_options['sc_comments']; // whether or not to show spamcaptcher on the comment post
               $option_defaults['show_in_registration'] = $old_options['sc_registration']; // whether or not to show spamcaptcher on the registration page
			   $option_defaults['show_in_password_reset'] = $old_options['show_in_password_reset']; //whether or not to show spamcaptcher on the password reset page
			   $option_defaults['show_in_account_login'] = $old_options['show_in_account_login']; //whether or not to show spamcaptcher on the login page
			   
			   // TrustMe Account
			   $option_defaults['comments_force_tma'] = $old_options['comments_force_tma'];
			   $option_defaults['registration_force_tma'] = $old_options['registration_force_tma'];
			   
               // bypass levels
               $option_defaults['bypass_for_registered_users'] = ($old_options['sc_bypass'] == "on") ? 1 : 0; // whether to skip spamcaptchers for registered users
               $option_defaults['minimum_bypass_level'] = $old_options['sc_bypasslevel']; // who doesn't have to do the spamcaptcher (should be a valid WordPress capability slug)

               if ($option_defaults['minimum_bypass_level'] == "level_10") {
                  $option_defaults['minimum_bypass_level'] = "activate_plugins";
               }

               // error handling
               $option_defaults['incorrect_response_error'] = $old_options['error_incorrect']; // message for incorrect CAPTCHA response
			   
			   // misc
				if ($this->is_ssl_capable()){
					$options_defaults['use_ssl'] = $old_options['use_ssl'];
					$options_defaults['ssl_capable'] = 1;
				}else{
					$options_defaults['use_ssl'] = 0;
					$options_defaults['ssl_capable'] = 0;
				}
               $options_defaults['send_spam_data'] = $old_options['send_spam_data'];
			   $options_defaults['bind_to_form'] = $old_options['bind_to_form'];
			   $options_defaults['toggle_opacity'] = $old_options['toggle_opacity'];
			   $option_defaults['min_moderation_score'] = $old_options['min_moderation_score'];
			   $option_defaults['max_moderation_score'] = $old_options['max_moderation_score'];
			   $option_defaults['comments_use_proof_of_work'] = $old_options['comments_use_proof_of_work'];
			   $option_defaults['comments_proof_of_work_time'] = $old_options['comments_proof_of_work_time'];
			   $options_defaults['account_login_failed_attempt_count'] = $old_options['account_login_failed_attempt_count'];
			   $options_defaults['account_login_reset_time'] = $old_options['account_login_reset_time'];
            }
           
            else {
               // keys
               $option_defaults['account_id'] = ''; // the account's id for spamcaptcher
               $option_defaults['account_private_key'] = ''; // the account's password for spamcaptcher

               // placement
               $option_defaults['show_in_comments'] = 1; // whether or not to show spamcaptcher on the comment post
               $option_defaults['show_in_registration'] = 1; // whether or not to show spamcaptcher on the registration page
			   $option_defaults['show_in_password_reset'] = 1; //whether or not to show spamcaptcher on the password reset page
			   $option_defaults['show_in_account_login'] = 1; //whether or not to show spamcaptcher on the login page
			   
               // bypass levels
               $option_defaults['bypass_for_registered_users'] = 1; // whether to skip spamcaptchers for registered users
               $option_defaults['minimum_bypass_level'] = 'read'; // who doesn't have to do the spamcaptcher (should be a valid WordPress capability slug)

               // error handling
               $option_defaults['incorrect_response_error'] = '<span style=\'color:red\'><strong>ERROR</strong>: Incorrect SpamCaptcher response. Please try again.</span>'; 
			   
			   // misc
			   $options_defaults['ssl_capable'] = ($this->is_ssl_capable() ? 1 : 0);
			   $options_defaults['use_ssl'] = $options_defaults['ssl_capable'];
               $options_defaults['send_spam_data'] = 1;
			   $options_defaults['bind_to_form'] = 0;
			   $options_defaults['toggle_opacity'] = 0;
			   $option_defaults['min_moderation_score'] = 35;
			   $option_defaults['max_moderation_score'] = 99;
			   $option_defaults['comments_use_proof_of_work'] = 0;
			   $option_defaults['comments_proof_of_work_time'] = 2;
			   $options_defaults['account_login_failed_attempt_count'] = 3;
			   $options_defaults['account_login_reset_time'] = 600;
			   
			   // TrustMe Account
			   $option_defaults['comments_force_tma'] = 0;
			   $option_defaults['registration_force_tma'] = 0;
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
            return (empty($this->options['account_id']) || empty($this->options['account_private_key']));
        }
        
        function create_error_notice($message, $anchor = '') {
            $options_url = admin_url('options-general.php?page=spamcaptcher/spamcaptcher.php') . $anchor;
            $error_message = sprintf(__($message . ' <a href="%s" title="SpamCaptcher Options">Fix this</a>', 'spamcaptcher'), $options_url);
            
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
            $validated['account_private_key'] = trim($input['account_private_key']);
            
            $validated['show_in_comments'] = ($input['show_in_comments'] == 1 ? 1 : 0);
            $validated['bypass_for_registered_users'] = ($input['bypass_for_registered_users'] == 1 ? 1: 0);
            
            $capabilities = array ('read', 'edit_posts', 'publish_posts', 'moderate_comments', 'activate_plugins');
            
            $validated['minimum_bypass_level'] = $this->validate_dropdown($capabilities, 'minimum_bypass_level', $input['minimum_bypass_level']);
      
            $validated['show_in_registration'] = ($input['show_in_registration'] == 1 ? 1 : 0);
			
			$validated['show_in_password_reset'] = $input['show_in_password_reset'];
            
            $validated['incorrect_response_error'] = $input['incorrect_response_error'];
			
			$validated['toggle_opacity'] = $input['toggle_opacity'];
			$validated['bind_to_form'] = $input['bind_to_form'];
			$validated['comments_use_proof_of_work'] = $input['comments_use_proof_of_work'];
			$validated['comments_proof_of_work_time'] = 2;
			
			$validated['registration_force_tma'] = $input['registration_force_tma'];
			$validated['comments_force_tma'] = $input['comments_force_tma'];
			
			$minVal = (int) $input['min_moderation_score'];
			$maxVal = (int) $input['max_moderation_score'];
			if (is_int($minVal) && is_int($maxVal) && $minVal >= 0 && $maxVal >= 0 && $minVal <= 100 && $maxVal <= 100 && $maxVal >= $minVal){
				$validated['min_moderation_score'] = $minVal;
				$validated['max_moderation_score'] = $maxVal;
			}else{
				$validated['min_moderation_score'] = 35;
				$validated['max_moderation_score'] = 99;
			}
			
			$validated['show_in_account_login'] = ($input['show_in_account_login'] == 1 ? 1 : 0);
			$invalidLoginCount = (int) $input['account_login_failed_attempt_count'];
			if (is_int($invalidLoginCount) && $invalidLoginCount > 0){
				$validated['account_login_failed_attempt_count'] = $invalidLoginCount;
			}else{
				$validated['account_login_failed_attempt_count'] = 3;
			}
			$invalidLoginResetTime = (int) $input['account_login_reset_time'];
			if (is_int($invalidLoginResetTime) && $invalidLoginResetTime > 0){
				$validated['account_login_reset_time'] = $invalidLoginResetTime;
			}else{
				$validated['account_login_reset_time'] = 600;
			}
            
			$validated['ssl_capable'] = $this->is_ssl_capable();
			$validated['use_ssl'] = $input['use_ssl'];
			// can't use SSL if it's not supported
			if (!$validated['ssl_capable']){
				$validated['use_ssl'] = 0;
			}
			
            $validated['send_spam_data'] = $input['send_spam_data'];
            
            return $validated;
        }
        
        // display spamcaptcher
        function show_spamcaptcher_in_registration($errors) {
			echo $this->show_spamcaptcher_captcha($this->options['registration_force_tma']);
        }
		
		function show_spamcaptcher_in_password_reset($errors = null){
			echo $this->show_spamcaptcher_captcha(false, false) . "<br />";
		}
		
		function validate_spamcaptcher_password_reset(){
			if (empty($_POST['spamCaptcherSessionID']) || $_POST['spamCaptcherSessionID'] == '') {
                return false;
            }
			$sc_obj = $this->check_spamcaptcher_answer(null, false, false, SpamCaptcher::$USER_ACTION_FORGOT_PASSWORD);
			$recommendation = $sc_obj->getRecommendedAction();
			if($recommendation == SpamCaptcher::$SHOULD_PASS){
				return true;
			}
			return new WP_Error('spamcaptcher_invalid_captcha_password_reset', '<strong>ERROR</strong>: Invalid CAPTCHA solution. To help protect your account from hacking a valid CAPTCHA solution is required to reset your password.');
		}
        
        function validate_spamcaptcher_registration($errors) {
			$sc_obj = $this->check_spamcaptcher_answer(null, $this->options['registration_force_tma'], true, SpamCaptcher::$USER_ACTION_ACCOUNT_REGISTRATION);
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
                    
				$sc_obj = $this->check_spamcaptcher_answer(null, $this->options['registration_force_tma'], true, SpamCaptcher::$USER_ACTION_ACCOUNT_REGISTRATION);
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
		
		function check_spamcaptcher_answer($csessID = null, $forceTrustMeAccount = false, $allowTrustMeAccount = true, $userAction = "", $overwrite_gtmas = true, $minModerationScore = null, $maxModerationScore = null, $useProofOfWork = false, $proofOfWorkTime = 1){
			$result = SpamCaptcher::$SHOULD_DELETE;
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
				'ftma' => ($forceTrustMeAccount ? "1" : "0"),
				'atma' => ($allowTrustMeAccount ? "1" : "0"),
				'ogtmas' => ($overwrite_gtmas ? "1" : "0"),
				'spamCaptcherAnswer' => $answer
			);
			if (is_null($minModerationScore)){
				$minModerationScore = $this->options['min_moderation_score'];
			}
			if (is_null($maxModerationScore)){
				$maxModerationScore = $this->options['max_moderation_score'];
			}
			$sc_obj = new SpamCaptcher($this->options['account_id'],$this->options['account_private_key']);
			$sc_obj->setUseSSL(($this->options['use_ssl'] == "1" ? true : false));
			$sc_obj->setSessionID($sessionID);
			$sc_obj->setCustomerSessionID($csessID);
			$sc_obj->setMinModerationScore($minModerationScore);
			$sc_obj->setMaxModerationScore($maxModerationScore);
			$sc_obj->useProofOfWork($useProofOfWork);
			$sc_obj->setProofOfWorkTime($proofOfWorkTime);
			$sc_obj->setUserAction($userAction);
			$sc_obj->validate($args);
			return $sc_obj;
		}
        
        function hash_comment($id) {
            define ("spamcaptcher_WP_HASH_SALT", "928180d65e6561343cb46b4f5237ca0b51aba962636926a1"); 
            
            if (function_exists('wp_hash'))
                return wp_hash(spamcaptcher_WP_HASH_SALT . $id);
            else
                return md5(spamcaptcher_WP_HASH_SALT . $this->options['private_key'] . $id);
        }
        
		function is_ssl_capable(){
			return is_numeric(OPENSSL_VERSION_NUMBER);
		}
		
		function show_spamcaptcher_captcha($forceTMA = false, $allowTMA = true, $useProofOfWork = false, $proofOfWorkTime = 1){
			return $this->show_spamcaptcher_captcha_all_options($forceTMA, $allowTMA, $this->options['toggle_opacity'], $this->options['bind_to_form'], null, true, $useProofOfWork, $proofOfWorkTime);
		}
		
		function show_spamcaptcher_captcha_all_options($forceTMA = false, $allowTMA = true, $toggleOpacity = false, $bindToForm = false, $anchor = null, $overwrite_gtmas = true, $useProofOfWork = false, $proofOfWorkTime = 1){
			$sc_obj = new SpamCaptcher($this->options['account_id'],$this->options['account_private_key']);
			$strForceTMA = "false";
			$strToggleOpacity = "false";
			$strBindToForm = "false";
			$strAllowTMA = "false";
			$strOverwriteGTMA = "false";
			$strUseProofOfWork = "false";
			if ($forceTMA){
				$strForceTMA = "true";
			}
			if ($allowTMA){
				$strAllowTMA = "true";
			}
			if ($toggleOpacity){
				$strToggleOpacity = "true";
			}
			if ($bindToForm){
				$strBindToForm = "true";
			}
			if ($overwrite_gtmas){
				$strOverwriteGTMA = "true";
			}
			if ($useProofOfWork){
				$strUseProofOfWork = "true";
			}
			$sc_obj->setForceTrustMeAccount($forceTMA);
			$sc_obj->setAllowTrustMeAccount($allowTMA);
			$sc_obj->setSettings("{forceTrustMeAccount:$strForceTMA,allowTrustMeAccount:$strAllowTMA,toggleOpacity:$strToggleOpacity,bindToForm:$strBindToForm,overwriteGlobalTrustMeAccountSettings:$strOverwriteGTMA" . (isset($anchor) ? ",anchor:'$anchor'" : "") . ",proofOfWork:$strUseProofOfWork,proofOfWorkTime:$proofOfWorkTime}");
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
				
                echo $this->show_spamcaptcher_captcha($this->options['comments_force_tma'], true, $this->options['comments_use_proof_of_work'], $this->options['comments_proof_of_work_time']) . $comment_string;
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
                    
                    $sc_obj = $this->check_spamcaptcher_answer(null, $this->options['comments_force_tma'], true, SpamCaptcher::$USER_ACTION_LEAVE_COMMENT, true, $this->options['min_moderation_score'], $this->options['max_moderation_score'], $this->options['comments_use_proof_of_work'], $this->options['comments_proof_of_work_time']);
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
			if ($sc_sess_id){ // for right now we will just handle comments we provided the CAPTCHA for
				$sc_obj = new SpamCaptcher($this->options['account_id'],$this->options['account_private_key']);
				$comment_data = '';
                if ($this->options['send_spam_data']){
                    $comment_obj = get_comment($comment_id);
                    $comment_data = $comment_obj->comment_content;
                }
                $sc_obj->flag($sc_sess_id, null, SpamCaptcher::$FLAG_FOR_SPAM, $comment_data);
			}
			return true; 
		}
		
		function set_user_invalid_login_info($userID, $invalidLoginCount, $lastInvalidLogin){
			if (!is_null($invalidLoginCount)){
				update_user_meta($userID, 'spamCaptcherInvalidLoginCount', $invalidLoginCount);
			}
			if (!is_null($lastInvalidLogin)){
				update_user_meta($userID, 'spamCaptcherLastInvalidLogin', $lastInvalidLogin);
			}
		}
		
		function update_user_invalid_login_info($username){
			$user = get_user_by('login',$username);
			if (!$user){
				return;
			}
			$num_of_failed_login_attempts = get_user_meta($user->ID, 'spamCaptcherInvalidLoginCount',true);
			if (is_null($num_of_failed_login_attempts)){
				$num_of_failed_login_attempts = 0;
			}
			$num_of_failed_login_attempts = intval($num_of_failed_login_attempts) + 1;
			$this->set_user_invalid_login_info($user->ID, $num_of_failed_login_attempts, time());
		}
		
		function reset_user_invalid_login_count($username, $user){
			$this->set_user_invalid_login_info($username, 0, null);
		}
		
		function does_user_login_require_captcha($username){
			$retVal = false;
			$user = get_user_by('login',$username);
			if (!$user || !$this->options['show_in_account_login']){
				return false;
			}
			$last_failed_login = intval(get_user_meta($user->ID, 'spamCaptcherLastInvalidLogin',true));
			if (is_null($last_failed_login) || !is_int($last_failed_login)){
				return false;
			}
			$time_since_last_failed_login = time() - $last_failed_login;
			if ($time_since_last_failed_login >= $this->options['account_login_reset_time']){
				return false;
			}
			$num_of_failed_login_attempts = get_user_meta($user->ID, 'spamCaptcherInvalidLoginCount',true);
			if ($num_of_failed_login_attempts > $this->options['account_login_failed_attempt_count']){
				$retVal = true;
			}
			return $retVal;
		}
		
		function show_spamcaptcher_in_login_if_necessary(){
			$username = isset($_POST['log']) ? stripslashes($_POST['log']) : '';
			if ($this->does_user_login_require_captcha($username)){
				echo $this->show_spamcaptcher_captcha(false, false);
			}
		}
		
		function validate_spamcaptcher_in_login_if_necessary($user, $username, $password){
			remove_filter('authenticate', 'wp_authenticate_username_password', 20, 3);
			$userdata = get_user_by('login',$username);
			if ($this->does_user_login_require_captcha($username)){
				if (!(isset($_POST["spamCaptcherSessionID"]) && $_POST["spamCaptcherSessionID"])){
					return new WP_Error('spamcaptcher_invalid_login_captcha', '<strong>ERROR</strong>: This account requires a CAPTCHA due to too many invalid login attempts.');
				}
				$sc_obj = $this->check_spamcaptcher_answer(null, false, false, SpamCaptcher::$USER_ACTION_ACCOUNT_LOGIN);
				$recommendation = $sc_obj->getRecommendedAction();
				if($recommendation == SpamCaptcher::$SHOULD_DELETE){
					return new WP_Error('spamcaptcher_invalid_login_captcha', '<strong>ERROR</strong>: Incorrect CAPTCHA solution. This account requires a CAPTCHA due to too many invalid login attempts.');
				}
			}

			$userdata = apply_filters('wp_authenticate_user', $userdata, $password);
			if ( !isset($userdata->ID)){
				return new WP_Error('', '');
			}
			if ( is_wp_error($userdata) ) {
				return $userdata;
			}
			
			if ( !wp_check_password($password, $userdata->user_pass, $userdata->ID) ) {
				return new WP_Error('incorrect_password', sprintf(__('<strong>ERROR</strong>: Incorrect password. <a href="%s" title="Password Lost and Found">Lost your password</a>?'), site_url('wp-login.php?action=lostpassword', 'login')));
			}

			$user =  new WP_User($userdata->ID);
			$this->reset_user_invalid_login_count($user, $username);
			
			return $user;
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
               $settings_link = '<a href="options-general.php?page=spamcaptcher/spamcaptcher.php" title="' . $settings_title . '">' . $settings . '</a>';
               array_unshift($links, $settings_link);
            }
            
            return $links;
        }
        
        // add the settings page
        function add_settings_page() {
            // add the options page
            if ($this->environment == Environment::WordPressMU && $this->is_authority())
                add_submenu_page('wpmu-admin.php', 'SpamCaptcher', 'SpamCaptcher', 'manage_options', __FILE__, array(&$this, 'show_settings_page'));

            if ($this->environment == Environment::WordPressMS && $this->is_authority())
                add_submenu_page('ms-admin.php', 'SpamCaptcher', 'SpamCaptcher', 'manage_options', __FILE__, array(&$this, 'show_settings_page'));
            
            add_options_page('SpamCaptcher', 'SpamCaptcher', 'manage_options', __FILE__, array(&$this, 'show_settings_page'));
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
				<script type="text/javascript">
					function spamcaptcher_set_use_global_miscellaneous_options(val){
						SetFieldProperty('spamcaptcher_use_global_miscellaneous_options', val);
						spamcaptcher_enable_disable_miscellaneous_options(val);
						if (val){
							jQuery("#spamcaptcher_bind_to_form").attr("checked", <?php echo ($this->options["bind_to_form"] ? "true" : "false"); ?>);
							jQuery("#spamcaptcher_toggle_opacity").attr("checked", <?php echo ($this->options["toggle_opacity"] ? "true" : "false"); ?>);
						}else{
							SetFieldProperty('spamcaptcher_bindtoform', jQuery("#spamcaptcher_bind_to_form").attr("checked") == "checked");
							SetFieldProperty('spamcaptcher_toggleopacity', jQuery("#spamcaptcher_toggle_opacity").attr("checked") == "checked");
						}
					}
					
					function spamcaptcher_enable_disable_miscellaneous_options(val){
						jQuery("#spamcaptcher_bind_to_form").attr("disabled", val);
						jQuery("#spamcaptcher_toggle_opacity").attr("disabled", val);
					}
					
					function spamcaptcher_clicked_use_default_tma_settings_checkbox(val){
						SetFieldProperty('spamcaptcher_use_default_tma_settings', val);
						spamcaptcher_show_hide_tma_checkboxes(val);
					}
					
					function spamcaptcher_show_hide_tma_checkboxes(use_default){
						var div_obj = jQuery("#spamcaptcher_overwrite_tma_checkboxes_area");
						if (use_default){
							div_obj.hide(300);
						}else{
							div_obj.show(300);
						}
					}
					
					function spamcaptcher_clicked_ftma(val){
						if (val){
							SetFieldProperty('spamcaptcher_use_proof_of_work', false);
							jQuery("#spamcaptcher_use_proof_of_work").attr("checked", false);
						}
						jQuery("#spamcaptcher_use_proof_of_work").attr("disabled", val);
					}
				</script>
				<li class="spamcaptcher field_setting">
					<label for="field_admin_label">
						<?php _e("TrustMe Account Settings", "gravityforms"); ?>
					</label>
					<input type="checkbox" id="spamcaptcher_use_default_tma_settings" onclick="spamcaptcher_clicked_use_default_tma_settings_checkbox(this.checked);" /> Use My Default Settings
					<?php gform_tooltip("spamcaptcher_use_default_tma_settings") ?>
					<br />
					<div id="spamcaptcher_overwrite_tma_checkboxes_area">
						<input type="checkbox" id="allow_trust_me_account" onclick="SetFieldProperty('allowTrustMeAccount', this.checked);spamcaptcher_allow_tma_checkbox_clicked(this.checked);" /> Allow TrustMe Account
						<?php gform_tooltip("allow_trust_me_account") ?>
						<br />
						<input type="checkbox" id="force_trust_me_account" onclick="SetFieldProperty('forceTrustMeAccount', this.checked);spamcaptcher_clicked_ftma(this.checked);" /> Force TrustMe Account
						<?php gform_tooltip("force_trust_me_account") ?>
					</div>
				</li>
				<li class="spamcaptcher field_setting">
					<label for="field_admin_label">
						<?php _e("Miscellaneous", "gravityforms"); ?>
					</label>
					<input type="checkbox" id="spamcaptcher_use_global_miscellaneous_options" onclick="spamcaptcher_set_use_global_miscellaneous_options(this.checked);" /> Use Global Miscellanous Options
					<?php gform_tooltip("spamcaptcher_use_global_miscellaneous_options") ?>
					<br />
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
		
		function gform_advanced_settings($position, $form_id){

			//create settings on position 50 (right after Admin Label)
			if($position == 50){
				?>
				<li class="spamcaptcher field_setting">
					<label for="field_admin_label">
						<?php _e("Trigger Checkbox", "gravityforms"); ?>
					</label>
					<select id="spamcaptcher_trigger_checkbox_dropdown" onchange="spamcaptcher_changed_trigger_checkbox_dropdown(jQuery(this).find('option:selected').val());">
						<option value="spamcaptcher_option_not_selectable" disabled="disabled">-- Pre-Defined --</option>
						<option value="spamcaptcher_default_trigger">Default</option>
						<option value="spamcaptcher_custom_trigger">Custom</option>
						<?php 
							require_once(WP_PLUGIN_DIR . "/gravityforms/forms_model.php");
							$form = RGFormsModel::get_form_meta($form_id);
							if ($form_id > 0){
								$checkbox_arr = array();
								foreach($form["fields"] as $field){
									if ($field["type"] == "checkbox"){
										echo "<option value=\"spamcaptcher_option_not_selectable\" disabled=\"disabled\">-- " . $field["label"] . " --</option>"; 
										foreach ($field["inputs"] as $checkbox){
											// TODO: figure out if name field for checkbox can be something other than input_<id value>
											echo "<option value=\"input_" . $checkbox["id"] . "\">" . $checkbox["label"] . "</option>";
											$checkbox_arr["input_" . $checkbox["id"]] = $checkbox["label"];
										}
									}
								}
							}
						?>
					</select>
					<?php gform_tooltip("spamcaptcher_checkbox_trigger") ?>
					<br />
					<div id="spamcaptcher_custom_checkbox_area" style="display:none;">
						<label for="field_admin_label">
							<br />
							<?php _e("Custom Checkbox", "gravityforms"); ?>
						</label>
						<input type="text" size="35" id="spamcaptcher_custom_checkbox_text" onkeyup="spamcaptcher_update_preview(this.value);SetFieldProperty('spamcaptcher_custom_checkbox_text', this.value);"/>
						<?php gform_tooltip("spamcaptcher_custom_checkbox_text") ?>
					</div>
				</li>
				<li class="spamcaptcher field_setting">
					<label for="field_admin_label">
						<?php _e("CAPTCHA Type", "gravityforms"); ?>
					</label>
					<input type="checkbox" size="35" id="spamcaptcher_use_proof_of_work" onclick="SetFieldProperty('spamcaptcher_use_proof_of_work',this.checked);"/>
						Proof-of-Work
						<?php gform_tooltip("spamcaptcher_use_proof_of_work") ?>
					<br />
				</li>
				<li class="spamcaptcher field_setting">
					<label for="field_admin_label">
						<?php _e("Error Message", "gravityforms"); ?>
					</label>
					<input type="text" id="spamcaptcher_error_message" onkeyup="SetFieldProperty('spamcaptcher_error_message', this.value);" onblur="spamcaptcher_error_msg_change_focus(this, false);" onfocus="spamcaptcher_error_msg_change_focus(this, true);" />
						<?php gform_tooltip("spamcaptcher_error_message") ?>
					<br />
				</li>
				<li class="spamcaptcher field_setting">
					<label for="field_admin_label">
						<?php _e("Target", "gravityforms"); ?>
					</label>
					<input type="checkbox" size="35" id="spamcaptcher_target" onclick="spamcaptcher_set_use_target(this.checked);"/>
					Hide for registered users who can
						<?php 
							$capabilities = array (
								__('all registered users', 'spamcaptcher') => 'read',
								__('edit posts', 'spamcaptcher') => 'edit_posts',
								__('publish posts', 'spamcaptcher') => 'publish_posts',
								__('moderate comments', 'spamcaptcher') => 'moderate_comments',
								__('activate plugins', 'spamcaptcher') => 'activate_plugins'
							);
            
							echo $this->build_dropdown('spamcaptcher_target_value', $capabilities, $this->options['minimum_bypass_level']);
							gform_tooltip("spamcaptcher_target");
						?>
					<br />
				</li>
				<style type="text/css">
					.spamcaptcher-use-default-err-msg{
						color:#808080;
						font-size:9px;
						width: 50%;
					}
					
					#spamcaptcher_error_message{
						width: 50%;
					}
				</style>
				<script type="text/javascript">
					function spamcaptcher_changed_trigger_checkbox_dropdown(val){
						spamcaptcher_show_hide_custom_checkbox_area(val);
						var checkbox_text = jQuery("#spamcaptcher_trigger_checkbox_dropdown option:selected").text();
						if (val == "spamcaptcher_default_trigger"){
							checkbox_text = "I certify that I am a human";
						}else if (val == "spamcaptcher_custom_trigger"){
							checkbox_text = jQuery("#spamcaptcher_custom_checkbox_text").val();
						}
						spamcaptcher_update_preview(checkbox_text);
						SetFieldProperty('spamcaptcher_trigger_checkbox', val);
					}
					
					function spamcaptcher_show_hide_custom_checkbox_area(val){
						var div_area = jQuery("#spamcaptcher_custom_checkbox_area");
						if (val == "spamcaptcher_custom_trigger"){
							div_area.show(300);
						}else{
							div_area.hide(300);
						}
					}

					function spamcaptcher_update_preview(label_text){
						jQuery(".field_selected .ginput_container").html('<input readonly="readonly" disabled="disabled" id="spamcaptcher_preview_checkbox" type="checkbox" /><label style="margin-left: 4px; padding:0!important; width: auto; line-height: 1.5; vertical-align: top;" for="spamcaptcher_preview_checkbox">' + label_text + '</label>');
					}
					
					function spamcaptcher_set_use_target(val){
						SetFieldProperty('spamcaptcher_use_target', val);
					}
					
					function spamcaptcher_error_msg_change_focus(obj, focused){
						obj = jQuery(obj);
						if (focused){
							if (obj.hasClass("spamcaptcher-use-default-err-msg")){
								obj.val("");
							}
							obj.removeClass("spamcaptcher-use-default-err-msg");
						}else if (!obj.val().length){
							obj.addClass("spamcaptcher-use-default-err-msg").val('<?php echo preg_replace("/'/", "\\'", $this->options['incorrect_response_error']); ?>');
						}
					}
				</script>
				<?php
			}
		}
		
		function gform_init_defaults($form){
			foreach($form["fields"] as &$field){
				if($field["type"] == "spamcaptcher"){
					if (!isset($field["allowTrustMeAccount"])){
						$field["allowTrustMeAccount"] = true;
						$field["forceTrustMeAccount"] = false;
					}
					$field["noDuplicates"] = true;
					$field["label"] = "";
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
				
				function spamcaptcher_allow_tma_checkbox_clicked(isChecked){
					jQuery("#force_trust_me_account").attr("disabled", !isChecked);
					if (!isChecked){
						jQuery("#force_trust_me_account").attr("checked", false);
						SetFieldProperty('forceTrustMeAccount', false);
					}
				}
				
				// initialize default values when adding a new spamcaptcher field
				function SetDefaultValues_spamcaptcher(field){
					field.label = "";
					return field;
				}
				
				//binding to the load field settings event to initialize the fields
				jQuery(document).bind("gform_load_field_settings", function(event, field, form){
					if (field["type"] == "spamcaptcher"){
						jQuery("#allow_trust_me_account").attr("checked", field["allowTrustMeAccount"] == true || typeof field["allowTrustMeAccount"] != "boolean");
						jQuery("#force_trust_me_account").attr("checked", field["forceTrustMeAccount"] == true);
						spamcaptcher_allow_tma_checkbox_clicked(jQuery("#allow_trust_me_account").attr("checked"));
						jQuery("#spamcaptcher_trigger_checkbox_dropdown").val(field["spamcaptcher_trigger_checkbox"]).attr('selected', 'selected');
						spamcaptcher_show_hide_custom_checkbox_area(field["spamcaptcher_trigger_checkbox"]);
						SetFieldProperty('spamcaptcher_trigger_checkbox', jQuery("#spamcaptcher_trigger_checkbox_dropdown").find('option:selected').val());
						jQuery("#spamcaptcher_custom_checkbox_text").val(field["spamcaptcher_custom_checkbox_text"]);
						jQuery("#spamcaptcher_bind_to_form").attr("checked", field["spamcaptcher_bindtoform"] == true);
						jQuery("#spamcaptcher_toggle_opacity").attr("checked", field["spamcaptcher_toggleopacity"] == true);
						jQuery("#spamcaptcher_use_global_miscellaneous_options").attr("checked", field["spamcaptcher_use_global_miscellaneous_options"]);
						jQuery("#spamcaptcher_use_default_tma_settings").attr("checked", field["spamcaptcher_use_default_tma_settings"]);
						if (field["spamcaptcher_use_default_tma_settings"]){
							jQuery("#spamcaptcher_overwrite_tma_checkboxes_area").hide();
						}
						spamcaptcher_set_use_global_miscellaneous_options(field["spamcaptcher_use_global_miscellaneous_options"]);
						jQuery("#spamcaptcher_error_message").val(field["spamcaptcher_error_message"]);
						jQuery("#spamcaptcher_target_value").val(field["spamcaptcher_target_value"]).attr('selected', 'selected');
						jQuery("#spamcaptcher_target_value").change( function(){
							SetFieldProperty('spamcaptcher_target_value', jQuery("#spamcaptcher_target_value option:selected").val());
						});
						jQuery("#spamcaptcher_target").attr('checked', field["spamcaptcher_use_target"] === true);
						spamcaptcher_error_msg_change_focus(jQuery("#spamcaptcher_error_message"), false);
						jQuery("#spamcaptcher_use_proof_of_work").attr('checked', field["spamcaptcher_use_proof_of_work"] === true);
						if (field["forceTrustMeAccount"]){
							jQuery("#spamcaptcher_use_proof_of_work").attr('checked', false).attr('disabled', true);
						}
					}
				});
				
			</script>
			<?php
		}
		
		function gform_tooltips($tooltips){
		   $tooltips["spamcaptcher_use_default_tma_settings"] = "<h6>Default TrustMe Settings</h6>Check this box to use the TrustMe Account settings for your account on <a href='https://www.spamcaptcher.com' target='_blank'>our site</a>.";
		   $tooltips["allow_trust_me_account"] = "<h6>Allow TrustMe Account</h6>Check this box to allow the user to authenticate the CAPTCHA session with a TrustMe Account.";
		   $tooltips["force_trust_me_account"] = "<h6>Force TrustMe Account</h6>Check this box to force the user to authenticate the CAPTCHA session with a TrustMe Account.";
		   $tooltips["spamcaptcher_checkbox_trigger"] = "<h6>Trigger Checkbox</h6>The checkbox that will trigger the CAPTCHA to be displayed. You can choose a checkbox on this form, the default one provided by SpamCaptcher or create a custom one.";
		   $tooltips["spamcaptcher_custom_checkbox_text"] = "<h6>Custom Checkbox</h6>The text that will be displayed as the label for the custom trigger checkbox. <p>Note: You can use HTML markup here but be careful as it is <strong>NOT</strong> escaped.</p>";
		   $tooltips["spamcaptcher_use_global_miscellaneous_options"] = "<h6>Global Miscellaneous Options</h6>Check this box to always have this form's Bind To Form and Toggle Opacity settings synched with the values in the Settings page of your SpamCaptcher plugin.";
		   $tooltips["spamcaptcher_bind_to_form"] = "<h6>Bind To Form</h6>Check this box to have the CAPTCHA auto verified client side prior to allowing the form to submit. Please note that server side validation will still occur.";
		   $tooltips["spamcaptcher_toggle_opacity"] = "<h6>Toggle Opacity</h6>Check this box to make the CAPTCHA box become somewhat transparent when the user's mouse cursor leaves the box.";
		   $tooltips["spamcaptcher_field_label"] = "<h6>Field Label</h6>The field title the user will see for the SpamCaptcher CAPTCHA.";
		   $tooltips["spamcaptcher_error_message"] = "<h6>Error Message</h6>The message to display to the user when an invalid CAPTCHA response has been given. If left blank the error message set in the Settings page of your SpamCaptcher plugin will be used. <p>Note: You can use HTML markup here but be careful as it is <strong>NOT</strong> escaped.</p>";
		   $tooltips["spamcaptcher_target"] = "<h6>Target</h6>Select which group of registered users, if any, do <strong>NOT</strong> have to solve the CAPTCHA. <p>Note: If enabled, you may not see the CAPTCHA show up when you preview this form.</p>";
		   return $tooltips;
		}
		
		function gform_add_spamcaptcher_button($field_groups){
		 
			foreach($field_groups as &$group){
				if($group["name"] == "advanced_fields"){
					$group["fields"][] = array("class"=>"button", "value" => __("SpamCaptcher", "gravityforms"), "onclick" => "if (GetFieldsByType(['spamcaptcher']).length > 0){alert('Only one SpamCaptcher field can be added to the form.');return false;}StartAddField('spamcaptcher');");
					break;
				}
			}
			return $field_groups;
		}
		
		function gform_add_spamcaptcher_to_form($input, $field, $value, $lead_id, $form_id){
			if (IS_ADMIN){
				if ($this->keys_missing()){
					return "To use SpamCaptcher you must enter your Account Keys on the SpamCaptcher Plugin Settings Page.";
				}
				$checkbox_arr = array();
				require_once(WP_PLUGIN_DIR . "/gravityforms/forms_model.php");
				$form = RGFormsModel::get_form_meta($form_id);
				if ($form_id > 0){
					foreach($form["fields"] as $tmp_field){
						if ($tmp_field["type"] == "checkbox"){
							foreach ($tmp_field["inputs"] as $checkbox){
								$checkbox_arr["input_" . $checkbox["id"]] = $checkbox["label"];
							}
						}
					}
				}
				$spamcaptcher_tmp_checkbox_text = "I certify that I am a human";
				if ($field["type"] == "spamcaptcher"){
					$spamcaptcher_tmp_checkbox_val = $field["spamcaptcher_trigger_checkbox"];
					if (!isset($field["spamcaptcher_trigger_checkbox"])){
						$spamcaptcher_tmp_checkbox_text = "I certify that I am a human";
					}else{
						if ($spamcaptcher_tmp_checkbox_val == "spamcaptcher_custom_trigger"){
							$spamcaptcher_tmp_checkbox_text = $field["spamcaptcher_custom_checkbox_text"];
						}elseif ($spamcaptcher_tmp_checkbox_val == "spamcaptcher_default_trigger"){
							$spamcaptcher_tmp_checkbox_text = "I certify that I am a human";
						}else{
							$spamcaptcher_tmp_checkbox_text = $checkbox_arr[$spamcaptcher_tmp_checkbox_val];
						}
					}
					return "<div class='ginput_container'><input readonly='readonly' disabled='disabled' id='spamcaptcher_preview_checkbox' type='checkbox' /><label style='margin-left: 4px; padding:0!important; width: auto; line-height: 1.5; vertical-align: top;' for='spamcaptcher_preview_checkbox'>" . $spamcaptcher_tmp_checkbox_text . "</label></div><script type='text/javascript'>jQuery('#spamcaptcher_preview_checkbox').closest('li').find('a.field_duplicate_icon').remove();</script>";
				}
			}else{
				if ($field["type"] == "spamcaptcher"){
					// skip the SpamCaptcher display if the minimum capability is met
					if (isset($field['spamcaptcher_use_target']) && $field['spamcaptcher_use_target'] && isset($field['spamcaptcher_target_value']) && $field['spamcaptcher_target_value'] && current_user_can($field['spamcaptcher_target_value'])){
						return "";
					}
						
					$input = "";
					
					if ($field["spamcaptcher_trigger_checkbox"] == "spamcaptcher_custom_trigger"){
						$input = "<input type=\"checkbox\" name=\"" . $field["spamcaptcher_trigger_checkbox"] . "\" />";
						$input .= "<label for=\"" . $field["spamcaptcher_trigger_checkbox"] . "\">" . $field["spamcaptcher_custom_checkbox_text"] . "</label>";
					}
					$toggle_opacity = $field["spamcaptcher_toggleopacity"];
					$bind_to_form = $field["spamcaptcher_bindtoform"];
					if ($field["spamcaptcher_use_global_miscellaneous_options"]){
						$toggle_opacity = $this->options['toggle_opacity'];
						$bind_to_form = $this->options['bind_to_form'];
					}
					$useProofOfWork = $field["spamcaptcher_use_proof_of_work"];
					$input .= $this->show_spamcaptcher_captcha_all_options($field["forceTrustMeAccount"],$field["allowTrustMeAccount"],$toggle_opacity,$bind_to_form, ($field["spamcaptcher_trigger_checkbox"] != "spamcaptcher_default_trigger" ? $field["spamcaptcher_trigger_checkbox"] : null), !$field["spamcaptcher_use_default_tma_settings"], $useProofOfWork);
				}
				return $input;
			}
		}
		
		function gform_validate_spamcaptcher_response($validation_result){
			if($validation_result["is_valid"]){
				foreach($validation_result["form"]["fields"] as &$field){
					if($field["type"] == "spamcaptcher"){
						// skip the SpamCaptcher validation if the minimum capability is met
						if (isset($field['spamcaptcher_use_target']) && $field['spamcaptcher_use_target'] && isset($field['spamcaptcher_target_value']) && $field['spamcaptcher_target_value'] && current_user_can($field['spamcaptcher_target_value'])){
							break;
						}
						$sc_obj = $this->check_spamcaptcher_answer(null, $field["forceTrustMeAccount"],$field["allowTrustMeAccount"],"",!$field["spamcaptcher_use_default_tma_settings"], null, null, $field["spamcaptcher_use_proof_of_work"]);
						$recommendation = $sc_obj->getRecommendedAction();
						if($recommendation != SpamCaptcher::$SHOULD_PASS){
							$validation_result["is_valid"] = false;
							$field["failed_validation"] = true;
							$validation_message = $field["spamcaptcher_error_message"];
							if (is_null($validation_message) || trim($validation_message) == ""){
								$validation_message = $this->options['incorrect_response_error'];
							}
							$field["validation_message"] = $validation_message;
						}
						$this->spamScore = $sc_obj->getSpamScore();
						break;
					}
				}
			}
			return $validation_result;
		}
		
		function gform_update_entry_meta($entry){
			// add the spamCaptcherSessionID value to the meta data
			// of the entry for future flagging purposes
			gform_update_meta($entry['id'], 'spamCaptcherSessionID', $_POST["spamCaptcherSessionID"]);
			gform_update_meta($entry['id'], 'spamCaptcherSpamScore', $this->spamScore);
		}
        
    } 
} 

?>
