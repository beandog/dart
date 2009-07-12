<?

	class Matroska {
	
		private $filename;
		private $chapters;
		private $global_tags;
		private $aspect_ratio;
		private $flags = array();
		private $streams = array();
		private $xml;
		private $sxe;
		private $tag;
		private $dtd;
	
		function __construct($filename = null) {
		
			if(!is_null($filename))
				$this->setFilename($filename);
			
			$this->dtd = "/usr/local/share/matroska/xml/matroskatags.dtd";
		
		}
		
		/** Filename **/
		public function setFilename($str) {
			if(is_string($str))
				$this->filename = $filename;
		}
		
		public function getFilename() {
			return (string)$this->filename;
		}
		
		/** Streams **/
		private function add($filename, $type = null) {
			if($type) {
				$this->streams[] = array(
					'filename' => $filename,
					'type' => $type,
				);
			} else {
				$this->streams[] = array(
					'filename' => $filename,
				);
			}
		}
		
		public function addFile($filename) {
			if(file_exists($filename)) {
				$this->add($filename);
			}
		}
		
		public function addVideo($filename) {
			if(file_exists($filename)) {
				$this->add($filename, 'video');
			}
		}
		
		public function addAudio($filename) {
			if(file_exists($filename)) {
				$this->add($filename, 'audio');
			}
		}
		
		public function addSubtitles($filename) {
			if(file_exists($filename)) {
				$this->add($filename, 'subtitles');
			}
		}
		
		public function addChapters($filename) {
			if(file_exists($filename)) {
				$this->add($filename, 'chapters');
			}
		}
		
		public function addGlobalTags($filename) {
			if(file_exists($filename)) {
				$this->add($filename, 'global_tags');
			}
		}
		
		/** Metadata **/
		public function setTitle($str) {
			$str = trim($str);
			if(is_string($str) && !empty($str)) {
				$this->title = $str;
			}
		}
		
		public function getTitle() {
			return (string)$this->title;
		}
		
		public function setAspectRatio($str) {
			$str = trim($str);
			if(is_string($str) && !empty($str)) {
				$this->aspect_ratio = $str;
			}
		}
		
		public function getAspectRatio() {
			return (string)$this->aspect_ratio;
		}
	
	
		/** Global Tags **/
		function createXML() {
		
			$dtd =& $this->dtd;
			
			$str = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE Tags SYSTEM "$this->dtd">
<Tags>
</Tags>
XML;

			$this->sxe = new SimpleXMLElement($str);

		}
		
		function addTag() {
		
			if(!is_object($this->sxe))
				$this->createXML();
		
			$this->tag = $this->sxe->addChild("Tag");
		}
		
		function addSimpleTag($name, $string, $language = "eng") {
		
			$this->simple = $this->tag->addChild("Simple");
			$this->simple->addChild("Name", $name);
			$this->simple->addChild("String", $string);
			$this->simple->addChild("TagLanguage", $language);
		
		}
		
		function addTarget($value, $type) {
			$this->targets = $this->tag->addChild("Targets");
			$this->targets->addChild("TargetTypeValue", $value);
			$this->targets->addChild("TargetType", $type);
		}
		
		function getXML() {
			$doc = new DOMDocument("1.0");
			$doc->preserveWhiteSpace = false;
			$doc->loadXML($this->sxe->asXML());
			$doc->formatOutput = true;
			return $doc->saveXML();
		}
		
		/** Muxing **/
		
		private function arguments() {
		
			$flags['output'] = $this->getFilename();
		
			foreach($streams as $arr) {
			
				switch($arr['type']) {
					
					case 'audio':
						$flags['no-video'] = $arr['filename'];
						break;
					
					case 'video':
						$flags['no-audio'] = $arr['filename'];
						break;
					
					case 'subtitles':
						$args[] = $arr['filename'];
						break;
					
					case 'chapters':
						$flags['chapters'] = $arr['filename'];
						break;
						
					default:
						$args[] = $arr['filename'];
						break;
					
					
				}
			
			}
			
			if($this->title)
				$flags['title'] = $this->title;
			
		}
	
	}
?>