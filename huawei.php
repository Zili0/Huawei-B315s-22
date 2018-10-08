<?php
include 'huawei.class.php';
$HU = new huawei("192.168.8.1");
$ret=""; $com="";
if (isset ($_POST['comando'])) { $com=$_POST['comando'];
    switch ($_POST['comando']) {
        case 'reboot':   $ret=shell_exec("sudo reboot");            break;
        case 'shutdown': $ret=shell_exec("sudo shutdown -h now");   break;
        case 'ifconfig': $ret=shell_exec("/sbin/ifconfig");         break;
        case 'crontab':  $ret=shell_exec("sudo crontab -l");        break;
        case 'WiFi':     $ret=shell_exec(
                            "sudo iwlist wlan0 scan | ".
                            "grep 'ESSID\|Quality=' | ".
                            "sed 's/^[ \\t]*//;s/[ \\t]*$//;s/\"//g' | ".
//                          "awk '{ if (substr($0,1,5)==\"ESSID\") printf \"\t%s\\n\",substr($0,7); else printf \"\t%s\",substr($0,9,5);}' | ".
                            "awk '{ if (substr($0,1,5)==\"ESSID\") printf \"\t%s\\n\",substr($0,7); ".
                                                             "else printf \"\t%s ( %s dBm )\",substr($0,9,5),substr($3,7);}' | ".
                            "grep -v x00 | ".
                            "sort -r");
                            break;
    }
    if (!$ret){ 
    if ($HU->login("UserID", "PassWord")==FALSE) $ret=$HU->LastError;
    else {
    switch ($_POST['comando']) {
        case 'DeviceInformation':   if (!($ret=$HU->DeviceInformation()))   $ret=$HU->LastError;    break;
        case 'Status':              if (!($ret=$HU->Status()))              $ret=$HU->LastError;    break;
        case 'CurrentPlmn':         if (!($ret=$HU->CurrentPlmn()))         $ret=$HU->LastError;    break;
        case 'TrafficStatistics':   if (!($ret=$HU->TrafficStatistics()))   $ret=$HU->LastError;    break;
        case 'ListSmsIn':           if (!($ret=$HU->ListSmsIn()))           $ret=$HU->LastError;    break;
        case 'ListSmsout':          if (!($ret=$HU->ListSmsout()))          $ret=$HU->LastError;    break;
        case 'SmsCount':            if (!($ret=$HU->SmsCount()))            $ret=$HU->LastError;    break;
        case 'WiFi On':             if (!($ret=$HU->wifiON()))              $ret=$HU->LastError;    break;
        case 'WiFi Off':            if (!($ret=$HU->wifiOFF()))             $ret=$HU->LastError;    break;
        case 'WiFi Settings':       if (!($ret=$HU->wifiSetting()))         $ret=$HU->LastError;    break;
        case 'Reboot':              if (!($ret=$HU->reboot()))              $ret=$HU->LastError;    break;
        case 'SendSms': 
            if ($_POST['tocli']!='' && $_POST['testo']!='') {
                if (!($ret=$HU->SendSms($_POST['tocli'], $_POST['testo']))) $ret=$HU->LastError;
            }
            else $com='';
            break;
        case 'DeleteSms': 
            if ($_POST['tocli']!='') { if (!($ret=$HU->DeleteSms($_POST['tocli']))) $ret=$HU->LastError; }
            else $com='';
            break;
            
    }
    $HU->logout();
    }
}
}
$HU=NULL;
?>
<!DOCTYPE html>
<!-- 
    Done on 24 September 2018
-->
<html>
<head>
<link rel="icon" type="image/gif" href="huawei.gif" />
<title>Huawei B315s-22</title>
<style type="text/css">
body { margin:0; padding:0; background: #e7e7e7; font-family: Verdana, sans-serif; font-size: 8pt;}
.comandi td { font-weight: bold; padding:0 12px 0 12px; vertical-align:middle; height: 24px; cursor:pointer; border:solid blue 2px;
               border-radius:8px; box-shadow:3px 3px 2px #000; text-align:center; background: #d7d7d7; }
.comandi td:hover { background: #FC0; }
#risu { font-family:monospace; padding-left:5px; font-style:italic; font-size: 8pt; border-radius:8px; box-shadow:3px 3px 2px #000;}
#ff { display:none; }
#II { color:blue; letter-spacing:2px; margin: 5px 0 10px 0; font-weight: bold; height: 16px; font-size:12pt;}
.ii {color:blue; margin: 0px 0 5px 0; font-weight: bold; font-style:italic;}
.rosso { color:red; }
#hu td, #lx td { width:130px; }
#risu { background: #efefef; }
</style>
<script type='text/javascript'><!--
    function invia(evt) {
        var obj=evt.target;
        ffc.value=obj.innerText;
        ff.submit();
    }
    function sendsms(evt) {
        ffto.value=prompt ("Numero del cellulare");
        if (ffto.value!='') fftx.value=prompt ("testo dell'SMS");
        return;
    }
    function delesms(evt) {
        ffto.value=prompt ("SMS index ( >=40000 )");
        return;
    }
--></script>
</head>
<body>
    <br>
    <table align=center>
        <caption id=II align=center><?php echo $com; ?></caption>
        <tr>
            <td valign=top>
                <table id=hu class=comandi onclick='invia(event);' cellspacing=8>
                    <caption class=ii>Huawei B315s-22</caption>
                    <tr><td>DeviceInformation</td></tr>
                    <tr><td>Status</td></tr>
                    <tr><td>CurrentPlmn</td></tr>
                    <tr><td>TrafficStatistics</td></tr>
                    <tr><td>WiFi On</td></tr>
                    <tr><td>WiFi Off</td></tr>
                    <tr><td>WiFi Settings</td></tr>
                    <tr><td>ListSmsIn</td></tr>
                    <tr><td>ListSmsout</td></tr>
                    <tr><td>SmsCount</td></tr>
                    <tr><td onclick='sendsms(event)'>SendSms</td></tr>
                    <tr><td onclick='delesms(event)'>DeleteSms</td></tr>
                    <tr><td class=rosso>Reboot</td></tr>
                </table>
            </td>
            <td><textarea id=risu rows=40 cols=80><?php echo $ret; ?></textarea></td>
            <td valign=top>
                <table id=lx class=comandi onclick='invia(event);' cellspacing=8>
                    <caption class=ii>Raspberry</caption>
                    <tr><td>ifconfig</td></tr>
                    <tr><td>crontab</td></tr>
                    <tr><td>WiFi</td></tr>
                    <tr><td class=rosso>reboot</td></tr>
                    <tr><td class=rosso>shutdown</td></tr>
                </table>
            </td>
        </tr>
    </table>
    <form id=ff method='POST' action='huawei.php'>
        <input id=ffc  type=hidden name=comando value=''>
        <input id=ffto type=hidden name=tocli   value=''>
        <input id=fftx type=hidden name=testo   value=''>
    </form>
</body>
</html> 
