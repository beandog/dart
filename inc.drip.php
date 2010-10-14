<?
	function display_help() {
	
		shell::msg("Options:");
		shell::msg("  -i, --info\t\tList episodes on DVD");
		
		shell::msg("  --rip\t\t\tRip everything on DVD");
		shell::msg("  --nosub\t\tDon't rip VobSub subtitles");
		shell::msg("  --encode\t\tEncode episodes in queue");
		
		shell::msg("  --archive\t\tAdd DVD to database");
		shell::msg("  --season <int>\tSet season #");
		shell::msg("  --volume <int>\tSet volume #");
		shell::msg("  --disc <int>\t\tSet disc # for season");
		shell::msg("  --series <int>\tPass TV Series ID");
		
		shell::msg("  --demux\t\tUse MEncoder to demux audio and video streams into separate files");
		
		shell::msg("  --skip <int>\t\tSkip # of episodes");
		shell::msg("  --max <int>\t\tMax # of episodes to rip and/or encode");
		shell::msg("  -v, -verbose\t\tVerbose output");
		shell::msg("  --debug\t\tEnable debugging");
		shell::msg("  --update\t\tUpdate DVD specs in database");
		shell::msg("  -q, --queue\t\tList episodes in queue");
		
		shell::msg("Subtitles:");
		shell::msg("  --vobsub\t\tRip and mux VobSub subtitles");
		shell::msg("  --cc\t\t\tRip and mux Closed Captioning subtitles");
		
		shell::msg("Handbrake:");
		shell::msg("  --handbrake\t\tUse Handbrake to reencode video");
		shell::msg("  --preset\t\tEncoding preset to use");
		
		shell::msg("Movies:");
		shell::msg("  --movie\t\tUse some settings to archive as a movie");
		shell::msg("  --title\t\tMovie Title");
	
	}