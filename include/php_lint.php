<?php

class php_lint{

	var $results = array();

	var $root = '';
	var $root_len = 0;

	var $checked_count = 0;
	var $checked_files = array();
	var $checked_messages = array();


	function php_lint(){
		global $config;

		if( !function_exists('shell_exec') ){
			echo '<p>Sorry, shell_exec() is requred for php lint</p><p>Please enable the shell_exec() function then try again.</p>';
			return;
		}

		$id = $_GET['id'];
		if( !isset($config['packages'][$id]) ){
			message('Invalid request.');
			return false;
		}
		$this->root = realpath($config['packages'][$id]['source']);
		$this->root_len = strlen($this->root);


		$this->LintDir($this->root);
		echo '<p>Checked '.number_format($this->checked_count).' files using <i>php -l</i></p>';
		echo '<div id="tabs"></div>';
		foreach($this->checked_messages as $hash => $message){
			$files = $this->results[$hash];

			echo '<div>';
			echo '<h2 class="checkphphead">'.$this->FilesCount(count($files)).$message.'</h2>';
			echo showArray($files);
			echo '</div>';
		}
	}

	function FilesCount($count){
		if( $count === 1 ){
			return ' (1 file) ';
		}else{
			return ' ('.number_format($count).' files) ';
		}
	}
	function LintDir($dir){
		$files = scandir($dir);
		foreach($files as $file){
			if( $file == '.' || $file == '..' ){
				continue;
			}
			$full = $dir.'/'.$file;
			if( is_dir($full) ){
				$this->LintDir($full);
				continue;
			}

			$parts = explode('.',$file);
			$ext = array_pop($parts);
			if( strtolower($ext) !== 'php' ){
				continue;
			}

			$this->LintFile($full);
		}
	}

	function LintFile($full){
		$full = realpath($full);
		if( strpos($full,$this->root) === false ){
			return;
		}
		$relative = substr($full,$this->root_len);
		if( array_key_exists($relative,$this->checked_files) ){
			return;
		}

		$this->checked_count++;
		$output = shell_exec('php -l "'.$full.'"');
		$output = htmlspecialchars($output);
		$output = str_replace(htmlspecialchars($full),'<i>--filename--</i>',$output);
		$hash = md5($output).sha1($output);

		if( !array_key_exists($hash,$this->checked_messages) ){
			$this->checked_messages[$hash] = $output;
		}

		$this->checked_files[$relative] = $hash;
		$this->results[$hash][] = $relative;
	}

}