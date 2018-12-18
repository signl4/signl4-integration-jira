<?php
// Array of Jira events to react to
const ARRAY_JIRA_EVENTS		= array("jira:issue_created");

// Jira user data (e. g. your login email, and password). Assigns, and comments Jira issues.
const STRING_JIRA_ADMIN 	= "YOUR-JIRA-ACCOUNT";
const STRING_JIRA_PASSWORD 	= "YOUR-JIRA-PASSWORD";

$aData = (array) json_decode(file_get_contents("php://input"));
$sSignlUrl = "";

foreach($_REQUEST as $sKey => $sValue) {
	$sValue = urldecode($sValue);
	// Check if redirect string to SIGNL4 exists
    if ($sKey == "redirect" && strpos("connect.signl4.com", $sValue) == 0) {
        $sSignlUrl = (string) $sValue;
    }
    $aData[$sKey] = (string) $sValue;
}

$oData = json_decode(json_encode($aData));

// Handle request from Jira to SIGNL4
if ($sSignlUrl) {
    if (!isset($oData->webhookEvent) || !in_array($oData->webhookEvent, ARRAY_JIRA_EVENTS)) {
		stop("Jira event not found or allowed");
	} 
	
	$sUserId = $oData->user->accountId;
	$sIssueKey = $oData->issue->key;
	$aData["X-S4-ExternalID"] = $sIssueKey . ";" . $sUserId . ";" . $oData->issue->self;

    $oCurl = curl_init($sSignlUrl);
	setCurlOptions($oCurl, $aData);
    curl_setopt($oCurl, CURLOPT_POST, true);
    $sRes = curl_exec($oCurl);
    $aRes = json_decode($sRes, true);
    curl_close($oCurl);

// Handle SIGNL4 to Jira requests
} else {
	$aReq = $aRes = array();
	$sUrl = "";
	
	// Explode former submitted X-S4-ExternalID into array
	$aId = explode(";", $oData->alert->externalEventId);
	// Extract API URI, and add to ID array
	$aId[3] = explode("issue", $aId[2])[0];
	
	// If a SIGNL gets acknowledged, assign the Jira issue to the depending user
    if ($oData->eventType == 201 && $oData->alert->statusCode == 2) {
		
		// Find Jira user by email
		$sUrl = $aId[3] . "user/assignable/search?issueKey=" . $aId[0];
		$oCurl = curl_init($sUrl);
		curl_setopt($oCurl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, true);
		setCurlAuthentication($oCurl);
		$oRes = json_decode(curl_exec($oCurl));
		curl_close($oCurl);
		
		$sName = "";
		if ($oRes) {
			foreach ($oRes as $oSingleRes) {
				if ($oSingleRes->emailAddress == $oData->user->mailaddress) {
					$sName = $oSingleRes->name;
					break;
				}
			}
		}
		
		// If user was found, assign Jira issue to user
		if ($sName) {
			$aReq["name"] = $sName;
			$sUrl = $aId[2] . "/assignee";
			$oCurl = curl_init($sUrl);
			setCurlOptions($oCurl, $aReq);
			setCurlAuthentication($oCurl);
			curl_setopt($oCurl, CURLOPT_CUSTOMREQUEST, 'PUT'); // <-- remarkable, because CURLOPT_PUT did not work
			curl_setopt($oCurl, CURLINFO_HEADER_OUT , true);
			$sRes = curl_exec($oCurl);
			$aRes = json_decode($sRes, true);
			$aInfo = curl_getinfo($oCurl);
			if (!$aInfo) {
				$aInfo = array(curl_error($oCurl));
			}
			curl_close($oCurl);
		}
			
	// If a SIGNL gets a comment, transfer this comment to the depending Jira issue
	} else if ($oData->eventType == 203) {
		// Here you can format the message content, which will be sent to Jira
		// Check the SIGNL4 API for the ability to get the email or name of a user by his ID to make the user part human readable
		$aReq["body"] = "SIGNL4 comment '" . $oData->annotation->message . "' by SIGNL4 user " . $oData->user->id;

		$sUrl = $aId[2] . "/comment";
		$oCurl = curl_init($sUrl);
		setCurlOptions($oCurl, $aReq);
		setCurlAuthentication($oCurl);
		curl_setopt($oCurl, CURLOPT_POST, true);
		$sRes = curl_exec($oCurl);
		$aRes = json_decode($sRes, true);
		curl_close($oCurl);
	}
}

function setCurlAuthentication($oCurl) {
	curl_setopt($oCurl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($oCurl, CURLOPT_USERPWD, STRING_JIRA_ADMIN . ":" . STRING_JIRA_PASSWORD);
}

function setCurlOptions($oCurl, $aReq) {
    curl_setopt($oCurl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($oCurl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
	curl_setopt($oCurl, CURLOPT_POSTFIELDS, json_encode($aReq));
	curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, true);
}

function stop($sMessage) {	
    header ("Content-type: application/json");
    echo "{ 'message': '" . $sMessage . "' }\n";
	
	exit;
}

stop("OK");
