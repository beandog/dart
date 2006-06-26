<?
	if($dvd->args['movie'] == 1 || $dvd2mkv === true) {

		if(empty($dvd->args['title'])) {
			echo "Enter a movie title: ";
			$title = fgets($stdin, 255);

			echo "Is this movie an animated cartoon? [y/N] ";
			$cartoon = fgets($stdin, 2);
		}
		else {
			$title = $dvd->args['title'];
			$cartoon = $dvd->args['cartoon'];
		}

		$title = $dvd->escapeTitle($title);
		$vob = "$title.vob";
		$txt = "$title.txt";
		$avi = "$title.avi";
		$mkv = "$title.mkv";

		if(strtolower($cartoon) == 'y' || $cartoon == 1)
			$dvd->arr_encode['cartoon'] = 't';

		$scandir = preg_grep('/(avi|mkv|vob)$/', scandir('./'));

		// Mount/read DVD contents if we need to
		if(!file_exists($txt) || !in_array($vob, $scandir)) {

			$dvd->executeCommand('mount /mnt/dvd');
			$dvd->lsdvd();

			if(!file_exists($txt)) {
				$dvd->arr_encode['chapters'] = $dvd->getChapters($dvd->longest_track);
				$dvd->writeChapters($txt);
			}

			// file_exists doesn't work on LARGE files (such as VOB files over 2gb)
			// so we use scandir and in_array instead
			if(!in_array($vob, $scandir)) {
				echo("Ripping movie track to VOB...\n");
				$dvd->ripTrack($dvd->longest_track, $vob);
				#$exec = "mencoder dvd://{$dvd->longest_track} -ovc copy -oac copy -ofps 24000/1001 -o $vob";
				$dvd->executeCommand('eject');
			}
		}

		$midentify = $dvd->midentify($vob);
		#print_r($midentify);

		switch($midentify['ID_VIDEO_ASPECT']) {
			case '1.7778':
				$arr_ratio = array('640x352', '512x288', '384x208', '256x144');
			break;
		}

		if(count($arr_ratio) > 0) {
			echo "Select an aspect ratio to encode to:\n";
			foreach($arr_ratio as $key => $value) {
				echo " [$key] $value\n";
			}
			echo "Your choice: ";
		}

#$exec = "transcode -a 0 -b 128,0,0 -i $vob -w 2200,250,100 -A -N 0x2000 -M 2 -Y 4,4,4,4 -B 1,11,8 -R 1 -x vob -y xvid4,null $flags -o /dev/null";
		#$exec = "transcode -a 0 -b 128,0,0 -i $vob -w 2200,250,100 -A -N 0x2000 -M 2 -Y 4,4,4,4 -x vob -y xvid4,null $flags -o /dev/null";
		#$exec = "transcode -a 0 -b 128,0,0 -i $vob -w 2200,250,100 -A -N 0x2000 -M 2 -Y 4,4,4,4 -Z 640x360,fast -x vob -y xvid4 $flags -o $avi";
		#$exec = "transcode -a 0 -b 128,0,0 -i $vob -w 2200,250,100 -A -N 0x2000 -M 2 -Y 4,4,4,4 -Z 854x480,fast -x vob -y xvid4 $flags -o $avi";
		#$dvd->executeCommand($exec);
		if(!file_exists($avi) && !file_exists($mkv)) {
			$dvd->transcode($vob, $avi, '-Z 640x360,fast', $mkv);
		}

		if(!file_exists($mkv) && file_exists($avi)) {
			$dvd->createMatroska($avi, $mkv, $txt);
		}

		if(file_exists($mkv)) {
			unlink($vob);
			unlink($avi);
			unlink($txt);
		}

		#print_r($dvd);
		#print_r($chapters);

	}
?>