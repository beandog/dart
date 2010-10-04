<?

	require_once 'class.shell.php';

	class Handbrake {
	
		private $binary = "handbrake";
		
		private $verbose = false;
		private $debug = false;
	
		private $filename;
		private $flags = array();
		private $args = array();
		
		private $audio_tracks = array();
		private $audio_encoders = array('ac3', 'dts');
		
		private $preset = 'Normal';
		private $format = 'mkv';
		private $encoder = 'x264';
		
		private $add_chapters = false;
		private $video_quality = 20;
		
		private $crop = "0:0:0:0";
		
		private $deinterlace = false;
		private $decomb = true;
		private $detelecine = true;
		private $grayscale = false;
		
		private $subtitle_tracks = array();
		
		private $srt_language = 'eng';
		
		private $cc = false;
		private $cc_ix;
		
		private $num_bitmaps;
		
		function __construct($filename = null) {
		
			if(!is_null($filename))
				$this->input_filename($filename);
		
		}
		
		function debug($bool = true) {
			$this->debug = $this->verbose = (boolean)$bool;
		}
		
		function verbose($bool = true) {
			$this->verbose = (boolean)$bool;
		}
		
		/** Filename **/
		public function input_filename($str) {
			$this->input = $str;
			$this->scan();
		}
		
		public function output_filename($str) {
			$this->output = $str;
		}
		
		public function set_debug($bool = true) {
			$this->debug = $this->verbose = (boolean)$bool;
		}
		
		public function set_preset($str) {
			if($str == 'Normal' || $str == 'High Profile')
				$this->preset = $str;
		}
		
		public function output_format() {
		
			if($str == 'mkv' || $str == 'mp4')
				$this->format = $str;
		
		}
		
		public function add_chapters($bool = true) {
			$this->add_chapters = (boolean)$bool;
		}
		
		public function set_video_quality($int) {
		
			$int = intval($int);
			
			$this->video_quality = $int;
		
		}
		
		public function add_audio_track($int) {
		
			$int = intval($int);
			
			$this->audio_tracks[] = $int;
			
		}
		
		public function add_audio_stream($stream_id) {
		
			$audio_track = $this->audio_streams[$stream_id];
			
			// Add the audio track only if the stream ID is available from scan
			if(!is_null($audio_track))
				$this->add_audio_track($this->audio_streams[$stream_id]);
		
		}
		
		public function autocrop($bool = true) {
		
			$bool = (boolean)$bool;
			
			if($bool)
				$this->crop = null;
			else
				$this->crop = "0:0:0:0";
				
		
		}
		
		public function deinterlace($bool = true) {
		
			$this->deinterlace = (boolean)$bool;
		
		}
		
		public function decomb($bool = true) {
		
			$this->decomb = (boolean)$bool;
		
		}
		
		public function detelecine($bool = true) {
		
			$this->detelecine = (boolean)$bool;
		
		}
		
		public function grayscale($bool = true) {
		
			$this->grayscale = (boolean)$bool;
		
		}
		
		public function add_subtitle_track($int) {
		
			$int = intval($int);
			
			$this->subtitle_tracks[] = $int;
		
		}
		
		public function get_options() {
		
			$options = array();
			
			// Check for muxing chapters
			if($add_chapters)
				$options[] = "--markers";
			
			// Check for decombing filter
			if($this->decomb)
				$options[] = "--decomb";
			
			// Check for detelecine filter
			if($this->detelecine)
				$options[] = "--detelecine";
			
			// Check for grayscale
			if($this->grayscale)
				$options[] = "--grayscale";
				
			return $options;
			
		}
		
		public function get_arguments() {
		
			$args = array();
			
			$args['--input'] = $this->input;
			$args['--output'] = $this->output;
			
			// Set encoder
			$args['--encoder'] = $this->encoder;
			
			// Add audio tracks
			if(count($this->audio_tracks)) {
				$str = implode(",", $this->audio_tracks);
				$args['--audio'] = $str;
			} elseif(count($this->audio_streams)) {
			
				// FIXME temporary?
				// Hit a but on a DVD where lsdvd reported
				// 8 English audio tracks, but Handbrake
				// correctly said there is only one.
				// So, in this case, there are audio streams
				// so encoding the first one will work, it's
				// just that none were passed in.
				
				// This is an obvious workaround to the lsdvd
				// bug.  The correct approach would be to sync
				// up the output of lsdvd's report and handbrake's
				// scan.
				$args['--audio'] = 1;
			} else {
				$args['--audio'] = 'none';
			}
			
			// Add subtitle tracks
			if(count($this->subtitle_tracks)) {
				$str = implode(",", $this->subtitle_tracks);
				$args['--subtitle'] = $str;
			}
			
			// Add audio encoders
			if(count($this->audio_encoders)) {
				$str = implode(",", $this->audio_encoders);
				$args['--aencoder'] = $str;
			}
			
			// Add preset
			if(!is_null($this->preset)) {
				$args['--preset'] = $this->preset;
			}
			
			// Add format
			if(!is_null($this->format))
				$args['--format'] = $this->format;
			
			// Add video quality
			if(!is_null($this->video_quality))
				$args['--quality'] = $this->video_quality;
			
			// Set cropping parameters
			if(!is_null($this->crop))
				$args['--crop'] = $this->crop;
				
			return $args;
			
		}
		
		public function get_executable_string() {
		
			$exec = "";
		
			$options = $this->get_options();
			
			foreach($options as $str)
				$exec[] = escapeshellarg($str);
			
			$args = $this->get_arguments();
			
			foreach($args as $key => $value)
				$exec[] = "$key ".escapeshellarg($value);
				
			$str = $this->binary." ".implode(" ", $exec);
			
			return $str;
		
		}
		
		public function scan() {
		
			$exec = $this->binary." --scan --input ".escapeshellarg($this->input)." 2>&1";
			exec($exec, $arr, $return);
			
			$audio = preg_grep("/.*add_audio_to_title.*/", $arr);
			
			$audio_index = 1;
			
			foreach($audio as $str) {
				$stream_id = str_replace("bd", "", end(explode(" ", $str)));
				$this->audio_streams[$stream_id] = $audio_index;
				$audio_index++;
			}
			
			$vobsubs = preg_grep("/.*(Bitmap).*/", $arr);
			
			$this->num_bitmaps = count($vobsubs);
			
			$cc = preg_grep("/.*Closed Captions.*/", $arr);
			
			if(count($cc)) {
				$this->cc = true;
				$this->cc_ix = (count($vobsubs) + 1);
			}
		
		}
		
		public function get_audio_index($stream_id) {
			return $this->audio_streams[$stream_id];
		}
		
		public function has_cc() {
			return $this->cc;
		}
		
		public function get_cc_ix() {
			return $this->cc_ix;
		}
		
		public function encode() {
		
			$str = $this->get_executable_string();
			
			if($this->debug)
				shell::msg("Executing: $str");
				
			shell::cmd($str, !$this->verbose, false, $this->debug, array(0));
			
		}
		
	}
?>