all:
install:
	doas ln -sf `realpath dart.php` /usr/local/bin/dart
	doas ln -sf `realpath emo.php` /usr/local/bin/emo
	doas ln -sf `realpath videoinfo` /usr/local/bin/
	doas ln -sf `realpath utils/dvd_ddrescue` /usr/local/bin/
	doas ln -sf `realpath utils/ffjson` /usr/local/bin/
	doas ln -sf `realpath utils/mjson` /usr/local/bin/
