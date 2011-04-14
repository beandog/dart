<?

	require_once 'class.handbrake.php';
	
	$handbrake = new Handbrake();
	
	$input = "/home/steve/dvds/Superman:_The_Animated_Series/212._World's_Finest,_Part_3.vob";
	
	$output = "/home/steve/dvds/test.x264.mkv";
	
	$handbrake->input_filename($input);
	$handbrake->output_filename($output);
	$handbrake->autocrop();
	
	$handbrake->set_x264('ref', 6);
	$handbrake->set_x264('bframes', 5);
	
 	echo $handbrake->get_executable_string();
	
// 	echo $handbrake->scan();
	


?>