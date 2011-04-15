<?

	require_once 'mdb2/charlie.dvds.php';

	class Series_Model extends DBTable {
	
		function __construct($id = null) {
		
			$table = "series";
			
			$this->id = parent::__construct($table, $id);
				
		}
		
		function get_crf() {
		
			$sql = "SELECT presets.crf FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->db->quote($this->id).";";
			
			$var = $this->db->getOne($sql);
			
			return $var;
		
		}
		
		function get_handbrake_base_preset() {
		
			$sql = "SELECT presets.base_preset FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->db->quote($this->id).";";
			
			$var = $this->db->getOne($sql);
			
			return $var;
		
		}
		
		function get_x264opts() {
		
			$sql = "SELECT presets.x264opts FROM presets INNER JOIN series_presets ON series_presets.preset_id = presets.id AND series_presets.series_id = ".$this->db->quote($this->id).";";
			
			$var = $this->db->getOne($sql);
			
			return $var;
		
		}
		
	}
?>
