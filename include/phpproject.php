<?php
define('is_running',true);
ini_set( 'default_charset', 'utf-8' );
error_reporting(E_ALL);

require('common.php');
require('jsmin.php');


global $rootDir,$config,$project;
$config = array();
$project = new phpproject();
$project->Run();

require('template.php');


class phpproject{

	var $content;
	var $startTime;
	var $title = '';
	var $ready_and_writable = false;


	//cache info
	var $cache_size = 0;
	var $cache_oldest = 0;
	var $cache_files = array();

	function phpproject(){

		$this->startTime = microtime();
		$this->Init();

		//message('<a href="http://code.google.com/p/closure-compiler/issues/detail?id=205" target="_blank">UTF Issue</a>');
		//http://groups.google.com/group/closure-compiler-discuss/browse_thread/thread/1345e67b388f8251/11d682335fdeebd3');

	}

	function GetTime(){
		echo '<p>';
		$duration = microtime_diff($this->startTime, microtime());
		echo $duration.' seconds';
		echo '</p>';
	}

	function Run(){
		ob_start();

		if( !$this->ready_and_writable ){
			$this->NotReady();
		}else{
			$this->RunInner();
		}

		$this->content = ob_get_clean();
	}

	function RunInner(){

		/* actions */
		$cmd = common::GetCommand();
		$show = true;
		switch($cmd){


			case 'cache':
				$this->CacheInfo();
			return;

			case 'checkphp';
				$this->title = 'Analyze PHP';
				require('php_version/php_version.php');
				new php_version_check();
				$show = false;
			break;

			case 'about':
				$this->About();
				$show = false;
			break;

			case 'save_config':
				$this->PostConfig();
			case 'config':
				$this->ShowConfig();
				$show = false;
			break;


			case 'min_project':
			case 'min_proj_prompt':
				$this->title = 'Minimize Project';
				require('package.php');
				new package($cmd);
				$show = false;
				$this->ReduceCache();
			break;

			case 'rmpackage':
				if( $this->RmPackage() ){
					$show = false;
				}
			break;
			case 'confirm_remove':
				$this->RmPackageConfirm();
			break;


			case 'save_package':
				if( $this->SavePackage() ){
					break;
				}

			case 'newproject':
				$this->StartPackage();
				$show = false;
			break;

			case 'editpackage':
				$this->EditPackage();
				$show = false;
			break;
			case 'save_changes':
				if( !$this->SaveChanges() ){
					$show = false;
				}
			break;


			/**
			 * One file at a time
			 *
			 */
			case 'minfile':
				$this->MinFile();
				$show = false;
			break;

			case 'minifyjsfile';
			case 'Minimize JavaScript':
				$this->MinimizeJS();
				$this->MinFile();
				$show = false;
				$this->ReduceCache();
			break;

			case 'Minimize CSS':
				$this->MinimizeCSS();
				$this->MinFile();
				$show = false;
			break;

		}
		if( $show ){
			$this->ShowPackages();
		}
	}

	function About(){
		$this->title = 'About phpEasyMin';

		echo '<p>';
		echo 'phpEasyMin can be used to organize your projects and compress CSS and JavaScript files. ';
		echo '</p>';

		echo '<h2>CssMin & Closure Compiler</h2>';
		echo '<p>';
		echo 'Minimizing CSS and JavaScript is accomplished using <a href="http://code.google.com/p/cssmin/">CssMin</a> and <a href="http://code.google.com/closure/compiler/">Google\'s Closure Compiler</a>. ';
		echo '</p>';
		echo '<p>';
		echo 'phpEasyMin uses the Closure Compiler service API to make remote calls to the compiler. phpEasyMin will cache the responses to reduce overhead and help prevent reaching Closure Compiler\'s usage limit.';
		echo '</p>';


		echo '<h2>Example</h2>';
		echo '<p>The following example shows how phpEasyMin can reduce the number of files and reduce file sizes to quickly and easily create a downloadable package.';


		echo '<table class="bordered" cellpadding="0" cellspacing="0">';
		echo '<tr><th>Original Folder</th><th>Minimized Folder</th></tr>';
		echo '<tr><td>';
		echo '<ul><li>Number of Files: 781</li>';
		echo '<li>Size: 16.4 MB</li>';
		echo '<li>JavaScript: 239 KB</li>';
		echo '<li>CSS: 6 KB</li>';
		echo '</ul>';
		echo '<p><img src="include/static/example1.png" /></p>';
		echo '</td><td>';
		echo '<ul><li>Number of Files: 20</li>';
		echo '<li>Size: 1.6 MB</li>';
		echo '<li>JavaScript: 92 KB</li>';
		echo '<li>CSS: 5 KB</li>';
		echo '</ul>';
		echo '<p><img src="include/static/example2.png" /></p>';
		echo '</td></tr>';
		echo '</table>';



		echo '<p>';
		echo '<a href="http://phpEasyMin.com">phpEasyMin.com</a>';
		echo '</p>';

	}




	function Init(){
		global $rootDir, $config;
		$rootDir = str_replace('\\','/',dirname(dirname(__FILE__)));
		$configFile = $rootDir.'/data/x_config.php';

		if( !is_writable($rootDir.'/data') ){
			return false;
		}

		if( file_exists($configFile) && !is_writable($configFile) ){
			return false;
		}

		if( file_exists($configFile) ){
			require($configFile);
		}

		$config += array(
					'destination' => $rootDir.'/output',
					'cache_max' => 10485760 //10mb
					);

		$this->ready_and_writable = true;
	}

	function NotReady(){
		global $rootDir;
		$this->title = 'Installation';

		echo '<p>
				This looks like a new installation of phpEasyMin. Welcome!
				</p>
				<p>
				To get started, phpEasyMin needs to be able to write to the <tt>'.$rootDir.'/data</tt> folder.
				On unix style machines, this can be done with the following in your terminal:
				<blockquote>chmod 777 '.$rootDir.'/data/</blockquote>
				</p>
				<p>
				Please Remember: phpEasyMin was not designed with any security features and should not be used on any computer that can be accessed by others via the internet.
				</p>
			 ';
	}

	function SaveConfig(){
		global $rootDir,$config;
		$configFile = $rootDir.'/data/x_config.php';

		if( !is_writable($rootDir.'/data') ){
			message('<b>Warning:</b> The data directory is not writable: '.$rootDir.'/data');
			return false;
		}
		if( !is_array($config) ){
			return false;
		}

		return common::SaveArray($configFile,'config',$config);
	}



	/**
	 * Show the configuration form
	 * @To do
	 * 	- add ignore file types
	 *
	 */
	function ShowConfig(){
		global $config,$rootDir;
		$this->title = 'Configuration';

		echo '<div id="blockcenter">';
		echo '<form method="post" action="">';
		echo '<table class="bordered" cellspacing="0" cellpadding="0">';

		echo '<tr><th colspan="2">Configuration</th></tr>';

		echo '<tr><td>Default Destination</td><td>';
		echo '<input type="text" class="text" name="destination" value="'.htmlspecialchars($config['destination']).'"/>';
		echo '</td></tr>';

		echo '<tr><td>Maximum Cache Size</td><td>';
		echo '<input type="text" class="sm_text" name="cache_max" value="'.htmlspecialchars($config['cache_max']).'"/> bytes';
		echo '</td></tr>';


		echo '<tr><td>&nbsp;</td><td>';
		echo ' <input type="hidden" name="cmd" value="save_config" />';
		echo ' <input type="submit" name="" value="Save Config" />';
		echo ' <input type="submit" name="cmd" value="Cancel" />';
		echo '</td></tr>';

		echo '</table>';
		echo '</form>';

		echo '</div>';
	}

	function PostConfig(){
		global $config;
		$save = true;

		//destination
		$dest = $_POST['destination'];
		if( !file_exists($dest) ){
			message('Sorry, the destination path you entered does not exist.');
			$save = false;
		}elseif( !is_writable($dest) ){
			message('Sorry, the destination path you entered is not writable.');
			$save = false;
		}else{
			$config['destination'] = $dest;
		}

		$config['cache_max'] = (int)$_POST['cache_max'];

		unset($config['ignore_types']);
		unset($config['ignore_prefix']);
		unset($config['ignore_beginning']);

		if( !$save ){
			return;
		}

		if( $this->SaveConfig() ){
			message('Your configuration was saved.');
		}else{
			message('Oops, the configuration was not saved. Please try again.');
		}

		$this->ReduceCache();
	}


	function ShowPackages(){
		global $config;

		if( !isset($config['packages']) || count($config['packages']) == 0 ){
			$this->EasyMinStart();
			return;
		}

		echo '<div id="blocklist">';
		$temp = array();
		foreach($config['packages'] as $key => $package ){
			$package['key'] = $key;
			$temp[$package['group']][] = $package;
		}

		foreach($temp as $group => $package_group ){
			echo '<div>';
			echo '<table class="bordered" cellspacing="0" cellpadding="0">';
			echo '<tr><th colspan="2">';
			echo $group;
			echo '</th></tr>';
			echo '<tbody class="list">';

			$i = 0;
			foreach($package_group as $id => $package ){
				echo '<tr class="'.( $i%2==0 ? 'even' : 'odd' ).'">';
				echo '<td>';
				echo '<a href="?cmd=min_proj_prompt&id='.$package['key'].'">'.$package['name'].'</a>';
				echo '</td>';
				echo '<td class="options">';
				echo '<a href="?cmd=min_proj_prompt&id='.$package['key'].'">Minimize...</a>';
				echo '<a href="?cmd=checkphp&id='.$package['key'].'">Check PHP</a>';
				echo ' <a href="?cmd=editpackage&id='.$package['key'].'">Edit</a>';
				echo ' <a href="?cmd=rmpackage&id='.$package['key'].'">Delete</a>';
				echo '</td>';
				echo '</tr>';
				$i++;
			}
			echo '</tbody>';
			echo '</table>';
			echo '</div>';
		}
		echo '</div>';
	}

	function EasyMinStart(){
		echo '<p>Welcome to phpEasyMin.</p>';
		echo '<p>To test things out, you can <a href="?cmd=minfile">minimize individual files</a>, but the real power and benefit of phpEasyMin is the ability to minimize all the files of your project in one sweep.</p>

		<p>To get started, you\'ll first need to <a href="?cmd=newproject">set up a project</a>. Give it a name, tell phpEasyMin where the source directory is, and where you want phpEasyMin to put the minimized code.
		Then save, click "Minimize" and you\'re done. You\'ll notice a few more options on the way, but they\'re hopefully somewhat self-explanatory.</p>

		<p><a href="?cmd=newproject">Start a new project now...</a></p>

		<p>Please Remember: phpEasyMin was not designed with any security features and should not be used on any computer that can be accessed by others via the internet.</p>';

	}



	function RmPackageConfirm(){
		global $config;

		$id = $_GET['id'];
		if( !isset($config['packages'][$id]) ){
			return false;
		}
		unset($config['packages'][$id]);
		$this->SaveConfig();
	}

	function RmPackage(){
		global $config;

		$id = $_GET['id'];
		if( !isset($config['packages'][$id]) ){
			return false;
		}
		$package =& $config['packages'][$id];

		echo '<form method="post" action="">';
		echo '<p>';
		echo 'Are you sure you want to remove package <em>'.$package['name'].'</em>.';
		echo '<input type="hidden" name="id" value="'.$id.'"/>';
		echo '<input type="hidden" name="cmd" value="confirm_remove"/>';
		echo ' <input type="submit" name="" value="Confirm" />';
		echo ' <input type="submit" name="cmd" value="Cancel" />';
		echo '</p>';
		echo '</form>';

		return true;
	}




	/*
	 *
	 * Creating and Editing Package Information
	 *
	 *
	 */

	function CheckPackageInfo(){

		if( !file_exists($_POST['source']) ){
			message('Source does not exist.');
			return false;
		}
		if( !file_exists($_POST['destination']) ){
			message('Destination does not exist.');
			return false;
		}
		if( empty($_POST['name']) ){
			message('Name cannot be empty');
			return false;
		}

		return true;
	}

	function SavePackage(){
		global $config;

		if( !$this->CheckPackageInfo() ){
			return false;
		}

		$package = array();
		$package['name'] = htmlspecialchars($_POST['name']);
		$package['group'] = substr(htmlspecialchars($_POST['group']),0,30);
		$package['source'] = rtrim($_POST['source'],'/');
		$package['destination'] = rtrim($_POST['destination'],'/');

		$config['packages'][] = $package;
		$this->SaveConfig();

		return true;

	}


	function SaveChanges(){
		global $config;


		$id = $_POST['id'];
		if( !isset($config['packages'][$id]) ){
			message('Invalid request.');
			return true;
		}

		if( !$this->CheckPackageInfo() ){
			$this->EditPackage($_POST);
			return false;
		}

		$package =& $config['packages'][$id];
		$package['name'] = htmlspecialchars($_POST['name']);
		$package['group'] = substr(htmlspecialchars($_POST['group']),0,30);
		$package['source'] = rtrim($_POST['source'],'/');
		$package['destination'] = rtrim($_POST['destination'],'/');

		$this->SaveConfig();
		return true;
	}

	function EditPackage($package = false){
		global $rootDir,$config;

		$id = $_REQUEST['id'];
		if( !isset($config['packages'][$id]) ){
			message('Invalid request.');
			return false;
		}

		if( $package === false ){
			$package = $_REQUEST;
			$package =& $config['packages'][$id];
		}

		$this->title = 'Edit Project'; //$package['name'];

		echo '<div id="blockcenter">';
		echo '<form method="post" action="">';
		echo '<input type="hidden" name="id" value="'.$id.'" />';
		$this->DetailsForm($package);
		echo '<tr><td>&nbsp;</td><td>';
		echo ' <input type="submit" name="" value="Save Changes" />';
		echo ' <input type="hidden" name="cmd" value="save_changes" />';
		echo ' <input type="submit" name="cmd" value="Cancel" />';
		echo '</td></tr>';
		echo '</table>';
		echo '</form>';
		echo '</div>';
	}

	function DetailsForm($vars = array() ){
		global $rootDir,$config;

		$vars += array('source'=>'','destination'=>'','name'=>'','new_group'=>'','group'=>'');

		echo '<table class="bordered" cellpadding="0" cellspacing="0">';

		echo '<tr><th>Option </th><th>Value</th></tr>';

		echo '<tr><td>Name </td><td>';
		echo '<input type="text" size="40" class="text" name="name" value="'.htmlspecialchars($vars['name']).'"/>';
		echo '</td></tr>';

		echo '<tr><td>Source </td><td>';
		echo '<input type="text" size="40" class="text" name="source" value="'.htmlspecialchars($vars['source']).'"/>';
		echo '<div class="example">Example: '.$rootDir.'/include</div>';
		echo '</td></tr>';

		echo '<tr><td>Destination </td><td>';
		if( empty($vars['destination']) ){
			$temp = $rootDir.'/output';
			if( is_writable($temp) ){
				$vars['destination'] = $temp;
			}
		}
		echo '<input type="text" size="40" class="text" name="destination" value="'.htmlspecialchars($vars['destination']).'"/>';
		echo '<div class="example">Example: '.$rootDir.'/output</div>';
		echo '</td></tr>';

		echo '<tr><td>Group </td><td>';

			$groups = array();
			if( isset($config['packages']) ){
				foreach($config['packages'] as $key => $package ){
					$groups[$package['group']] = $package['group'];
				}
			}
			$groups['Default'] = 'Default';


			echo '<div class="group_input">';
			echo '<select name="group">';
			foreach($groups as $key => $value){
				if( $vars['group'] == $key ){
					echo '<option value="'.$key.'" selected="selected">'.$value.'</option>';
				}else{
					echo '<option value="'.$key.'">'.$value.'</option>';
				}
			}
			echo '</select>';
			echo '<br/>';
			echo '<a href="#" class="toggle_group">Add New Group</a>';
			echo '</div>';

			echo '<div class="group_input" style="display:none">';
			echo '<input type="text" size="40" class="text" name="group_hidden" value="'.htmlspecialchars($vars['new_group']).'" />';
			echo '<br/>';
			echo '<a href="#" class="toggle_group">Select From Existing</a>';
			echo '</div>';
			echo '</td></tr>';
	}

	function StartPackage(){
		global $rootDir,$config;
		$this->title = 'New Project';

		$_POST += array('destination'=>$config['destination']);


		echo '<div id="blockcenter">';
		echo '<form method="post" action="">';
		$this->DetailsForm($_POST);

		echo '<tr><td>&nbsp;</td><td>';
		echo ' <input type="hidden" name="cmd" value="save_package" />';
		echo ' <input type="submit" name="" value="Save Project" />';
		echo ' <input type="submit" name="cmd" value="Cancel" />';
		echo '</td></tr>';

		echo '</table>';
		echo '</form>';
		echo '</div>';
	}



	/**
	 * Display the form for minimizing a single file
	 *
	 */
	function MinFile(){
		$this->title = 'Minimize File';

		$_REQUEST += array('code'=>'');

		echo '<div id="blockcenter">';

		echo '<form action="" method="post"  enctype="multipart/form-data">';
		echo '<table class="bordered" cellpadding="0" cellspacing="0">';
		echo '<tr><th colspan="2">Minimize File</th></tr>';

		echo '<tr><td class="nowrap">Select a File</td><td>';
		echo '<input type="file" class="text" size="15" name="file" />';
		echo '</td></tr>';

		echo '<tr><td class="nowrap">or</td><td></td></tr>';

		echo '<tr><td class="nowrap">Paste the Text</td><td>';
		echo '<textarea cols="50" rows="7" name="code">'.htmlspecialchars($_REQUEST['code']).'</textarea>';
		echo '</td></tr>';

		echo '<tr><td>&nbsp;</td><td>';
		echo ' <input type="submit" name="cmd" value="Minimize JavaScript" />';
		echo ' <input type="submit" name="cmd" value="Minimize CSS" />';
		echo ' <input type="submit" name="cmd" value="Cancel" />';
		echo '</td></tr>';

		echo '</table>';
		echo '</form>';
		echo '</div>';
	}



	/**
	 * Minimize a single css file
	 *
	 */
	function MinimizeCSS(){
		$content = $this->GetPostedContent();
		if( !$content ) return;

		$result = phpproject::MinCss($content);
		$this->MinimizedResults($content,$result);
	}


	/**
	 * Minimize a single javascript file
	 *
	 */
	function MinimizeJS(){

		$content = $this->GetPostedContent();
		if( !$content ) return;

		$result = easyjsmin::MinResult($content);
		if( !$result ) return;

		easyjsmin::ResultErrors($result,'Uploaded File');

		$this->MinimizedResults($content,$result['compiledCode']);
	}

	function MinimizedResults($orig,$result){

		$orig_size = strlen($orig);
		$min_size = strlen($result);


		echo '<table class="bordered" cellpadding="0" cellspacing="0">';
		echo '<tr><th colspan="2">Minimized Result</th></tr>';

		echo '<tr><td class="nowrap">Original Size</td><td>';
		echo number_format($orig_size).' bytes';
		echo '</td></tr>';

		echo '<tr><td class="nowrap">Minimized Size</td><td>';
		echo number_format($min_size).' bytes';
		echo '</td></tr>';

		echo '<tr><td class="nowrap">Reduction</td><td>';
		$percent = 0;
		if( $orig_size > 0 ){
			$percent = ($orig_size - $min_size) / $orig_size;
		}
		echo (number_format($percent,2)*100).'%';
		echo '</td></tr>';

		echo '<tr><td class="nowrap">Minimized Text</td><td>';
		echo '<textarea cols="70" rows="20" readonly="readonly" onclick="this.select()" class="result">'.htmlspecialchars($result).'</textarea>';
		echo '</td></tr>';

		echo '</table>';
	}


	/**
	 * Get the content posted from the
	 *
	 */
	function GetPostedContent(){

		if( !empty($_FILES['file']['name']) ){

			if( !empty($_FILES['file']['error']) ){
				message('Oops, there was an error uploading your file. Please try again. (Upload Error 1)');
				return false;
			}

			$location = $_FILES['file']['tmp_name'];
			$contents = file_get_contents($location);
			if( !$contents ){
				message('Oops, there was an error uploading your file. Please try again. (Upload Error 2)');
				return false;
			}

			return $contents;
		}

		if( !empty($_POST['code']) ){
			return $_POST['code'];
		}

		return false;
	}



	/**
	 * Functions for using cssmin
	 *
	 */
	function MinCss($content){
		if( defined('cssmin_v3') ){
			return CssMin::minify($content);
		}

		return cssmin::minify($content, 'remove-last-semicolon,preserve-urls');
	}





	/**
	 * Side toolbar
	 *
	 */
	function Toolbar(){
		$_POST += array('source'=>'','destination'=>'','file'=>'');


		echo '<h4>Minimize</h4>';
		echo '<ul>';
		echo '<li><a href="?" class="current">Home</a></li>';
		echo '<li><a href="?cmd=newproject">Add New Project</a></li>';
		echo '<li><a href="?cmd=minfile">Minimize File</a></li>';
		echo '<li><a href="?cmd=config">Configuration</a></li>';
		echo '<li><a href="?cmd=cache">Cache Info</a></li>';
		echo '</ul>';

		echo '<h4>Info</h4>';
		echo '<ul>';
		echo '<li><a href="?cmd=about">About phpEasyMin</a></li>';
		echo '<li><a href="http://phpeasymin.com">phpEasyMin.com</a></li>';
		echo '</ul>';

		echo '<h4>Resources</h4>';
		echo '<ul>';
		echo '<li><a href="http://code.google.com/p/cssmin/">CssMin</a></li>';
		echo '<li><a href="http://code.google.com/closure/compiler/docs/gettingstarted_api.html">Closure Compiler</a></li>';
		echo '</ul>';
	}



	function Content(){
		if( $this->title === false ){
			return;
		}
		if( !empty($this->title) ){
			echo '<h1>'.$this->title.'</h1>';
		}else{
			echo '<h1>All Projects</h1>';
		}

		common::GetMessages();
		echo $this->content;
	}

	function Title(){
		$str = '<a href="?">phpEasyMin</a>';
		if( !empty($this->title) ){
			$str .= ' &#187; '.$this->title;
		}
		return $str;
	}


	static function FormatBytes($size, $precision = 2){
		$base = log($size) / log(1024);
		$suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
		$floor = max(0,floor($base));
		return '<span title="'.number_format($size).' bytes">'.round(pow(1024, $base - $floor), $precision) .' '. $suffixes[$floor].'</span>';
	}

	function CacheInfo(){
		global $rootDir;

		$this->title = 'Cache Info';

		$cache_dir = $rootDir.'/data/_cache';
		$this->ReadCacheDir( $cache_dir );


		$count = count($this->cache_files);
		if( !$count ){
			echo '<p>The cache is empty</p>';
			return;
		}

		echo '<h3>Oldest File: '.gmdate( 'D, j M Y H:i:s', $this->cache_oldest).'</h3>';
		echo '<h3>Total Size: '.self::FormatBytes($this->cache_size).'</h3>';
		echo '<h3>Number of Files: '.number_format( $count ).'</h3>';
		$avg = $this->cache_size/$count;
		echo '<h3>Average File Size: '.self::FormatBytes( $avg ).'</h3>';


		echo '<p>The cache stores data about minimized JavaScript files to reduce the number of requests to  <a href="http://code.google.com/closure/compiler/docs/gettingstarted_api.html">Google\'s Closure Compiler</a>.';
		echo '</p>';

	}

	function ReadCacheDir( $dir ){
		$files = scandir($dir);
		$files = array_diff($files,array('.','..'));
		foreach($files as $file){
			$full_path = $dir.'/'.$file;
			if( is_dir($full_path) ){
				$this->ReadCacheDir( $full_path );
				continue;
			}

			$modified = filemtime($full_path);
			if( $this->cache_oldest === 0 || $modified < $this->cache_oldest ){
				$this->cache_oldest = $modified;
			}
			$this->cache_size += filesize($full_path);
			$this->cache_files[$full_path] = $modified;
		}
	}

	/**
	 * Reduce the cache directory within the maxiumum size
	 *
	 */
	function ReduceCache(){
		global $rootDir, $config;
		$cache_dir = $rootDir.'/data/_cache';
		$this->ReadCacheDir( $cache_dir );

		if( $this->cache_size <= $config['cache_max'] ){
			return;
		}

		$amount_to_delete = $this->cache_size - $config['cache_max'];
		$amount_deleted = 0;
		asort($this->cache_files);
		foreach($this->cache_files as $full_path => $modified){
			$size = filesize($full_path);
			if( unlink( $full_path ) ){
				$amount_deleted += $size;
				unset($this->cache_files[$full_path]);
			}
			if( $amount_deleted > $amount_to_delete ){
				break;
			}
		}
		$percentage = round($this->cache_size / $amount_deleted * 100);
	}

}
