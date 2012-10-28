<?php

class package{

	//package info
	var $package_id;
	var $package;
	var $source;
	var $destination;
	var $full_dest = '';

	//settings
	var $options;
	var $ignore_types = array();
	var $ignore_prefixes = array();
	var $noshrink_paths = array();


	//used while minimizing
	var $make_ignore_paths = array();

	//minimized resulsts
	var $file_count = array('.js'=>0, '.css'=>0 );
	var $size_start = array( '.js'=>0, '.css'=>0 );
	var $size_end = array( '.js'=>0, '.css'=>0 );


	function package($cmd){
		global $config;

		$id = $_REQUEST['id'];
		if( !isset($config['packages'][$id]) ){
			message('Invalid request.');
			return false;
		}
		$this->package_id = $id;
		$this->package = $config['packages'][$id];
		$this->source = rtrim($this->package['source'],'/');
		$this->make_dir = $this->source.'/.easymin';
		$this->package['destination'] = rtrim($this->package['destination'],'/');
		$this->SetOptions();

		//check destination folder
		if( !file_exists($this->source) ){
			message('Source does not exist.');
			return false;
		}

		if( !file_exists($this->package['destination']) ){
			message('Destination does not exist.');
			return false;
		}
		if( !is_writable($this->package['destination']) ){
			message('Destination is not writable.');
			return false;
		}

		$minimized = false;
		ob_start();
		switch($cmd){
			case 'min_project':
				$minimized = $this->MinimizePackage();
			break;
		}
		$this->CreatePackagePrompt();
		$content = ob_get_clean();


		if( $minimized ){
			$total = array_sum($this->file_count);
			echo '<h3>';
			echo number_format($total).' files were copied to <i>'.$this->full_dest.'</i>';
			echo '</h3>';
			echo '<div id="blocklist">';
		}else{
			echo '<div id="blockcenter">';
		}
		echo $content;
		echo '</div>';

	}



	/**
	 * Project Minimize Prompt
	 *
	 */
	function CreatePackagePrompt(){
		global $config;

		$folder = $this->package['name'];

		echo '<form method="post" action="">';
		echo '<input type="hidden" name="id" value="'.htmlspecialchars($this->package_id).'" />';
		echo '<table class="bordered" cellspacing="0" cellpadding="0">';

		echo '<tr><th colspan="2">';
		echo $this->package['name'];
		echo '</th></tr>';


		echo '<tr><td>Source </td><td><tt>';
		echo htmlspecialchars($this->source);
		echo '</tt></td</tr>';


		echo '<tr><td>Destination </td><td><tt>';
		echo htmlspecialchars($this->package['destination']);
		echo '</tt>/ <input type="text" class="sm_text" name="folder" value="'.htmlspecialchars($folder).'"/>';
		echo '</td></tr>';

		echo '<tr><td>CSS Options </td><td>';

			echo '<input type="hidden" name="min_css" value="" />';
			echo '<label>';
			echo '<input type="checkbox" name="min_css" value="min_css" '. ( $this->options['min_css'] ? 'checked="checked"' : '' ).'/>';
			echo ' Minimize CSS';
			echo '</label>';

			echo ' <br/> ';

			echo '<input type="hidden" name="css_full" value="" />';
			echo '<label>';
			echo '<input type="checkbox" name="css_full" value="css_full" '. ( $this->options['css_full'] ? 'checked="checked"' : '' ).'/>';
			echo ' Add {$filename}-full.css';
			echo '</label>';
			echo '</td></tr>';


		echo '<tr><td>JS Options </td><td>';

			echo '<input type="hidden" name="min_js" value="" />';
			echo '<label>';
			echo '<input type="checkbox" name="min_js" value="min_js" '. ( $this->options['min_js'] ? 'checked="checked"' : '' ).'/>';
			echo ' Minimize JS ';
			echo '</label>';

			echo ' <br/> ';

			echo '<input type="hidden" name="js_full" value="" />';
			echo '<label>';
			echo '<input type="checkbox" name="js_full" value="js_full" '. ( $this->options['js_full'] ? 'checked="checked"' : '' ).'/>';
			echo ' Add {$filename}-full.js';
			echo '</label>';
			echo '</td></tr>';

		echo '<tr><td>Ignore File Types </td><td>';
		$types = implode(' ',$this->ignore_types);
		echo '<input type="text" class="text" name="ignore_types" value="'.htmlspecialchars($types).'"/>';
		echo '<div class="example"><tt>'.$this->make_dir.'/ignore_types</tt></div>';
		echo '</td></tr>';

		echo '<tr><td>Ignore Prefixes</td><td>';
		$prefixes = implode(' ',$this->ignore_prefixes);
		echo '<input type="text" class="text" name="ignore_prefixes" value="'.htmlspecialchars($prefixes).'"/>';
		echo '<div class="example"><tt>'.$this->make_dir.'/ignore_prefixes</tt></div>';
		echo '</td></tr>';


		echo '<tr><td>Noshrink Paths</td><td>';
		echo '<textarea type="text" class="text" rows="6" name="noshrink_paths">'.htmlspecialchars($_POST['noshrink_paths']).'</textarea>';
		echo '<div class="example"><tt>'.$this->make_dir.'/noshrink_paths</tt></div>';
		echo '</td></tr>';


		echo '<tr><td></td><td>';
		echo ' <input type="hidden" name="cmd" value="min_project" />';
		echo ' <input type="submit" name="" value="Minimize" />';
		echo ' &nbsp; <input type="submit" name="cmd" value="Cancel" />';
		echo '</td></tr>';


		echo '</table>';
		echo '</form>';

		return true;
	}


	/**
	 * Minimize a project
	 *
	 */
	function MinimizePackage(){
		global $config;

		//get the full destination folder
		$folder =& $_POST['folder'];

		$i = 0;
		do{
			if( $i > 0 ){
				$folder = $this->package['name'].' ('.$i.')';
			}
			$full_dest = $this->package['destination'].'/'.$folder;
			$i++;

		}while( file_exists($full_dest) );


		if( !$this->CopyDir($this->source,$full_dest) ){
			message('Oops, the process was not completed.');
			return false;
		}

		$this->full_dest = $full_dest;

		$this->MinimizeStats();
		return true;
	}

	/**
	 * Display information about the minimized project
	 *
	 */
	function MinimizeStats(){

		echo '<div>';
		echo '<table class="bordered" cellpadding="0" cellspacing="0">';
		echo '<th colspan="2">JavaScript Files </th></tr>';

		echo '<tr><td>Files Minimized </td><td>';
		echo number_format($this->file_count['.js']);
		echo '</td></tr>';

		echo '<tr><td>Original Size </td><td>';
		echo phpproject::FormatBytes( $this->size_start['.js'] );
		echo '</td></tr>';

		echo '<tr><td>Minimized Size </td><td>';
		echo phpproject::FormatBytes( $this->size_end['.js'] );
		echo '</td></tr>';

		echo '<tr><td>Reduction</td><td>';
		$percent = 0;
		if( $this->size_start['.js'] > 0 ){
			$percent = ($this->size_start['.js'] - $this->size_end['.js']) / $this->size_start['.js'];
		}
		echo (number_format($percent,2)*100).'%';
		echo '</td></tr>';

		echo '</table>';
		echo '</div>';


		echo '<div>';
		echo '<table class="bordered" cellpadding="0" cellspacing="0">';
		echo '<th colspan="2">CSS Files </th></tr>';

		echo '<tr><td>Files Minimized </td><td>';
		echo number_format($this->file_count['.css']);
		echo '</td></tr>';

		echo '<tr><td>Original Size </td><td>';
		echo phpproject::FormatBytes( $this->size_start['.css'] );
		echo '</td></tr>';

		echo '<tr><td>Minimized Size </td><td>';
		echo phpproject::FormatBytes( $this->size_end['.css'] );
		echo '</td></tr>';

		echo '<tr><td>Reduction</td><td>';
		$percent = 0;
		if( $this->size_start['.css'] > 0 ){
			$percent = ($this->size_start['.css'] - $this->size_end['.css']) / $this->size_start['.css'];
		}
		echo (number_format($percent,2)*100).'%';
		echo '</td></tr>';

		echo '</table>';
		echo '</div>';

	}

	function SetOptions(){
		global $config;
		$this->options = array();
		$_POST += array(
					'min_css'=>'min_css',
					'css_full'=>'',
					'min_js'=>'min_js',
					'js_full'=>'',
					);


		//ingore file types
		$file = $this->make_dir.'/ignore_types';
		$types = '';
		if( isset($_POST['ignore_types']) ){
			$types = $_POST['ignore_types'];
		}elseif( file_exists($file) ){
			$types = file_get_contents($file);
		}
		$types = preg_split('#\s+#',$types);
		foreach($types as $type){
			$type = trim($type,"\n\r\t\0\x0B ");
			if( empty($type) ){
				continue;
			}
			$this->ignore_types[] = $type;
		}


		//ignore prefix
		$file = $this->make_dir.'/ignore_prefixes';
		$prefixes = '';
		if( isset($_POST['ignore_prefixes']) ){
			$prefixes = $_POST['ignore_prefixes'];
		}elseif( file_exists($file) ){
			$prefixes = file_get_contents($file);
		}
		$prefixes = preg_split('#\s+#',$prefixes);
		foreach($prefixes as $prefix){
			$prefix = trim($prefix,"\n\r\t\0\x0B ");
			if( empty($prefix) ){
				continue;
			}
			$this->ignore_prefixes[$prefix] = $prefix;
		}

		//noshrink
		$file = $this->make_dir.'/noshrink_paths';
		$this->options['noshrink'] = false;
		if( !isset($_POST['noshrink_paths']) ){
			if( file_exists($file) ){
				$_POST += array( 'noshrink_paths'=>file_get_contents($file) );
			}else{
				$_POST['noshrink_paths'] = '';
			}
		}
		$files = preg_split('#\s+#',$_POST['noshrink_paths']);
		foreach($files as $file){
			$file = trim($file);

			if( strpos($file,$this->source) === false ){
				$full_path = $this->source.'/'.ltrim($file,'/');
			}else{
				$full_path = $file;
			}
			if( !file_exists($full_path) ){
				message('The noshrink_path <i>'.$file.'</i> does not match a file or folder in this projects source.');
				continue;
			}

			if( !empty($file) ){
				$this->noshrink_paths[] = $full_path;
				$this->options['noshrink'] = true;
			}
		}

		//min css
		$this->options['min_css'] = true;
		if( empty($_POST['min_css']) ){
			$this->options['min_css'] = false;
		}

		//full css
		$this->options['css_full'] = true;
		if( empty($_POST['css_full']) ){
			$this->options['css_full'] = false;
		}


		//min js
		$this->options['min_js'] = true;
		if( empty($_POST['min_js']) ){
			$this->options['min_js'] = false;
		}


		//full js
		$this->options['js_full'] = true;
		if( empty($_POST['js_full']) ){
			$this->options['js_full'] = false;
		}
	}


	/**
	 * Convert .combine files in a single file
	 *
	 */
	function CombineFiles($combine_file,$to_dir){
		$this->make_ignore_paths[] = $combine_file;

		//file names
		$dir = dirname($combine_file);
		$file_name = basename($combine_file);
		$name_parts = explode('.',$file_name);
		array_pop($name_parts);
		$to_name = implode('.',$name_parts);
		$to_full = $to_dir.'/'.$to_name;
		$type = $this->GetFileType($to_name);


		//contents
		$content = file_get_contents($combine_file);
		$lines = explode("\n",$content);


		ob_start();
		foreach($lines as $line){
			if( strpos($line,'/') !== 0 && strpos($line,'.') !== 0 ){
				echo $line;
				continue;
			}
			$line = trim($line);
			$line = '/'.ltrim($line,'/');
			$full_path = $dir.$line;
			$full_path = realpath($full_path);
			if( !$full_path ){
				message('The file <i>"'.$line.'"</i> does not exist. Expected as defined in <i>'.$file_name.'</i>');
				continue;
			}
			if( !$this->AddCombinePath($full_path,$type) ){
				return false;
			}
		}

		$content = ob_get_clean();

		//save the contents
		switch($type){
			case '.js':
				if( $this->options['min_js'] ){
					return $this->SaveMinJS( $to_full, $content );
				}
			break;

			case '.css':
				if( $this->options['min_css'] ){
					return $this->SaveMinCSS( $to_full, $content );
				}
			break;
		}

		return $this->SaveContents( $to_full, $content );
	}


	/**
	 * Add to the list of ignore paths
	 *
	 */
	function AddCombinePath( $file, $type ){

		$this->make_ignore_paths[] = $file;

		//directory
		if( is_dir($file) ){
			$files = scandir($file);
			$files = array_diff($files,array('.','..'));
			foreach($files as $sub_file){
				$full_path = $file.'/'.$sub_file;
				if( !$this->AddCombinePath($full_path, $type) ){
					return false;
				}
			}
			return true;
		}


		$content = file_get_contents($file);
		$this->size_start[$type] += strlen($content);
		$this->file_count[$type]++;

		switch($type){

			//js
			case '.js':
				$content .= ';';
				if( $this->options['min_js'] ){
					$content = easyjsmin::minimize($content,$file);
					return true;
				}

			//css
			case '.css':
				if( $this->options['min_css'] ){
					$content = phpproject::MinCss($content);
				}
		}

		if( $content === false ){
			return false;
		}

		echo $content;
		return true;
	}




	/**
	 * Copy a directory from the source to destination
	 *
	 */
	function CopyDir( $from_dir, $to_dir ){

		if( !is_dir($from_dir) ){
			trigger_error('Not a directory: '.$from_dir);
			message('Not a directory: '.$from_dir);
			return false;
		}

		if( !is_dir($to_dir) ){
			if( !@mkdir($to_dir) ){
				message('Could not make directory <em>'.$to_dir.'</em>.');
				return false;
			}
			chmod($to_dir,0777);
		}

		set_time_limit(30);
		$files = scandir($from_dir);
		$files = array_diff($files,array('.','..'));


		//convert .combine files
		foreach($files as $file){
			$type = $this->GetFileType($file);
			if( $type != '.combine' ){
				continue;
			}
			$full_from = $from_dir.'/'.$file;

			if( !$this->CombineFiles($full_from,$to_dir) ){
				return false;
			}
		}



		//copy each file if needed
		foreach($files as $file){

			$full_from = $from_dir.'/'.$file;
			$full_to = $to_dir.'/'.$file;

			if( !$this->CheckFile($full_from) ){
				//if( !is_dir($from) ){
				//	$this->size_end[$type] += $curr_size;
				//}
				continue;
			}


			if( is_dir($full_from) ){
				if( !$this->CopyDir($full_from,$full_to) ){
					return false;
				}
				continue;
			}

			if( !$this->CopyFile($full_from,$full_to) ){
				return false;
			}

		}

		return true;
	}


	/**
	 * Copy a single file from the source to destination
	 *
	 */
	function CopyFile($from,$to){
		$type = $this->GetFileType($from);

		if( !isset($this->size_start[$type]) ){
			$this->size_start[$type] = 0;
			$this->size_end[$type] = 0;
			$this->file_count[$type] = 0;
		}
		$this->file_count[$type]++;
		$curr_size = filesize($from);
		$this->size_start[$type] += $curr_size;




		$result = true;
		switch($type){
			case '.js':
				if( $this->CanShrink($from) ){
					return $this->CopyJS($from,$to);
				}
			break;

			case '.css':
				if( $this->CanShrink($from) ){
					return $this->CopyCSS($from,$to);
				}
			break;
		}

		if( copy($from,$to) ){
			chmod($to,0777);
			$this->size_end[$type] += $curr_size;
			return true;
		}

		return false;
	}


	function CopyCSS($fromNew,$toNew){
		$buffer = file_get_contents($fromNew);
		return $this->SaveMinCSS( $toNew, $buffer );
	}

	function SaveMinCSS( $to_full, $content ){

		//make a full copy first
		if( $this->options['css_full'] ){
			$full_file = substr($to_full,0,-4).'-full.css';
			if( !$this->SaveContents( $full_file, $content) ) return false;
		}


		$content = phpproject::MinCss($content);
		$this->size_end['.css'] += strlen($content);

		return $this->SaveContents( $to_full ,$content );
	}


	function CopyJS($from,$to){
		global $project;

		$buffer = file_get_contents($from);


		return $this->SaveMinJS($to,$buffer);
	}

	function SaveMinJS($to_full,$content){

		if( $this->options['js_full'] ){
			$full_file = substr($to_full,0,-3).'-full.js';
			if( !$this->SaveContents($full_file,$content) ) return false;
		}

		$content = easyjsmin::minimize($content,$to_full);

		$this->size_end['.js'] += strlen($content);

		return $this->SaveContents($to_full,$content);
	}

	function CheckFile($full_from){

		$filename = basename($full_from);
		$type = $this->GetFileType($filename);

		if( is_link($full_from) ){
			return false;;
		}

		if( in_array($full_from,$this->make_ignore_paths) ){
			return false;
		}

		//by file type
		if( in_array($type,$this->ignore_types) ){
			return false;
		}

		//by file name
		foreach($this->ignore_prefixes as $prefix){
			if( strpos($filename,$prefix) === 0 ){
				return false;
			}
		}

		return true;
	}

	//check for noshrink paths
	function CanShrink($from){

		$type = $this->GetFileType($from);
		switch($type){
			case '.js':
				if( !$this->options['min_js'] ){
					return false;
				}
			break;

			case '.css':
				if( !$this->options['min_css'] ){
					return false;
				}
			break;
		}

		if( $this->options['noshrink'] ){
			foreach($this->noshrink_paths as $path){
				if( strpos($from,$path) === 0 ){
					return false;
				}
			}
		}
		return true;
	}

	function SaveContents( $file, $content ){
		if( $content === false ){
			return false;
		}
		$fh = fopen($file,'w');
		if( !$fh ) return false;
		if( fwrite($fh,$content) === false ){
			fclose($fh);
			message('SaveContents() failed: '.htmlspecialchars($file));
			return false;
		}
		fclose($fh);
		chmod($file,0777);
		return true;
	}

	function GetFileType($file){
		$type = '';
		$dotSpot = strrpos($file,'.');
		if( $dotSpot !== false ){
			$type = substr($file,$dotSpot);
		}
		return strtolower($type);
	}

}



