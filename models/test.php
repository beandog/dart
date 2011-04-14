<?

	require_once 'mdb2/charlie.dvds.php';

	class Test_Model extends DBTable {
	
		public function __construct($id = null) {
	
			$table = "test";
	
			parent::__construct($table, $id);
			
			echo "Finished\n";
			
		}
	
	}
	
	$testing = new Test_Model();
	
	echo $testing->get_id();
	
	$testing->set_name('Testing #2!');
	
	$testing->name = "Another test";
	
	echo "Id: $testing\n";
	
	
	echo $testing->find_id('name', "Another test", 'name');
	
	
?>