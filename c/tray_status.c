#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/ioctl.h>
#include <fcntl.h>
#include <linux/cdrom.h>

/**
 * tray_status.c
 * Get the status of the disc tray
 * Last updated: 2014-01-27
 *
 * - Returns an exit code similar to CDROM_DRIVE_STATUS in cdrom.h
 */
int main(int argc, char **argv) {

	int cdrom;
	int drive_status;
	char* device;
	char* status;

	if(argc == 1)
		device = "/dev/sr0";
	else
		device = argv[1];
	
	cdrom = open(device, O_RDONLY | O_NONBLOCK);
	drive_status = ioctl(cdrom, CDROM_DRIVE_STATUS);
	close(cdrom);

	switch(drive_status) {
		case 1:
			status = "no disc";
			break;
		case 2:
			status = "tray open";
			break;
		case 3:
			status = "drive not ready";
			break;
		case 4:
			status = "drive ready";
			break;
		default:
			status = "no info";
			break;
	}

	printf("%s\n", status);

	exit(drive_status);
}
