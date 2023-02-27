<?php

// ProcessForm 3.0.18 from MindPalette LLC - copyright 2014 (last updated May 10, 2014)
// @$suppress = error_reporting(0);
// @$suppress = ini_set('display_errors', '0');
unset($recipients);
$badIPs = array();

// -------------------------------------------------------------------------------------------------------------------------------
//   START USER-EDITABLE SETTINGS...
// -------------------------------------------------------------------------------------------------------------------------------

	// LIST OF EMAIL RECIPIENTS...
	$recipients[0] = "moralesgarp2@lopers.unk.edu";
	$recipients[1] = "hoggn@unk.edu";
	$recipients[2] = "email_address_here";
	$recipients[3] = "email_address_here";
	$recipients[4] = "email_address_here";
	$recipients[5] = "email_address_here";
	$recipients[6] = "email_address_here";
	$recipients[7] = "email_address_here";
	$recipients[8] = "email_address_here";
	$recipients[9] = "email_address_here";

// ...add as many "$recipients" lines as you need, just give each address a different number inside the [brackets]

// OPTIONAL SETTINGS -------------------------------------------------------------------------------------------------------------
	
	$dateFormat = 1;  	// see options listed below (1, 2 or 3)...
	
	/*
	Date/Time Formatting Options (enter the key number, 1 - 3)...
	1 = "January 4, 2004 @ 6:00 pm";
	2 = "2004-01-04 @ 18:00";
	3 = "4 January 2004, 18:00";
	*/
	
	$replaceUnderscores = true; 	// enter true or false - changes underscores to spaces in field names if true
	$initialCaps = true; 			// enter true or false - capitalizes the first letter of every word in field names if true
	$attachmentMax = 2000; 			// in KB (1000 = 1 MB) Maximum file attachment/upload size
	$serverTimeOffset = 0; 			// number of hours to add or subtract from server time to get your local time (-1, +2, 0, etc.)
	$doubleSpaceEmail = true;		// true to double space between fields in email message. false for single space.
	$forceAttachText = true;		// false to let text attachments become email text. true to force as attached text file.
	unset($referrerCheck);
	$referrerCheck = true;			// make sure form is on same server as script (recommended - to avoid hacking)
	$redirect = "thanks.php";					// enter the path (include style) or URL (query style) of the confirmation page
	
	// Styling for Thank You / Confirmation page (in CSS)...
	
	// Page background color and styling for <body> <div> <td> <p>...
	$pageStyle = 'background-color: white';
	
	// Main page text...
	$MPinfo = 'color: black; font-size: 13px; font-family: Verdana, Arial, Helvetica, sans-serif';
	
	// Form field names and values...
	$MPFieldNames = 'color: black; font-weight: bold; font-size: 12px; font-family: Verdana, Arial, Helvetica, sans-serif';
	$MPFieldValues = 'color: #00327d; font-style: normal; font-size: 12px; font-family: Verdana, Arial, Helvetica, sans-serif';
	
	// Headline (Thank You) text...
	$MPthankyou = 'color: #00327d; font-size: 36px; font-family: "Times New Roman", Times, Georgia, serif';
	
	// Sub-Head (sent from and date/time text)...
	$MPsubhead = 'color: #7d8287; font-size: 11px; font-family: Verdana, Arial, Helvetica, sans-serif';
	
	// Error message text...
	$MPerror = 'color: #c80019; font-size: 13px; font-family: Verdana, Arial, Helvetica, sans-serif';
	$MPerrorlist = 'color: #c80019; font-size: 12px; font-family: Verdana, Arial, Helvetica, sans-serif';
	
	// JavaScript back to form text...
	$MPsmall = 'color: black; font-size: 10px; font-family: Verdana, Arial, Helvetica, sans-serif';
	
	// Text links (back to home)...
	$MPlink = 'color: #00327d; font-size: 13px; font-family: Verdana, Arial, Helvetica, sans-serif; text-decoration: none';
	
	// Text link rollovers...
	$MPlink_hover = 'color: #0093ff; font-size: 13px; font-family: Verdana, Arial, Helvetica, sans-serif; text-decoration: underline';
	
	// Credit text...
	$MPcredit = 'color: #7d8287; font-size: 9px; font-family: Verdana, Arial, Helvetica, sans-serif';

// The following optional settings are only used if writing form results to a mysql database -------------------------------------

	$mysql_access_file = "ProcessFormDB.php";
	// between the double quotes, enter the path to your file that establishes a MySQL database connection for the script to use.
	// there is a default file you can use as a template in the Extras folder included with this download - "ProcessFormDB.php"
	// it's recommended for security reasons to keep the file above your web root directory. Consult the manual for details.

// The following optional settings are only used if writing form results to a text file ------------------------------------------
	
	$write_to_file = '';
	$includeFieldNames = false;		// true if field names are written into text file, false if only values
	
	// if $includeFieldName = true, then specify character(s) to separate names from values...
	$sepNameVals = ": ";			// character(s) to separate field names and values (": " = colon and space)
	
	$sepFormFields = "\t";			// character(s) to separate form fields ("\t" = tab)
	$sepFormEntries = "\n";			// character(s) to separate form entries ("\n" = unix line break - or a RETURN)
	
	// the values below say what character to replace special characters with above, if used in text entry form fields, etc...
	$changeNameVals = " ";			// replace extra name/value separation characters with this (a space by default)
	$changeFormFields = " ";		// replace extra form field separation characters with this (a space by default)
	$changeFormEntries = " ";		// replace extra form entry separation characters with this (a space by default)

// the following optional settings determine the confirmation message the visitors see after a successful submission -------------

	// Thank you / confirmation title...
	$confirmMsgTitle = "Thank You!";
	
	// Thank you / confirmation message text...
	// NOTE: do not change the "[message recipient]" text (though it can be moved)
	// the "[message recipient]" text will be replaced by either the recipient email address, or
	// the value of the "recipient_name" form field. If you want neither, just delete the "[message recipient]" text.
	$confirmMsgText = "Below is the information you submitted to [message recipient]:";

// the following optional settings determine the error message the visitors see when the script finds a problem ------------------

	// error message for when "required" fields are left blank...
	// NOTE: will be followed by list of field names
	$reqErrMsg = "The following REQUIRED field(s) were left empty:";
	
	// error message for when "email_only" fields aren't valid email formats...
	// NOTE: do not change the "[email field]" text (though it can be moved) - will be replaced with offending field name
	$emailErrMsg = "The value entered for [email field] does not appear to be a valid email address.";
	
	// error message for when "numbers_only" fields contain more than just numbers...
	// NOTE: do not change the "[number field]" text (though it can be moved) - will be replaced with offending field name
	$numErrMsg = "The value entered for [number field] can only be numbers.";
	
	// error message for when "letters_only" fields contain more than just numbers...
	// NOTE: do not change the "[letter field]" text (though it can be moved) - will be replaced with offending field name
	$letterErrMsg = "The value entered for [letter field] can only be upper or lower case letters.";
	
	// error message for when "force_match" field entries do not match...
	// NOTE: will be followed by list of field names
	$matchErrMsg = "The following field values must match:";
	
	// error message for if file upload fails for attachment...
	// NOTE: do not change the "[file name]" text (though it can be moved) - will be replaced with failed file name
	$fileErrMsg = "[file_name] failed to be uploaded. Please check file and try again.";
	
	// error message for if file attachment is too large (larger than $attachmentMax size)...
	// NOTE: do not change "[file name]" or "[max size]" text (though it can be moved) - will be replaced with offending field name and max size
	$sizeErrMsg = "An attached file [file name] is over the maximum size ([max size])";
	
	// error message for if the email fails to be sent due to server error...
	$mailErrMsg = "Server Error - the form was processed successfully, but email could not be sent.";
	
	// error message in case MySQL database is down or connection info is incorrect...
	$mysqlErrMsg1 = "Error connecting to MySQL database - settings are incorrect or server is down, sorry.";
	
	// error message in case form data could not be written into the MySQL database...
	$mysqlErrMsg2 = "Error writing form information into database (server error).";
	
	// error message in case form data could not be updated the MySQL database...
	$mysqlErrMsg3 = "Error updating record in database (server error).";
	
	// error message in case form data could not be written into specified text file...
	$textErrMsg = "Form results failed to be saved to text file (server error).";

// line ending character (for either unix/linux or windows servers)...
$le = "\n";		// change to "\r\n" for windows server, "\n" for unix/linux server

// specify a sender address to send to server...
$MPForceSender = '';

// spam guard variables...
$MPSendIP = false;	// true to send IP address of visitor in email
$MPCheckIP = true;	// true to check IP address against blacklisted addresses (below)
$MPHideIP = true;	// true to hide the visitor IP field from the confirmation page
// reject the following IP addresses...
$badIPs[] = '';  // add as many of these lines as you need, IP address between single quotes

// form security settings (supply path to security script if entry is required)
$securityFile = "";
$securityError = "Form security error (security image mismatch or unauthorized form page).";

// -------------------------------------------------------------------------------------------------------------------------------
//   END USER-EDITABLE SETTINGS (you shouldn't have to change the rest of this PHP code, but you're welcome to try)...
// -------------------------------------------------------------------------------------------------------------------------------

// determine global variables based on PHP version...
$MPPostVars = array();
if (is_array($_POST)) $MPPostVars = $_POST;
else if (is_array($HTTP_POST_VARS)) $MPPostVars = $HTTP_POST_VARS;
$MPPostFiles = array();
if (is_array($_FILES)) $MPPostFiles = $_FILES;
else if (is_array($HTTP_POST_FILES)) $MPPostFiles = $HTTP_POST_FILES;
$MPServerVars = array();
if (is_array($_SERVER)) $MPServerVars = $_SERVER;
else if (is_array($HTTP_SERVER_VARS)) $MPServerVars = $HTTP_SERVER_VARS;

// Determine if a form has been submitted to the script yet...
$formSubmitted = false;
if (is_array($MPPostVars)) if (count($MPPostVars) > 0) $formSubmitted = true;

// Set default status for error variable...
$errors = "";
$printHTML = true;

if ($formSubmitted == true) {
	
	// Validate form security if required...
	if ($securityFile) {
		if (file_exists($securityFile)) {
			require_once($securityFile);
			if (!$MPFS->SecurityCheck()) $errors .= $securityError."<br>$le";
			} else $errors .= "Could not locate form security script file.<br>$le";
		}

	// Adjust the server time and build selected date/time text format...
	$adjustedTime = time() + ($serverTimeOffset * 3600);
	if ($dateFormat == 2)
		$dateTime = date("Y-m-d @ H:i", $adjustedTime);
		else if ($dateFormat == 3) $dateTime = date("d F Y, H:i", $adjustedTime);
		else $dateTime = date("F j, Y @ g:i a", $adjustedTime);

// -------------------------------------------------------------------------------------------------------------------------------
//   DECLARE CUSTOM FUNCTIONS...
// -------------------------------------------------------------------------------------------------------------------------------
	
	// Build function for making form field names more readable...
	function MPAdjustFields($thisField) {
		global $replaceUnderscores;
		global $initialCaps;
		if ($replaceUnderscores == true) $thisField = str_replace("_", " ", $thisField);
		if ($initialCaps == true) $thisField = ucwords($thisField);
		return trim($thisField);
		}
	
	// Build function for comparing referring server to script server...
	function MPCompareServers($thisURL) {
		$thisURL = str_replace("http://", "", $thisURL);
		$thisURL = str_replace("https://", "", $thisURL);
		$thisURL = str_replace("www.", "", $thisURL);
		if ($thisURL != "") $urlArray = explode("/", $thisURL);
		return $urlArray[0];
		}
	
	// Build function for converting array submissions into comma-separated strings, up to 2 levels...
	function MPFixArrays($thisValue) {
		if (is_array($thisValue)) {
			for ($n=0; $n<count($thisValue); $n++) {
				if (isset($thisValue[$n])) {
					if (is_array($thisValue[$n])) $thisValue[$n] = implode(", ", $thisValue[$n]);
					}
				}
			$thisValue = implode(", ", $thisValue);
			}
		return $thisValue;
		}
	
	// Build function for sending form data to redirect page via query string...
	function MPParseRedirectData($thisString) {
		$thisString = stripslashes($thisString);
		$thisString = str_replace("&", "and", $thisString);
		$thisString = str_replace("=", "equals", $thisString);
		$thisString = str_replace("\r\n", "<br>", $thisString);
		$thisString = str_replace("\n", "<br>", $thisString);
		$thisString = str_replace("\r", "<br>", $thisString);
		$thisString = rawurlencode($thisString);
		return $thisString;
		}
	
	// Build function for writing form field names and values into HTML...
	function MPNameValueHTML($thisPair, $fieldCSS, $valueCSS) {
		$thisFieldName = "";
		$thisFieldValue = "";
		if (substr($fieldCSS, 0, 1) == ".") $fieldCSS = substr($fieldCSS, 1);
		if (substr($valueCSS, 0, 1) == ".") $valueCSS = substr($valueCSS, 1);
		if ($thisPair['value'] != "") {
			$thisFieldName = MPAdjustFields(stripslashes($thisPair['key']));
			$thisFieldName = htmlspecialchars($thisFieldName);
			$thisFieldName = str_replace(" ", "&nbsp;", $thisFieldName);
			$thisFieldName = "<span class=\"".$fieldCSS."\">$thisFieldName:</span>";
			$thisFieldValue = stripslashes($thisPair['value']);
			$thisFieldValue = htmlspecialchars($thisFieldValue);
			$thisFieldValue = str_replace("\r\n", "<br>", $thisFieldValue);
			$thisFieldValue = str_replace("\n", "<br>", $thisFieldValue);
			$thisFieldValue = str_replace("\r", "<br>", $thisFieldValue);
			$thisFieldValue = "<span class=\"".$valueCSS."\">$thisFieldValue</span>";
			}
		return array($thisFieldName, $thisFieldValue);
		}
	
	// Build function for writing field name/values into redirect page...
	function MPRedirectHTML($fieldCSS, $valueCSS) {
		global $displayArray;
		if (is_array($displayArray)) {
			if (count($displayArray) > 0) {
				$printHTML = "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\">$le";
				for ($n=0; $n<count($displayArray); $n++) {
					if ($displayArray[$n]['value'] != "") {
						$htmlPair = MPNameValueHTML($displayArray[$n], $fieldCSS, $valueCSS);
						$printHTML .= "<tr>$le<td align=\"right\" valign=\"top\" nobreak>".$htmlPair[0]."&nbsp;&nbsp;</td>$le";
						$printHTML .= "<td align=\"left\" valign=\"top\">".$htmlPair[1]."</td>$le<tr>$le";
						}
					}
				if ($printHTML != "<table width=\"450\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">$le") {
					$printHTML .= "</table>$le";
					print($printHTML);
					}
				}
			}
		}
	
	// Build MPRedirectHTML function alias for instructions error...
	function redirectHTML($fieldCSS, $valueCSS) {
		MPRedirectHTML($fieldCSS, $valueCSS);
		}
	
	// Build function for adding slashes regardless of "magic quotes"...
	function MPAddSlashes($thisString) {
		$thisString = stripslashes($thisString);
		$thisString = mysql_real_escape_string($thisString);
		return $thisString;
		}
	
	// Build function for checking required fields (any kind)...
	function MPCheckRequired($fieldList, $type, $return) {
		global $MPPostVars;
		global $MPPostFiles;
		$result = "";
		$result2 = "";
		if ($fieldList != "") {
			$groupValid = false;
			$tempArray = array();
			$requiredFields = explode(",", $fieldList);
			for ($n=0; $n<count($requiredFields); $n++) {
				$thisReqField = MPIgnoreBrackets($requiredFields[$n]);
				$tempArray[] = MPAdjustFields($thisReqField);
				$fieldValid = false;
				if ($thisReqField != "" AND isset($MPPostVars[$thisReqField])) {
					if ($MPPostVars[$thisReqField] != "") $fieldValid = true;
					} else if (isset($MPPostFiles[$thisReqField])) {
					if (is_uploaded_file($MPPostFiles[$thisReqField]['tmp_name'])) $fieldValid = true;
					}
				if ($fieldValid == true) $groupValid = true;
					else $result2 .= "<li><span class=\"MPerrorlist\">".MPAdjustFields($thisReqField)."</span></li>$le";
				}
			if ($type == "all") $result = $result2;
				else {
				if (!$groupValid AND count($tempArray) > 0) {
					if ($return == "single") {
						$result = $result2;
						} else {
						$tempString = implode(", ", $tempArray);
						$result .= "<li><span class=\"MPerrorlist\">One of the following: ".$tempString."</span></li>$le";
						}
					}
				}
			}
		return $result;
		}
	
	// Build function for determining field name without [] characters...
	function MPIgnoreBrackets($thisString) {
		$tempName = trim($thisString);
		$last2Chars = substr($tempName, (strlen($tempName)-2), 2);
		if ($last2Chars == "[]") $tempName = substr($tempName, 0, (strlen($tempName)-2));
		$thisString = $tempName;
		return $thisString;
		}
		
	// Build regular expression to check for valid email addresses and number fields...
	$emailPattern = "/^.+\@[A-Z0-9._-]+\.[A-Z]{1,8}/i";
	$numberPattern = "/^[0-9.]+$/";
	$letterPattern = "/^[A-Za-z]+$/";
	
// -------------------------------------------------------------------------------------------------------------------------------
//   TRY TO MAKE SURE WE'RE NOT GETTING HIJACKED...
// -------------------------------------------------------------------------------------------------------------------------------

	unset($confirmation);
	unset($confirmEmailAddr);
	unset($recipientString);
	unset($MPSubject);
	unset($MPEmailBody);
	unset($headers);
	unset($formServer);
	unset($thisServer);
	$formServer = isset($MPServerVars['HTTP_REFERER']) ? MPCompareServers($MPServerVars['HTTP_REFERER']) : "";
	$thisServer = isset($MPServerVars['HTTP_HOST']) ? MPCompareServers($MPServerVars['HTTP_HOST']) : "";
	if ($formServer != "" AND $thisServer != "" AND $formServer != $thisServer AND $referrerCheck == true)
		$errors .= "Error receiving form contents - your form must be on the same server as the script.<br>";

// -------------------------------------------------------------------------------------------------------------------------------
//   IMPORT AND PROCESS FORM...
// -------------------------------------------------------------------------------------------------------------------------------

	// Import special formatting fields if used...
	$recipient = (isset($MPPostVars['recipient'])) ? $MPPostVars['recipient'] : "";
	$subject = (isset($MPPostVars['subject'])) ? $MPPostVars['subject'] : "";
	$required = (isset($MPPostVars['required'])) ? $MPPostVars['required'] : "";
	$required_any = (isset($MPPostVars['required_any'])) ? $MPPostVars['required_any'] : "";
	$required_if = (isset($MPPostVars['required_if'])) ? $MPPostVars['required_if'] : "";
	$required_if_empty = (isset($MPPostVars['required_if_empty'])) ? $MPPostVars['required_if_empty'] : "";
	$required_if_any = (isset($MPPostVars['required_if_any'])) ? $MPPostVars['required_if_any'] : "";
	$required_if_any_empty = (isset($MPPostVars['required_if_any_empty'])) ? $MPPostVars['required_if_any_empty'] : "";
	$redirect_type = (isset($MPPostVars['redirect_type'])) ? $MPPostVars['redirect_type'] : "query";
	$sort = (isset($MPPostVars['sort'])) ? $MPPostVars['sort'] : "";
	$exclude = (isset($MPPostVars['exclude'])) ? $MPPostVars['exclude'] : "";
	$exclude_display = (isset($MPPostVars['exclude_display'])) ? $MPPostVars['exclude_display'] : "";
	$exclude_email = (isset($MPPostVars['exclude_email'])) ? $MPPostVars['exclude_email'] : "";
	$force_match = (isset($MPPostVars['force_match'])) ? $MPPostVars['force_match'] : "";
	$recipient_name = (isset($MPPostVars['recipient_name'])) ? $MPPostVars['recipient_name'] : "";
	$sender_name = (isset($MPPostVars['sender_name'])) ? $MPPostVars['sender_name'] : "";
	$sender_email = (isset($MPPostVars['sender_email'])) ? $MPPostVars['sender_email'] : "";
	$attach_text_file = (isset($MPPostVars['attach_text_file'])) ? $MPPostVars['attach_text_file'] : "";
	$force_format = (isset($MPPostVars['force_format'])) ? $MPPostVars['force_format'] : "";
	$write_to_mysql = (isset($MPPostVars['write_to_mysql'])) ? $MPPostVars['write_to_mysql'] : "";
	$mysql_update_field = (isset($MPPostVars['mysql_update_field'])) ? $MPPostVars['mysql_update_field'] : "";
	$mysql_update_value = (isset($MPPostVars['mysql_update_value'])) ? $MPPostVars['mysql_update_value'] : "";
	$numbers_only = (isset($MPPostVars['numbers_only'])) ? $MPPostVars['numbers_only'] : "";
	$letters_only = (isset($MPPostVars['letters_only'])) ? $MPPostVars['letters_only'] : "";
	$email_only = (isset($MPPostVars['email_only'])) ? $MPPostVars['email_only'] : "";
	$uppercase = (isset($MPPostVars['uppercase'])) ? $MPPostVars['uppercase'] : "";
	$lowercase = (isset($MPPostVars['lowercase'])) ? $MPPostVars['lowercase'] : "";
	$link_text = (isset($MPPostVars['link_text'])) ? $MPPostVars['link_text'] : "";
	$link_url = (isset($MPPostVars['link_url'])) ? $MPPostVars['link_url'] : "";
	
	// Attempt to detect and eliminate spamming attempts...
	if (isset($evilFound)) unset($evilFound);
	function MPSeeNoEvil($string) {
		$results = true;
		$string = trim(strtolower($string));
		$string = stripslashes($string);
		$string = str_replace("\r\n", "[evil]", $string);
		$string = str_replace("\r", "[evil]", $string);
		$string = str_replace("\n", "[evil]", $string);
		$string = str_replace("bcc:", "[evil]", $string);
		$string = str_replace("cc:", "[evil]", $string);
		if (stristr($string, '[evil]') !== false) $results = false;
		return $results;
		}
	if (!MPSeeNoEvil($subject)) $errors .= "This submission appears to be a spamming attempt.<br>$li";
	if (!MPSeeNoEvil($recipient_name)) $errors .= "This submission appears to be a spamming attempt.<br>$li";
	if (isset($MPPostVars[$sender_name])) if (!MPSeeNoEvil($MPPostVars[$sender_name]))
		$errors .= "This submission appears to be a spamming attempt.<br>$li";
	if (isset($MPPostVars[$sender_email])) if (!MPSeeNoEvil($MPPostVars[$sender_email]))
		$errors .= "This submission appears to be a spamming attempt.<br>$li";
	$ip = (isset($MPServerVars['REMOTE_ADDR'])) ? $MPServerVars['REMOTE_ADDR'] : '';
	if ($MPSendIP) $MPPostVars['visitor_IP'] = $ip;
	if ($MPCheckIP) {
		if (in_array($ip, $badIPs))
			$errors .= "This IP address ($ip) has been blacklisted due to spamming attempts.<br>$li";
		}
	if ($MPHideIP) {
		if ($exclude_display == '') $exclude_display = 'visitor_IP';
			else $exclude_display .= ', visitor_IP';
		}
	
	// Verify the "recipient" field...
	if ($recipient == "") {
		if ($write_to_mysql == "" AND $write_to_file == "")
			$errors .= "No \"recipient\" field was included, or the \"recipient\" value was empty.<br>$le";
		} else {
		$recipKeys = (!is_array($recipient)) ? explode(",", $recipient) : $recipient;
		$recipientArray = array();
		for ($n=0; $n<count($recipKeys); $n++) {
			$thisRecipKey = trim($recipKeys[$n]);
			if ($thisRecipKey != '') {
				$thisRecipValue = $recipients[$thisRecipKey];
				if ($thisRecipValue == "" OR $thisRecipValue == "email_address_here" OR $thisRecipValue == "address@yourdomain.com")
					$errors .= "No email address was found in the recipients list with key number \"$thisRecipKey\"<br>$le";
					else $recipientArray[] = $thisRecipValue;
				}
			}
		if (count($recipientArray) < 1)
			$errors .= "No \"recipient\" field was included, or the \"recipient\" value was empty.<br>$le";
		}
	
	$reqErrors = "";
	$reqErrors2 = "";
	
	// Verify "required" fields if specified...
	if ($required != "") $reqErrors .= MPCheckRequired($required, "all", "single");
	
	// Verify "required_any" fields if specified...
	if ($required_any != "") $reqErrors .= MPCheckRequired($required_any, "any", "group");
	
	// Verify "required_if" fields if specified...
	if ($required_if != "") {
		$runValidation = true;
		$tempPair = explode(";", $required_if);
		if (count($tempPair) == 2 AND trim($tempPair[0]) != "" AND trim($tempPair[1] != "")) {
			$conditionArray = explode(",", trim($tempPair[0]));
			for ($n=0; $n<count($conditionArray); $n++) {
				$testField = MPIgnoreBrackets($conditionArray[$n]);
				if ($testField != "") {
					$testValue = (isset($MPPostVars[$testField])) ? $MPPostVars[$testField] : "";
					if ($testValue == "") $runValidation = false;
					}
				}
			if ($runValidation == true) $reqErrors .= MPCheckRequired($tempPair[1], "all", "single");
			}
		}

	// Verify "required_if_any" fields if specified...
	if ($required_if_any != "") {
		$runValidation = false;
		$tempPair = explode(";", $required_if_any);
		if (count($tempPair) == 2 AND trim($tempPair[0]) != "" AND trim($tempPair[1] != "")) {
			$conditionArray = explode(",", trim($tempPair[0]));
			for ($n=0; $n<count($conditionArray); $n++) {
				$testField = MPIgnoreBrackets($conditionArray[$n]);
				if ($testField != "") {
					$testValue = (isset($MPPostVars[$testField])) ? $MPPostVars[$testField] : "";
					if ($testValue != "") $runValidation = true;
					}
				}
			if ($runValidation == true) $reqErrors .= MPCheckRequired($tempPair[1], "all", "single");
			}
		}
	
	// Verify "required_if_empty" fields if specified...
	if ($required_if_empty != "") {
		$runValidation = true;
		$tempPair = explode(";", $required_if_empty);
		if (count($tempPair) == 2 AND trim($tempPair[0]) != "" AND trim($tempPair[1] != "")) {
			$conditionArray = explode(",", trim($tempPair[0]));
			for ($n=0; $n<count($conditionArray); $n++) {
				$testField = MPIgnoreBrackets($conditionArray[$n]);
				if ($testField != "") {
					$testValue = (isset($MPPostVars[$testField])) ? $MPPostVars[$testField] : "";
					if ($testValue != "") $runValidation = false;
					}
				}
			// if ($runValidation == true) $reqErrors .= MPCheckRequired($tempPair[1], "all");
			if ($runValidation == true) {
				$group1 = MPCheckRequired($tempPair[1], "all", "single");
				if ($group1 != "") {
					$group2 = MPCheckRequired($tempPair[0], "all", "single");
					if ($reqErrors == "") $reqErrors2 .= "<br>&nbsp;<br>".$le;
					$reqErrors2 .= '
						<tr>
						<td colspan="3" align="left" valign="middle"><hr></td>
						</tr>
						<tr>
						<td align="left" valign="top">
						<ul class="MPerrorlist">
						'.$group1.'
						</ul>
						</td>
						<td align="left" valign="top" nowrap><span class="MPinfo">&nbsp;&nbsp;- or -</span></td>
						<td align="left" valign="top">
						<ul class="MPerrorlist">
						'.$group2.'
						</ul>
						</td>
						</tr>
						';
					}
				}
			}
		}
	
	// Verify "required_if_any_empty" fields if specified...
	if ($required_if_any_empty != "") {
		$runValidation = false;
		$tempPair = explode(";", $required_if_any_empty);
		if (count($tempPair) == 2 AND trim($tempPair[0]) != "" AND trim($tempPair[1] != "")) {
			$group2 = MPCheckRequired($tempPair[0], "all", "single");
			if ($group2 != "") $runValidation = true;
			if ($runValidation == true) {
				$group1 = MPCheckRequired($tempPair[1], "all", "single");
				if ($group1 != "") {
					if ($reqErrors == "") $reqErrors2 .= "<br>&nbsp;<br>".$le;
					$reqErrors2 .= '
						<tr>
						<td colspan="3" align="left" valign="middle"><hr></td>
						</tr>
						<tr>
						<td align="left" valign="top">
						<ul class="MPerrorlist">
						'.$group1.'
						</ul>
						</td>
						<td align="left" valign="top" nowrap><span class="MPinfo">&nbsp;&nbsp;- or -</span></td>
						<td align="left" valign="top">
						<ul class="MPerrorlist">
						'.$group2.'
						</ul>
						</td>
						</tr>
						';
					}
				}
			}
		}
	
	if ($reqErrors2 != "") {
		$reqErrors2 = '
						<table border="0" cellspacing="0" cellpadding="0" width="100%">
			'.$reqErrors2;
		$reqErrors2 .= '
						<tr>
						<td colspan="3" align="left" valign="middle"><hr></td>
						</tr>
						</table>
			';
		}
	if ($reqErrors != "") $reqErrors = "<ul class=\"MPerrorlist\">".$reqErrors."</ul>";
	$reqErrors .= $reqErrors2;
	if ($reqErrors != "") $errors .= $reqErrMsg.$reqErrors;
	
	// Convert field values to uppercase if specified...
	if ($uppercase != "") {
		$ucFields = explode(",", $uppercase);
		for ($n=0; $n<count($ucFields); $n++) {
			$tempName = trim($ucFields[$n]);
			$last2Chars = substr($tempName, (strlen($tempName)-2), 2);
			if ($last2Chars == "[]") $tempName = substr($tempName, 0, (strlen($tempName)-2));
			if (isset($MPPostVars[$tempName])) {
				$tempValue = strtoupper($MPPostVars[$tempName]);
				$MPPostVars[$tempName] = $tempValue;
				}
			}
		}
	
	// Convert field values to lowercase if specified...
	if ($lowercase != "") {
		$lcFields = explode(",", $lowercase);
		for ($n=0; $n<count($lcFields); $n++) {
			$tempName = trim($lcFields[$n]);
			$last2Chars = substr($tempName, (strlen($tempName)-2), 2);
			if ($last2Chars == "[]") $tempName = substr($tempName, 0, (strlen($tempName)-2));
			if (isset($MPPostVars[$tempName])) {
				$tempValue = strtolower($MPPostVars[$tempName]);
				$MPPostVars[$tempName] = $tempValue;
				}
			}
		}
	
	// Verify formatting for email fields if specified...
	if ($email_only != "") {
		$emailFields = explode(",", $email_only);
		for ($n=0; $n<count($emailFields); $n++) {
			$tempName = trim($emailFields[$n]);
			$last2Chars = substr($tempName, (strlen($tempName)-2), 2);
			if ($last2Chars == "[]") $tempName = substr($tempName, 0, (strlen($tempName)-2));
			if (isset($MPPostVars[$tempName])) {
				$thisTest = $MPPostVars[$tempName];
				if (!preg_match($emailPattern, $thisTest) AND $thisTest != "") 
					$errors .= str_replace("[email field]", MPAdjustFields($tempName), $emailErrMsg)."<br>";
				}
			}
		}
	
	// Verify formatting for number fields if specified...
	if ($numbers_only != "") {
		$numberFields = explode(",", $numbers_only);
		for ($n=0; $n<count($numberFields); $n++) {
			$tempName = trim($numberFields[$n]);
			$last2Chars = substr($tempName, (strlen($tempName)-2), 2);
			if ($last2Chars == "[]") $tempName = substr($tempName, 0, (strlen($tempName)-2));
			if (isset($MPPostVars[$tempName])) {
				$thisTest = str_replace(",", "", $MPPostVars[$tempName]);
				if (!preg_match($numberPattern, $thisTest) AND $thisTest != "") 
					$errors .= str_replace("[number field]", MPAdjustFields($tempName), $numErrMsg)."<br>";
					else $MPPostVars[$tempName] = $thisTest;
				}
			}
		}
	
	// Verify formatting for letter fields if specified...
	if ($letters_only != "") {
		$letterFields = explode(",", $letters_only);
		for ($n=0; $n<count($letterFields); $n++) {
			$tempName = trim($letterFields[$n]);
			$last2Chars = substr($tempName, (strlen($tempName)-2), 2);
			if ($last2Chars == "[]") $tempName = substr($tempName, 0, (strlen($tempName)-2));
			if (isset($MPPostVars[$tempName])) {
				$thisTest = $MPPostVars[$tempName];
				if (!preg_match($letterPattern, $thisTest) AND $thisTest != "") 
					$errors .= str_replace("[letter field]", MPAdjustFields($tempName), $letterErrMsg)."<br>";
				}
			}
		}
	
	// Compare "force_match" fields if specified...
	if ($force_match != "") {
		$allFound = true;
		$matchFields = explode(";", $force_match);
		for ($n=0; $n<count($matchFields); $n++) {
			$tempName = trim($matchFields[$n]);
			$matchFields[$n] = $tempName;
			if ($matchFields[$n] != "") {
				$thisMatchField = trim($matchFields[$n]);
				$thisMatchField = explode(",", $thisMatchField);
				$fieldsMatch = true;
				$matchTest = "";
				for ($i=0; $i<count($thisMatchField); $i++) {
					if ($thisMatchField[$i] != "") {
						$tempField = MPIgnoreBrackets($thisMatchField[$i]);
						if ($matchTest == "") $matchTest = $MPPostVars[$tempField];
							else {
							$tempValue = (isset($MPPostVars[$tempField])) ? $MPPostVars[$tempField] : "";
							if ($tempValue != $matchTest) $fieldsMatch = false;
							}
						}
					}
				if ($fieldsMatch == false AND is_array($thisMatchField)) {
					$matchERR = "";
					for ($n=0; $n<count($thisMatchField); $n++) {
						if (trim($thisMatchField[$n]) != "") {
							$matchERR .= "<li><span class=\"MPerrorlist\">".MPAdjustFields($thisMatchField[$n])."</span></li>".$le;
							}
						}
					if ($matchERR != "") $errors .= $matchErrMsg.$le."<ul>".$le.$matchERR."</ul>".$le;
					}
				}
			}
		}
	
	// Verify and process "sort" field if specified...
	if ($sort != "") {
		$formArray = "";
		$x = 0;
		$sortArray = explode(",", $sort);
		for ($n=0; $n<count($sortArray); $n++) {
			$tempName = trim($sortArray[$n]);
			$last2Chars = substr($tempName, (strlen($tempName)-2), 2);
			if ($last2Chars == "[]") $tempName = substr($tempName, 0, (strlen($tempName)-2));
			$thisPair["key"] = $tempName;
			if (isset($MPPostVars[$thisPair["key"]])) {
				$thisPair["value"] = stripslashes(MPFixArrays($MPPostVars[$thisPair["key"]]));
				$formArray[$x] = $thisPair;
				$x++;
				}
			}
			
		// If no sort order was specified, bring in all form fields in the default order...
		} else {
		reset($MPPostVars);
		$n = 0;
		while($thisPair = each($MPPostVars)) {
			$thisPair["value"] = stripslashes(MPFixArrays($thisPair["value"]));
			$formArray[$n] = $thisPair;
			$n++;
			}
		}
		
	// Strip out "exclude" field names from $formArray...
	$excludeFields = "recipient,redirect,redirect_type,required,sort,exclude,subject,exclude_display,sender_name,sender_email,";
	$excludeFields .= "exclude_email,force_match,recipient_name,write_to_file,force_format,uppercase,lowercase,link_text,link_url,attach_text_file,";
	$excludeFields .= "write_to_mysql,mysql_table,mysql_update_field,mysql_update_value,numbers_only,letters_only,email_only,SubmitButtonName,Submit,";
	$excludeFields .= "required_any,required_if,required_if_empty,required_if_any,required_if_any_empty,captcha_entered,captcha_encoded,";
	$excludeFields .= "mp_security_entry,mp_security_id";
	if ($exclude != "") $excludeFields .= ",$exclude";
	$excludeArray = explode(",", $excludeFields);
	$tempArray = array();
	for ($n=0; $n<count($formArray); $n++) {
		$formArray[$n]['key'] = trim($formArray[$n]['key']);
		$excludeHits = false;
		for ($i=0; $i<count($excludeArray); $i++) {
			$tempName = trim($excludeArray[$i]);
			$last2Chars = substr($tempName, (strlen($tempName)-2), 2);
			if ($last2Chars == "[]") $tempName = substr($tempName, 0, (strlen($tempName)-2));
			$excludeArray[$i] = $tempName;
			if ($formArray[$n]['key'] == $excludeArray[$i]) $excludeHits = true;
			}
		if ($excludeHits == false) $tempArray[] = $formArray[$n];
		}
	$formArray = $tempArray;
		
	// Strip out "exclude_display" fields if specified and build display array...
	if ($exclude_display != "") {
		$displayArray = array();
		$exDisArray = explode(",", $exclude_display);
		for ($n=0; $n<count($formArray); $n++) {
			$formArray[$n]['key'] = trim($formArray[$n]['key']);
			$excludeHits = false;
			for ($i=0; $i<count($exDisArray); $i++) {
				$tempName = trim($exDisArray[$i]);
				$last2Chars = substr($tempName, (strlen($tempName)-2), 2);
				if ($last2Chars == "[]") $tempName = substr($tempName, 0, (strlen($tempName)-2));
				$exDisArray[$i] = $tempName;
				if ($formArray[$n]['key'] == $exDisArray[$i]) $excludeHits = true;
				}
			if ($excludeHits == false) $displayArray[] = $formArray[$n];
			}
		} else $displayArray = $formArray;
	
	// Strip out "exclude_email" fields if specified and build email array...
	if ($exclude_email != "") {
		$emailArray = array();
		$exEmailArray = explode(",", $exclude_email);
		for ($n=0; $n<count($formArray); $n++) {
			$formArray[$n]['key'] = trim($formArray[$n]['key']);
			$excludeHits = false;
			for ($i=0; $i<count($exEmailArray); $i++) {
				$tempName = trim($exEmailArray[$i]);
				$last2Chars = substr($tempName, (strlen($tempName)-2), 2);
				if ($last2Chars == "[]") $tempName = substr($tempName, 0, (strlen($tempName)-2));
				$exEmailArray[$i] = $tempName;
				if ($formArray[$n]['key'] == $exEmailArray[$i]) $excludeHits = true;;
				}
			if ($excludeHits == false) $emailArray[] = $formArray[$n];
			}
		} else $emailArray = $formArray;
	
	// If no subject was specified, set it to the default...
	$MPSubject = ($subject == "") ? "Web Form Submission" : stripslashes($subject);
	
	// find and process any file attachments...
	$fileArray = array();
	$n = 0;
	if (is_array($MPPostFiles)) {
		while($thisPair = each($MPPostFiles)) {
			$fileArray[$n] = $thisPair["key"];
			$n++;
			}
		}
	$attachmentsFound = 0;
	$attachFile = "";
	$attachStart = "";
	$headers = "";
	$MPAttachments = array();
	$semi_rand = md5(time());
	$mime_boundary = "==Multipart_Boundary_x{$semi_rand}x";
	for ($n=0; $n<count($fileArray); $n++) {
		$thisFile = $fileArray[$n];
		if ($thisFile != "") {
			$file = (isset($MPPostFiles[$thisFile]['tmp_name'])) ? $MPPostFiles[$thisFile]['tmp_name'] : "";
			$file_name = (isset($MPPostFiles[$thisFile]['name'])) ? $MPPostFiles[$thisFile]['name'] : "";
			$file_size = (isset($MPPostFiles[$thisFile]['size'])) ? $MPPostFiles[$thisFile]['size'] : "";
			$file_type = (isset($MPPostFiles[$thisFile]['type'])) ? $MPPostFiles[$thisFile]['type'] : "";
			if (substr($file_type, 0, 5) == "text/") {
				if ($forceAttachText == true OR $file_type != "text/plain") $file_type = "unknown/nothing";
				}
			if ($file_type == "") $file_type = "unknown/nothing";
			if ($file != "") {
				if (is_uploaded_file($file)) {
					if ($file_size > ($attachmentMax * 1000)) {
						$thisErrMsg = str_replace("[file name]", $file_name, $sizeErrMsg);
						$thisErrMsg = str_replace("[max size]", $attachmentMax." KB", $thisErrMsg);
						$errors .= $thisErrMsg."<br>";
						} else {
						$MPAttachments[] = $file_name;
						@$thisFile = fopen($file,'rb');
						if (!$thisFile) {
							$errors .= "Uploaded file could not be accessed on the server (insufficient permissions). ";
							$errors .= "Contact your web host to allow access to form file uploads for PHP.<br>$le<br>$le";
							} else {
							@$data = fread($thisFile, @filesize($file));
							@fclose($thisFile);
							$attachmentsFound++;
							$data = chunk_split(base64_encode($data));
							$attachFile .= "--{$mime_boundary}$le";
							$attachFile .= "Content-Type: {$file_type};$le";
							$attachFile .= " name=\"{$file_name}\"$le";
							$attachFile .= "Content-Transfer-Encoding: base64$le$le";
							$attachFile .= $data;
							$attachFile .= "$le$le";
							}
						}
					} else $errors .= str_replace("[file name]", $file_name, $fileErrMsg)."<br>";
				}
			}
		}
	
	if ($errors == "") {
		// Write results to text file if specified...
		if ($write_to_file != "" OR $attach_text_file != "") {
			$fileWriteSuccess = false;
			$fileWriteArray = array();
			$fwa = 0;
			if ($force_format != "") {
				$tempArray = explode(",", $force_format);
				for ($n=0; $n<count($tempArray); $n++) {
					$tempVar = trim($tempArray[$n]);
					if ($tempVar != "") {
						$fileWriteArray[$fwa] = array();
						$fileWriteArray[$fwa]['key'] = $tempVar;
						$fileWriteArray[$fwa]['value'] = isset($MPPostVars[$tempVar]) ? $MPPostVars[$tempVar] : "";
						$fwa++;
						}
					}
				} else $fileWriteArray = $formArray;
			$fileContentsArray = array();
			$fc = 0;
			for ($n=0; $n<count($fileWriteArray); $n++) {
				if ($fileWriteArray[$n]['key'] != "") {
					str_replace($sepNameVals, $changeNameVals, $fileWriteArray[$n]['value']);
					str_replace($sepNameVals, $changeNameVals, $fileWriteArray[$n]['key']);
					str_replace($sepFormFields, $changeFormFields, $fileWriteArray[$n]['value']);
					str_replace($sepFormFields, $changeFormFields, $fileWriteArray[$n]['key']);
					str_replace($sepFormEntries, $changeFormEntries, $fileWriteArray[$n]['value']);
					str_replace($sepFormEntries, $changeFormEntries, $fileWriteArray[$n]['key']);
					if ($includeFieldNames == true)
						$fileContentsArray[$fc] = MPAdjustFields($fileWriteArray[$n]['key']).$sepNameVals.$fileWriteArray[$n]['value'];
						else $fileContentsArray[$fc] = $fileWriteArray[$n]['value'];
					$fc++;
					}
				}
			$thisTextEntry = (count($fileContentsArray) > 0) ? implode($sepFormFields, $fileContentsArray).$sepFormEntries : "";
			if ($write_to_file != "" AND $thisTextEntry != "") {
				$write_to_file_pathinfo = pathinfo($write_to_file);
				if ($write_to_file_pathinfo && strtolower($write_to_file_pathinfo['extension']) == 'txt') {
					if (!file_exists($write_to_file)) {
						@$makeNewFile = touch($write_to_file);
						if ($makeNewFile) @chmod($write_to_file, 0666);
						} else @chmod($write_to_file, 0666);
					@$filePointer = fopen($write_to_file, "a");
					if ($filePointer) {
						$thisTextEntry .= "$sepFormEntries";
						@$writeFile = fwrite($filePointer, $thisTextEntry);
						@fclose($thisPointer);
						if ($writeFile) $fileWriteSuccess = true;
						} else $errors .= "Could not write results to text file (file not found, can't be opened, or wrong permission settings).<br><br>";
					if ($fileWriteSuccess == false) $errors .= $textErrMsg."<br>";
					}
				}			
			if ($attach_text_file != "" AND $thisTextEntry != "") {
				$attachmentsFound++;
				$MPAttachments[] = $attach_text_file;
				$attachFile .= "--{$mime_boundary}$le";
				$attachFile .= "Content-Type: unknown/nothing;$le";
				$attachFile .= " name=\"{$attach_text_file}\"$le";
				$attachFile .= "Content-Transfer-Encoding: 7bit$le$le";
				$attachFile .= $thisTextEntry;
				}
			}
		}
		
	if ($errors == "") {
		if ($attachmentsFound > 0 AND $attachFile != "") {
			$attachSorP = (count($MPAttachments) > 1) ? "s" : "";
			$tempArray = array();
			$tempArray['key'] = "Attachment".$attachSorP;
			$tempArray['value'] = implode(", ", $MPAttachments);
			$displayArray[count($displayArray)] = $tempArray;
			$emailArray[count($emailArray)] = $tempArray;
			$attachStart = "This is a multi-part message in MIME format.$le$le";
			$attachStart .= "--{$mime_boundary}$le";
			$attachStart .= "Content-Type: text/plain; charset=\"UTF-8\"$le";
			$attachStart .= "Content-Transfer-Encoding: 7bit$le$le";
			$headers .= "Content-Type: multipart/mixed;$le";
			$headers .= " boundary=\"{$mime_boundary}\"";
			$attachFile .= "--{$mime_boundary}--$le";
			} else {
			$headers .= "Content-Type: text/plain; charset=\"UTF-8\"$le";
			$headers .= "Content-Transfer-Encoding: 7bit";
			}
		}
	$headers = trim($headers);
	
	if ($errors == "") {
		// Write results to MySQL database if specified...
		if ($write_to_mysql != "") {
			@$testInclude = include($mysql_access_file);
			if (!$testInclude) $errors .= "Could not access database information - bad include file path or permission.<br>$le";
				else {
				if ($mysql_table != "") {
					$mysqlArray = array();
					$mysqlArrayVals = array();
					$mysqlCount = 0;
					$tempArray = explode(",", $write_to_mysql);
					for ($n=0; $n<count($tempArray); $n++) {
						$tempVar = trim($tempArray[$n]);
						if ($tempVar != "") {
							$tempPair = explode(">", $tempVar);
							if (count($tempPair) == 2) {
								$tempForm = trim($tempPair[0]);
								$tempDB = trim($tempPair[1]);
								} else {
								$tempForm = $tempVar;
								$tempDB = $tempVar;
								}
							$mysqlArray[$mysqlCount] = $tempDB;
							$mysqlArrayVals[$mysqlCount] = isset($MPPostVars[$tempForm]) ? "'".MPAddSlashes($MPPostVars[$tempForm])."'" : "''";
							$mysqlCount++;
							}
						}
					$mysql_table = MPAddSlashes($mysql_table);
					$query = "SELECT COUNT(*) FROM `$mysql_table`";
					@$testTableName = mysql_query($query);
					if ($testTableName === false) $errors .= "The table \"$mysql_table\" was not found in the MySQL database.<br>";
						else {
						$insertFieldList = implode(", ", $mysqlArray);
						$insertValueList = implode(", ", $mysqlArrayVals);
						if ($insertFieldList != "") {
							$query = "SELECT $insertFieldList FROM `$mysql_table`";
							@$testFieldNames = mysql_query($query);
							if (!$testFieldNames) $errors .= "One of the MySQL database fields listed in \"write_to_mysql\" could not be found (case sensitive).";
								else {
								$thisQueryType = "insert";
								if ($mysql_update_field != "" AND $mysql_update_value != "") {
									$query = "SELECT $mysql_update_field FROM `$mysql_table` WHERE $mysql_update_field='$mysql_update_value'";
									@$result = mysql_query($query);
									@$hits = mysql_num_rows($result);
									if ($hits == 1) {
										$updateFields = array();
										for ($x=0; $x<count($mysqlArray); $x++) {
											if ($mysqlArray[$x] != "") {
												$thisSet = $mysqlArray[$x]."=".$mysqlArrayVals[$x];
												$updateFields[] = $thisSet;
												}
											}
										if (count($updateFields) > 0) {
											$thisQueryType = "update";
											$updateFieldVals = implode(", ", $updateFields);
											}
										}
									}
								if ($errors == "") {
									if ($thisQueryType == "update") {
										$query = "UPDATE `$mysql_table` SET $updateFieldVals WHERE $mysql_update_field='$mysql_update_value'";
										@$testDbUpdate = mysql_query($query);
										if (!$testDbUpdate) $errors .= $mysqlErrMsg3."<br>";
										} else {
										$query = "INSERT INTO `$mysql_table` ($insertFieldList) VALUES ($insertValueList)";
										@$testDbInsert = mysql_query($query);
										if (!$testDbInsert) $errors .= $mysqlErrMsg2."<br>";
										}
									}
								}
							}
						}
					} else $errors .= "No database table name was included in the \"mysql_table\" field.<br>";
				}
			}
		}
	
	if ($errors == "") {
	
		// Send out email if recipients were found...
		if (count($recipientArray) > 0) {
			$MPEmailBody = "The following information was submitted on $dateTime:$le$le";
			$MPEmailBody .= "-------------------------------------------------------$le$le";
			$emailSepChars = ($doubleSpaceEmail == true) ? "$le$le" : "$le";
			$emailNameVals = "";
			for ($n=0; $n<count($emailArray); $n++) {
				if ($emailArray[$n]['value'] != "") {
					$thisFieldName = MPAdjustFields(stripslashes($emailArray[$n]['key']));
					$thisFieldValue = stripslashes($emailArray[$n]['value']);
					$emailNameVals .= $thisFieldName.": ".$thisFieldValue.$emailSepChars;
					}
				}
			$MPEmailBody .= $emailNameVals;
			$MPEmailBody .= "-------------------------------------------------------$le $le";
			$MPEmailBody = $attachStart.$MPEmailBody.$attachFile;
			if ($MPForceSender == '') {
				$MPSender = "";
				$sender_email = (isset($MPPostVars[$sender_email])) ? $MPPostVars[$sender_email] : "";
				if ($sender_email != "") $sender_email = (preg_match($emailPattern, $sender_email)) ? $sender_email : "";
				$sender_name = (isset($MPPostVars[$sender_name])) ? $MPPostVars[$sender_name] : "";
				if ($sender_email != "") {
					$MPSender = ($sender_name != "") ? stripslashes($sender_name)." <".stripslashes($sender_email).">" : stripslashes($sender_email);
					}
				if ($MPSender != "") $MPSender = "From: $MPSender$le";
				$headers = $MPSender.$headers;
				} else {
				$headers = 'From: '.$MPForceSender.$le.$headers;
				$addParams = '-f'.$MPForceSender;
				}
			$goodSends = 0;
			for ($n=0; $n<count($recipientArray); $n++) {
				$recipientString = trim($recipientArray[$n]);
				@$mailStatus = mail(
					$recipientString,
					'=?UTF-8?B?'.base64_encode($MPSubject).'?=',
					$MPEmailBody,
					$headers
					);
				if ($mailStatus) $goodSends++;
				}
			if ($goodSends < 1) $errors .= $mailErrMsg."<br>";
			}
		
		// Redirect if specified, adding query string to URL with form results for extraction...
		if ($redirect != "") {
			$printHTML = false;
			if ($redirect_type == "include") {
				include("$redirect");
				} else if ($redirect_type == "query") {
				$queryArray = "";
				$q = 0;
				for ($n=0; $n<count($displayArray); $n++) {
					if ($displayArray[$n]['value'] != "") {
						$queryPair = MPParseRedirectData(MPAdjustFields($displayArray[$n]['key']))."=".MPParseRedirectData($displayArray[$n]['value']);
						if ($queryPair != "=") {
							$queryArray[$q] = $queryPair;
							$q++;
							}
						}
					}
				$redirectPage = "Location: $redirect";
				if (is_array($queryArray)) $redirectPage .= "?".implode("&", $queryArray);			
				header($redirectPage);
				exit;
				} else { header("Location: $redirect"); exit; }
			}
		}
	}

// -------------------------------------------------------------------------------------------------------------------------------
//   PRINT HTML FOR DEFAULT AND CONFIRMATION PAGES...
// -------------------------------------------------------------------------------------------------------------------------------

// if not redirecting, start printing HTML response page...
if ($printHTML == true OR $formSubmitted == false) {
	print('<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">

<html>

	<head>
		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		<title>Form Results</title>
		<style type="text/css" media="screen"><!--
body, div, td, p { '.$pageStyle.' }
.MPinfo { '.$MPinfo.' }
.MPFieldNames { '.$MPFieldNames.' }
.MPFieldValues  { '.$MPFieldValues.' }
.MPthankyou   { '.$MPthankyou.' }
.MPerror { '.$MPerror.' }
.MPerrorlist { '.$MPerrorlist.' }
.MPsmall { '.$MPsmall.' }
.MPsubhead { '.$MPsubhead.' }
.MPlink   { '.$MPlink.' }
.MPlink a:link  { '.$MPlink.' }
.MPlink a:visited  { '.$MPlink.' }
.MPlink a:hover { '.$MPlink_hover.' }
.MPcredit { '.$MPcredit.' }
--></style>
	</head>

	<body>
		<div align="center">
			<table border="0" cellspacing="0" cellpadding="0">
				<tr>
		');
					
	// If there were errors, list them...
	if ($errors != "") {
		print("
					<td align=\"left\" valign=\"top\">
					<br>&nbsp;<br><span class=\"MPerror\">$errors</span><br>&nbsp;<br>
					</td>
				</tr>
				<tr>
					<td align=\"center\" valign=\"top\" class=\"MPinfo\">
					[ <span class=\"MPlink\"><a href=\"javascript:history.back();\">back to form</a></span> ]<br>
					&nbsp;<br>
					<span class=\"MPsmall\">(If JavaScript is disabled, use the back button on your browser.)</span><br>
			");
	
	// If no errors were encountered, list emailed results and home link if specified...
		} else if ($formSubmitted == true) {
		if ($recipient == "") $sentTo = "this page";
			else $sentTo = ($recipient_name != "") ? $recipient_name : $recipientString;
		$sentToMsg = str_replace("[message recipient]", $sentTo, $confirmMsgText);
		print("
			<td align=\"center\" valign=\"top\" width=\"570\">
			&nbsp;<br>
			<span class=\"MPthankyou\">$confirmMsgTitle</span><br>
			<span class=\"MPsubhead\">$sentToMsg<br>
			($dateTime)</span><hr>
			<table border=\"0\" cellspacing=\"0\" cellpadding=\"1\">
			");
		for ($n=0; $n<count($displayArray); $n++) {
			if ($displayArray[$n]['value'] != "") {
				$htmlPair = MPNameValueHTML($displayArray[$n], "MPFieldNames", "MPFieldValues");
				$thisFieldName = $htmlPair[0];
				$thisFieldValue = $htmlPair[1];
				print("<tr>$le<td align=\"right\" valign=\"top\" nobreak>".$thisFieldName."&nbsp;&nbsp;</td>$le");
				print("<td align=\"left\" valign=\"top\">".$thisFieldValue."</td>$le<tr>$le");
				}
			}
		print("</table>$le<hr>$le");
		if ($link_url != "" AND $link_url != "http://") {
			if ($link_url == "close") {
				$link_url = "javascript:self.close();";
				if ($link_text == "") $link_text = "close window";
				} else if ($link_url == "back") {
				$link_url = "javascript:history.back();";
				if ($link_text == "") $link_text = "back to form";
				} else {
				if (substr($link_url, 0, 7) != "http://") $link_url = "http://".$link_url;
				$link_text = ($link_text != "") ? $link_text : "back to home";
				}
			print("[ <span class=\"MPlink\"><a href=\"$link_url\">$link_text</a></span> ]<br>$le&nbsp;<br>$le");
			}
		} else {
		print("
			<br>&nbsp;<br><span class=\"MPthankyou\">ProcessForm 3</span><br>
			&nbsp;<br>
			");
		}
	print('<br>
						&nbsp;<br>
					</td>
				</tr>
			</table>
		</div>
	</body>

</html>');

	}

// -------------------------------------------------------------------------------------------------------------------------------
//   END OF SCRIPT!  ProcessForm 3.0.17 by Nate Baldwin, www.mindpalette.com - copyright 2014
// -------------------------------------------------------------------------------------------------------------------------------

?>