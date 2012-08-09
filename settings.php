<?php

    if (defined('ALLOW_INCLUDE') === false)
        die('no direct access');

?>

<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.6.2/jquery.min.js"></script>
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.16/jquery-ui.min.js"></script>
<link rel="stylesheet" type="text/css" media="all" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.16/themes/start/jquery-ui.css" />
<script type="text/javascript">
	function isIntOrValidControl(theEvent){
		var key;
		var keyCharacter;

		if (window.event){
			key = window.event.keyCode;
		}else if (theEvent){
			key = theEvent.which;
		}else{
			return true;
		}
		
		keyCharacter = String.fromCharCode(key);
		
		if ((key==null) || (key==0) || (key==8) || (key==9) || (key==13) || (key==27) ){ // control key
		   return true;
		}else if ((("0123456789").indexOf(keyCharacter) > -1)){ // numeric key
		   return true;
		}else{ // not numeric or control key
		   return false;
		}
	}
	function validateMinMaxModerationValues(minVal,maxVal){
		var retVal = true;
		if (minVal < 0 || maxVal < 0 || minVal > 100 || maxVal > 100 || minVal > maxVal){
			retVal = false;
		}
		return retVal;
	}
</script>

<script type="text/javascript">
	var moderation_slider;
	if (typeof jQuery != "undefined" && typeof jQuery.ui != "undefined"){
		jQuery(function() {
			moderation_slider = jQuery( "#moderation-score-slider-range" ).slider({
				range: true,
				min: 0,
				max: 100,
				values: [ <?php echo $this->options['min_moderation_score']; ?>, <?php echo $this->options['max_moderation_score']; ?> ],
				slide: function( event, ui ) {
					jQuery( "#spamcaptcher_options\\[min_moderation_score\\]" ).val( ui.values[ 0 ]);
					jQuery( "#spamcaptcher_options\\[max_moderation_score\\]" ).val( ui.values[ 1 ]);
				}
			});
			function resetSlider(){
				var minVal = parseInt(jQuery("#spamcaptcher_options\\[min_moderation_score\\]").val(),10) || -1;
				var maxVal = parseInt(jQuery("#spamcaptcher_options\\[max_moderation_score\\]").val(),10) || -1;
				if (!validateMinMaxModerationValues(minVal,maxVal)){
					minVal = <?php echo $this->options['min_moderation_score']; ?>;
					maxVal = <?php echo $this->options['max_moderation_score']; ?>;
					jQuery("#spamcaptcher_options\\[min_moderation_score\\]").val(minVal);
					jQuery("#spamcaptcher_options\\[max_moderation_score\\]").val(maxVal);
				}
				moderation_slider.slider("values",[minVal,maxVal]);
			}
			jQuery("#spamcaptcher_options\\[min_moderation_score\\]").change(resetSlider);
			jQuery("#spamcaptcher_options\\[max_moderation_score\\]").change(resetSlider);
		});
	}
</script>

<div class="wrap">
   <a name="spamcaptcher"></a>
   <h2><?php _e('SpamCaptcher Options', 'spamcaptcher'); ?></h2>
   <p><?php _e('SpamCaptcher is a free and accessible CAPTCHA service that fights spam and automated attacks without ruining the user experience.', 'spamcaptcher'); ?></p>
   
   <form method="post" action="options.php">
      <?php settings_fields('spamcaptcher_options_group'); ?>

      <h3><?php _e('Authentication', 'spamcaptcher'); ?></h3>
      <p><?php _e('These keys are required before you are able to do anything else.', 'spamcaptcher'); ?> <?php _e('You can get the keys', 'spamcaptcher'); ?> <a href="http://www.spamcaptcher.com" title="<?php _e('Get your SpamCaptcher API Keys', 'spamcaptcher'); ?>" target="_blank"><?php _e('here', 'spamcaptcher'); ?></a>.</p>
      <p><?php _e('Be sure not to mix them up! The two are not interchangeable and you do NOT want to expose your private key!'); ?></p>
      
      <table class="form-table">
         <tr valign="top">
            <th scope="row"><?php _e('Account ID', 'spamcaptcher'); ?></th>
            <td width="500px">
               <input type="text" name="spamcaptcher_options[account_id]" size="60" value="<?php echo $this->options['account_id']; ?>" />
            </td>
			<td>
				<a href="https://www.spamcaptcher.com/documentation/wordPress.jsp#authentication_account_id" target="_blank" title="Help">?</a>
			</td>
         </tr>
         <tr valign="top">
            <th scope="row"><?php _e('Account Private Key', 'spamcaptcher'); ?></th>
            <td width="500px">
               <input type="text" name="spamcaptcher_options[account_private_key]" size="60" value="<?php echo $this->options['account_private_key']; ?>" />
            </td>
			<td>
				<a href="https://www.spamcaptcher.com/documentation/wordPress.jsp#authentication_account_private_key" target="_blank" title="Help">?</a>
			</td>
         </tr>
      </table>
      
      <h3><?php _e('Comment Options', 'spamcaptcher'); ?></h3>
      <table class="form-table">
         <tr valign="top">
            <th scope="row"><?php _e('Activation', 'spamcaptcher'); ?></th>
            <td width="500px">
               <input type="checkbox" id ="spamcaptcher_options[show_in_comments]" name="spamcaptcher_options[show_in_comments]" value="1" <?php checked('1', $this->options['show_in_comments']); ?> />
               <label for="spamcaptcher_options[show_in_comments]"><?php _e('Enable for comments form', 'spamcaptcher'); ?></label>
            </td>
			<td>
				<a href="https://www.spamcaptcher.com/documentation/wordPress.jsp#comments_activation" target="_blank" title="Help">?</a>
			</td>
         </tr>
         
         <tr valign="top">
            <th scope="row"><?php _e('Target', 'spamcaptcher'); ?></th>
            <td width="500px">
               <input type="checkbox" id="spamcaptcher_options[bypass_for_registered_users]" name="spamcaptcher_options[bypass_for_registered_users]" value="1" <?php checked('1', $this->options['bypass_for_registered_users']); ?> />
               <label for="spamcaptcher_options[bypass_for_registered_users]"><?php _e('Hide for Registered Users who can', 'spamcaptcher'); ?></label>
               <?php $this->capabilities_dropdown(); ?>
            </td>
			<td>
				<a href="https://www.spamcaptcher.com/documentation/wordPress.jsp#comments_target" target="_blank" title="Help">?</a>
			</td>
         </tr>
		 
		 <tr valign="top">
            <th scope="row"><?php _e('TrustMe Account', 'spamcaptcher'); ?></th>
            <td width="500px">
               <input type="checkbox" id ="spamcaptcher_options[comments_force_tma]" name="spamcaptcher_options[comments_force_tma]" value="1" <?php checked('1', $this->options['comments_force_tma']); ?> />
               <label for="spamcaptcher_options[comments_force_tma]"><?php _e('Force TrustMe Account for Comments', 'spamcaptcher'); ?></label>
            </td>
			<td>
				<a href="https://www.spamcaptcher.com/documentation/wordPress.jsp#comments_trust_me_account" target="_blank" title="Help">?</a>
			</td>
         </tr>
		 
		 <tr valign="top">
            <th scope="row"><?php _e('Proof-of-Work', 'spamcaptcher'); ?></th>
            <td width="500px">
               <input type="checkbox" id ="spamcaptcher_options[comments_use_proof_of_work]" name="spamcaptcher_options[comments_use_proof_of_work]" value="1" <?php checked('1', $this->options['comments_use_proof_of_work']); ?> />
               <label for="spamcaptcher_options[comments_use_proof_of_work]"><?php _e('Use Proof-of-Work', 'spamcaptcher'); ?></label>
            </td>
			<td>
				<a href="https://www.spamcaptcher.com/documentation/wordPress.jsp#comments_proof_of_work" target="_blank" title="Help">?</a>
			</td>
         </tr>
		 
		 <tr valign="top">
            <th scope="row"><?php _e('Moderation Scores', 'spamcaptcher'); ?></th>
            <td width="500px">
               <div id="moderation-score-slider-range" style="width:400px;"></div>
			   <label for="spamcaptcher_options[min_moderation_score]"><?php _e('Minimum', 'spamcaptcher'); ?></label>
			   <input size="8" maxlength="3" type="text" id ="spamcaptcher_options[min_moderation_score]" name="spamcaptcher_options[min_moderation_score]" value="<?php echo $this->options['min_moderation_score']; ?>" onkeypress="return isIntOrValidControl(event);" />
			   <label for="spamcaptcher_options[max_moderation_score]"><?php _e('Maximum', 'spamcaptcher'); ?></label>
			   <input size="8" maxlength="3" type="text" id ="spamcaptcher_options[max_moderation_score]" name="spamcaptcher_options[max_moderation_score]" value="<?php echo $this->options['max_moderation_score']; ?>" onkeypress="return isIntOrValidControl(event);" />
            </td>
			<td>
				<a href="https://www.spamcaptcher.com/documentation/wordPress.jsp#comments_moderation_scores" target="_blank" title="Help">?</a>
			</td>
         </tr>

      </table>
      
      <h3><?php _e('Registration Options', 'spamcaptcher'); ?></h3>
      <table class="form-table">
		 
		 <tr valign="top">
            <th scope="row"><?php _e('Activation', 'spamcaptcher'); ?></th>
            <td width="500px">
               <input type="checkbox" id ="spamcaptcher_options[show_in_registration]" name="spamcaptcher_options[show_in_registration]" value="1" <?php checked('1', $this->options['show_in_registration']); ?> />
               <label for="spamcaptcher_options[show_in_registration]"><?php _e('Enable for registration form', 'spamcaptcher'); ?></label>
            </td>
			<td>
				<a href="https://www.spamcaptcher.com/documentation/wordPress.jsp#registration_activation" target="_blank" title="Help">?</a>
			</td>
         </tr>
		 
		 <tr valign="top">
            <th scope="row"><?php _e('TrustMe Account', 'spamcaptcher'); ?></th>
            <td width="500px">
               <input type="checkbox" id ="spamcaptcher_options[registration_force_tma]" name="spamcaptcher_options[registration_force_tma]" value="1" <?php checked('1', $this->options['registration_force_tma']); ?> />
               <label for="spamcaptcher_options[registration_force_tma]"><?php _e('Force TrustMe Account for Registration', 'spamcaptcher'); ?></label>
            </td>
			<td>
				<a href="https://www.spamcaptcher.com/documentation/wordPress.jsp#registration_spam_free_account" target="_blank" title="Help">?</a>
			</td>
         </tr>

      </table>
	  
	  <h3><?php _e('Login Options', 'spamcaptcher'); ?></h3>
      <table class="form-table">
         <tr valign="top">
            <th scope="row"><?php _e('Activation', 'spamcaptcher'); ?></th>
            <td width="500px">
               <input type="checkbox" id ="spamcaptcher_options[show_in_account_login]" name="spamcaptcher_options[show_in_account_login]" value="1" <?php checked('1', $this->options['show_in_account_login']); ?> />
               <label for="spamcaptcher_options[show_in_account_login]"><?php _e('Enable for account login', 'spamcaptcher'); ?></label>
            </td>
			<td>
				<a href="https://www.spamcaptcher.com/documentation/wordPress.jsp#account_login_activation" target="_blank" title="Help">?</a>
			</td>
         </tr>
		 
		 <tr valign="top">
            <th scope="row"><?php _e('Show After', 'spamcaptcher'); ?></th>
            <td width="500px">
               <input size="8" type="text" id ="spamcaptcher_options[account_login_failed_attempt_count]" name="spamcaptcher_options[account_login_failed_attempt_count]" value="<?php echo ($this->options['account_login_failed_attempt_count']); ?>" /> invalid login attempts
            </td>
			<td>
				<a href="https://www.spamcaptcher.com/documentation/wordPress.jsp#account_login_failed_count" target="_blank" title="Help">?</a>
			</td>
         </tr>
		
		<tr valign="top">
            <th scope="row"><?php _e('Reset After', 'spamcaptcher'); ?></th>
            <td width="500px">
               <input size="8" type="text" id ="spamcaptcher_options[account_login_reset_time]" name="spamcaptcher_options[account_login_reset_time]" value="<?php echo ($this->options['account_login_reset_time']); ?>" /> seconds
            </td>
			<td>
				<a href="https://www.spamcaptcher.com/documentation/wordPress.jsp#account_login_reset_time" target="_blank" title="Help">?</a>
			</td>
         </tr>
		 
      </table>
	  
	  <h3><?php _e('Password Reset Options', 'spamcaptcher'); ?></h3>
      <table class="form-table">
         <tr valign="top">
            <th scope="row"><?php _e('Activation', 'spamcaptcher'); ?></th>
            <td width="500px">
               <input type="checkbox" id ="spamcaptcher_options[show_in_password_reset]" name="spamcaptcher_options[show_in_password_reset]" value="1" <?php checked('1', $this->options['show_in_password_reset']); ?> />
               <label for="spamcaptcher_options[show_in_password_reset]"><?php _e('Enable for password reset', 'spamcaptcher'); ?></label>
            </td>
			<td>
				<a href="https://www.spamcaptcher.com/documentation/wordPress.jsp#password_reset_activation" target="_blank" title="Help">?</a>
			</td>
         </tr>

      </table>
      
      <h3><?php _e('Miscellaneous', 'spamcaptcher'); ?></h3>
      <table class="form-table">
         
         <tr valign="top">
            <th scope="row"><?php _e('Security', 'spamcaptcher'); ?></th>
            <td width="500px">
              <input type="checkbox" id ="spamcaptcher_options[use_ssl]" name="spamcaptcher_options[use_ssl]" <?php checked('1', $this->options['use_ssl']); ?> value="1"  <?php disabled('0', $this->options['ssl_capable']); ?> />
               <label for="spamcaptcher_options[use_ssl]"><?php _e('Server to Server SSL', 'spamcaptcher'); ?></label>
            </td>
			<td>
				<a href="https://www.spamcaptcher.com/documentation/wordPress.jsp#miscellaneous_server_ssl" target="_blank" title="SSL">?</a>
			</td>
         </tr>
         
         <tr valign="top">
            <th scope="row"><?php _e('Spam Data', 'spamcaptcher'); ?></th>
            <td width="500px">
              <input type="checkbox" id ="spamcaptcher_options[send_spam_data]" name="spamcaptcher_options[send_spam_data]" <?php checked('1', $this->options['send_spam_data']); ?> value="1" />
               <label for="spamcaptcher_options[send_spam_data]"><?php _e('Send Spam to SpamCaptcher', 'spamcaptcher'); ?></label>
            </td>
			<td>
				<a href="https://www.spamcaptcher.com/documentation/wordPress.jsp#miscellaneous_send_spam" target="_blank" title="Send Spam">?</a>
			</td>
         </tr>
		 
		 <tr valign="top">
            <th scope="row"><?php _e('Form Binding', 'spamcaptcher'); ?></th>
            <td width="500px">
              <input type="checkbox" id ="spamcaptcher_options[bind_to_form]" name="spamcaptcher_options[bind_to_form]" value="1" <?php checked('1', $this->options['bind_to_form']); ?> />
               <label for="spamcaptcher_options[bind_to_form]"><?php _e('Bind to Form', 'spamcaptcher'); ?></label>
            </td>
			<td>
				<a href="https://www.spamcaptcher.com/documentation/wordPress.jsp#miscellaneous_bind_to_form" target="_blank" title="Help">?</a>
			</td>
         </tr>
		 
		 <tr valign="top">
            <th scope="row"><?php _e('Aesthetics', 'spamcaptcher'); ?></th>
            <td width="500px">
               <input type="checkbox" id ="spamcaptcher_options[toggle_opacity]" name="spamcaptcher_options[toggle_opacity]" value="1" <?php checked('1', $this->options['toggle_opacity']); ?> />
               <label for="spamcaptcher_options[toggle_opacity]"><?php _e('Toggle Opacity', 'spamcaptcher'); ?></label>
            </td>
			<td>
				<a href="https://www.spamcaptcher.com/documentation/wordPress.jsp#miscellaneous_toggle_opacity" target="_blank" title="Help">?</a>
			</td>
         </tr>

      </table>
      
      <h3><?php _e('Error Messages', 'spamcaptcher'); ?></h3>
      <table class="form-table">
         
         <tr valign="top">
            <th scope="row"><?php _e('Incorrect Guess', 'spamcaptcher'); ?></th>
            <td width="500px">
               <input type="text" name="spamcaptcher_options[incorrect_response_error]" size="70" value="<?php echo $this->options['incorrect_response_error']; ?>" />
            </td>
			<td>
				<a href="https://www.spamcaptcher.com/documentation/wordPress.jsp#error_messages_incorrect_guess" target="_blank" title="Help">?</a>
			</td>
         </tr>
      </table>

      <p class="submit"><input type="submit" class="button-primary" title="<?php _e('Save SpamCaptcher Options') ?>" value="<?php _e('Save SpamCaptcher Changes') ?> &raquo;" /></p>
   </form>
   
   <?php do_settings_sections('spamcaptcher_options_page'); ?>
</div>