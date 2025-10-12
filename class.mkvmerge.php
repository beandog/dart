<?php

	class Mkvmerge {

		public $debug = false;
		public $verbose = false;

		// mkvmerge
		public $chapters = '';
		public $mkvmerge_opts = '';
		public $mkvmerge_output = '/dev/null';
		public $track_order = '';

		// mkvmerge source
		public $input_filename = '/dev/sr0';
		public $input_filenames = array();
		public $output_filename = 'media_track.mkv';

		// Video
		public $video_tracks = array();

		// Audio
		public $audio_tracks = array();

		// Subtitles
		public $subtitle_tracks = array();

		public function debug($bool = true) {
			$this->debug = $this->verbose = boolval($bool);
		}

		public function verbose($bool = true) {
			$this->verbose = boolval($bool);
		}

		/** Filename **/
		public function input_filename($src) {
			$this->input_filenames[] = $src;
		}

		public function output_filename($str) {
			$this->output_filename = $str;
		}

		public function add_input_filename($str) {
			$this->input_filenames[] = $str;
		}

		public function add_video_track($str) {
			$this->video_tracks[] = abs(intval($str));
		}

		public function add_audio_track($str) {
			$this->audio_tracks[] = abs(intval($str));
		}

		public function add_subtitle_track($str) {
			$this->subtitle_tracks[] = abs(intval($str));
		}

		public function add_chapters($str) {
			$this->chapters = $str;
		}

		public function set_track_order($str) {
			$this->track_order = $str;
		}

		public function get_arguments() {

			$args = array();

			$args['-o'] = $this->output_filename;

			if(count($this->video_tracks))
				$args['--video-tracks'] = implode(',', $this->video_tracks);

			if(count($this->audio_tracks))
				$args['--audio-tracks'] = implode(',', $this->audio_tracks);

			if(count($this->subtitle_tracks))
				$args['--subtitle-tracks'] = implode(',', $this->subtitle_tracks);

			if(strlen($this->chapters))
				$args['--chapters'] = $this->chapters;

			if(strlen($this->track_order))
				$args['--track-order'] = $this->track_order;

			$args['--default-language'] = "eng";

			return $args;

		}

		public function get_executable_string() {

			$cmd = array();

			$cmd[] = "mkvmerge";

			$args = $this->get_arguments();

			foreach($args as $key => $value) {
				$arg_value = escapeshellarg($value);
				$cmd[] = "$key $arg_value";
			}

			if(!count($this->subtitle_tracks))
				$cmd[] = "--no-subtitles";

			if($this->verbose)
				$cmd[] = "-v";

			foreach($this->input_filenames as $filename)
				$cmd[] = escapeshellarg($filename);

			$str = implode(" ", $cmd);

			return $str;

		}

	}

	// Old code from dart.encode_info.php, put here for archival purposes

		/*
		// Legacy mkvmerge -- needs to scan file and parse JSON output for correct track IDs.
		// Currently it's broken, and has to be changed manually a lot.
		// Also this is a FIXME because tracks_model isn't working. Not going to fix for now, until
		// this option is restored.
		if($disc_type == 'bluray' && $tracks_model->codec != 'vc1' && (($dvd_encoder == 'bluraycopy' && !$opt_ffplay && !$opt_ffmpeg) || $opt_bluraycopy)) {

			$display_txt = true;
			$display_m2ts = true;
			$display_mkv = true;

			$bluray_m2ts = substr($filename, 0, strlen($filename) - 3)."m2ts";
			$bluray_txt = substr($filename, 0, strlen($filename) - 3)."txt";

			if(file_exists($bluray_txt) && $opt_skip_existing)
				$display_txt = false;

			if(file_exists($bluray_m2ts) && $opt_skip_existing)
				$display_m2ts = false;

			$bluray_copy = new BlurayCopy();

			$bluray_copy->input_track($tracks_model->ix);

			$bluray_copy->set_chapters($episodes_model->starting_chapter, $episodes_model->ending_chapter);
			$bluray_copy->output_filename($bluray_m2ts);

			$bluray_m2ts_command = $bluray_copy->get_executable_string();

			$bluray_chapters = new BlurayChapters();

			$bluray_chapters->input_filename($input_filename);

			$bluray_chapters->input_track($tracks_model->ix);

			$bluray_chapters->set_chapters($episodes_model->starting_chapter, $episodes_model->ending_chapter);
			$bluray_chapters->output_filename($bluray_txt);

			$bluray_chapters_command = $bluray_chapters->get_executable_string();

			$mkvmerge = new Mkvmerge();
			$mkvmerge->add_video_track(0);

			// This was originally here to grab the TrueHD audio streams which
			// looked like they were the second stream instead of the first. That is
			// not always the case, and while it seems ideal to check all the
			// variables, practically speaking the best quality track is going to be
			// the first one matching the language.
			// $audio_ix = $tracks_model->get_best_quality_audio_ix('bluray');
			$audio_ix = $tracks_model->get_first_english_ix('bluray');
			$mkvmerge->add_audio_track($audio_ix);

			$num_pgs_tracks = $tracks_model->get_num_subp_tracks();
			$num_active_pgs_tracks = $tracks_model->get_num_active_subp_tracks();
			$num_active_en_pgs_tracks = $tracks_model->get_num_active_subp_tracks('eng');

			if($num_pgs_tracks) {
				$pgs_ix = 0;
				$pgs_ix += count($tracks_model->get_audio_streams());
				$pgs_ix += $tracks_model->get_first_english_subp();
				$mkvmerge->add_subtitle_track($pgs_ix);
			}

			$mkvmerge->add_input_filename($bluray_m2ts);
			$mkvmerge->output_filename($filename);
			$mkvmerge->add_chapters($bluray_txt);

			$mkvmerge_command = $mkvmerge->get_executable_string();

			if($display_txt)
				echo "$bluray_chapters_command\n";

			if($display_m2ts)
				echo "$bluray_m2ts_command\n";

			if($display_mkv)
				echo "$mkvmerge_command\n";

		}
		*/

