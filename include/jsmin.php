<?php

class easyjsmin{

	function minimize($content,$file=''){

		$result = self::MinResult($content);
		if( !$result ){
			return false;
		}

		self::ResultErrors($result,$file);

		if( self::TooManyCompiles($result) ){
			return false;
		}

		if( (int)$result['statistics']['originalSize'] <= (int)$result['statistics']['compressedSize'] ){
			return $content;
		}else{
			return $result['compiledCode'];
		}
	}

	function MinResult($content){
		$cache_file = self::CachePath($content);

		$result = self::GetFromCache($cache_file);

		if( !$result ){

			$result = self::FetchFromClosure($content,$cache_file);
			if( $result === false ){
				message('Sorry, your code was not successfully minified. Check to make sure you\'re connected to the internet.');
				return false;
			}
		}

		return $result;
	}


	function GetFromCache($file){
		if( !file_exists($file) ){
			return false;
		}

		ob_start();
		readgzfile($file);
		$content = ob_get_clean();
		return json_decode($content,true);
	}

	function CachePath($content){
		global $rootDir;
		$hash = md5($content).'.'.sha1($content).'.'.strlen($content);
		return $rootDir.'/data/_cache/'.substr($hash,0,1).'/'.substr($hash,1,1).'/'.substr($hash,2);
	}


	//use google's closure compiler
	//http://closure-compiler.appspot.com/home
	function FetchFromClosure($content,$cache_file){


		$host = 'closure-compiler.appspot.com';
		$path = '/compile';
		$port = '80';

		//data
		$req = 'js_code='.urlencode($content);
		$req .= '&output_format=json';
		$req .= '&output_info=compiled_code&output_info=warnings&output_info=errors&output_info=statistics';
		$req .= '&compilation_level=SIMPLE_OPTIMIZATIONS';


		//request
		$http_request  = "POST $path HTTP/1.0\r\n";
		$http_request .= "Host: $host\r\n";
		$http_request .= "Content-Type: application/x-www-form-urlencoded;\r\n";
		$http_request .= "Content-Length: " . strlen($req) . "\r\n";
		$http_request .= "User-Agent: phpEasyMin\r\n";
		$http_request .= "\r\n";
		$http_request .= $req;


		if( false == ( $fs = fsockopen($host, $port, $errno, $errstr, 10) ) ) {
			message('Could not open socket');
			return false;
		}

		fwrite($fs, $http_request);

		$response = '';
		while ( !feof($fs) ){
			$response .= fgets($fs, 1160); // One TCP-IP packet
		}
		fclose($fs);

		$response = explode("\r\n\r\n", $response, 2);
		if( empty($response[1]) ){
			return false;
		}

		$content = $response[1];
		$result = json_decode($content,true);

		if( !self::TooManyCompiles($result) ){
			self::SaveToCache($cache_file,$content);
		}

		return $result;
	}

	function SaveToCache($file,$content){
		$content = gzencode($content,9);
		return common::Save($file,$content);
	}


	/**
	 * Don't save "Too many compiles performed recently." error
	 *
	 */
	function TooManyCompiles($result){
		if( isset($result['serverErrors'])
			&& is_array($result['serverErrors'])
			&& isset($result['serverErrors'][0])
			&& is_array($result['serverErrors'][0])
			&& isset($result['serverErrors'][0]['code'])
			&& $result['serverErrors'][0]['code'] == 22 ){
			return true;
		}
		return false;
	}

	function ResultErrors( $result, $file=''){

		$file = basename($file);
		if( !$result ){
			return false;
		}
		if( isset($result['errors']) ){
			message('Errors found in JavaScript file: '.$file.' '.showArray($result['errors']));
			return false;
		}
		if( isset($result['serverErrors']) ){
			message('Errors occurred while compressing JavaScript file: '.$file.' '.showArray($result['serverErrors']));
			return false;
		}

		//don't think this will ever happen
		if( !isset($result['compiledCode']) ){
			message('No Compiled Code for file: '.$file);
			return false;
		}

		if( isset($result['warnings']) ){
			message('Warnings found for JavaScript file: <a href="#" class="jswarnings">'.$file.'</a>.<div class="jswarnings">'.showArray($result['warnings']).'</div>');
		}

		return true;
	}

}