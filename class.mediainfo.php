<?php

	class MediaInfo {

		public $filename = '';
		public $json = array();
		public $metadata = array();

		function __construct($filename) {

			$filename = realpath($filename);

			if(!file_exists($filename))
				return false;

			// Get XML
			$arg_filename = escapeshellarg($filename);
			$cmd = "mediainfo --output=XML $arg_filename 2> /dev/null";

			exec($cmd, $output, $retval);

			if($retval !== 0)
				return false;

 			$this->xml = implode("\n", $output);
 			$this->sxe = simplexml_load_string($this->xml);
			$str = json_encode($this->sxe);
			$json = json_decode($str, true);

			// Parse JSON

			foreach($json['File']['track'] as $key => $arr) {

				$type = $json['File']['track'][$key]['@attributes']['type'];

				if($type == 'General')
					$this->import_general($json['File']['track'][$key]);
				elseif($type == 'Video')
					$this->import_video($json['File']['track'][$key]);
				elseif($type == 'Audio')
					$this->import_audio($json['File']['track'][$key]);
				elseif($type == 'Text')
					$this->import_text($json['File']['track'][$key]);
				elseif($type == 'Menu')
					$this->import_menu($json['File']['track'][$key]);

			}

			// Build a subtitles array even if there are none
			if(!array_key_exists('subtitles', $this->metadata)) {
				$this->metadata['num_subtitles'] = 0;
				$this->metadata['subtitles'] = array();
			}

			if(!array_key_exists('vobsub', $this->metadata))
				$this->metadata['vobsub'] = false;

			if(!array_key_exists('closed_captioning', $this->metadata))
				$this->metadata['closed_captioning'] = false;

			return true;

		}

		public function __get($var) {

			if(array_key_exists($var, $this->metadata))
				return $this->metadata[$var];
			else
				return false;

		}

		// Container
		private function import_general($arr_episode) {

			// Metadata
			$arr['filesize'] = preg_replace('/\D/', '', $arr_episode['File_size']);
			$arr['title'] = $arr_episode['DVD_EPISODE_TITLE'];
			$arr['encoding_spec'] = $arr_episode['ENCODING_SPEC'];
			$arr['metadata_spec'] = $arr_episode['METADATA_SPEC'];
			$arr['collection'] = $arr_episode['DVD_COLLECTION'];
			$arr['season'] = $arr_episode['DVD_SERIES_SEASON'];
			$arr['volume'] = $arr_episode['DVD_SERIES_VOLUME'];
			$arr['title_track_number'] = $arr_episode['DVD_TRACK_NUMBER'];
			$arr['episode_number'] = $arr_episode['DVD_EPISODE_NUMBER'];
			$arr['dvd_id'] = $arr_episode['DVD_ID'];
			$arr['series_id'] = $arr_episode['DVD_SERIES_ID'];
			$arr['track_id'] = $arr_episode['DVD_TRACK_ID'];
			$arr['episode_id'] = $arr_episode['DVD_EPISODE_ID'];
			$arr['part_number'] = $arr_episode['PART_NUMBER'];

			$this->metadata += $arr;

		}

		// Video
		private function import_video($arr_video) {

			// Video
			$arr['h264_profile'] = $arr_video['Format_profile'];
			$arr['video_aspect_ratio'] = $arr_video['Display_aspect_ratio'];

			$this->metadata += $arr;

		}

		// Audio
		private function import_audio($arr_audio) {

			$arr['audio_format'] = $arr_audio['Format'];
			$arr['audio_bitrate'] = current(explode(' ', $arr_audio['Bit_rate']));
			$arr['audio_channels'] = current(explode(' ', $arr_audio['Channel_s_']));

			$this->metadata += $arr;

		}

		// Subtitles
		private function import_text($arr_text) {

			if(!array_key_exists('num_subtitles', $this->metadata))
				$this->metadata['num_subtitles'] = 0;

			if($arr_text['Format'] == 'VobSub') {
				$this->metadata['vobsub'] = true;
				$this->metadata['subtitles'][] = 'vobsub';
				$this->metadata['num_subtitles']++;
			} elseif($arr_text['Format'] == 'UTF-8') {
				$this->metadata['closed_captioning'] = true;
				$this->metadata['subtitles'][] = 'closed captioning';
				$this->metadata['num_subtitles']++;
			}

		}

		// Chapters
		private function import_menu($arr_menu) {

			if(!array_key_exists('num_chapters', $this->metadata))
				$this->metadata['num_chapters'] = 0;

			// Drop the attributes
			unset($arr_menu['@attributes']);

			$arr = preg_grep('/.*Chapter.*/', $arr_menu);

			// Original format is "_00_04_01841", change to "00:04:01.841"
			foreach($arr as $key => $value) {

				$hours = substr($key, 1, 2);
				$minutes = substr($key, 4, 2);
				$seconds = substr($key, 7, 2);
				$ms = substr($key, 9, 3);

				$chapter_length = "$hours:$minutes:$seconds.$ms";

				$chapters[] = $chapter_length;

				$this->metadata['num_chapters']++;

			}

			$this->metadata['chapters'] = $chapters;

		}

	}
