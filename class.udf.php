<?php

	class UDF {

		// UDF Info
		public $udf_info;
		public $udf_uuid;
		public $udf_blocksize;
		public $udf_blocks;
		public $udf_numfiles;
		public $udf_numdirs;
		public $udf_udfrev;

		function __construct($device = "/dev/bluray", $debug = false) {

			$this->udf_info = array();

			// Get udfinfo
			$arg_device = escapeshellarg($device);
			$cmd = "udfinfo $arg_device";

			if(!$debug)
				$cmd .= " 2> /dev/null";

			if($debug)
				echo "* Executing: $cmd\n";

			exec($cmd, $output, $retval);

			if($retval !== 0 || !count($output)) {
				if($debug)
					echo "* udfinfo FAILED\n";
				return false;
			}

			foreach($output as $str) {
				$arr = explode('=', $str);
				$arr_udfinfo[$arr[0]] = trim($arr[1]);
			}

			extract($arr_udfinfo);

			$udf_info = array(
				'uuid' => $uuid,
				'blocksize' => $blocksize,
				'blocks' => $blocks,
				'numfiles' => $numfiles,
				'numdirs' => $numdirs,
				'udfrev' => $udfrev,
			);

			$this->udf_info = $udf_info;

			return true;

		}

	}
