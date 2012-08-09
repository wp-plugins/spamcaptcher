=== Plugin Name ===
Contributors: SpamCaptcher
Tags: comments, registration, spamcaptcher, antispam, captcha, spam, trustme account, behavior analysis, gravity forms, form, protection, security, hashcash, proof of work, hidden captcha, invisible captcha
Requires at least: 2.7
Tested up to: 3.3.2
Stable tag: 1.2.1

Integrates SpamCaptcher anti-spam methods with WordPress including comment and registration spam protection.

== Description ==

= What is SpamCaptcher? =

The [SpamCaptcher](http://www.spamcaptcher.com/) plugin helps keep spam off your site by integrating the CAPTCHA, TrustMe Account and Behavior Analysis technologies of SpamCaptcher on your site. Combining these free technologies you can keep spammers away while keeping your end users happy. Below is a brief description of each of the services we offer:

= CAPTCHA =

Instead of having users strain their eyes to try and figure out what some distorted image says our CAPTCHA asks users to rotate four images to their approximate upright positions. We have found that users find this task easier and more enjoyable while computers find it more difficult than solving the traditional distorted text CAPTCHA. We have also seen that users get very fast at solving our CAPTCHAs very quickly.

For users with visual impairments we offer an audio alternative that asks the user to construct a string of characters that meet a set of randomly generated constraints. For example, the user might be asked to construct a string of characters that is at least five characters long, starts with the letter Q, ends with an even number, and contains three consecutive numbers of which each number is less than six. This may sound like a daunting task but we have found that users get get pretty fast at solving these types of problems. 

We also highly recommend that all users get a TrustMe Account in order to securely bypass CAPTCHAs.

= TrustMe Account =

Our TrustMe Account service is one that benefits both website owners and end users. For website owners it provides a way to fight off Human CAPTCHA Solvers which we believe will also provide a long term solution to spam. For end users it provides a way to not have to solve CAPTCHAs so long as the user is not a spammer. For those of you that think we are making some pretty strong claims ... you are correct, and we invite you to read our [TrustMe Account documentation](http://www.spamcaptcher.com/documentation/spamFreeAccount.jsp) and check out our service so you can verify our claims yourself. 

= Behavior Analysis =

As a second line of defense to our CAPTCHA is our Behavior Analysis service that analyzes the user's behavior and returns a score that indicates how likely it is that the user is a spammer. This score, coupled with the user's answer to the CAPTCHA, is what is used to determine whether the session should be passed, moderated, or trashed.

== Installation ==

To install via the WordPress Installer:

1. Click Install Now under the SpamCaptcher plugin.
2. Click OK on the confirmation popup.
3. Click Activate Plugin.
4. Click the Settings link under SpamCaptcher on the Plugins page of your WordPress Dashboard.
5. Enter your Account ID and Account Private Key.
6. Click Save SpamCaptcher Changes.

To install from a zipped up SpamCaptcher bundle:

1. Unzip the zip file to a temporary folder.
2. Upload the `spamcaptcher` folder to the `/wp-content/plugins` directory.
3. Go to the Plugins page of your WordPress Dashboard.
4. Click the Activate link under SpamCaptcher.
5. Click the Settings link under SpamCaptcher.
6. Enter your Account ID and Account Private Key.
7. Click Save SpamCaptcher Changes.
8. Delete the unzipped contents from the temporary folder from step 1.

== Requirements ==

* You need a [free SpamCaptcher account](http://www.spamcaptcher.com)
* You need to be running PHP 5 or later

== ChangeLog ==

= Version 1.2.1 =
* Updated some text on the admin settings page.

= Version 1.2.0 =
* Added the proof of work capability (similar to hashcash).

= Version 1.1.1 =
* Fixed SSL issue.

= Version 1.1.0 =
* Supports change in naming and interfaces from Spam-Free Account to TrustMe Account.
* Added ability to protect against brute-force login attempts.
* Automatically notifies server of user's action when validating the CAPTCHA request so as to know what part(s) of your website are being attacked by bots.
* Renamed field from Account Password to Account Private Key.
* Supports server-to-server SSL during validation and flagging to protect the Account Private Key.
* Supports sending spam comments for analysis.
* Improved Gravity Forms extension to be able to choose the triggering checkbox, set the TrustMe Account and miscellaneous settings, and customize the error message per form.

= Version 1.0.3 =
* Fixed logic that dictates whether a session gets a SHOULD_MODERATE or SHOULD_DELETE response when the session ID is not present.

= Version 1.0.2 =
* Fixed issue of invalid permissions to view the settings page.
* Removed references to WPMU as that needs to be further tested.
* Updated installation notes.

= Version 1.0.1 =
* Updated the README file with correct links and the Tested up to value.

= Version 1.0.0 =
* First release

== Frequently Asked Questions ==

= HELP, I'm still getting spam! =
There are four common issues that make SpamCaptcher appear to be broken:

1. **Moderation Emails**: SpamCaptcher marks comments as spam, so even though the comments don't actually get posted, you will be notified of what is supposedly new spam. It is recommended to turn off moderation emails with SpamCaptcher.
2. **Akismet Spam Queue**: Again, because SpamCaptcher marks comments with a wrongly entered CAPTCHA as spam, they are added to the spam queue. These comments however weren't posted to the blog so SpamCaptcher is still doing it's job. It is recommended to either ignore the Spam Queue and clear it regularly or disable Akismet completely. SpamCaptcher takes care of all of the spam created by bots, which is the usual type of spam. The only other type of spam that would get through is human spam, where humans are hired to manually solve CAPTCHAs. If you still get spam while only having SpamCaptcher enabled, you could be a victim of the latter practice. If this is the case, you should force TrustMe Account authentication.
3. **Trackbacks and Pingbacks**: SpamCaptcher can't do anything about pingbacks and trackbacks. You can disable pingbacks and trackbacks in Options > Discussion > Allow notifications from other Weblogs (Pingbacks and trackbacks).
4. **Human Spammers**: Some people are actually paid to solve CAPTCHAs so that spam can get through. The good news is that you can use our TrustMe Account service to fight back against these spammers and using it couldn't be simpler. Just go to your Settings page for your SpamCaptcher plugin and check the Force TrustMe Account checkbox. 

= Do CAPTCHAs really fight off spam? =

Absolutely. To keep the cost of spamming low spammers use automated bots to push as much spam as possible out across the web. Thus, by keeping the bots out you force spammers to use humans to push out spam which significantly drives up the cost of spam and reduces the frequency of it.

= What is this TrustMe Account thing all about? =

Our TrustMe Account service is designed to fight back against Human CAPTCHA Solvers while rewarding legitimate users. It fights off the Human CAPTCHA Solvers by increasing the cost to them to spam. It rewards legitimate users by allowing them to bypass CAPTCHAs as long as they don't spam. For more detailed information check out our [TrustMe Account documentation](http://www.spamcaptcher.com/documentation/trustMeAccountDetails.jsp#details_trust_me_account).

= Do you have any other documentation? =

[Yes we do](http://www.spamcaptcher.com/documentation)

== Demo ==
You can see a live demo of our CAPTCHA and TrustMe Account services on our website's [demo page](http://www.spamcaptcher.com/captcha/Demo.action).