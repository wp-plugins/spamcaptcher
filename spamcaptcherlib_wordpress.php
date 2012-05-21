<?php
class SpamCaptcher
{
    /**
	 *
	 */
    private $isValid = false;
	
	/**
	 *
	 */
    private $spamScore = 0;
	
	/**
	 *
	 */
    private $accountID = "663d6a9abf99c7635619f2f7657c02fb913854fb57293923";
	
	/**
	 *
	 */
    private $privateKey = "098f6bcd4621d373cade4e832627b4f6";
	
	/**
	 *
	 */
	private $languageOrFrameworkID = 'wp';
	
	/**
	 *
	 */
	private $languageOrFrameworkVersion = "1.1.0";
	
	/**
	 *
	 */
    private $sessionID;
	
	/**
	 *
	 */
    private $customerSessionID;
	
	/**
	 *
	 */
    private $initSettings = "{}";
	
	/**
	 *
	 */
    private $useSSL = true;
	
	/**
	 *
	 */
    private $forceTrustMeAccount = false;
	
	/**
	 *
	 */
    private $allowTrustMeAccount = true;
	
	/**
	*
	*/
	private $overwriteGlobalTMASettings = false;
	
	/**
	 *
	 */
    private $MAX_PASSABLE_SCORE = 35;
	
	/**
	 *
	 */
    private $MAX_MODERATE_SCORE = 99;
	
	/**
	 *
	 */
    private $timeToCompleteForm = 300;
	
	/**
	 *
	 */
    public static $SHOULD_PASS = 0;
	
	/**
	 *
	 */
    public static $SHOULD_MODERATE = 1;
	
	/**
	 *
	 */
    public static $SHOULD_DELETE = 2;
	
	/**
	 *
	 */
    public static $FLAG_FOR_MULTI_POST = 1;
	
	/**
	 *
	 */
    public static $FLAG_FOR_VIOLATES_TOS = 2;
	
	/**
	 *
	 */
    public static $FLAG_FOR_SPAM = 3;
	
	/**
	 *
	 */
    public static $USER_ACTION_ACCOUNT_REGISTRATION = "ar";
	
	/**
	 *
	 */
    public static $USER_ACTION_FORGOT_PASSWORD = "fp";
	
	/**
	 *
	 */
    public static $USER_ACTION_CHANGE_SETTINGS = "cs";
	
	/**
	 *
	 */
    public static $USER_ACTION_UNLOCK_ACCOUNT = "ua";
	
	/**
	 *
	 */
    public static $USER_ACTION_ACCOUNT_LOGIN = "al";
	
	/**
	 *
	 */
    public static $USER_ACTION_CREATE_POST = "cp";
	
	/**
	 *
	 */
    public static $USER_ACTION_LEAVE_COMMENT = "lc";
	
	/**
	 *
	 */
    public static $USER_ACTION_UPLOAD_DATA = "ud";
	
	/**
	 *
	 */
    public static $USER_ACTION_DELETE_DATA = "dd";
	
	/**
	 *
	 */
    public static $USER_ACTION_VIEW_DATA = "vd";
	
	/**
	 *
	 */
    public static $USER_ACTION_MAKE_PURCHASE = "mp";
	
	/**
	 *
	 */
    public static $USER_ACTION_CAST_VOTE = "cv";
	
	/**
	 *
	 */
    private $recommendedAction = 1; // default to moderate
	
	/**
	 *
	 */
    private $baseURL = "api.spamcaptcher.com";
	
	/**
	*
	*/
	private $userAction = "";
	
	/**
	 *
	 */
	public function __construct($accID, $pwd) {
	   $this->setAccountID($accID);
	   $this->setPrivateKey($pwd);
	   $this->useSSL = $this->is_ssl_capable();
	}
   
	/**
	 *
	 */
	public function setAccountID($accID){
		$this->accountID = $accID;
	}
	
	public function setPrivateKey($pwd){
		$this->privateKey = $pwd;
	}
	
	public function setCustomerSessionID($csessID){
		$this->customerSessionID = $csessID;
	}

	public function getCustomerSessionID(){
		return $this->customerSessionID;
	}
	
	public function setSessionID($sessID){
		$this->sessionID = $sessID;
	}

	public function getSessionID(){
		return $this->sessionID;
	}
	
	public function getSettings(){
		return $this->initSettings;
	}
	
	public function setSettings($settings){
		$this->initSettings = $settings;
	}
	
	public function setMinModerationScore($minMod){
		$this->MAX_PASSABLE_SCORE = $minMod - 1;
	}
	
	public function setMaxModerationScore($maxMod){
		$this->MAX_MODERATE_SCORE = $maxMod;
	}
	
	public function getIsValid(){
		return $this->isValid;
	}
	
	public function getRecommendedAction(){
		return $this->recommendedAction;
	}
	
	public function getSpamScore(){
		return $this->spamScore;
	}
	
	public function getUserAction(){
		return $this->userAction;
	}
	
	public function setUserAction($user_action){
		$this->userAction = $user_action;
	}
	
	public function getUseSSL(){
		return $this->useSSL;
	}
	
	public function setUseSSL($use_ssl){
		$this->useSSL = $use_ssl;
	}
	
	public function setAllowTrustMeAccount($allow_tma){
		$this->allowTrustMeAccount = $allow_tma;
		$this->overwriteGlobalTMASettings = true;
	}
	
	public function setForceTrustMeAccount($force_tma){
		$this->forceTrustMeAccount = $force_tma;
		$this->overwriteGlobalTMASettings = true;
	}
	
	public function getCaptcha(){
		return "<script type=\"text/javascript\">var spamCaptcher ={settings : " . $this->initSettings . "};spamCaptcher.settings.accountID = \"" . $this->accountID . "\";</script><script type=\"text/javascript\" src=\"http" . ($this->useSSL ? "s" : "") . "://api.spamcaptcher.com/initCaptcha.js\"></script><noscript>SpamCaptcher NoScript Session:&nbsp;<input type=\"text\" name=\"spamCaptcherSessionID\" /><br /><iframe height=\"275px\" width=\"500px\" src=\"http" . ($this->useSSL ? "s" : "") . "://api.spamcaptcher.com/noscript/getCaptcha.jsp?k=" . $this->accountID ."&atma=" . ($this->allowTrustMeAccount ? "1" : "0") . "&ftma=" . ($this->forceTrustMeAccount ? "1" : "0") . "&ogtmas=" . ($this->overwriteGlobalTMASettings ? "1" : "0") . "\"><strong>Please upgrade your browser to one that supports iframes or enable JavaScript.</strong></iframe></noscript>";
	}
   
   private function postData($host, $path, $useSSL, $data){
		// define port and protocol
		$port = 443;
		$protocol = "ssl://";
		if (!$useSSL){
			$port = 80;
			$protocol = "";
		}
		
		$poststring = "";
		foreach ($data as $key => $val){
			// build the string of data to use in POST
			if (is_array($val)){
				foreach ($val as $sub_key => $sub_val){
					$poststring .= urlencode($key) . "=" . urlencode($sub_val) . "&";
				}
			}else{
				$poststring .= urlencode($key) . "=" . urlencode($val) . "&";
			}
		}
		// strip off trailing ampersand
		$poststring = substr($poststring, 0, -1);
		
		// open the socket
		$fp = fsockopen($protocol . $host, $port, $errno, $errstr, $timeout = 30);
		
		if (!$fp){
			// couldn't open socket so returning no data
			return null;
		}
		
		// send the data to the server
		fputs($fp, "POST $path HTTP/1.1\r\n");
		fputs($fp, "Host: $host\r\n"); 
		fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n"); 
		fputs($fp, "Content-length: " . strlen($poststring) . "\r\n"); 
		fputs($fp, "Connection: close\r\n\r\n"); 
		fputs($fp, $poststring . "\r\n\r\n"); 

		$header = "";
		$body = "";
		
		// grab the header data ... not currently used but might be in the future
		do 
		{
			$header .= fgets ( $fp, 128 );
		} while ( strpos ( $header, "\r\n\r\n" ) === false ); // loop until the end of the header
		
		// grab the body data ... this is what is returned
		while ( ! feof ( $fp ) )
		{
			$body .= fgets ( $fp, 128 );
		}
		
		// close socket
		fclose($fp); 
		
		return $body;
   }
   
   public function validate($args){
		$responseReceived = false;
		if (!(isset($this->sessionID))){
			if ($this->serverDownShouldModerate()){
				// couldn't access the server, moderate the session
				$this->recommendedAction = self::$SHOULD_MODERATE;
			}else{
				// no session ID but the server hasn't experienced downtime.
				$this->recommendedAction = self::$SHOULD_DELETE;
			}
		}else{
			$args['lofi'] = $this->languageOrFrameworkID;
			$args['lofv'] = $this->languageOrFrameworkVersion;
			$args['k'] = $this->accountID;
			$args['pwd'] = $this->privateKey;
			$args['ua'] = $this->userAction;
			$xmlresponse = $this->postData($this->baseURL, "/validate", $this->useSSL, $args);
			if ($xmlresponse){
				$doc = DOMDocument::loadXML($xmlresponse);
				if ($doc){
					$isValidResponse = $doc->getElementsByTagName('isValid');
					if (!($isValidResponse && $isValidResponse->item(0))){
						// got a response but it isn't in the expected format
						$this->recommendedAction = self::$SHOULD_MODERATE;
					}else{
						// parse out result from spamcaptcher server
						$this->spamScore = $doc->getElementsByTagName('spamScore')->item(0)->nodeValue;
						$this->isValid = $this->strToBoolean($doc->getElementsByTagName('isValid')->item(0)->nodeValue);
						$responseReceived = true;
					}
				}else{
					// got a response but it isn't in the expected format
					$this->recommendedAction = self::$SHOULD_MODERATE;
				}
			}else{
				// couldn't access the server, moderate the session
				$this->recommendedAction = self::$SHOULD_MODERATE;
			}
		}
		if ($responseReceived){
			if (!$this->isValid){
				// CAPTCHA was NOT solved correctly AND no TrustMe Account was used
				$this->recommendedAction = self::$SHOULD_DELETE;
			}else{
				if ($this->spamScore > $this->MAX_MODERATE_SCORE){
					// SpamScore is too high
					$this->recommendedAction = self::$SHOULD_DELETE;
				}elseif ($this->spamScore > $this->MAX_PASSABLE_SCORE){
					// SpamScore is questionable
					$this->recommendedAction = self::$SHOULD_MODERATE;
				}else{
					// Goldilocks is happy with the SpamScore
					// Yes yes, technically one of the scores
					// should have been "too low" for me to make
					// a Goldilocks reference ... I don't care.
					$this->recommendedAction = self::$SHOULD_PASS;
				}
			}
		}
		return $this->recommendedAction;
   }
   
   public function flag($session_id, $csess_id, $flagType){
		$args = array (
			'id' => $session_id,
			'c' => $csess_id,
			'k' => $this->accountID,
			'pwd' => $this->privateKey,
			'lofi' => $this->languageOrFrameworkID,
			'lofv' => $this->languageOrFrameworkVersion,
			'f' => "$flagType"
		);
		$xmlresponse = $this->postData($this->baseURL, "/flag", $this->useSSL, $args);
   }
   
   /**
	* Encodes the given data into a query string format
	* @param $data - array of string elements to be encoded
	* @return string - encoded request
	*/
	private function spamcaptcher_qsencode ($data) {
		$req = "";
		if (!is_null($data)){
			foreach ( $data as $key => $value ){
				$strData = '';
				if (!is_null($value)){
					if (is_string($value)){
						$strData = $key . "=" . urlencode( stripslashes($value) ) . '&';
					}elseif (is_array($value)){
						foreach ($value as $val){
							$strData .= $key . "=" . urlencode( stripslashes($val) ) . '&';
						}
					}
				}
				$req .= $strData;
			}

			// Cut the last '&'
			$req=substr($req,0,strlen($req)-1);
		}
		return $req;
	}
	
	public function serverDownShouldModerate(){
		$args = array (
			'k' => $this->accountID,
			'pwd' => $this->privateKey,
			'lofi' => $this->languageOrFrameworkID,
			'lofv' => $this->languageOrFrameworkVersion
		);
		$xmlresponse = $this->postData($this->baseURL,"/checkStatus",$this->useSSL,$args);
		$retVal = false;
		if ($xmlresponse){
			$doc = DOMDocument::loadXML($xmlresponse);
			if ($doc){
				$isRunningResponse = $doc->getElementsByTagName('isRunning');
				if (!($isRunningResponse && $isRunningResponse->item(0))){
					$retVal = true;
				}else{
					$retVal = !($this->strToBoolean($isRunningResponse->item(0)->nodeValue));
					if (!$retVal){
						$secondsSinceLastDowntime = $doc->getElementsByTagName('SecondsSinceLastDowntime')->item(0)->nodeValue;
						$secondsSinceLastRestart = $doc->getElementsByTagName('SecondsSinceLastRestart')->item(0)->nodeValue;
						if ($this->timeToCompleteForm > $secondsSinceLastRestart && $this->timeToCompleteForm < $secondsSinceLastDowntime){
							$retVal = true;
						}
					}
				}
			}else{
				$retVal = true;
			}
		}else{
			$retVal = true;
		}
		return $retVal;
	}
	
	private function strToBoolean($value) {
		if ($value && strtolower($value) === "true") {
		  return true;
		} else {
		  return false;
		}
	}
	
	private function is_ssl_capable(){
		return defined('OPENSSL_VERSION_NUMBER') && is_numeric(OPENSSL_VERSION_NUMBER);
	}
}

function spamcaptcher_get_captcha($accountID, $settings = "{}"){
	$sc_obj = new SpamCaptcher($accountID, '');
	$sc_obj->setSettings($settings);
	return $sc_obj->getCaptcha();
}

function spamcaptcher_validate($accountID, $privateKey, $forceTrustMeAccount = false, $allowTrustMeAccount = true, $csessID = null){
	$sessionID = null;
	$answer = null;
	if ($_POST["spamCaptcherSessionID"]){
		$sessionID = $_POST["spamCaptcherSessionID"];
		$answer = $_POST["spamCaptcherAnswer"];
	}elseif ($_GET["spamCaptcherSessionID"]){
		$sessionID = $_GET["spamCaptcherSessionID"];
		$answer = $_GET["spamCaptcherAnswer"];
	}
	$args = array (
		'ip' => $_SERVER['REMOTE_ADDR'],
		'ftma' => ($forceTrustMeAccount ? "1" : "0"),
		'atma' => ($allowTrustMeAccount ? "1" : "0"),
		'id' => $sessionID,
		'spamCaptcherAnswer' => $answer
	);
	$sc_obj = new SpamCaptcher($accountID, $privateKey);
	$sc_obj->setCustomerSessionID($csessID);
	$sc_obj->setSessionID($sessionID);
	return $sc_obj->validate($args);
}

function spamcaptcher_flag($sessionID, $csessID, $flagType){
	$sc_obj = new SpamCaptcher();
	$sc_obj->flag($sessionID, $csessID, $flagType);
}

?>
