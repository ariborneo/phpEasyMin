<?php

set_time_limit(0);
global $rootDir,$PHPFunctions;


/*
 * get_defined_constants() //doesn't work well till 5.3.0 (5.3.1 fow windows)
 *
 * @todo
 * - count variables in nested functions separately
 */
$PHPFunctions = unserialize(file_get_contents($rootDir.'/include/php_version/php-functions.txt'));


//require('test_script.php');
require('php_version_output.php');

class php_version_check extends php_version_output{

	var $PHPConstants = array();

	var $tokens = array();
	var $token_key = 0;
	var $token_count = 0;

	//new from php_version
	var $functionsUsed = array();
	var $functionsCheckedFor = array();
	var $customFunctions = array();
	var $constantsUsed = array();
	var $objectLookup = array();
	var $unknownFunctions = array();
	var $customDefines = array();


	function php_version_check(){
		global $config;

		$id = $_GET['id'];
		if( !isset($config['packages'][$id]) ){
			message('Invalid request.');
			return false;
		}

		$this->Init();

		$source = $config['packages'][$id]['source'];

		if( is_dir($source) ){
			$this->ByDir($source);
		}else{
			$this->php_parse_file($source);
		}
		$this->Output_Results();
	}

	function Init(){

		//constants
		$PHPConstants = get_defined_constants(true);

		foreach($PHPConstants as $category => $constants){
			foreach($constants as $constant => $value){
				$this->PHPConstants[$constant] = array('category'=>$category,'value'=>$value);
			}
		}

	}


	function ByDir($dir){
		$files = scandir($dir);

		foreach($files as $file){
			if( ($file == '.') || ($file == '..') ){
				continue;
			}
			if( strpos($file,'x_') === 0 ){
				return false;
			}
			if( strpos($file,'X_') === 0 ){
				return false;
			}
			if( strpos($file,'_') === 0 ){
				return false;
			}


			$full_path = $dir.'/'.$file;
			if( is_dir($full_path) ){
				$this->ByDir($full_path);
				continue;
			}

			if( substr($file,-4) == '.php' ){
				$this->php_parse_file($full_path);
			}
		}
	}



	/* from php-versions */

	function php_parse_file($inputfile){
		global $PHPFunctions;

		$source = file_get_contents($inputfile);
		$this->tokens = token_get_all($source);
		$token_key = 0;
		$this->token_count = count($this->tokens);

		$in_class = 0;
		$class = '';
		$functionname = '';

		for($token_key;$token_key<$this->token_count;$token_key++){
			$token =& $this->tokens[$token_key];
			if( ! is_array($token) ){
				if( $token == '}' && $in_class > 0){
					--$in_class;
					if( $in_class < 1){
						$class = '';
					}
				}
				if( $token == '{' && $in_class > 0 ){
					++$in_class;
				}
				continue;
			}

			switch( $token[0] ){


				case T_PUBLIC:
				case T_PRIVATE:
				case T_PROTECTED:
					//message('its here: '.$token[1]);
					// is it a var or function?
				break;
				case T_VAR:
					if( $in_class && !empty($class) ){
						$this->T_VAR_class($token_key,$class);
						break;
					}
				break;

				case T_CURLY_OPEN:
				case T_DOLLAR_OPEN_CURLY_BRACES:
					if( $in_class ){
						$in_class++;
					}
				break;

				//Class declarations may not be nested
				case T_CLASS:
					if( $in_class > 0 ){
						message('Can not next class declarations: '.showArray($token));
					}
					$in_class = 1;
					$class = $this->Get_Next_String($token_key,T_STRING);
					$this->Get_Next_String($token_key,'{');
				break;

				case T_NEW:
					$this->T_NEW($token_key,$inputfile,$functionname,$class);
				break;


				case T_STRING:

					//if the function call or variable is too complicated, we skip it
					if( !$this->get_full_string($token_key,$class,$full_string,$string,$string_class) ){
						continue;
					}

					if( $this->is_function_call($token_key) ){

						//functions are case insensitive
						if( $string_class !== false ){
							$full_string = $string_class.'::'.strtolower($string);
						}else{
							$full_string = strtolower($string);
						}

						$this->increment($this->functionsUsed,$full_string);

						switch($full_string){
							case 'function_exists':
								$this->function_check($token_key);
							break;
							case 'define':
								$this->new_constant($token_key);
							break;
						}

						continue;
					}

					if( $string_class !== false ){
						$this->increment($this->customClasses[ $string_class ]['variableuses'],'$'.$string);
						continue;
					}

					//constants
					if( isset($this->PHPConstants[$string])
						|| in_array( strtolower($string), array('true','false','null') )
						|| isset($this->customDefines[ $string ])
						){
						$this->increment($this->constantsUsed,$string);
					}

				break;

				case T_FUNCTION:

					$functionname = $this->Get_Next_String($token_key,T_STRING);
					$functionline = $token[2];
					$functionargs = $this->get_function_args($token_key,$string);
					$functionvars = $this->count_function_vars($token_key);

					$key = strtolower($functionname); //function/method names are case insensitive

					if( $in_class ){
						$this->customClasses[ $class ]['functions'][] = $functionname;
						$key = "$class::$key";
					}

					if( isset($this->customFunctions[$key]) ){
						//message('function defined twice: '.$key);
					}

					$this->customFunctions[ $key ] = array(	'name' => $functionname,
															'class'=> $class,
															'file'=> $inputfile,
															'line' => $functionline,
															'args' => $functionargs,
															'variables' => $functionvars);
				break;
			}
		}
	}


	function count_function_vars($token_key){

		$function_vars = array();

		$level = 0;
		for($i = $token_key; $i < $this->token_count; $i++){
			$token = $this->tokens[$i];
			if( !is_array($token) ){
				if( $token == '{' ){
					$level++;
				}elseif( $token == '}' ){
					$level--;
					if( $level < 1 ){
						return $function_vars;
					}
				}
				continue;
			}


			switch($token[0]){

				case T_DOLLAR_OPEN_CURLY_BRACES:
				case T_CURLY_OPEN:
					$level++;
				break;

				case T_VARIABLE:
					if( $token[1] == '$this' ){
						continue;
					}
					if( $token[1] == '$GLOBALS' ){
						continue;
					}
					if( $token[1]{1} == '_' ){
						continue;
					}
					$this->increment($function_vars,$token[1]);
				break;
				case T_STRING:
					if( $token[1] == 'compact' ){
						$this->get_compact_vars($i,$function_vars);
					}
				break;
			}
		}

		return $function_vars;
	}

	function get_compact_vars($token_key,$function_vars){

		for( $k = $token_key; $k < $this->token_count; $k++){
			$token = $this->tokens[$k];
			if( ')' == $token ){
				return;
			}
			if( !is_array($token) ){
				continue;
			}

			if( T_CONSTANT_ENCAPSED_STRING == $token[0] ){
				$string = '$' . trimq($token[1]);
				$this->increment($function_vars,$string);
			}
		}
	}


	function get_function_args($token_key,&$function_string){

		// $function_string can be used to help debug
		$function_string = '';


		$last_arg = false;
		$function_args = array();
		$i = $token_key;
		$this->Get_Next_String($i,'(');

		for($i; $i < $this->token_count; $i++){

			$token = $this->tokens[$i];

			if( !is_array($token) ){
				//$function_string .= $token;
				if( '{' === $token ){
					return $function_args;
				}
				continue;
			}
			//$function_string .= $token[1];

			switch($token[0]){

				case T_VARIABLE:
					$last_arg = $token[1];
					$function_args[ $last_arg ] = null;
				break;

				case T_LNUMBER:
				case T_CONSTANT_ENCAPSED_STRING:
				case T_STRING:
					if( $last_arg ) $function_args[ $last_arg ] = $token[1];
				break;
				case T_ARRAY:
					//if no parentheses, then it's probably a php5 style array type cast
					if( $this->ExpectingNext($i,'(') ){
						if( $last_arg ) $function_args[ $last_arg ] = $this->get_simple_array($i);
					}
				break;
			}
		}
		return $function_args;
	}

	function get_simple_array(&$i){

		$this->Get_Next_String($i,'(');
		$array_string = 'array(';

		for($i; $i < $this->token_count; $i++){
			$token = $this->tokens[$i];
			if( !is_array($token) ){
				if( ')' === $token ){
					$array_string .= ')';
					return $array_string;
				}
				continue;
			}
			switch($token[0]){
				case T_COMMENT:
				case T_DOC_COMMENT:
				case T_WHITESPACE:
				break;

				case T_ARRAY:
					$this->get_simple_array($i);
				break;

				default:
					$array_string .= $token[1];
				break;
			}
		}
	}


	function new_constant($token_key){


		$define = false;
		for($i = $token_key+1; $i < $this->token_count; $i++){
			$token = $this->tokens[$i];

			if( !is_array($token) ){
				if( $token == '(' ){
					continue;
				}
				if( ',' == $token ){
					$i++;
					break;
				}
				return;
			}


			switch($token[0]){
				case T_CONSTANT_ENCAPSED_STRING:
					$define = trimq($token[1]);
				break;

				case T_COMMENT:
				case T_DOC_COMMENT:
				case T_WHITESPACE:
				break;

				default:
				return;
			}
		}

		if( $define == false ){
			return;
		}


		//get the value

		$value = '';
		for($i; $i < $this->token_count; $i++){
			$token = $this->tokens[$i];
			if( !is_array($token) ){
				if( $token == ')' ){
					continue;
				}
				break;
			}
			switch($token[0]){
				case T_NUM_STRING:
				case T_LNUMBER:
				case T_DNUMBER:
				case T_STRING:
				case T_CONSTANT_ENCAPSED_STRING:
				case T_VARIABLE:
				case T_ENCAPSED_AND_WHITESPACE:
					$value = trimq($token[1]);
				break 2;

				case T_COMMENT:
				case T_DOC_COMMENT:
				case T_WHITESPACE:
				break;

				default:
				break 2;
			}
		}

		$this->customDefines[ $define ] = $value;
	}

	function function_check(&$token_key){

		for($token_key; $token_key < $this->token_count; $token_key++){
			$token = $this->tokens[$token_key];
			if( !is_array($token) ){
				if( ')' == $token ){
					return;
				}
				continue;
			}

			if( $token[0] == T_CONSTANT_ENCAPSED_STRING ){
				$string = trimq($this->tokens[$token_key][1]);
				$string = strtolower($string);
				$this->increment($this->functionsCheckedFor, $string);
			}
		}

	}


	//get full function or variable string.
	//	this could be class::method() or $variable->method() or function() or $this->variable
	function get_full_string($token_key,$class,&$full_string,&$string,&$string_class){
		$string_class = false;
		$full_string = $string = $this->tokens[$token_key][1];

		$another = false;

		for($i = $token_key-1;$i > 0; $i--){
			$token = $this->tokens[$i];

			if( !is_array($token) ){
				$token_code = $token;
				$token_value = $token;
			}else{
				$token_code = $token[0];
				$token_value = $token[1];
			}

			switch($token[0]){

				case T_OBJECT_OPERATOR:
				case T_DOUBLE_COLON:
					$another = true; //expecting a $variable or class_name
				break;

				case T_COMMENT:
				case T_DOC_COMMENT:
				case T_WHITESPACE:
				break;

				case T_STRING:
					if( $another ){
						$string_class = $token_value;
						$full_string = $token_value.'::'.$string;
					}
				return true;

				case T_VARIABLE:
					if( $another ){
						if( $token_value == '$this' && $class ){
							$string_class = $class;
							$full_string = $class.'::'.$string;
						}elseif( isset($this->objectLookup[$token_value]) ){
							$best_class = $this->select_best_class($token_value);
							$string_class = $best_class['name'];
							$full_string = $best_class['name'].'::'.$string;
						}else{
							$string_class = $token_value;
							$full_string = $token_value.'::'.$string;
						}
					}
				return true;

				default:
				break 2;
			}
		}

		//if expecting another part, but din't find, the function call is probably too complicated
		//... could even be $variable()->get_class()::method()
		if( $another ){
			return false;
		}
		return true;
	}

	function is_function_call($token_key){

		for($i = $token_key+1;$i < $this->token_count; $i++){
			$token = $this->tokens[$i];
			if( $token == '(' ){
				return true;
			}
			if( !is_array($token) ){
				return false;
			}

			switch($token[0]){


				case T_COMMENT:
				case T_DOC_COMMENT:
				case T_WHITESPACE:
				break;

				default:
				return false;
			}
		}
	}

	function Get_Next_String(&$token_key,$search_code=T_STRING){
		for($token_key; $token_key < $this->token_count; $token_key++){
			$token = $this->tokens[$token_key];
			if( is_array($token) ){
				$token_code = $token[0];
				$token_value = $token[1];
			}else{
				$token_code = $token_value = $token;
			}

			if( $token_code == $search_code ){
				return $token_value;
			}
		}
	}

	function ExpectingNext($token_key,$search_code=T_STRING){
		for($token_key; $token_key < $this->token_count; $token_key++){
			$token = $this->tokens[$token_key];
			if( is_array($token) ){
				$token_code = $token[0];
				$token_value = $token[1];
			}else{
				$token_code = $token_value = $token;
			}

			switch($token_code){
				case $search_code:
				return true;

				case T_COMMENT:
				case T_DOC_COMMENT:
				case T_WHITESPACE:
				break;

				default:
				return false;
			}
		}
		return false;
	}


	function T_NEW(&$token_key,$inputfile,$functionname,$class){

		//Find variable name
		$variable = '';
		$class_name = '';
		for( $j = $token_key; $j > 0; $j--){
			$token = $this->tokens[$j];

			if( !is_array($token) ){
				if( $token == ';' ){
					break;
				}
				continue;
			}

			if( $token[0] == T_VARIABLE ){
				$variable = $token[1];
				break;
			}
			if( $token[0] == T_RETURN ){
				$variable = $functionname . '()';
				break;
			}
		}


		if( '$this' == $variable){
			for( $j = $token_key; $j < $this->token_count; $j++){
				$token = $this->tokens[$j];
				if( !is_array($token) ){
					continue;
				}
				if( T_STRING == $token[0] ){
					$variable = $class . '::' . $token[1];
					break;
				}
			}
		}

		//Find class name
		for( $token_key; $token_key < $this->token_count; $token_key++){
			$token = $this->tokens[$token_key];
			if( !is_array($token) ){
				continue;
			}

			//Found it!
			if( T_STRING == $token[0] ){
				$class_name = $token[1];
				break;
			}
		}

		//Put them together:
		$this->objectLookup[ $variable ][] = array('name' => $class_name, 'file' => $inputfile, 'line' => $this->tokens[ $token_key ][2]);
	}


	//We're in a class and just encountered a class Variable.
	function T_VAR_class(&$key,$class){

		for($key; $key < $this->token_count; $key++){
			$token = $this->tokens[$key];

			if( !is_array($token) ){
				if( ';' == $token ){
					return;
				}
				continue;
			}

			switch($token[0]){
				case T_VARIABLE:
					$this->customClasses[ $class ]['variables'][ $token[1] ] = true;
				break;
			}
		}
	}

	function select_best_class($variable, $class=''){
		foreach( $this->objectLookup[ $variable ] as $name => $info){
			if( stripos(strtolower($name), 'error') > -1 && count($this->objectLookup[ $variable ]) > 1 )
				continue;
			return $info;//['name'];
		}
	}

	function increment(&$array, $key, $increment=1, $startval=1){
		if( isset($array[ $key ]) ){
			$array[ $key ] += $increment;
		}else{
			$array[ $key ] = $startval;
		}
	}


}




function trimq($in){
	return trim( $in,'"\'');
}

