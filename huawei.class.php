<?php
/*
   Done on September 22nd 2018 for the Huawei-B315s-22 modem / router

            DeviceName:         B315s-22
            HardwareVersion:    WL1B310FM01
            SoftwareVersion:    21.328.01.00.983
            WebUIVersion:       17.100.09.00.03

    How to use:

            include 'huawei.class.php';
            $HU = new huawei("192.168.8.1");    //  192.168.8.1 is the modem's IP address
            if ($HU->login("userID", "PassWord")==FALSE) { echo $HU->LastError. "!\n"; die(); } // use the real UserID and PassWord
                 . . . 
                 enter all the necessary calls here
                 . . . 
            if ($HU->logout()==FALSE) { echo $HU->LastError. "!\n"; die(); }
            $HU=NULL;

    Runs on Raspberry pi 3 model B with Raspbian jessie
*/
class huawei
{
    public  $url       = "";
    public  $LastError = "";
    private $ch        = NULL;
    private $SessionID = "";
    private $TokInfo   = "";
    private $ContLen   = 0;
    private $options   = array(
        CURLOPT_RETURNTRANSFER => true,     // return web page
        CURLOPT_HEADER         => FALSE,    // NO return headers in addition to content
        CURLOPT_FOLLOWLOCATION => true,     // follow redirects
        CURLOPT_CONNECTTIMEOUT =>  5,       // timeout on connect
        CURLOPT_TIMEOUT        =>  5,       // timeout on response
        CURLOPT_MAXREDIRS      =>  1,       // stop after 1 redirect
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
    );
    private $firstNonce  = "";  // example: "f0ce57a3413f75627f2fb0748f6c0edfd7ca88f6f9538fe2a3dc0e03bb01f5c0" //  MUST change at every login !
    
    public function __destruct (  ) { if ($this->ch) curl_close( $this->ch ); }
        
    public function __construct($URL="192.168.8.1") { $this->url=$URL;  }
    
    /********************************************
    *       settings for the next POST          *
    ********************************************/
    private function SETOPT($post, $Url)
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->url . $Url);
        curl_setopt($this->ch, CURLOPT_POST, 1);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS,$post);
        curl_setopt($this->ch, CURLOPT_COOKIE, $this->SessionID);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, array( "__RequestVerificationToken: ".$this->TokInfo,
                                                          "Connection: keep-alive"));
    }
    
    /********************************************
    *   Error   Error   Error   Error   Error   *
    ********************************************/
    private function ERROR($f)
    {
        $this->LastError = $f ." error - ". curl_error($this->ch) . " ( ". curl_errno($this->ch)." )"; 
        return FALSE;
    }
    
    /********************************************
    *               Generic GET                 *
    ********************************************/
    private function GET($fun)
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->url . $fun);
        curl_setopt($this->ch, CURLOPT_COOKIE, $this->SessionID);
        curl_setopt($this->ch, CURLOPT_POST, 0);
        $rough_content = curl_exec( $this->ch );
        if ($rough_content==FALSE) return FALSE;
        return substr($rough_content, -$this->ContLen);
    }     

    /********************************************
    *       GET /api/webserver/SesTokInfo       *
    *       Recovery <TokInfo> and <SessionID>  *
    ********************************************/
    private function SesTokInfo()
    {
        $body_content = $this->GET("/api/webserver/SesTokInfo");
        if ($body_content==FALSE) return $this->ERROR(__METHOD__);
        $xmlP=simplexml_load_string($body_content);
        $this->SessionID=$xmlP->SesInfo;
        $this->TokInfo  =$xmlP->TokInfo;
        if (!$this->SessionID || !$this->TokInfo) return $this->ERROR (__METHOD__ ."->NAK");
        return TRUE;
    }

    /********************************************
    *       GET /api/user/state-login           *
    ********************************************/
    private function StateLogin()
    {
        if (!($ret=$this->GET("/api/user/state-login"))) return $this->ERROR(__METHOD__);
        return $ret;
    }     

    /********************************************
    *       GET /api/monitoring/status          *
    ********************************************/
    public function Status()
    {
        if (!($ret=$this->GET("/api/monitoring/status"))) return $this->ERROR(__METHOD__);
        return $ret;
    }     

    /********************************************************************************
    *   login with SCRAM ( Salted Challenge Response Authentication Mechanism )     *
    *                                                                               *
    *       Warning: the session expires after 5 minutes from login                 *
    ********************************************************************************/
    public function login($user, $password)
    {
        $this->ch = curl_init(); 
        $ch=$this->ch;
        curl_setopt_array( $ch, $this->options );
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($CH,$header) {
            if (substr($header,0,27) == '__RequestVerificationToken:')  $this->TokInfo  =     trim(substr($header,27)); else
            if (substr($header,0,11) == 'Set-Cookie:')                  $this->SessionID=     trim(substr($header,11)); else
            if (substr($header,0,15) == 'Content-Length:')              $this->ContLen  =(int)trim(substr($header,15));
            return strlen($header);
            });
            
        if ($this->SesTokInfo() == FALSE) return FALSE;
        
        /*------------------------------------------+
        |       Now I create a new firstNonce       |
        +------------------------------------------*/

        $ctx = hash_init('sha256');
        hash_update($ctx, time().date("-m-d H:i:s"));   $this->firstNonce=hash_final($ctx);
        
        /*------------------------------------------+
        |       POST /api/user/challenge_login      |
        |       This POST changes the TokInfo !     |
        +------------------------------------------*/

        $post="<?xml version='1.0' encoding='UTF-8'?>".
                    "<request>".
                    "<username>".$user."</username>".
                    "<firstnonce>".$this->firstNonce."</firstnonce>".
                    "<mode>1</mode>".
                    "</request>";

        $this->SETOPT($post, "/api/user/challenge_login");
        $rough_content = curl_exec( $ch );
        if ($rough_content==FALSE) return $this->ERROR(__METHOD__ . "->challenge_login");
        $body_content   = substr($rough_content, -$this->ContLen);
        $xmlP           = simplexml_load_string($body_content);
        $salt           =       $xmlP->salt;
        $servernonce    =       $xmlP->servernonce;
        $iter           = (int) $xmlP->iterations;  //  always = 100

        /*------------------------------------------+
        |       Calculating the clientproof         |
        +------------------------------------------*/

        $authMsg = $this->firstNonce . "," . $servernonce . "," . $servernonce;

        $ctx = hash_init('sha256');
        $saltPassword = hash_pbkdf2('sha256', $password, hex2bin($salt), $iter, 0, TRUE);
        $clientKey    = hash_hmac  ('sha256', $saltPassword, "Client Key", TRUE);
                        hash_update($ctx,     $clientKey);
        $storedkey    = hash_final ($ctx, TRUE);
        $signature    = hash_hmac  ('sha256', $storedkey, $authMsg, TRUE);

        for($i = 0; $i < strlen($clientKey); $i++) $clientKey[$i] = $clientKey[$i] ^ $signature[$i];
        $clientproof=bin2hex($clientKey);

        /*------------------------------------------+
        |   POST api/user/authentication_login      |
        |   This POST changes the SessionID cookie  |
        +------------------------------------------*/

        $post="<?xml version='1.0' encoding='UTF-8'?>".
                    "<request>".
                    "<clientproof>" . $clientproof . "</clientproof>".
                    "<finalnonce>"  . $servernonce . "</finalnonce>".
                    "</request>";
                    
        $this->SETOPT($post, "/api/user/authentication_login");
        $rough_content = curl_exec( $ch );
        if ($rough_content==FALSE) return $this->ERROR(__METHOD__ . "->authentication_login");

        /*------------------------------------------+
        |       GET /api/user/state-login           |
        +------------------------------------------*/
        $body_content = $this->StateLogin();
        if ($body_content==FALSE) return FALSE;

        $xmlP=simplexml_load_string($body_content);
        if ($xmlP->State != "0" || $xmlP->Username != $user) return $this->ERROR(__METHOD__ ."->StateLogin NAK");

        return TRUE;
    }
    
    /********************************************
    *       GET /api/device/information         *
    ********************************************/
    public function DeviceInformation  ()
    {   
        if (!($ret=$this->GET("/api/device/information"))) return $this->ERROR(__METHOD__);
        return $ret;
    }
        
    /********************************************
    *           GET /api/net/current-plmn       *
    ********************************************/
    public function CurrentPlmn  ()
    {   
        if (!($ret=$this->GET("/api/net/current-plmn"))) return $this->ERROR(__METHOD__);
        return $ret;
    }
        
    /********************************************
    *   GET /api/monitoring/traffic-statistics  *
    ********************************************/
    public function TrafficStatistics  ()
    {   
        if (!($ret=$this->GET("/api/monitoring/traffic-statistics"))) return $this->ERROR(__METHOD__);
        return $ret;
    }
        
    /********************************************
    *           GET /api/sms/sms-count          *
    ********************************************/
    public function SmsCount ()
    {       
        if (!($ret=$this->GET("/api/sms/sms-count"))) return $this->ERROR(__METHOD__);
        return $ret;
    }

    /********************************************
    *           POST /api/sms/sms-list          *
    ********************************************/
    private function SmsList ($InOut)
    {       
        $post=  "<?xml version='1.0' encoding='UTF-8'?>\n".
                "<request>\n".
                "<PageIndex>1</PageIndex>\n".
                "<ReadCount>20</ReadCount>\n".
                "<BoxType>".$InOut."</BoxType>\n".  //   1=SMS received   |   2=SMS sent
                "<SortType>0</SortType>\n".
                "<Ascending>0</Ascending>\n".
                "<UnreadPreferred>0</UnreadPreferred>\n".
                "</request>\n";

        $this->SETOPT($post, "/api/sms/sms-list");
        $rough_content = curl_exec( $this->ch );
        if ($rough_content==FALSE) return $this->ERROR(__METHOD__);

        return substr($rough_content, -$this->ContLen);
    }
    /********************************************
    *           List of received SMS            *
    ********************************************/
    public function ListSmsIn () {  return $this->SmsList("1"); }
    
    /********************************************
    *               List of SMS sent            *
    ********************************************/
    public function ListSmsOut () { return $this->SmsList("2"); }
    
    /********************************************
    *           POST /api/sms/send-sms          *
    ********************************************/
    public function SendSms ($to, $testo)
    {       
        $post=  "<?xml version='1.0' encoding='UTF-8'?>\n".
                "<request>\n".
                "<Index>-1</Index>\n".
                "<Phones>\n".
                "<Phone>".$to."</Phone>\n".
                "</Phones>\n".
                "<Sca></Sca>\n".
                "<Content>".$testo."</Content>\n".
                "<Length>".strlen($testo)."</Length>\n".
                "<Reserved>1</Reserved>\n".
                "<Date>".date("Y-m-d H:i:s")."</Date>\n".
                "</request>\n";

        $this->SETOPT($post, "/api/sms/send-sms");
        $rough_content = curl_exec( $this->ch );
        if ($rough_content==FALSE) return $this->ERROR(__METHOD__);
        if (strpos($rough_content, "<response>OK</response>")==FALSE) return $this->ERROR(__METHOD__ ."->NAK");
        return substr($rough_content, -$this->ContLen);
    }

    /********************************************
    *           POST /api/sms/delete-sms        *
    ********************************************/
    public function DeleteSms ($index)
    {       
        $post=  "<?xml version='1.0' encoding='UTF-8'?>\n".
                "<request>\n".
                "<Index>".$index."</Index>\n".
                "</request>\n";

        $this->SETOPT($post, "/api/sms/delete-sms");
        $rough_content = curl_exec( $this->ch );
        if ($rough_content==FALSE) return $this->ERROR(__METHOD__);
        if (strpos($rough_content, "<response>OK</response>")==FALSE) return $this->ERROR(__METHOD__ ."->NAK");
        return substr($rough_content, -$this->ContLen);
    }

    /********************************************
    *       POST /api/device/control reboot     *
    ********************************************/
    public function reboot ()
    {
        $post=  "<?xml version='1.0' encoding='UTF-8'?>\n".
                "<request>\n".
                "<Control>1</Control>\n".   
                "</request>\n";

        $this->SETOPT($post, "/api/device/control");
        $rough_content = curl_exec( $this->ch );
        if ($rough_content==FALSE) return $this->ERROR(__METHOD__);
        if (strpos($rough_content, "<response>OK</response>")==FALSE) return $this->ERROR(__METHOD__ ."->NAK");
        curl_close( $this->ch ); $this->ch=NULL;
        return substr($rough_content, -$this->ContLen);
    }

    /********************************************
    *           POST /api/user/logout           *
    ********************************************/
    public function logout ()
    {
        $post=  "<?xml version='1.0' encoding='UTF-8'?>\n".
                "<request>\n".
                "<Logout>1</Logout>\n".
                "</request>\n";

        $this->SETOPT($post, "/api/user/logout");
        $rough_content = curl_exec( $this->ch );
        if ($rough_content==FALSE) return $this->ERROR(__METHOD__);
        if (strpos($rough_content, "<response>OK</response>")==FALSE) return $this->ERROR(__METHOD__ .":NAK");
        curl_close( $this->ch ); $this->ch=NULL;
        return TRUE;
    }
}
?>
