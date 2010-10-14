<?

	class drip {
		function __construct() {
		
			$this->db = MDB2::singleton();
		
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
		 * Create the Matroska global tags containing the episode metadata
		 */
		function globalTags($episode) {
		
			/**
			 * Need:
			 * series title, season #, season year, episode #, episode title, production studio
			 */
			 
			$sql = "SELECT * FROM view_episodes WHERE episode_id = $episode;";
			$arr = $this->db->getRow($sql);
			
			
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
			
			$simple = $tag->addChild("Simple");
			$simple->addChild("Name", "ORIGINAL_MEDIA_TYPE");
			$simple->addChild("String", "DVD");
			$simple->addChild("TagLanguage", "eng");
		
			$simple = $tag->addChild("Simple");
			$simple->addChild("Name", "DATE_TAGGED");
			$simple->addChild("String", date("Y-m-d"));
			$simple->addChild("TagLanguage", "eng");
			
			$simple = $tag->addChild("Simple");
			$simple->addChild("Name", "PLAY_COUNTER");
			$simple->addChild("String", 0);
			$simple->addChild("TagLanguage", "eng");
			
			$tag = $sxe->addChild("Tag");
			
			$targets = $tag->addChild("Targets");
			$targets->addChild("TargetTypeValue", "70");
			$targets->addChild("TargetType", "COLLECTION");
			
			$simple = $tag->addChild("Simple");
			$simple->addChild("Name", "TITLE");
			$simple->addChild("String", $tv_show_title_long);
			$simple->addChild("TagLanguage", "eng");
			
			$simple = $tag->addChild("Simple");
			$simple->addChild("Name", "SORT_WITH");
			$simple->addChild("String", $tv_show_title);
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
			
			if($episode_title) {
				// Episode title
				$simple = $tag->addChild("Simple");
				$simple->addChild("Name", "TITLE");
				$simple->addChild("String", $episode_title);
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
		
		function inDatabase($disc_id = null) {
		
			if(is_null($disc_id) && $this->disc_id)
				$disc_id = $this->disc_id;
			else
				$this->disc_id = $disc_id;
				
			$str = pg_escape_string($disc_id);
		
			$sql = "SELECT COUNT(1) FROM discs WHERE disc_id = $str;";
			$num_rows = $this->db->getOne($sql);
			
			if($num_rows)
				return true;
			else
				return false;
			
		}
		
		function getDiscID($dvd_id) {
		
			$db = MDB2::singleton();
			
			$str = pg_escape_string($dvd_id);
			
			$sql = "SELECT id FROM discs WHERE disc_id = $str;";
			$id = $db->getOne($sql);
			
			return $id;
		
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
			$flags[] = "-vobsubout \"$filename\"";
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
				$this->removeQueue($episode);
				
				$sql = "INSERT INTO queue(queue, episode) VALUES ('$hostname', $episode);";
				$this->db->query($sql);
			}
			
		}
		
		function removeQueue($episode_id) {
		
			$hostname = pg_escape_string($this->hostname);
			
			if($episode_id) {
				$sql = "DELETE FROM queue WHERE queue = '$hostname' AND episode = $episode_id;";
				$this->db->query($sql);
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
		
			// This query dynamically returns the correct episode # out of the entire season for a TV show based on its track #.
			// It works by calculating the number of valid tracks that come before it
			// So, you can archive discs outside of their order, just don't transcode them
			// or your numbering scheme will be off
// 			$sql = "SELECT d.tv_show, d.season, d.disc AS disc_number, d.side, e.episode_order, t.track, t.track_order FROM episodes e INNER JOIN tracks t ON e.track = t.id INNER JOIN discs d ON t.disc = d.id WHERE e.id = $episode;";
			$sql = "SELECT tv_show_id AS tv_show, season, disc AS disc_number, volume, side, episode_order, track, track_order FROM view_episodes WHERE episode_id = $episode;";
//  			shell::msg($sql);
			$row = $this->db->getRow($sql);
			extract($row);
			
			if(is_null($track_order))
				$track_order = 'NULL';
			if(is_null($season))
				$season = 'NULL';
			
			// Need to calculate:
			// # of epsiodes on previous discs plus 
			// # of episodes on current disc plus earlier tracks plus
			// # of episodes on current disc plus current track plus earlier episodes
			$sql = "SELECT (count(e.id) + 1) FROM episodes e INNER JOIN tracks t ON e.track = t.id INNER JOIN discs d ON t.disc = d.id WHERE d.tv_show = $tv_show AND e.season = $season AND t.bad_track = FALSE AND e.title != '' AND ( (d.disc < $disc_number) OR ( d.disc = $disc_number AND d.side < '$side' ) OR ( e.season = $season AND d.volume < $volume) OR (d.disc = $disc_number AND t.track != $track AND t.track_order <= $track_order AND e.episode_order <= $episode_order ));";
//     			shell::msg($sql); die;
			$int = $this->db->getOne($sql);
			
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
			
			// Find the episodes, and their order, that belong
			// on the same disc
			$sql = "SELECT episodes.id, season FROM episodes INNER JOIN tracks ON episodes.track = tracks.id WHERE disc IN (SELECT d.id FROM discs d INNER JOIN tracks t ON t.disc = d.id INNER JOIN episodes e ON e.track = t.id AND e.id = $episode) AND tracks.bad_track = FALSE AND title != '' ORDER BY track_order, episode_order, title;";
// 			shell::msg($sql);
			
			$arr = $this->db->getAssoc($sql);
			
// 			print_r($arr);
			
// 			die;
			
			// Get the starting episode number for that disc
			// using the first episode ID
			$e = $this->startingEpisodeNumber(current(array_keys($arr)));
			
// 			var_dump($e); die;
			
			// How many episodes is this one away from the starting one
			$offset = array_search($episode, array_keys($arr));
			
// 			var_dump($offset);
			
			// If we switch seasons mid-disc (rawr, what a pain), then
			// account for that as well.
			// Do that by checking to see how many times each season shows up
			// then calcuate the offset again to the first episode
			// for that new season.
			if(count(array_unique($arr)) > 1) {
			
// 				echo "Season switch";
				
				$arr_count = array_count_values($arr);
// 				print_r($arr_count);
				
 				if($offset >= current($arr_count)) {
 				
 					$new_season = end(array_keys($arr_count));
 				
 					// Reset the starting episode number
 					if($pad_string)
						$e = $new_season."01";
					else
						$e = 1;
 					$offset -= current($arr_count);
 				}
			
			}
			
// 			var_dump($offset + $e);
			
// 			die;
			
			if($pad_string)
				return $offset + $e;
			else
				return intval(substr($offset + $e, 1));
			
		}
		
		/**
		 * Mux a Matroska file
		 *
		 * @param string source video filename
		 * @param string source audio filename
		 * @param string target filename
		 * @param string episode title
		 * @param string aspect ratio
		 * @param string chapters
		 * @param string subtitles vobsub filename
		 * @param string global tags in XML format filename
		 * @param int audio track id
		 *
		 */
		function mkvmerge($video, $audio, $target, $title = '', $aspect = null, $chapters = null, $audio_track = 1, $vobsub = null, $global_tags = null) {
		
			$flags = array();
			
			// mkvmerge format
			// mkvmerge [global options] -o out [options1] <file1> [[options2] <file2> ...] [@optionsfile]
			
			$flags[] = "--default-language eng";
			
			$flags[] = "-o \"$target\"";
			
			if($aspect)
				$flags[] = "--aspect-ratio 0:$aspect";
			
			if($video == $audio) {
				// Source must immediately follow atrack flag
				if($audio_track)
					$flags[] = "-a $audio_track";
				$flags[] = "\"$video\"";
			} else {
				$flags[] = "-A \"$video\"";
				$flags[] = "-D \"$audio\"";
			}
			
			if($vobsub && file_exists($vobsub))
				$flags[] = "--default-track 0:no \"$vobsub\"";
			
			if($title)
				$flags[] = "--title \"$title\"";
			
			if($chapters) {
				$flags[] = "--chapters \"$chapters\"";
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
		function getQueue($max = 0) {
		
			if($max)
				$limit = " LIMIT $max";
			else
				$limit = '';
		
			$sql = "SELECT e.id, tv.id AS series, tv.title AS series_title, e.title, e.season, e.part, d.disc, t.id AS track_id, t.track, t.aspect, tv.unordered, e.starting_chapter, e.ending_chapter, e.episode_order FROM episodes e INNER JOIN tracks t ON e.track = t.id INNER JOIN discs d ON t.disc = d.id INNER JOIN tv_shows tv ON d.tv_show_id = tv.id INNER JOIN queue q ON q.episode = e.id AND q.queue = '".pg_escape_string($this->hostname)."' WHERE t.bad_track = FALSE AND e.title != '' ORDER BY insert_date $limit;";
			$arr = $this->db->getAssoc($sql);
			
			$sql = "SELECT episode_id FROM view_episodes e INNER JOIN queue q ON q.episode = e.episode_id AND q.queue = '".pg_escape_string($this->hostname)."' WHERE e.bad_track = FALSE AND e.episode_title != '' ORDER BY insert_date $limit;";
			
			$arr = $this->db->getCol($sql);
			
			return $arr;
		
		}
		
		function getDefaultAudioTrack($track_id, $language = "en") {
		
			$sql = "SELECT id, ix, lang FROM audio_tracks WHERE track = $track_id ORDER BY ix;";
			$arr = $this->db->getAssoc($sql);
			
			if(count($arr)) {
				foreach($arr as $row) {
					if($row['lang'] == 'en') {
						$audio_track = $row['ix'];
						break;
					}
					
					if(!$audio_track)
						$audio_track = 1;
					
				}
			} else {
				$audio_track = 1;
			}
			
			$this->audio_track = $audio_track;
				
			return $this->audio_track;
		
		}
		
		function getDefaultAudioAID($track_id, $language = "en") {
			if(is_null($this->audio-track)) {
				$this->getDefaultAudioTrack($track_id, $language);
			}
			
			return $this->audio_track + 127;
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