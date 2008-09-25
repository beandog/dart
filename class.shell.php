<?

	class shell {
	
		function __construct() {
		}
		
		/**
		* Execute shell scripts
		*
		* @param string execution string
		* @param boolean drop stderr to /dev/null
		* @param boolean ignore exit codes
		* @return output array
		*/
		function cmd($str, $stderr_to_null = true, $ignore_exit_code = false) {
			
			if($stderr_to_null)
				$exec = "$str 2> /dev/null";
			else
				$exec =& $str;
			
			exec($exec, $arr, $return);
			
			if($return !== 0 && !$ignore_exit_code) {
				shell::msg("execution died: $str");
				die($return);
			} else
				return $arr;
			
		}
		
		/**
		 * Output text to stdout or stderr)
		 *
		 * @param string output string
		 * @param boolean outout to stderr
		 * @param boolean debugging
		 */
		function msg($str = '', $stderr = false, $debug = false) {
		
			if($debug === true) {
				if($this->debug == true)
					$str = "[Debug] $str";
				else
					$str = '';
			}
		
			if(!empty($str)) {
				if($stderr === true) {
					fwrite(STDERR, "$str\n");
				} else {
					fwrite(STDOUT, "$str\n");
				}
			}
		}
		
		/**
		 * Ask a question
		 *
		 */
		function ask($str, $default = false) {
			if(is_string($str)) {
				fwrite(STDOUT, "$str ");
				$input = fread(STDIN, 255);
				
				if($input == "\n") {
					return $default;
				} else {
					$input = trim($input);
					return $input;
				}
			}
		}
		
		/**
		* Parse CLI arguments
		*
		* If a value is unset, it will be set to 1
		*
		* @param $argc argument count (system variable)
		* @param $argv argument array (system variable)
		* @return array
		*/
		function parseArguments() {
		
			global $argc;
			global $argv;
		
			$args = array();
			
			if($argc > 1) {
				array_shift($argv);
	
				for($x = 0; $x < count($argv); $x++) {
				
					if(preg_match('/^(-\w$|--\w+)/', $argv[$x]) > 0) {
						$argv[$x] = preg_replace('/^-{1,2}/', '', $argv[$x]);
						$args[$argv[$x]] = 1;
					}
					else {
						if(in_array($argv[($x-1)], array_keys($args))) {
							$args[$argv[($x-1)]] = $argv[$x];
						}
					}
				}
	
				return $args;
			}
			else
				return array();
		}
		
		/**
		 * Check for a file in a directory
		 *
		 * @param string filename
		 * @param string directory
		 * @return boolean
		 */
		function in_dir($file, $dir) {
		
			if(!is_dir($dir))
				return false;
			
			$arr = scandir($dir);
			
			$file = basename($file);
			
			if(in_array($file, $arr))
				return true;
			else
				return false;
		}
	}

?>