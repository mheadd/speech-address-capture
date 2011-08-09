<?php

// Include required classes.
require 'classes/sag/Sag.php';
require 'classes/limonade.php';

// CouchDB setttings.
define("COUCH_HOST", "");
define("COUCH_PORT", 5984);
define("COUCH_PATH", "_design/app/_list/grammar-by-zip/names?group=true&street_zip=");
define("COUCH_USER", "");
define("COUCH_PASS", "");

dispatch_get("/create", "createCallRecord");
function createCallRecord() {
	
	$sessionid = $_REQUEST["sessionid"];
	$callerID = $_REQUEST["callerid"];
	
	// Create a new Sag instance to connect to CouchDB.
	$sag = new Sag(COUCH_HOST, COUCH_PORT);
	$sag->setDatabase("callrecords");
	$sag->login(COUCH_USER, COUCH_PASS);
	
	$doc = array("callerID" => $callerID, "callStartTime" => time());
	
	// Save the caller record.
	$response = $sag->put($sessionid, $doc)->headers->_HTTP;
	return $response->raw;
	
} 

dispatch_get("/save", "saveCallRecord");
function saveCallRecord() {
	
	$sessionid = $_REQUEST["sessionid"];
	$address = urldecode($_REQUEST["address"]);
	$zip = urldecode($_REQUEST["zip"]);
	
	// Create a new Sag instance to connect to CouchDB.
	$sag = new Sag(COUCH_HOST, COUCH_PORT);
	$sag->setDatabase("callrecords");
	$sag->login(COUCH_USER, COUCH_PASS);
	
	// Update the exiting caller record.
	$doc = $sag->get($sessionid)->body;
	$doc->callEndTime = time();
	$doc->address = $address;	
	$doc->zip = $zip;
	
	// Save the caller record.
	$response = $sag->put($sessionid, $doc)->headers->_HTTP;
	return $response->raw;
	
}

dispatch_post("/record", "saveAudio");
function saveAudio() {

	// TODO: Need to add callEndTime to doc when recording is saved.
	
	$sessionid = $_REQUEST["sessionid"];
	
	// Create a new Sag instance to connect to CouchDB.
	$sag = new Sag(COUCH_HOST, COUCH_PORT);
	$sag->setDatabase("callrecords");
	$sag->login(COUCH_USER, COUCH_PASS);
	
	// Update the exiting caller record.
	$doc = $sag->get($sessionid)->body;
	$attachment = file_get_contents($_FILES['filename']['tmp_name']);
	$sag->setAttachment('address_recording.mp3', $attachment, "audio/mp3", $doc->_id, $doc->_rev);
		
}

run();

?>