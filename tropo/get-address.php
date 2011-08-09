<?php

define("APPLICATION_PATH", "http://{your-app-host}/address-finder/index.php");
define("GRAMMAR_PATH", "http://{your-couchdb-host}/{your-couchdb-database}/_design/app/_list/grammar-by-zip/names?group=true&street_zip=");

// English prompts.
$english = array();
$english["getzip"] = 'Please enter your five digit zip code.';
$english["sorry"] = 'Sorry, I had trouble hearing you.';
$english["zipdtmf"] = 'Please enter your zip code using your touch tone key pad.';
$english["didyousay"] = 'Did you say, ';
$english["address"] = 'Please say your street address, beginning with your street number.';
$english["recording"] = 'Please say your full addrress, including your street nunber, street name and zip code. Press the pound key when finished.';
$english["gotaddress"] = 'I heard you say, ';
$english["correct"] = 'Is that correct?';
$english["thanks"] = 'Thank you.';
$english["sorry"] = 'Sorry, Lets try again.';
$english["goodbye"] = 'Goodbye.';

// Spanish prompts.
$spanish = array();
$spanish["getzip"] = 'Por favor, ingrese su c—digo postal cinco d’gitos.';
$spanish["sorry"] = 'Lo sentimos, no ten’a problemas para escuchar usted.';
$spanish["zipdtmf"] = 'Por favor, ingrese su c—digo postal utilizando la marcaci—n por tonos teclado.';
$spanish["didyousay"] = 'Ha dicho usted, ';
$spanish["address"] = 'Por favor diga su direcci—n, empezando por el nœmero de su calle.';
$spanish["recording"] = '';
$spanish["gotaddress"] = 'He oido que usted dice, ';
$spanish["correct"] = 'Es correcto?';
$spanish["thanks"] = 'Gracias.';
$spanish["sorry"] = 'Lo sentimos, Vamos a intentarlo de nuevo.';
$spanish["goodbye"] = 'Adios.';

// Set prompts , TTS and ASR settings.
$prompts = array("english" => $english, "spanish" => $spanish);
$prompt = $prompts["english"];
$tts_voice = 'Allison';
$asr_recognizer = 'en-us';

// Helper function to confirm a prompt.
function confirm($prompt) {
	global $tts_voice, $asr_recognizer;
	$response = ask($prompt, array("choices" => 'yes,no', "voice" => $tts_voice, "recognizer" => $asr_recognizer, "bargein" => false));
	if($response->value == 'yes') {
		_log("*** Caller confirmed input. ***");
		return true;
	}
	else {
		_log("*** Caller rejected input.");
		return false;
	}
}

// Helper function to make an HTTP request.
function makeHTTPCall($url) {

	_log("*** URL: $url ***");

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	// Execute.
	$output = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	// Return results.
	if($code != '200') {
		return false;
	}
	else {
		return true;
	}

}

// Create a call record.
function createRecord($sessionid, $callerid) {
	$url = APPLICATION_PATH . "?uri=create&sessionid=$sessionid&callerid=$callerid";
	return makeHTTPCall($url);
}

// Save a call record for a completed call.
function saveRecord($sessionid, $address, $zip) {
	$address = urlencode($address);
	$url = APPLICATION_PATH . "?uri=save&sessionid=$sessionid&address=$address&zip=$zip";
	return makeHTTPCall($url);
}

// Get the caller's zip code.
function getZip() {

	global $prompt, $tts_voice, $asr_recognizer;

	$zip = ask($prompt["getzip"], array("choices" => '[5 DIGITS]', "voice" => $tts_voice, "recognizer" => $asr_recognizer, "bargein" => false));

	// Caller entered a zip code.
	if($zip->name == 'choice') {
		_log("Confidence level: ". $zip->choice->confidence);

		if($zip->choice->confidence < .40) {
			say($prompt["sorry"], array("voice" => tts_voice));
			$zip = ask($prompt["zipdtmf"], array("choices" => '[5 DIGITS]', "mode" => 'dtmf', "voice" => $tts_voice, "recognizer" => $asr_recognizer));
			return $zip->value;
		}
		else if($zip->choice->confidence < .60) {
			if(confirm($prompt["didyousay"] . implode(" ", str_split($zip->value)) . "?")) {
				return $zip->value;
			}
			else {
				$zip = ask($prompt["zipdtmf"], array("choices" => '[5 DIGITS]', "mode" => 'dtmf'));
				return $zip->value;
			}
		}
		else {
			return $zip->value;
		}
	}
	else {
		recordAddress();
	}

}

// Get the caller's address.
function getAddress($zip) {

	global $prompt, $tts_voice, $asr_recognizer;

	$grammar = GRAMMAR_PATH . $zip;

	_log("*** Grammar URL: $grammar ***");

	$address = ask($prompt["address"], array("choices" => $grammar, "timeout" => 30.0, "voice" => $tts_voice, "recognizer" => $asr_recognizer, "bargein" => false));
	_log("*** Captured address: " . $address->value . " ***");
	if(confirm($prompt["gotaddress"] . $address->value . ", " . $prompt["correct"] . "?")) {
		return $address->value;
	}
	else {
		say($prompt["sorry"], array("voice" => $tts_voice));
		recordAddress();
	}

}

// Record a caller's address.
function recordAddress() {
	global $prompt, $tts_voice, $currentCall;
	$recording_location = APPLICATION_PATH . "?uri=record&sessionid=" . $currentCall->sessionId;

	$recording = record($prompt["recording"],
						array(	"voice" => $tts_voice,
							  	"recordMethod" => "POST", 
								"recordFormat" => 'audio/mp3', 
								"recordURI" => $recording_location, 
								"beep" => true, "maxTime" => 60, 
								"terminator" => "#", "silenceTimeout" => 3.0)
						);
		
	say("I recorded your address as " . $recording->recordURI);
	say($prompt["thanks"], array("voice" => $tts_voice, "bargein" => false));
	hangup();

}

// Get call properties.
$sessionid = $currentCall->sessionId;
$callerid = $currentCall->callerID;

// Main call flow.
if(!createRecord($sessionid, $callerid)) {
	say("Sorry, there was a problem. Please try your call again later.", array("voice" => $tts_voice, "bargein" => false));
	hangup();
}
else {

	$zip = getZip();
	_log("Zip: $zip");
	$address = getAddress($zip);
	say($prompt["thanks"], array("voice" => $tts_voice, "bargein" => false));
	saveRecord($sessionid, $address, $zip);
	hangup();
}

?>