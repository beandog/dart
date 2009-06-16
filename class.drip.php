<?

	class drip {
		function __construct() {
		
			// Default config variables
			$this->device = '/dev/dvd';
			$this->export = getenv('HOME').'/dvds/';
			$this->eject = false;
			$this->hostname = trim(`hostname`);
			$this->debug = false;
			$this->verbose = false;
			
			// lsdvd output
			$this->lsdvd['titles'] = '';
			$this->lsdvd['output'] = '';
			$this->lsdvd['xml'] = '';
			
			// Hardware variables
			$this->dvd = array();
			$this->dvd['disc_id'] = '';
			$this->dvd['title'] = '';
			$this->dvd['num_tracks'] = 0;
			$this->dvd['tracks'] = array();
			$this->dvd['chapters'] = array();
			
			// Database variables
			$this->disc = array();
			$this->disc['id'] = null;
			$this->disc['tv_show'] = null;
			$this->disc['season'] = null;
			$this->disc['disc'] = null;
			
			// Series variables
			$this->series = array();
			
			// Episode number
			$this->episode = 0;
		
		}
		
		/**
		* Set the disc_id hash provided by disc_id,
		* part of the libdvdread library
		*
		*/
		function disc_id() {
		
			if(empty($this->dvd['disc_id'])) {
				$str = "disc_id ".$this->device;
				$arr = shell::cmd($str);
				$this->dvd['disc_id'] = $arr[0];
			}
		}
		
		/**
		 * Get the XML output of lsdvd
		 *
		 */
		function lsdvd() {
		
			if(empty($this->lsdvd['output'])) {
				$str = "lsdvd -Ox -v -a -s -c ".$this->device;
				$arr = shell::cmd($str);
				$str = implode("\n", $arr);
				
				// Fix broken encoding on langcodes, standardize output
				$str = str_replace('Pan&Scan', 'Pan&amp;Scan', $str);
				$str = str_replace('P&S', 'P&amp;S', $str);
				$str = preg_replace("/\<langcode\>\W+\<\/langcode\>/", "<langcode>und</langcode>", $str);
				
				$this->lsdvd['output'] = $str;
			}
		
		}
		
		/**
		 * Get the output of lsdvd and import into PHP simplexml format
		 *
		 */
		function lsdvdXML() {
		
			if(empty($this->lsdvd['xml'])) {
				$this->lsdvd();
		
				$xml = simplexml_load_string($this->lsdvd['output']) or die("Couldn't parse lsdvd XML output");
				
				$this->lsdvd['xml'] = $xml;
			}
		
		}
		
		/**
		 * Populate lsdvd titles
		 */
		function lsdvdTitles() {
		
			if(empty($this->lsdvd['titles'])) {
				$str = "lsdvd ".$this->device;
				$arr = shell::cmd($str);
				$arr = preg_grep('/^Title/', $arr);
				$arr = array_merge($arr);
				$str = implode("\n", $arr);
				$this->lsdvd['titles'] = $str;
			}
		
		}
		
		/**
		 * Execute dvdxchap to get chapter start/stop info
		 *
		 * FIXME Don't use dvdxchap, use lsdvd -t track -c to get
		 * chapter info!
		 *
		 * @param int track number
		 * @return dvdxchapter string output
		 */
		function dvdxchap($track) {
		
			$str = "dvdxchap -t $track ".$this->device;
			$arr = shell::cmd($str);
			$str = implode("\n", $arr);
			
			return($str);
		
		}
		
		/**
		 * Set the disc title
		 *
		 */
		function title() {
		
			if(empty($this->dvd['title'])) {
				$this->lsdvdXML();
				
				$this->dvd['title'] = (string)$this->lsdvd['xml']->title;
			}
			
		}
		
		/**
		 * Set the tracks array
		 *
		 */
		function tracks() {
		
			if(count($this->dvd['tracks']) == 0) {
			
				global $db;
			
				if($this->inDatabase()) {
					$this->disc();
					$sql = "SELECT track, id FROM tracks WHERE disc = ".$this->disc['id']." ORDER BY track;";
					$arr_track_ids = $db->getAssoc($sql);
				} else
					$arr_track_ids = array();
		
				$this->lsdvdXML();
				$this->lsdvdTitles();
				
				// Build the array of tracks and their lengths
				foreach($this->lsdvd['xml']->track as $track) {
				
					$this->dvd['tracks'][(int)$track->ix]['len'] = number_format((float)$track->length / 60, 2, '.', '');
					
					// Newer array
					// Only add ones where length != 0
					if($this->dvd['tracks'][(int)$track->ix]['len'] != '0.00') {
						$this->dvd['tracks'][(int)$track->ix]['aspect'] = (string)$track->aspect;
						
						// Get the audio streams
						foreach($track->audio as $audio) {
							$this->dvd['tracks'][(int)$track->ix]['audio'][] = array('lang' => (string)$audio->langcode, 'channels' => (int)$audio->channels, 'format' => (string)$audio->format);
						}
						
						// Get the subtitle streams
						foreach($track->subp as $subp) {
							$this->dvd['tracks'][(int)$track->ix]['vobsub'][] = array('lang' => (string)$subp->langcode, 'language' => (string)$subp->language);
						}
					}
					
				}
				
				$this->dvd['num_tracks'] = count($this->dvd['tracks']);
				
				// Get the *total* number of chapters, which is only output
				// in raw lsdvd export.
				$tmp = explode("\n", $this->lsdvd['titles']);
				
				$x = 1;
				foreach($tmp as $row) {
					$arr = explode(', ', $row);
					$this->dvd['tracks'][$x]['num_chapters'] = intval(substr($arr[1], -2, 2));
					$x++;
				}
				
			}
			
		}
		
		/**
		 * Populate the id variables in $dvd->tracks
		 *
		 */
		function trackIDs() {
		
			$this->tracks();
			
			global $db;
			
			if($this->inDatabase()) {
				$this->disc();
				$sql = "SELECT track, id FROM tracks WHERE disc = ".$this->disc['id']." ORDER BY track;";
				$arr_track_ids = $db->getAssoc($sql);
				
				foreach($this->dvd['tracks'] as $track => $arr)
					$this->dvd['tracks'][$track]['id'] = $arr_track_ids[$track];
				
			}
		}
		
		/**
		 * Populate the chapters array with 
		 * dvdxchap output as well as starting times of chapters
		 *
		 */
		function chapters() {
		
			$this->lsdvdTitles();
			$this->tracks();
			
			foreach($this->dvd['tracks'] as $track => $arr) {
			
				// Set dvdxchap variable
				$str = $this->dvd['tracks'][$track]['dvdxchap'] = $this->dvdxchap($track);
				
				// Build chapters array
				$chapter = 1;
				$arr = explode("\n", $str);
				$arr = preg_grep('/^CHAPTER\d+=/', $arr);
				$arr = preg_replace('/^CHAPTER\d+=/', '', $arr);
				foreach($arr as $start_time) {
					// Convert start times to seconds
					// This is the format we'll use when muxing chapters
					$tmp = explode(':', $start_time);
					$seconds = ($tmp[0] * 60 * 60) + ($tmp[1] * 60);
					$seconds = bcadd($seconds, $tmp[2], 3);
					$this->dvd['chapters'][$track][$chapter]['start'] = $seconds;
					$chapter++;
				}
				
				$num_chapters = $chapter;
				
				// Calculate chapter lengths
				foreach($this->dvd['chapters'][$track] as $chapter => $tmp) {
				
					$next_start = $this->dvd['chapters'][$track][($chapter + 1)]['start'];
				
					if($next_start) {
						$length = bcsub($next_start, $tmp['start'], 0);
					} else {
						$length = bcsub(bcmul($this->dvd['tracks'][$track]['len'], 60, 3), $tmp['start'], 0);
					}
					
// 					shell::msg($length);
	
					$this->dvd['chapters'][$track][$chapter]['len'] = $length;
		
				}
			
			}
			
		}
		
		/**
		 * Create the Matroska global tags
		 */
		function globalTags($episode) {
		
			global $db;
		
			/**
			 * Need:
			 * series title, season #, season year, episode #, episode title, production studio
			 */
			 
			$sql = "SELECT * FROM view_episodes WHERE id = $episode;";
			$arr = $db->getRow($sql);
			
			if(!count($arr))
				return "";
			
			$arr['episode_number'] = $this->episodeNumber($episode, false);
			
			extract($arr);
			
			if($start_year)
				$season_year = $start_year + $season - 1;
			
			$xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE Tags SYSTEM "/usr/local/share/matroska/xml/matroskatags.dtd">
<Tags>
</Tags>
XML;

			$sxe = new SimpleXMLElement($xml);
			
			/** Series **/
	
			$tag = $sxe->addChild("Tag");
			$targets = $tag->addChild("Targets");
			$targets->addChild("TargetTypeValue", "70");
			$targets->addChild("TargetType", "COLLECTION");
			
			$simple = $tag->addChild("Simple");
			$simple->addChild("Name", "TITLE");
			$simple->addChild("String", $series);
			$simple->addChild("TagLanguage", "eng");
			
			if($production_studio) {
				$simple = $tag->addChild("Simple");
				$simple->addChild("Name", "PRODUCTION_STUDIO");
				$simple->addChild("String", $production_studio);
				$simple->addChild("TagLanguage", "eng");
			}
			
			if($start_year) {
				$simple = $tag->addChild("Simple");
				$simple->addChild("Name", "DATE_RELEASE");
				$simple->addChild("String", $start_year);
				$simple->addChild("TagLanguage", "eng");
			}
			
			/** Season **/
			
			if($season) {
				$tag = $sxe->addChild("Tag");
				$targets = $tag->addChild("Targets");
				$targets->addChild("TargetTypeValue", "60");
				$targets->addChild("TargetType", "SEASON");
				
				if($season_year) {
					$simple = $tag->addChild("Simple");
					$simple->addChild("Name", "DATE_RELEASE");
					$simple->addChild("String", $season_year);
					$simple->addChild("TagLanguage", "eng");
				}
				
				// Season number
				$simple = $tag->addChild("Simple");
				$simple->addChild("Name", "PART_NUMBER");
				$simple->addChild("String", $season);
				$simple->addChild("TagLanguage", "eng");
			}
			
			/** Episode **/
	
			$tag = $sxe->addChild("Tag");
			$targets = $tag->addChild("Targets");
			$targets->addChild("TargetTypeValue", "50");
			$targets->addChild("TargetType", "EPISODE");
			
			if($title) {
				// Episode title
				$simple = $tag->addChild("Simple");
				$simple->addChild("Name", "TITLE");
				$simple->addChild("String", $title);
				$simple->addChild("TagLanguage", "eng");
			}
			
			if($episode_number) {
				// Episode number
				$simple = $tag->addChild("Simple");
				$simple->addChild("Name", "PART_NUMBER");
				$simple->addChild("String", $episode_number);
				$simple->addChild("TagLanguage", "eng");
			}
			
			$doc = new DOMDocument('1.0');
			$doc->preserveWhiteSpace = false;
			$doc->loadXML($sxe->asXML());
			$doc->formatOutput = true;
			$xml = $doc->saveXML();
			
			return $xml;
		
		}
		
		function inDatabase() {
		
			global $db;
		
			$this->disc_id();
				
			$sql = "SELECT COUNT(1) FROM discs WHERE disc_id = '".$this->dvd['disc_id']."';";
			$num_rows = $db->getOne($sql);
			
			if($num_rows)
				return true;
			else
				return false;
			
		}
		
		/**
		 * Populate disc array
		 *
		 * @return boolean disc is in database
		 */
		function disc() {
		
			if(empty($this->disc['id'])) {
				global $db;
				$this->disc_id();
				
				$sql = "SELECT id, tv_show, season, disc FROM discs WHERE disc_id = '".$this->dvd['disc_id']."';";
				$arr = $db->getRow($sql);
				if(count($arr)) {
					$this->disc = $arr;
				}
			}
		
		}
		
		/**
		 * Populate series array
		 *
		 */
		function series($id = false) {
		
			$id = abs(intval($id));
		
			if(empty($this->series['id'])) {
				global $db;
				
				// Select the show if specifically asked for
				if($id)
					$sql = "SELECT tv.id, tv.title, min_len, max_len, cartoon, unordered FROM tv_shows tv WHERE id = $id;";
				// Otherwise, just figure it out from the current disc
				else {
					$this->disc_id();
					$sql = "SELECT tv.id, tv.title, min_len, max_len, cartoon, unordered FROM tv_shows tv INNER JOIN discs d ON d.tv_show = tv.id AND d.disc_id = '".$this->dvd['disc_id']."';";
				}
				$arr = $db->getRow($sql);
				
				$this->series = $arr;
			}
			
		}
		
		/**
		 * --archive option
		 *
		 * Archive a new [series] disc
		 *
		 */
		function archiveDisc() {
		
			$this->disc_id();
			
			if($this->inDatabase()) {
				exit(0);
			} else {
				$this->title();
			}
		
		}
		
		/**
		 * Add a new series to the database
		 *
		 * @param string titles
		 * @param int min episode length
		 * @param int max episode length
		 * @param bool cartoon
		 *
		 */
		function newSeries($title, $min_len, $max_len, $cartoon) {
			
			global $db;
			
			$title = pg_escape_string($title);
			$min_len = abs(intval($min_len));
			$max_len = abs(intval($max_len));
			if($cartoon)
				$cartoon = 'TRUE';
			else
				$cartoon = 'FALSE';
			
			$sql = "SELECT NEXTVAL('public.tv_shows_id_seq');";
			$id = $db->getOne($sql);
			
			$sql = "INSERT INTO tv_shows(id, title, min_len, max_len, cartoon) VALUES ($id, '$title', $min_len, $max_len, $cartoon);";
			$db->query($sql);
			
			return $id;
			
		}
		
		function newDisc($series, $season, $disc, $side = "") {
		
			global $db;
		
			$this->disc_id();
			$this->title();
			
			$series = abs(intval($series));
			$season = abs(intval($season));
			$disc = abs(intval($disc));
			
			$sql = "INSERT INTO discs(tv_show, season, disc, side, disc_id, disc_title) VALUES ($series, $season, $disc, '$side', '".$this->dvd['disc_id']."', '".$this->dvd['title']."');";
			$db->query($sql);
			
			$this->disc();
		
		}
		
		/**
		 * Add a new track into the database
		 *
		 * @param int track number
		 * @param double length
		 * @param string aspect ratio
		 * @param array audio tracks
		 * @return track id
		 *
		 */
		function newTrack($track, $len, $aspect, $audio_tracks) {
		
			global $db;
		
			$this->disc();
			
			$track = abs(intval($track));
			if(!is_numeric($len))
				$len = 0.00;
				
			$sql = "SELECT NEXTVAL('public.tracks_id_seq');";
			$id = $db->getOne($sql);
				
			$sql = "INSERT INTO tracks(id, disc, track, len, track_order, aspect) VALUES ($id, ".$this->disc['id'].", $track, $len, $track, '$aspect');";
			$db->query($sql);
			
			// Store audio tracks in the database
			if(count($audio_tracks)) {
				foreach($audio_tracks as $key => $arr) {
					extract($arr);
					$sql = "INSERT INTO audio_tracks (track, audio_track, lang, channels, format) VALUES ('$id', '$key', '$lang', '$channels', '$format');";
					$db->query($sql);
				}
			}
			
			return $id;
		
		}
		
		/**
		 * Add a new track's chapter into the database
		 *
		 * @param int track id
		 * @param double start time
		 * @param int chapter number
		 *
		 */
		function newChapter($track, $start_time, $chapter, $len) {
		
			global $db;
			
			$this->disc();
			
			$track = abs(intval($track));
			if(!is_numeric($start_time))
				$start_time = 0.00;
			
			$chapter = abs(intval($chapter));
			
 			$sql = "INSERT INTO track_chapters(track, start_time, chapter, len) VALUES($track, $start_time, $chapter, $len);";
 			$db->query($sql);
			
		}
		
		function newEpisode($season, $track, $ignore = false, $chapters) {
		
			$track = abs(intval($track));
			if($ignore)
				$ignore = 'TRUE';
			else
				$ignore = 'FALSE';
		
			global $db;
			
			$chapters = pg_escape_string($chapters);
			
			$sql = "INSERT INTO episodes(season, track, ignore, chapters) VALUES($season, $track, $ignore, '$chapters');";
			$db->query($sql);
			
		}
		
		/**
		 * Format a title for saving to filesystem
		 *
		 * @param string original title
		 * @return new title
		 */
		function formatTitle($str = 'Title', $underlines = true) {
			$str = preg_replace("/[^A-Za-z0-9 \-,.?':!]/", '', $str);
			$underlines && $str = str_replace(' ', '_', $str);
			return $str;
		}
		
		/**
		 * Rip a DVD track + chapter(s)
		 *
		 * @param string target filename
		 * @param int track number
		 * @param int starting chapter
		 * @param int ending chapter
		 *
		 */
		function rip($filename, $track, $start = '', $end = '') {
		
			$flags = array();
			
			$track = abs(intval($track));
			
			$flags[] = "dvd://$track";
			$flags[] = "-dvd-device ".$this->device;
			$flags[] = "-dumpstream -dumpfile $filename";
			
			if(is_numeric($start)) {
				$start = abs(intval($start));
				if(is_numeric($end))
					$end = abs(intval($end));
				// Ripping Highway to Heaven, need to rip
				// -chapter 2-
				// Why in the world would I have it rip
				// only that one chapter by default?
				// It overrides the function input (might
				// as well not have a var)
// 				else
// 					$end =& $start;
				
				$flags[] = "-chapter $start-$end";
			}
			
			$str = "mplayer -really-quiet -quiet ".implode(' ', $flags);
			$str = escapeshellcmd($str);
			
			if($this->debug)
				shell::msg("Executing: $str");
			
			$start = time();
			shell::cmd($str, !$this->debug);
			$finish = time();
			
			if($this->debug) {
				$exec_time = shell::executionTime($start, $finish);
				shell::msg("Execution time: ".$exec_time['minutes']."m ".$exec_time['seconds']."s");
			}
		
		}
		
		/**
		 * Rip VobSub subtitles
		 *
		 */
		function sub($filename, $track, $start = '', $end = '') {
		
			$flags = array();
			
			$track = abs(intval($track));
			
			$flags[] = "dvd://$track";
			$flags[] = "-dvd-device ".$this->device;
			$flags[] = "-ovc copy";
			$flags[] = "-nosound";
			$flags[] = "-vobsubout $filename";
			$flags[] = "-o /dev/null";
			$flags[] = "-slang en";
			$flags[] = "-quiet";
			
			/** FIXME Not sure if this will work with chapters or not **/
			if(is_numeric($start)) {
				$start = abs(intval($start));
				if(is_numeric($end))
					$end = abs(intval($end));
				// Ripping Highway to Heaven, need to rip
				// -chapter 2-
				// Why in the world would I have it rip
				// only that one chapter by default?
				// It overrides the function input (might
				// as well not have a var)
// 				else
// 					$end =& $start;
				
				$flags[] = "-chapter $start-$end";
			}
			
// 			$exec = "mencoder -ovc copy -nosound -vobsubout $filename -o /dev/null -slang en";
			$str = "mencoder ".implode(' ', $flags);
			$str = escapeshellcmd($str);
			
			if($this->debug)
				shell::msg("Executing: $str");
			
			$start = time();
			shell::cmd($str, !$this->debug);
			$finish = time();
			
			if($this->debug) {
				$exec_time = shell::executionTime($start, $finish);
				shell::msg("Execution time: ".$exec_time['minutes']."m ".$exec_time['seconds']."s");
			}
		
		}
		
		/**
		 * Add an episode to the queue to be encoded
		 *
		 * @param int episode id
		 */
		function queue($episode) {
		
			$episode = abs(intval($episode));
			$hostname = pg_escape_string($this->hostname);
			
			if($episode) {
				global $db;
				$sql = "DELETE FROM queue WHERE queue = '$hostname' AND episode = $episode;";
				$db->query($sql);
				
				$sql = "INSERT INTO queue(queue, episode) VALUES ('$hostname', $episode);";
				$db->query($sql);
			}
			
		}
		
		/**
		 * Get the starting episode number
		 *
		 * @param int episode ID
		 */
		function startingEpisodeNumber($episode) {
		
			// This function is different from the original bend implementation.
			// Instead of calculating the episode number for each one,
			// we only grab the starting one and increment from there.  This
			// was changed because with episodes (tracks + chapters) the query
			// will return the same episode number twice or more.
			// This new method goes off the basis of the constants we have:
			// - # of episodes on this disc
			// - # of episodes on previous discs
			// - general order of episodes on this disc
			//
			// As a result the episode_order takes on its namesake, it is
			// a general order that episodes will be placed in, instead
			// of the old method of it setting the actual episode #.
			// Much simpler. :)
			//
			// In practice, the shell script will grab the episode number
			// of the first one to rip, then increment the # itself after that.
		
			$episode = abs(intval($episode));
		
			global $db;
		
			// This query dynamically returns the correct episode # out of the entire season for a TV show based on its track #.
			// It works by calculating the number of valid tracks that come before it
			// So, you can archive discs outside of their order, just don't transcode them
			// or your numbering scheme will be off
			$sql = "SELECT d.tv_show, d.season, d.disc AS disc_number, d.side, e.episode_order, t.track, t.track_order FROM episodes e INNER JOIN tracks t ON e.track = t.id INNER JOIN discs d ON t.disc = d.id WHERE e.id = $episode;";
			$row = $db->getRow($sql);
			extract($row);
			
			if(is_null($track_order))
				$track_order = 'NULL';
			
			// Need to calculate:
			// # of epsiodes on previous discs plus 
			// # of episodes on current disc plus earlier tracks plus
			// # of episodes on current disc plus current track plus earlier episodes
			$sql = "SELECT (count(e.id) + 1) FROM episodes e INNER JOIN tracks t ON e.track = t.id INNER JOIN discs d ON t.disc = d.id WHERE d.tv_show = $tv_show AND d.season = $season AND t.bad_track = FALSE AND e.title != '' AND e.ignore = FALSE AND ( (d.disc < $disc_number) OR ( d.disc = $disc_number AND d.side < '$side' ) OR (d.disc = $disc_number AND t.track != $track AND t.track_order <= $track_order AND e.episode_order <= $episode_order ));";
//   			shell::msg($sql); die;
			$int = $db->getOne($sql);
			
			// I'm not padding the seasons, because I'm assuming there's not
			// going to be any show that has more than 9, of which
			// something is going to prove me wrong, I know.
			// Hmm ... Simpsons, anyone?  Meh.  Patch it yourself. :)
			$int = $season.str_pad($int, 2, 0, STR_PAD_LEFT);

			return $int;
		
		}
		
		/**
		 * Get the *actual* episode number, based on all other considerations
		 *
		 * @param string episode ID
		 * @param boolean pad string with season (102: season 1, episode 2)
		 *
		 */
		function episodeNumber($episode, $pad_string = true) {
		
			// This second episodeNumber function calls the first one,
			// but it uses it to calculate the episode number based
			// on the relation to the starting one.
			// Uses two constants:
			// - episode # of first episode on disc
			// - relation of variable episode # to first episode.
			//
			// This function *should* always work as a wrapper
			// to the first function, even if its the first episode,
			// since it will only add 0 to the returned integer.
			
			global $db;
			
			// Find the episodes, and their order, that belong
			// on the same disc
			$sql = "SELECT episodes.id, title FROM episodes INNER JOIN tracks ON episodes.track = tracks.id WHERE disc IN (SELECT d.id FROM discs d INNER JOIN tracks t ON t.disc = d.id INNER JOIN episodes e ON e.track = t.id AND e.id = $episode) AND episodes.ignore = FALSE AND tracks.bad_track = FALSE AND title != '' ORDER BY track_order, episode_order, title;";
			
			$arr = $db->getCol($sql);
			
			// Get the starting episode number for that disc
			// using the first episode ID
			$e = $this->startingEpisodeNumber(current($arr));
			
			$key = array_search($episode, $arr);
			
			if($pad_string)
				return $key + $e;
			else
				return intval(substr($key + $e, 1));
			
		}
		
		/**
		 * Mux a Matroska file
		 *
		 * @param string source filename
		 * @param string target filename
		 * @param string episode title
		 * @param string aspect ratio
		 * @param string chapters
		 * @param string subtitles vobsub filename
		 * @param string global tags in XML format filename
		 * @param int audio track id
		 *
		 */
		function mkvmerge($source, $target, $title = '', $aspect = null, $chapters = null, $audio_track = 1, $vobsub = null, $global_tags = null) {
		
			$flags = array();
			
			// mkvmerge format
			// mkvmerge [global options] -o out [options1] <file1> [[options2] <file2> ...] [@optionsfile]
			
			$flags[] = "--default-language eng";
			
			$flags[] = "-o \"$target\"";
			
			if($aspect)
				$flags[] = "--aspect-ratio 0:$aspect";
			
			// Source must immediately follow atrack flag
			if($audio_track)
				$flags[] = "-a $audio_track";
			
			$flags[] = "\"$source\"";
			
			if($vobsub && file_exists($vobsub))
				$flags[] = "--default-track 0:no \"$vobsub\"";
			
			if($title)
				$flags[] = "--title \"$title\"";
			
			if(strlen($chapters)) {
				$txt = preg_replace("/mkv$/", "txt", $target);
				file_put_contents($txt, $chapters);
				$flags[] = "--chapters \"$txt\"";
			}
			
			if(!is_null($global_tags) && file_exists($global_tags))
				$flags[] = "--global-tags \"$global_tags\"";
				
			$exec = "mkvmerge ".implode(' ', $flags);
			
			if($this->debug)
				shell::msg("Executing: $exec");
			
			/**
			
			From the mkvmerge man:
			
			EXIT CODES
			mkvmerge exits with one of three exit codes:
		
			0      This exit codes means that muxing has completed successfully.
		
			1      In this case mkvmerge has output at least one warning, but muxing did continue.  A warning is prefixed with the text  ´Warning:´.   Depending  on
					the issues involved the resulting file might be ok or not.  The user is urged to check both the warning and the resulting file.
		
			2      This  exit  code is used after an error occured.  mkvmerge aborts right after outputting the error message.  Error messages range from wrong com‐
					mand line arguments over read/write errors to broken files.
			*/

			$start = time();
			shell::cmd($exec, !$this->verbose, false, $this->debug, array(0,1));
			$finish = time();
			
			if($this->debug) {
				$exec_time = shell::executionTime($start, $finish);
				shell::msg("Execution time: ".$exec_time['minutes']."m ".$exec_time['seconds']."s");
			}
		
		}
		
		/**
		 * Fetch the array of episodes in the queue to encode,
		 * along with all the information needed to encode them.
		 *
		 */
		function getQueue($max) {
		
			if($max)
				$limit = " LIMIT $max";
			else
				$limit = '';
		
			global $db;
			$sql = "SELECT e.id, tv.id AS series, tv.title AS series_title, e.title, d.season, d.disc, t.id AS track_id, t.track, t.aspect, tv.unordered, t.multi, COALESCE(e.starting_chapter, e.chapter, tv.starting_chapter) AS starting_chapter, e.ending_chapter, e.chapters, e.episode_order FROM episodes e INNER JOIN tracks t ON e.track = t.id INNER JOIN discs d ON t.disc = d.id INNER JOIN tv_shows tv ON d.tv_show = tv.id INNER JOIN queue q ON q.episode = e.id AND q.queue = '".pg_escape_string($this->hostname)."' WHERE e.ignore = FALSE AND t.bad_track = FALSE AND e.title != '' ORDER BY insert_date $limit;";
			$arr = $db->getAssoc($sql);
			
			// TODO: Get chapters
			// This query works to get the relevant chapters for that track,
			// but it's not checking to see if they apply (one episode per track or not)
			foreach($arr as $id => $arr_track) {
// 				$sql = "SELECT start_time, chapter FROM track_chapters WHERE track = ".$arr_track['track_id'].";";
// 				$arr_chapters = $db->getAll($sql);
// 				$arr[$id]['chapters'] = $arr_chapters;

				// Get audio tracks, find the first English one
				$sql = "SELECT * FROM audio_tracks WHERE track = ".$arr_track['track_id']." ORDER BY audio_track;";
				$arr_audio_tracks = $db->getAssoc($sql);
				
				if(count($arr_audio_tracks)) {
					foreach($arr_audio_tracks as $arr_audio) {
						if($arr_audio['lang'] == 'en') {
							$atrack = ($arr_audio['audio_track'] + 1);
							break;
						}
					}
				} else
					$atrack = 1;
				
				$arr[$id]['atrack'] = $atrack;

				// Unused
				unset($arr[$id]['track_id']);
				
			}
			
			return $arr;
		
		}
		
		function mount() {
			shell::cmd("mount ".$this->device, true, true);
		}
		
		function unmount() {
			shell::cmd("umount ".$this->device);
		}
		
		function eject() {
			shell::cmd("eject ".$this->device);
		}
		 
		
	}

?>