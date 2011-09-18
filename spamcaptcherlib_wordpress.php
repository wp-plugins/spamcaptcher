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
    private $accountPassword = "098f6bcd4621d373cade4e832627b4f6";
	
	/**
	 *
	 */
	private $languageOrFrameworkID = 2;
	
	/**
	 *
	 */
	private $languageOrFrameworkVersion = "1.0.3";
	
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
    private $recommendedAction = 1; // default to moderate
	
	/**
	 *
	 */
    private $baseURL = "http://api.spamcaptcher.com/";
	
	/**
	 *
	 */
	public function __construct($accID, $pwd) {
	   $this->setAccountID($accID);
	   $this->setPassword($pwd);
	}
   
	/**
	 *
	 */
	public function setAccountID($accID){
		$this->accountID = $accID;
	}
	
	public function setPassword($pwd){
		$this->accountPassword = $pwd;
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

	public function getCaptcha(){
		return "<script type=\"text/javascript\">var spamCaptcher ={settings : " . $this->initSettings . "};spamCaptcher.settings.accountID = \"" . $this->accountID . "\";</script><script type=\"text/javascript\" src=\"http" . ($this->useSSL ? "s" : "") . "://api.spamcaptcher.com/initCaptcha.js\"></script><noscript>SpamCaptcher NoScript Session:&nbsp;<input type=\"text\" name=\"spamCaptcherSessionID\" /><br /><iframe src=\"http" . ($this->useSSL ? "s" : "") . "://api.spamcaptcher.com/noscript/getCaptcha.jsp?k=" . $this->accountID ."\"><strong>Please upgrade your browser to one that supports iframes or enable JavaScript.</strong></iframe></noscript>";
	}
   
   public function validate($args){
		$answerSet = false;
		if (!(isset($this->sessionID))){
			if ($this->serverDownShouldModerate()){
				$this->recommendedAction = self::$SHOULD_MODERATE;
			}else{
				$this->recommendedAction = self::$SHOULD_DELETE;
			}
			$answerSet = true;
		}else{
			$args['lofi'] = $this->languageOrFrameworkID;
			$args['lofv'] = $this->languageOrFrameworkVersion;
			$strURL = $this->baseURL . "validate?k=" . $this->accountID . "&pwd=" . $this->accountPassword . "&" . $this->spamcaptcher_qsencode($args);
			$xmlresponse = file_get_contents($strURL);
			if ($xmlresponse){
				$doc = DOMDocument::loadXML($xmlresponse);
				if ($doc){
					$isValidResponse = $doc->getElementsByTagName('isValid');
					if (!($isValidResponse && $isValidResponse->item(0))){
						$this->recommendedAction = self::$SHOULD_MODERATE;
					}else{
						$this->spamScore = $doc->getElementsByTagName('spamScore')->item(0)->nodeValue;
						$this->isValid = $this->strToBoolean($doc->getElementsByTagName('isValid')->item(0)->nodeValue);
					}
				}else{
					$this->recommendedAction = self::$SHOULD_MODERATE;
				}
			}else{
				//couldn't access the server, moderate the session
				$this->recommendedAction = self::$SHOULD_MODERATE;
			}
		}
		if (!$answerSet){
			if (!$this->isValid){
				//CAPTCHA was NOT solved correctly
				$this->recommendedAction = self::$SHOULD_DELETE;
			}else{
				if ($this->spamScore > $this->MAX_MODERATE_SCORE){
					//SpamScore is too high
					$this->recommendedAction = self::$SHOULD_DELETE;
				}elseif ($this->spamScore > $this->MAX_PASSABLE_SCORE){
					//SpamScore is questionable
					$this->recommendedAction = self::$SHOULD_MODERATE;
				}else{
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
			'pwd' => $this->accountPassword,
			'lofi' => $this->languageOrFrameworkID,
			'lofv' => $this->languageOrFrameworkVersion,
			'f' => "$flagType"
		);
		$strArgs = $this->spamcaptcher_qsencode($args);
		$strURL = $this->baseURL . "flag?" . $this->spamcaptcher_qsencode($args);
		$xmlresponse = file_get_contents($strURL);
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
			'pwd' => $this->accountPassword,
			'lofi' => $this->languageOrFrameworkID,
			'lofv' => $this->languageOrFrameworkVersion
		);
		$strArgs = $this->spamcaptcher_qsencode($args);
		$strURL = $this->baseURL . "checkStatus?" . $this->spamcaptcher_qsencode($args);
		$xmlresponse = file_get_contents($strURL);
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
}

function spamcaptcher_get_captcha($settings = "{}"){
	$sc_obj = new SpamCaptcher();
	$sc_obj->setSettings($settings);
	return $sc_obj->getCaptcha();
}

function spamcaptcher_validate($forceSpamFreeAccount = false, $allowSpamFreeAccount = true, $csessID = null){
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
		// when testing against localhost the ip address may need to be hardcoded to the correct value otherwise 127.0.0.1 might get sent
		'ip' => $_SERVER['REMOTE_ADDR'],
		'fsfa' => ($forceSpamFreeAccount ? "1" : "0"),
		'asfa' => ($allowSpamFreeAccount ? "1" : "0"),
		'id' => $sessionID,
		'spamCaptcherAnswer' => $answer
	);
	$sc_obj = new SpamCaptcher();
	$sc_obj->setCustomerSessionID($csessID);
	$sc_obj->setSessionID($sessionID);
	return $sc_obj->validate($args);
}

function spamcaptcher_flag($sessionID, $csessID, $flagType){
	$sc_obj = new SpamCaptcher();
	$sc_obj->flag($sessionID, $csessID, $flagType);
}

?>
