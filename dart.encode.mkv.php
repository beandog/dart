<?php

	// Mark creating Matroska file as in progress
	$queue_model->set_episode_status($episode_id, 'mkv', 1);

	$matroska->addFile($episode->queue_handbrake_x264);
	$matroska->addGlobalTags($episode->queue_matroska_xml);
	$matroska->setFilename($episode->queue_matroska_mkv);

	file_put_contents($episode->queue_mkvmerge_script, $matroska->getCommandString()." $*\n");
	chmod($episode->queue_mkvmerge_script, 0755);

	exec($matroska->getCommandString()." 2>&1", $mkvmerge_output_arr, $mkvmerge_exit_code);

	$queue_mkvmerge_output = implode("\n", $mkvmerge_output_arr);

	file_put_contents($episode->queue_mkvmerge_output, $queue_mkvmerge_output."\n");
	assert(filesize($episode->queue_mkvmerge_output) > 0);

	$encodes_model->remux_command = $matroska->getCommandString();
	$encodes_model->remux_output = $queue_mkvmerge_output;
	$encodes_model->remux_exit_code = $mkvmerge_exit_code;

	if($mkvmerge_exit_code == 0 || $mkvmerge_exit_code == 1) {

		// Mark episode as successfully muxed
		$queue_model->set_episode_status($episode_id, 'mkv', 2);
		echo "Matroska:\tpassed\n";

	} else {

		// Mark episode as muxing failed
		$queue_model->set_episode_status($episode_id, 'mkv', 3);
		echo "Matroska:\t failed\n";

	}
