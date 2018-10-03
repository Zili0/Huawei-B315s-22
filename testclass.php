<?php
// use the CLI command : php testclass.php
include 'huawei.class.php';

$HU = new huawei("192.168.8.1");

if ($HU->login("UserID", "Password")==FALSE) { echo $HU->LastError. "!\n"; die(); }
//          activate one, or more, functions that you want to try

//if ($HU->reboot()==FALSE)             echo $HU->LastError. "!\n"; die(); 
//if ($ret=$HU->DeviceInformation())    echo $ret."\n"; else { echo $HU->LastError. "!\n"; die(); }
//if ($ret=$HU->CurrentPlmn())          echo $ret."\n"; else { echo $HU->LastError. "!\n"; die(); }
//if ($ret=$HU->TrafficStatistics())    echo $ret."\n"; else { echo $HU->LastError. "!\n"; die(); }
//if ($ret=$HU->ListSmsIn())            echo $ret."\n"; else { echo $HU->LastError. "!\n"; die(); }
//if ($ret=$HU->ListSmsOut())           echo $ret."\n"; else { echo $HU->LastError. "!\n"; die(); }
//if ($ret=$HU->SmsCount())             echo $ret."\n"; else { echo $HU->LastError. "!\n"; die(); }
if ($ret=$HU->Status())             echo $ret."\n"; else { echo $HU->LastError. "!\n"; die(); }
//if ($HU->SendSms("1234567890", "SMS sent via php class")==FALSE) { echo $HU->LastError. "!\n"; die(); }

if ($HU->logout() == FALSE) { echo $HU->LastError. "!\n"; die(); }

echo "\n\nall OK !\n";
$HU=NULL;
?>
