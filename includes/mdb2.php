<?

	require_once 'PEAR.php';
	require_once 'MDB2.php';

	$dsn = "pgsql://steve@charlie/dvds";

	$options = array(
		'debug'       => 2,
		'portability' => MDB2_PORTABILITY_ALL ^ MDB2_PORTABILITY_EMPTY_TO_NULL,
	);

	$db =& MDB2::factory($dsn, $options);
	$db->loadModule('Manager');
	$db->loadModule('Extended');

	$db->setFetchMode(MDB2_FETCHMODE_ASSOC);

	PEAR::setErrorHandling(PEAR_ERROR_DIE);
	
	function pearError ($e) {
		echo $e->getMessage().': '.$e->getUserinfo();
	}
	
	PEAR::setErrorHandling(PEAR_ERROR_CALLBACK, 'pearError');
	
	class DBTable {
	
		protected $id;
		protected $db;
		protected $table;
	
		public function __construct($table, $id = null) {
		
			$this->db = MDB2::singleton();
		
			$this->table = $table;
			
			return $this->id = $id;
		
		}
		
		public function __get($var) {
			
			$sql = "SELECT ".$this->db->escape($var)." FROM ".$this->table." WHERE id = ".$this->db->quote($this->id).";";
			
			return $this->db->getOne($sql);
			
		}
		
		public function __set($key, $value) {
		
			$arr_update = array(
				$key => $value
			);
		
			return $this->db->autoExecute($this->table, $arr_update, MDB2_AUTOQUERY_UPDATE, "id = ".$this->db->quote($this->id));
		
		}
		
		public function __call($str, $args) {
		
			$arr = explode("_", $str);
			$function_call = current($arr);
			array_shift($arr);
			$function_value = implode("_", $arr);
		
			// Check to see if they are setting a column
			if($function_call === "set" && strlen($function_value) && count($args)) {
				$value = current($args);
				$this->__set($function_value, $value);
			}
			
			// Otherwise check if they are fetching a column
			elseif($function_call === "get" && strlen($function_value)) {
				
				return $this->__get($function_value);
			
			}
			
			// See if you can find it
			// Cheap hack that should never be used outside of the home
			// FIXME
			elseif($function_call === "find" && strlen($function_value) && count($args)) {
			
				$orderby = 'id';
			
				if(!empty($args[2]))
					$orderby = $args[2];
			
				$sql = "SELECT id FROM ".$this->table." WHERE ".$args[0]." = ".$this->db->quote($args[1])." ORDER BY $orderby;";
				
				return $this->id = $this->db->getOne($sql);
			
			}
			
		}
		
		public function __toString() {
		
			return (string)$this->id;
		
		}
		
		public function create_new() {
		
			$this->db->query("INSERT INTO ".$this->table." DEFAULT VALUES;");
			
			return $this->id = $this->db->lastInsertID();
		
		}
		
		public function delete() {
		
			$sql = "DELETE FROM ".$this->table." WHERE id = ".$this->db->quote($this->id).";";
			
			return $this->db->query($sql);
		
		}
		
		public function load($id) {
		
			$this->id = $id;
		
		}
		
	}

?>
