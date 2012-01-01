<?php
require_once 'Console/ProgressBar.php';
$bar = new Console_ProgressBar('[%bar%] %percent%', ':', ' :D ', 80, 5);

//do some processing here
for ($i = 0; $i <= 5; $i++) {
    $bar->update($i);
    sleep(1);
}

$bar->update(2);

echo "\n";
?> 