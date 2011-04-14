<?

	require_once 'mdb2/charlie.dvds.php';

	class Series_Model extends DBTable {
	
		function __construct($id = null) {
		
			$table = "series";
			
			$this->id = parent::__construct($table, $id);
				
		}
		
	}
?>
