<?php

error_reporting(E_ALL);

if (!isSet($_GET['pubdate'])) {
	$pubDate = date('Y-m-d', time('+1 day'));
} else {
	$pubDate = $_GET['pubdate'];
}

if (!isSet($_GET['section'])) {
	http_response_code(400);
	die('No Section Specified');
} else {
	$section = $_GET['section'];
}

// Get Dropbox URL
$serverDropboxPath = '/daily/ProdSync/Dropbox/Current Day/';
//$serverDropboxPath = "C:/Users/Andrew/Dropbox/Current Day/";
$localDropboxPath = 'C:/Dropbox/Current Day/';

// Build URL from Query Params, then Download It
//$url = 'http://localhost/Projects/Tufts/Daily/public_html/xml/category/' . $section . '/date/' . $pubDate;
$url = 'http://tuftsdaily.com/xml/category/' . $section . '/date/' . $pubDate;
$response = load_remote($url);

// Handle Errors
if(!$response){
	http_response_code(500);
	die("Could Not Load Articles from Server (Error #2)");
}

// Interpret the Response, Make Sure It's Valid XML
$xml = simplexml_load_string($response);
if (!$xml) {
	http_response_code(500);
	die("Could Not Load Articles from Server (Error #3)");
}

// Make Sure no Error Happened Server-Side
if ($xml->getName() == "error") {
	http_response_code(500);
	die("Could Not Load Articles from Server (Error #4)");
}

// Parse Returned XML File for Photo URLs
$photo_urls = [];
foreach($xml->article as $article) {
	if ($article->photos) {
		foreach($article->photos->photo as $photo) {
			$urlString = (string)$photo['url'];
			$photo_urls[] = $urlString;
			$photo['url'] = $localDropboxPath.$section.'/'.basename($urlString);
		}
	}
}

// Save XML in Dropbox
$xml_filename = strtolower($section).'.xml';
save_file($xml->asXml(), $xml_filename);

// Download Each Photo
foreach($photo_urls as $url) {
	$filename = basename($url);

	if (!file_exists($serverDropboxPath.$filename)) {
		$photo = load_remote($url);
		if (!$photo) {
			echo 'File not found: '.$url."\n";
			// TODO Log This?
		} else {
			save_file($photo, $filename, strtolower($section));
		}
	}
}

http_response_code(200);
echo 'OK';

function load_remote($url) {
	$curl = curl_init();
	curl_setopt_array($curl, array(
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_URL => $url,
		CURLOPT_FAILONERROR => true
	));
	// Send the Request, Save Response to $response
	$response = curl_exec($curl);
	curl_close($curl);
	return $response;
}

function save_file($content, $filename, $directory='') {
	global $serverDropboxPath;
	if ($directory) {
		// If the Correct Folder in Current Day Doesn't Exist, Make It
		if (!file_exists($serverDropboxPath.$directory)) {
			$success = mkdir($serverDropboxPath.$directory, 0775, true);
			if (!$success) { 
				http_response_code(500);
				die('Error: Could not connect to Dropbox directory.'); 
			}
		}
		$path = $serverDropboxPath.$directory.'/'.$filename;
	} else {
		$path = $serverDropboxPath.$filename;
	}
	
	$fp = fopen($path, 'w');
	$success = fwrite($fp, $content);
	if (!$success) {
		http_response_code(500);
		die('Error: Could not write file: '.$path);
	}
	fclose($fp);
}