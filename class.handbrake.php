<?

	class Handbrake {
	
		private $binary = "handbrake-svn";
	
		private $filename;
		private $flags = array();
		private $args = array();
		
		private $audio_tracks = array();
		private $audio_encoders = array('ac3', 'dts');
		
		private $preset = 'High Profile';
		private $format = 'mkv';
		private $encoder = 'x264';
		
		private $add_chapters = true;
		private $video_quality = 20;
		
		private $crop = "0:0:0:0";
		
		private $deinterlace = false;
		private $decomb = true;
		private $detelecine = true;
		private $grayscale = false;
		
		private $subtitle_tracks = array();
		
		private $srt_language = 'eng';
		
		private $cc = false;
		
		function __construct($filename = null) {
		
			if(!is_null($filename))
				$this->input_filename($filename);
		
		}
		
		/** Filename **/
		public function input_filename($str) {
			$this->input = $str;
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
		
		public function add_chapters($bool) {
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
		
		public function autocrop($bool) {
		
			$bool = (boolean)$bool;
			
			if($bool)
				$this->crop = "0:0:0:0";
			else
				$this->crop = null;
		
		}
		
		public function deinterlace($bool) {
		
			$this->deinterlace = (boolean)$bool;
		
		}
		
		public function decomb($bool) {
		
			$this->decomb = (boolean)$bool;
		
		}
		
		public function detelecine($bool) {
		
			$this->detelecine = (boolean)$bool;
		
		}
		
		public function grayscale($bool) {
		
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
			
			$cc = preg_grep("/.*Closed Captions.*/", $arr);
			
			if(count($cc))
				$this->cc = true;
		
		}
		
		public function get_audio_index($stream_id) {
			return $this->audio_streams[$stream_id];
		}
		
		public function has_cc() {
			return $this->cc;
		}
		
	}
?>