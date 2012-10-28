<?php

class php_version_output{

	var $minVersion = 0;
	var $functionsVersion = array();


	function Output_Results(){
		global $PHPFunctions;



		foreach($this->functionsUsed as $function => $uses){

			$classvar = false;
			if( strpos($function,'::') !== false ){
				list($function,$classvar) = explode('::',$function); //For class Vars;
				if( $classvar ){
					$classvar = '$' . $classvar;
				}
			}

			if( ! isset($PHPFunctions[ $function ]) &&
				! isset($this->customFunctions[ $function ]) &&
				! in_array( strtolower($function), array('null','false','true')) &&
				! isset($this->objectLookup[ $function ]) &&
				( ! isset($this->customClasses[$function] ) && empty($classvar) ) &&
				! isset($this->customClasses[$function]['variables'][$classvar]) ){

				$this->unknownFunctions[] = $function;
				continue;
			}
			if( $classvar && isset($this->customClasses[$function]['variables'][$classvar]) ){
				$this->increment($this->customClasses[$function]['variableuses'], $classvar);
			}

			if( empty($PHPFunctions[ $function ]['since']) ){
				continue;
			}

			$ver = min($PHPFunctions[ $function ]['since']);
			$this->functionsVersion[ $function ] = $ver;
			if( ! array_key_exists( $function, $this->functionsCheckedFor) && version_compare($this->minVersion, $ver, '<') ){
				$this->minVersion = $ver;
			}
		}



		?>
		<div class="block">
			<div id="tabs"></div>

			<div>
			<h2 class="checkphphead">PHP Functions</h2>
			<p>Features of this package require a minimum of PHP v<?= $this->version_display($this->minVersion) ?></p>

			<div class="compat_included"><span></span>Indicates a compatible function has been included for backwards compatibility for this funciton.</div>
			<div class="compat_not_overridden"><span></span>Indicates this function was checked for using function_exists(). However, a compatible function has <b>NOT</b> been included</div>
			<table class="analysis">
				<?php
				echo '<tr><th>Version</th><th>Function Name</th><th>Used</th></tr>';
				arsort($this->functionsVersion);
				$current_version = false;
				$class = '';
				foreach($this->functionsVersion as $function => $version){

					if( array_key_exists( $function, $this->functionsCheckedFor) ){
						if( isset( $this->customFunctions[ $function ]) ){
							$class .= ' compat-included';
						}else{
							$class .= ' compat';
						}
					}else{
						$class = $class ? '' : 'alternate';
					}


					echo "\n<tr class='$class'>";
					echo '<td>'.$this->version_display($version).'</td>';
					echo '<td>'.$function.'</td>';
					echo '<td>'.$this->functionsUsed[ $function ].'</td>';
				}
				?>
			</table>
			</div>





			<div>
			<h2 class="checkphphead">Custom Functions</h2>
			<p>Hover for File/Line.</p>
			<p>
			<strong>WARNING:</strong> Variables created by using extract($array) may be shown here as only being used once, This is a unfortunate limitiation as the application does not know what values are contained within the array.
			<div class="legend_used_once"><span></span>Indicates a variable or argument is only referenced once within the function and may not be needed.</div>

			</p>
			<table class="analysis">
				<tr>
					<th>Function</th>
					<th>Args</th>
					<th>Used</th>
				</tr>
				<?php
				if( !empty($this->customFunctions) ){
					ksort($this->customFunctions);
					foreach($this->customFunctions as $function => $info){
						$class = $class ? '' : 'alternate';
						if( !isset($this->functionsUsed[ $function ]) ){
							$used = 0;
						}else{
							$used = $this->functionsUsed[ $function ];
						}

						$name = $info['class'] ?  $info['class'] . '::' . $info['name'] : $info['name'];
						$defined = $info['file'] . ':' . $info['line'];

						$args = array();
						foreach((array)$info['args'] as $argname => $argvalue){
							$used = $info['variables'][ $argname ];

							if( $argvalue ){
								$arg = "$argname = $argvalue";
							}else{
								$arg = "$argname";
							}
							$arg = htmlspecialchars($arg);
							if( $info['variables'][ $argname ] == 1 && $argname != '$deprecated' ){
								$arg = '<span class="arg_not_used">'.$arg.'</span>';
							}
							$args[] = $arg;
						}
						$args = implode(', ', $args);
						if( ! $args )
							$args = '<em>no arguments</em>';
						printf("\n<tr class='$class' title='Defined in: %s'>
								<td>%s</td>
								<td>%s</td>
								<td>%s</td>
								</tr>", $defined, htmlspecialchars($name), $args , $used);

						$warnings = $this->function_warnings($function);

						if( $warnings ){
							printf("\n<tr class='warning $class'>
									<td colspan='3'><div class='variables'>%s</div>%s</td>
									</tr>"
									, implode(',<br /> ',(array)$warnings['variables']), implode(',<br /> ',(array)$warnings['extra']) );
						}
					}
				}
				?>
			</table>
			</div>




			<div>
			<h2 class="checkphphead">PHP Constants</h2>
			<table class="analysis">
				<tr>
					<th>PHP Ver</th>
					<th>Name</th>
					<th>Used</th>
				</tr>
				<?php
				arsort($this->constantsUsed);
				foreach($this->constantsUsed as $constant => $count){
					if( array_key_exists($constant, $this->customDefines) ){
						continue;
					}
					$class = $class ? '' : ' class="alternate"';
					printf("<tr$class>
							<td>%s</td>
							<td>%s</td>
							<td>%s</td>
							</tr>",$this->version_display("0"), $constant, $count );
				}
				?>
			</table>
			</div>


			<div>
			<h2 class="checkphphead">Custom Constants</h2>
			<table class="analysis">
				<tr>
					<th>Name</th>
					<th>Value</th>
					<th>Used</th>
				</tr>
				<?php
				if( !empty($this->customDefines) ){
					asort($this->customDefines);
					foreach($this->customDefines as $constant => $value){
						if( !array_key_exists($constant, $this->constantsUsed) )
							$count = 0;
						else
							$count = $this->constantsUsed[ $constant ];
						$class = $class ? '' : ' class="alternate"';
						printf("\n<tr$class>
							<td>%s</td>
							<td>%s</td>
							<td>%s</td>
							</tr>
							", htmlspecialchars($constant), htmlspecialchars($value), $count );
					}
				}
				?>
			</table>
			</div>


			<div>
			<h2 class="checkphphead">Object Lookup</h2>
			<table class="analysis">
				<tr>
					<th>Variable</th>
					<th>ClassName</th>
				</tr>
				<?php

				if( !empty($this->objectLookup) ){
					ksort($this->objectLookup);
					foreach($this->objectLookup as $var => $info){
						$class = $class ? '' : ' class="alternate"';
						$classlocations = array();
						$classNames = array();
						$names = array();
						foreach($info as $entry){
							$classlocations[ strtolower($entry['name']) ][] = $entry['file'] . ':' . $entry['line'];
							$classNames[ strtolower($entry['name']) ] = $entry['name'];
						}

						ksort($classlocations);
						foreach($classlocations as $cname => $locations){
							$names[] = "<a title='" . implode(', ', $locations) . "'>" . $classNames[$cname] . "</a>";
						}


						printf("<tr$class>
								<td>%s</td>
								<td>%s</td>
								</tr>
								", $var, implode(', ', $names) );
					}
				}
				?>
			</table>
			</div>


			<div>
			<h2 class="checkphphead">Object Properties</h2>
			<table class="analysis">
				<tr>
					<th>Class</th>
					<th>Name</th>
					<th>Used</th>
				</tr>
				<?php
				ksort($this->customClasses);
				$class = '';
				foreach((array)$this->customClasses as $className => $info){

					if( isset($info['variables']) ){
						foreach((array)$info['variables'] as $varName => $true ){
							$class = $class ? '' : ' class="alternate"';
							$uses = isset($info['variableuses'][$varName]) ? $info['variableuses'][$varName] : 0;
							printf("\n<tr$class>
									<td>%s</td>
									<td>%s</td>
									<td>%s</td>
									</tr>", $className, htmlspecialchars($varName), $uses );
						}
					}

/*					//this function is in the Custom Functions
					if( isset($info['functions']) ){
						foreach((array)$info['functions'] as $funcName ){
							$class = $class ? '' : ' class="alternate"';
							$uses = isset($this->functionsUsed[ "$className::$funcName" ]) ? $this->functionsUsed[ "$className::$funcName" ] : 0;
							printf("\n<tr$class>
									<td>%s</td>
									<td>%s</td>
									<td>%s</td>
									</tr>", $className, $funcName . '()', $uses );
						}
					}
*/
				}
				?>
			</table>
			</div>



			<div>
			<h2 class="checkphphead">Unknown Functions</h2>
			<p>The following functions have been used within the source, However no definitition of the function was found, or it is an unknown PHP Function</p>
			<table class="analysis">
				<tr><th>Name</th></tr>
				<?php
				asort($this->unknownFunctions);
				$this->unknownFunctions = array_unique($this->unknownFunctions);
				foreach((array)$this->unknownFunctions as $function){
					$class = $class ? '' : ' class="alternate"';
					printf("<tr$class><td>%s</td></tr>", $function );
				}
				?>
			</table>
			</div>

		</div>
		<?
	}

	function function_warnings($function){
		global $PHPFunctions;
		$variables = array();
		$extra = array();

		$F =& $this->customFunctions[ $function ];


		if( !empty( $F['variables'] ) || !empty($F['args']) ){

			foreach( (array)$F['variables'] as $vname => $vuses){
				if( $vuses > 1 ){
					continue;
				}
				if( '$deprecated' == $vname && 1 == $vuses ){
					continue; //We dont want to know about the depreciated variables.
				}

				if( empty($F['args']) || !array_key_exists($vname,$F['args']) ){
					$variables[] = "Variable $vname is used only $vuses times";
				}

			}
		}

		if( array_key_exists( $function, $this->functionsCheckedFor) ){
			if( isset($PHPFunctions[ $function ]['since']) ){
				$extra[] = "This is a compat function for PHP < " . implode(', ', $PHPFunctions[ $function ]['since']);
			}
		}

		if( ! empty($variables) || ! empty($extra) )
			return array( 'variables' => $variables, 'extra' => $extra);
		else
			return false;
	}

	function version_display($ver){
		$ver .= '.0.0.0';
		list($major,$minor,$bug) = preg_split('|[\.\-]|',$ver);
		if( !$major )
			$major = 0;
		if( !$minor )
			$minor = 0;
		if( !$bug )
			$bug = 0;
		return "$major.$minor.$bug";
	}


}
