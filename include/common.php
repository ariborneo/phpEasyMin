<?php
defined('is_running') or die('Not an entry point...');


if( version_compare(phpversion(),'5.0','>=') ){
	require('thirdparty/cssmin-v3.0.1.php');
	define('cssmin_v3',true);
}else{
	require('thirdparty/cssmin_v.1.0.php');
}


class common{

	//returnMessages
	function GetMessages(){
		global $wbMessageBuffer;


		if( empty($wbMessageBuffer) ){
			return;
		}

		$result = '';
		foreach($wbMessageBuffer as $key2 => $args){
			if( !isset($args[0]) ){
				continue;
			}

			if( isset($args[1]) ){
				$result .= '<li>'.call_user_func_array('sprintf',$args).'</li>';
			}else{
				$result .= '<li>'.$args[0].'</li>';
			}
		}
		//$result = str_replace('%s',' ',$result);


		$wbMessageBuffer = array();
		echo '<div class="messages">';
		echo '<a style="float:right;text-decoration:none;line-height:0;font-weight:bold;margin:3px 0 0 2em;color:#666;font-size:larger;display:none;" href="" class="req_script" name="close_message">';
		echo 'x';
		echo '</a>';
		echo '<ul>'.$result.'</ul></div>';
	}

	function GetCommand($type='cmd'){

		if( isset($_POST[$type]) ){
			return $_POST[$type];
		}

		if( isset($_GET[$type]) ){
			return $_GET[$type];
		}
		return false;
	}

	/**
	 *  file functions
	 *
	 */
	function Save($file,$contents){
		$fp = common::fopen($file);
		if( !$fp ){
			return false;
		}
		if( !fwrite($fp,$contents) ){
			fclose($fp);
			return false;
		}

		fclose($fp);
		return true;
	}

	function SaveArray($file,$varname,&$array){

		$data = common::ArrayToPHP($varname,$array);

		$start = array();
		$start[] = '<'.'?'.'php';
		$start[] = 'defined(\'is_running\') or die(\'Not an entry point...\');';
		$start[] = '$fileModTime = \''.time().'\';';
		$start[] = '';
		$start[] = '';

		$start = implode("\n",$start);

		return common::Save($file,$start.$data);
	}

	//boolean, strings, and numbers
	function ArrayToPHP($varname,&$array){
		return '$'.$varname.' = '.var_export($array,true).';';
	}

	function fopen($file){

		if( file_exists($file) ){
			return fopen($file,'wb');
		}

		$dir = dirname($file);
		if( !file_exists($dir) ){
			common::CheckDir($dir);
		}


		$fp = fopen($file,'wb');
		chmod($file,0666);
		return $fp;
	}

	function CheckDir($dir){

		if( file_exists($dir) ){
			return true;
		}
		$parent = dirname($dir);
		common::CheckDir($parent);
		if( !mkdir($dir,0755) ){
			return false;
		}
		chmod($dir,0755); //some systems need more than just the 0755 in the mkdir() function

		return true;
	}

}


function microtime_diff($a, $b, $eff = 3) {
	$a = array_sum(explode(" ", $a));
	$b = array_sum(explode(" ", $b));
	return sprintf('%0.'.$eff.'f', $b-$a);
}

function message(){
	global $wbMessageBuffer;
	$wbMessageBuffer[] = func_get_args();
}
function showArray($array){
	if( is_object($array) ){
		$array = get_object_vars($array);
	}

	$text = array();
	$text[] = '<table cellspacing="0" cellpadding="7" class="tableRows" border="0">';
	if(is_array($array)){
		$odd = null;
		$odd2 = null;

		foreach($array as $key => $value){

			if($odd2==1){
				$odd = 'bgcolor="white"';
				//$odd = ' class="tableRowEven" ';
				$odd2 = 2;
			}else{
				$odd = 'bgcolor="#ddddee"';
				//$odd = ' class="tableRowOdd" ';
				$odd2 = 1;
			}
			$text[] = '<tr '.$odd.'><td>';
 			$text[] = htmlspecialchars($key);
			$text[] = "</td><td>";
			if( !empty($value) ){
				if( is_object($value) || is_array($value) ){
					$text[] = showArray($value);
				}elseif(is_string($value)||is_numeric($value)){
					$text[] = htmlspecialchars($value);
				}elseif( is_bool($value) ){
					if($value){
						$text[]= '<tt>TRUE</tt>';
					}else{
						$text[] = '<tt>FALSE</tt>';
					}
				}else{
					$text[] = '<b>--unknown value--:</b> '.gettype($value);
				}
			}
			$text[] = "</td></tr>";
		}
	}else{
		$text[] = '<tr><td>'.htmlspecialchars($array).'</td></tr>';
	}
	$text[] = "</table>";

	return "\n".implode("\n",$text)."\n";
}

