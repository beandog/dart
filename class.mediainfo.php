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
			if($this->sxe === false)
				return false;
			$str = json_encode($this->sxe);
			if($str === false)
				return false;
			$json = json_decode($str, true);
			if(is_null($json))
				return false;

			// Set defaults
			$this->metadata['video_tracks'] = 0;
			$this->metadata['audio_tracks'] = 0;
			$this->metadata['subtitle_tracks'] = 0;
			$this->metadata['menu_tracks'] = 0;

			// Parse JSON

			if(!array_key_exists('File', $json))
				return false;

			if(!array_key_exists('track', $json['File']))
				return false;

			if(!array_key_exists('0', $json['File']['track'])) {
				$json['File']['track'][0] = $json['File']['track'];
			}

			$arr_tracks = array();
			if(is_int(key($json['File']['track']))) {
				foreach($json['File']['track'] as $key => $arr) {
					$arr_tracks[$key] = $arr;
				}
			} else {
				$arr_tracks[] = $json['File']['track'];
			}

			foreach($arr_tracks as $key => $arr) {

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
				$this->metadata['subtitles'] = array();
			}

			$this->metadata['vobsub'] = false;
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
			// Legacy MKV metadata
			/*
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
			*/

			$this->metadata += $arr;

		}

		// Video
		private function import_video($arr_video) {

			$this->metadata['video_tracks']++;

			// Video
			$arr['h264_profile'] = $arr_video['Format_profile'];
			$arr['aspect_ratio'] = $arr_video['Display_aspect_ratio'];
			$arr['encoding_settings'] = $arr_video['Encoding_settings'];

			$arr['x264_encoding_settings'] = array();
			$arr_encoding_settings = explode('/', $arr['encoding_settings']);

			foreach($arr_encoding_settings as $value) {
				$arr_x264 = explode('=', $value);
				$arr_x264[0] = trim($arr_x264[0]);
				$arr_x264[1] = trim($arr_x264[1]);
				$arr['x264_encoding_settings'][$arr_x264[0]] = $arr_x264[1];
			}

			ksort($arr['x264_encoding_settings']);

			$arr['x264_preset'] = '';
			
			extract($arr['x264_encoding_settings']);

			if($aq == 0 && $b_adapt == 0 && $cabac == 0 && $deblock == '0:1:1' && $mbtree == 0 && $me == 'dia' && $mixed_ref == 0 && $rc_lookahead == 0 && $ref == 1 && $scenecut == 0 && $subme == 0 && $trellis == 0 && $weightb == 0 && $weightp == 0)
				$arr['x264_preset'] = 'ultrafast';
			
			if($mbtree == 0 && $me == 'dia' && $mixed_ref == 0 && $rc_lookahead == 0 && $subme == 1 && $trellis == 0 && $weightp == 1)

			 	$arr['x264_preset'] = 'superfast';

			if($mixed_ref == 0 && $rc_lookahead == 10 && $subme == 2 && $trellis == 0 && $weightp == 1)
			 	$arr['x264_preset'] = 'veryfast';

			if($mixed_ref == 0 && $rc_lookahead == 20 && $subme == 4 && $weightp == 1)
			 	$arr['x264_preset'] = 'faster';

			if($rc_lookahead == 30 && $subme == 6 && $weightp == 1)
			 	$arr['x264_preset'] = 'fast';

			if($b_adapt == 1 && $direct == 1 && $me == 'hex' && $rc_lookahead == 40 && $subme == 7 && $weightp == 2)
			 	$arr['x264_preset'] = 'medium';


			if($b_adapt == 2 && $direct == 3 && $me == 'umh' && $rc_lookahead == 50 && $subme == 8)
				$arr['x264_preset'] = 'slow';

			if($b_adapt == 2 && $direct == 3 && $me == 'umh' && $rc_lookahead == 60 && $subme == 9 && $trellis == 2)
				$arr['x264_preset'] = 'slower';

			if($b_adapt == 2 && $bframes == 10 && $direct == 3 && $me == 'umh' && $me_range == 24 && $subme == 10 && $trellis == 2 && $rc_lookahead == 60)
				$arr['x264_preset'] = 'veryslow';

			if($b_adapt == 2 && $bframes == 16 && $direct == 3 && $me == 'tesa' && $me_range == 24 && $rc_lookahead == 60 && $subme == 11 && $trellis == 2)
				$arr['x264_preset'] = 'placebo';

			$this->metadata += $arr;

		}

		// Audio
		private function import_audio($arr_audio) {

			$this->metadata['audio_tracks']++;

			$arr['audio_format'] = $arr_audio['Format'];
			if(array_key_exists('Bit_rate', $arr_audio))
				$arr['audio_bitrate'] = current(explode(' ', $arr_audio['Bit_rate']));
			$arr['audio_channels'] = current(explode(' ', $arr_audio['Channel_s_']));

			$this->metadata['audio'][] = $arr;

		}

		// Subtitles
		private function import_text($arr_text) {

			$this->metadata['subtitle_tracks']++;

			$arr = array();

			if($arr_text['Format'] == 'VobSub') {
				$arr['format'] = 'vobsub';
				$this->metadata['vobsub'] = true;
			} elseif($arr_text['Format'] == 'UTF-8') {
				$arr['format'] = 'closed captioning';
				$this->metadata['closed_captioning'] = true;
			}

			$this->metadata['subtitles'][] = $arr;

		}

		// Chapters
		private function import_menu($arr_menu) {

			$this->metadata['menu_tracks']++;

			if(!array_key_exists('num_chapters', $this->metadata))
				$this->metadata['num_chapters'] = 0;

			// Drop the attributes
			unset($arr_menu['@attributes']);

			$arr = preg_grep('/.*Chapter.*/', $arr_menu);

			$chapters = array();

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
