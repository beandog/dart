<?

	require_once 'mdb2/charlie.dvds.php';

	class Series_Dvds_Model extends DBTable {
	
		function __construct($id = null) {
		
			$table = "series_dvds";
			
			$this->id = parent::__construct($table, $id);
				
		}
		
	}
?>
