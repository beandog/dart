<?

	class dart {
	
		function __construct() {
		
			$this->export = getenv('HOME').'/dvds/';
		
		}
	
		function archived($dvd_id) {
		
			$dvd = dvds::find_by_uniq_id($dvd_id);
			
			if(!is_null($dvd->id))
				return true;
			else
				return false;
			
		}
		
	}

?>
