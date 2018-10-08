/*
   Done on October 1nd 2018 for the Huawei-B315s-22 modem / router

            DeviceName:         B315s-22
            HardwareVersion:    WL1B310FM01
            SoftwareVersion:    21.328.01.00.983
            WebUIVersion:       17.100.09.00.03

    How to use:

            #include <stdio.h>
            #include "huawei.api.c"
            . . .
            int main()
            {
                if (!login("UserID", "PassWord")) { printf("Login error\n%s",Buff); return 0; } else printf("login OK\n\n");

            //          activate one, or more, functions that you want to try

            //  printf ("\nTrafficStatistics = \n%s\n",     TrafficStatistics());
            //  printf ("\nCurrentPlmn = \n%s\n",           CurrentPlmn());
            //  printf ("\nDeviceInformation = \n%s\n",     DeviceInformation());
                printf ("\nSmsCount = \n%s\n",              SmsCount());
            //  printf ("\nDeleteSms = \n%s\n",             DeleteSms(40001));
            //  printf ("\nSendSms = \n%s\n",               SendSms("1234567890","Testo dell'SMS"));
            //  printf ("\nListSmsIn = \n%s\n",             ListSmsIn());
            //  printf ("\nListSmsOut = \n%s\n",            ListSmsOut());
            //  printf ("\nWiFiOnOff  = \n%s\n",            WiFiOnOff(1,0));
            //  printf ("\nreboot = \n%s\n",                reboot());  return 0;
        
                if (!logout()) { printf("Logout error\n"); return 0; }
                printf ("\n\tTutto OK !\n");
                return 0;
            }

    Runs on Raspberry pi 3 model B with Raspbian jessie
*/
#include <openssl/ssl.h>
#include <curl/curl.h>
#include <time.h>

#define FALSE 0
#define TRUE  1

#define MODEM "192.168.8.1"  //  set the real IP address of the modem here

static CURL     *ch;
static CURLcode res;
static struct curl_slist *headers=NULL;

static int  ContLen=0;
static char SessionID[1024]={0};

#define BuffSize 10240

static  char Buff[BuffSize];
typedef struct {  char *memory;  size_t size; }MemoryStruct;
static  MemoryStruct chunk = {Buff, 0};

static  char Token[36][34+27] = {{0}};  //  07.10.2018
#define sTok sizeof(Token[0])
#define nTok (sizeof(Token)/sTok)

/*------------------------------------------+
|       bin2hex     bin2hex     bin2hex     |
+------------------------------------------*/
static  char *bin2hex(unsigned char *s, long L)
{
    static  char hex[2048];
    long i,l=0;
    for (i=0; i<L; i++) l+=sprintf(&hex[l], "%02x", 0xFF & (*(s+i)));
    hex[l]=0;
    return hex;
}
/*------------------------------------------+
|       hex2bin     hex2bin     hex2bin     |
+------------------------------------------*/
static  char *hex2bin( char *s)
{
    static  char bin[2048];
    unsigned int i,e,l=0,L=strlen(s);
    for (i=0; i<L; i+=2) { sscanf(s+i, "%02x",&e); bin[l++]=(char)e; }
    bin[l]=0;
    return bin;
}
/*------------------------------------------+
|               Generic GET                 |
+------------------------------------------*/
static int GET(char *Url)
{
    char URL[128];
//  printf("\nGET  -------------> %s\n", Url);
    sprintf (URL, MODEM "%s", Url);
    curl_easy_setopt(ch, CURLOPT_URL, URL);
    curl_easy_setopt(ch, CURLOPT_COOKIE, SessionID);
    curl_easy_setopt(ch, CURLOPT_POST, 0);
    chunk.size = 0;
    res = curl_easy_perform(ch);
    if (res != CURLE_OK) return FALSE;
    return TRUE;
}

/*------------------------------------------+
|               Generic POST                |
+------------------------------------------*/
static int POST(char *post, char *Url)
{
    char URL[128];
//  printf("\nPOST ---------------> %s\n", Url);
    sprintf (URL, MODEM "%s", Url);
    curl_easy_setopt(ch, CURLOPT_URL, URL);
    curl_easy_setopt(ch, CURLOPT_POST, 1);
    curl_easy_setopt(ch, CURLOPT_POSTFIELDS, post);
    curl_easy_setopt(ch, CURLOPT_COOKIE, SessionID);
    headers=NULL;
    headers = curl_slist_append(headers, Token[0]);
    headers = curl_slist_append(headers, "Connection: keep-alive");
    curl_easy_setopt(ch, CURLOPT_HTTPHEADER, headers);
    chunk.size = 0;

    res = curl_easy_perform(ch);
    if (res != CURLE_OK)  return FALSE;
    curl_slist_free_all(headers);
    curl_easy_setopt(ch, CURLOPT_HTTPHEADER, NULL);
    return TRUE;
}

/*------------------------------------------+
|       GET /api/webserver/SesTokInfo       |
|       Recovery <TokInfo> and <SessionID>  |
+------------------------------------------*/
static int SesTokInfo()
{
    char *i,*f;

    if (!GET("/api/webserver/SesTokInfo")) return FALSE;

    i=strstr(Buff,"<SesInfo>"); f=strstr(Buff,"</SesInfo>");
    if (!i || !f) return FALSE;
    sprintf(SessionID, "%*.*s", f-i-9, f-i-9, i+9);
    i=strstr(Buff,"<TokInfo>"); f=strstr(Buff,"</TokInfo>");
    if (!i || !f) return FALSE;
    sprintf(Token[0], "__RequestVerificationToken:%*.*s", f-i-9, f-i-9, i+9);
    return TRUE;
}

/*----------------------------------------+
|           WriteMemoryCallback           |
+----------------------------------------*/
static size_t WriteMemoryCallback(void *contents, size_t size, size_t nmemb, void *userp)
{
  size_t realsize = size * nmemb;
  MemoryStruct *mem = ( MemoryStruct *)userp;

  if ((mem->size+realsize) >= BuffSize) return realsize; // ! too much data !

  memcpy(&(mem->memory[mem->size]), contents, realsize);
  mem->size += realsize;
  mem->memory[mem->size] = 0;
  return realsize;
}
/*----------------------------------------+
|           WriteHeaderCallback           |
+----------------------------------------*/
static size_t WriteHeaderCallback(void *contents, size_t size, size_t nmemb, void *userp)
{
   char *p=contents;
   size_t realsize = size * nmemb;
// printf ("> %s",p);
   if (!memcmp(p, "Content-Length:", 15))               ContLen=atoi(p+15); else
   if (!memcmp(p, "Set-Cookie:", 11))                   sprintf(SessionID,  "%*.*s", realsize-2-11, realsize-2-11, (p+11)); else
   if (!memcmp(p, "__RequestVerificationToken:", 27))
   {   int  i; char *t;
       t=strtok(p+27, "#");
       for (i=0;t && i<nTok;i++) { sprintf(Token[i], "__RequestVerificationToken:%32.32s", t); t=strtok(NULL, "#"); }
   }

  return realsize;
}

/********************************************************************************
*   login with SCRAM ( Salted Challenge Response Authentication Mechanism )     *
*                                                                               *
*       Warning: the session expires after 5 minutes from login                 *
********************************************************************************/
int login(char *user, char *password)
{
    char *i, *f;
    unsigned int La,j;
    unsigned char   firstNonce  [SHA256_DIGEST_LENGTH],
                    salt        [SHA256_DIGEST_LENGTH],
                    saltPassword[SHA256_DIGEST_LENGTH],
                    storedkey   [SHA256_DIGEST_LENGTH],
                    clientproof [SHA256_DIGEST_LENGTH],
                    clientKey   [SHA256_DIGEST_LENGTH],
                    signature   [SHA256_DIGEST_LENGTH];
    char authMsg    [2048];
    char servernonce[1024];
    char post       [2048];
    time_t rawtime = time(NULL);
    struct timespec TT;
    SHA256_CTX ctx;

    memset(Token, 0, sizeof(Token));    //  07.10.2018

    /*----------------------------------+
    |          Initialize curl          |
    +----------------------------------*/
    curl_global_init(CURL_GLOBAL_ALL);
    ch = curl_easy_init();   // init the curl session
    curl_easy_setopt(ch, CURLOPT_HEADERFUNCTION, WriteHeaderCallback);
    curl_easy_setopt(ch, CURLOPT_WRITEFUNCTION,  WriteMemoryCallback);
    curl_easy_setopt(ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_easy_setopt(ch, CURLOPT_TIMEOUT,        5);
    curl_easy_setopt(ch, CURLOPT_WRITEDATA, (void *)&chunk);
    curl_easy_setopt(ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

    if (!SesTokInfo()) return FALSE;

    /*----------------------------------+
    |       random  firstNonce          |
    +----------------------------------*/
    clock_gettime(CLOCK_MONOTONIC, &TT);
    SHA256_Init(&ctx);
    SHA256_Update(&ctx, ctime(&rawtime),                         SHA256_DIGEST_LENGTH);
    SHA256_Update(&ctx, bin2hex((unsigned char*)&TT,sizeof(TT)), SHA256_DIGEST_LENGTH);
    SHA256_Final(firstNonce, &ctx);

    /*------------------------------------------+
    |       POST /api/user/challenge_login      |
    |       This POST changes the TokInfo !     |
    +------------------------------------------*/

    sprintf(post,   "<?xml version='1.0' encoding='UTF-8'?>\n"
                    "<request>\n"
                    "<username>%s</username>\n"
                    "<firstnonce>%s</firstnonce>\n"
                    "<mode>1</mode>\n"
                    "</request>", user, bin2hex(firstNonce, SHA256_DIGEST_LENGTH));

    if (!POST(post, "/api/user/challenge_login")) return FALSE;

    i=strstr(Buff,"<salt>"); f=strstr(Buff,"</salt>");
    if (!i || !f) return FALSE;
    sprintf(post, "%*.*s", f-i-6, f-i-6, i+6); memcpy(salt, hex2bin(post), SHA256_DIGEST_LENGTH);

    i=strstr(Buff,"<servernonce>"); f=strstr(Buff,"</servernonce>");
    if (!i || !f) return FALSE;
    sprintf(servernonce, "%*.*s", f-i-13, f-i-13, i+13);

    La=sprintf(authMsg, "%s,%s,%s", bin2hex(firstNonce, SHA256_DIGEST_LENGTH), servernonce, servernonce);

    PKCS5_PBKDF2_HMAC(password, strlen(password), (const unsigned char*)salt, SHA256_DIGEST_LENGTH, 100, EVP_sha256(), SHA256_DIGEST_LENGTH, saltPassword);
    HMAC(EVP_sha256(), (const unsigned char *)"Client Key", 10, saltPassword, SHA256_DIGEST_LENGTH, clientKey, &j);
    SHA256_Init(&ctx);
    SHA256_Update(&ctx, clientKey, SHA256_DIGEST_LENGTH);
    SHA256_Final(storedkey, &ctx);
    HMAC(EVP_sha256(), (const unsigned char *)authMsg, La, storedkey, SHA256_DIGEST_LENGTH, signature, &j);
    for (j=0;j<SHA256_DIGEST_LENGTH; j++) clientproof[j] = clientKey[j] ^ signature[j];

    sprintf (post, "<?xml version='1.0' encoding='UTF-8'?>\n"
                    "<request>\n"
                    "<clientproof>%s</clientproof>\n"
                    "<finalnonce>%s</finalnonce>\n"
                    "</request>\n",bin2hex(clientproof, SHA256_DIGEST_LENGTH), servernonce);

    if (!POST(post, "/api/user/authentication_login"))      return FALSE; // reset Token
    if (!GET("/api/user/state-login"))                      return FALSE;
    if (!strstr(Buff, "<State>0</State>"))                  return FALSE;
    return TRUE;
}

/********************************************
*   GET /api/monitoring/traffic-statistics  *
********************************************/
static char * TrafficStatistics  ()
{
    if (!GET("/api/monitoring/traffic-statistics")) return FALSE;
    return Buff;
}

/********************************************
*           GET /api/net/current-plmn       *
********************************************/
static char * CurrentPlmn  ()
{
    if (!GET("/api/net/current-plmn")) return FALSE;
    return Buff;
}
/********************************************
*       GET /api/device/information         *
********************************************/
static char * DeviceInformation  ()
{
    if (!GET("/api/device/information")) return FALSE;
    return Buff;
}
/********************************************
*           GET /api/sms/sms-count          *
********************************************/
static char * SmsCount ()
{
    if (!GET("/api/sms/sms-count")) return FALSE;
    return Buff;
}

/********************************************
*       POST /api/device/control reboot     *
********************************************/
static char * reboot ()
{
    char *post= "<?xml version='1.0' encoding='UTF-8'?>\n"
                "<request>\n"
                "<Control>1</Control>\n" // 1=reboot | 2=Reset | 3=nothing
                "</request>\n";

    if (!POST(post, "/api/device/control")) return FALSE;

    curl_easy_cleanup(ch);
    curl_global_cleanup();
    ch=NULL;

    if (!strstr(Buff, "<response>OK</response>")) return FALSE;
    return Buff;
}

/********************************************
*           POST /api/sms/sms-list          *
********************************************/
static char * SmsList (int InOut)
{
    char post[256]={0};
    sprintf(post,"<?xml version='1.0' encoding='UTF-8'?>\n"
            "<request>\n"
            "<PageIndex>1</PageIndex>\n"
            "<ReadCount>20</ReadCount>\n"
            "<BoxType>%d</BoxType>\n"     //   1=SMS received   |   2=SMS sent
            "<SortType>0</SortType>\n"
            "<Ascending>0</Ascending>\n"
            "<UnreadPreferred>0</UnreadPreferred>\n"
            "</request>\n",InOut);

    if (!POST(post, "/api/sms/sms-list")) return FALSE;
    return Buff;
}

/********************************************
*           List of received SMS            *
********************************************/
static char * ListSmsIn () {  return SmsList(1); }

/********************************************
*               List of SMS sent            *
********************************************/
static char * ListSmsOut () { return SmsList(2); }

/********************************************
*           POST /api/sms/delete-sms        *
********************************************/
static char * DeleteSms (int index)
{
    char post[128];
    sprintf (post, "<?xml version='1.0' encoding='UTF-8'?>\n"
                    "<request>\n"
                    "<Index>%d</Index>\n"
                    "</request>\n", index);

    if (!POST(post, "/api/sms/delete-sms"))       return FALSE;
    if (!strstr(Buff, "<response>OK</response>")) return FALSE;
    return Buff;
}

/********************************************
*           POST /api/sms/send-sms          *
********************************************/
static char * SendSms (char *to, char *testo)
{
    char post[1024];
    time_t T = time(NULL);
    struct tm O = *localtime (&T);

    sprintf (post,"<?xml version='1.0' encoding='UTF-8'?>\n"
            "<request>\n"
            "<Index>-1</Index>\n"
            "<Phones>\n"
            "<Phone>%s</Phone>\n"
            "</Phones>\n"
            "<Sca></Sca>\n"
            "<Content>%s</Content>\n"
            "<Length>%d</Length>\n"
            "<Reserved>1</Reserved>\n"
            "<Date>%04d-%02d-%02d %02d:%02d:%02d</Date>\n"
            "</request>\n", to, testo, strlen(testo),
            O.tm_year+1900, O.tm_mon+1, O.tm_mday, O.tm_hour, O.tm_min, O.tm_sec);

    if (!POST(post, "/api/sms/send-sms"))         return FALSE;
    if (!strstr(Buff, "<response>OK</response>")) return FALSE;
    return Buff;
}

/********************************************   08.10.2018
*    GET /api/wlan/multi-basic-settings     *
********************************************/
static char * WiFiSetting ()
{
   char *p;
   if (!GET("/api/wlan/multi-basic-settings"))  return FALSE;
   if (!(p=strstr(Buff, "<?xml version=")))     return FALSE;
   return &Buff[p-Buff];
}

/********************************************   08.10.2018
*   POST /api/wlan/multi-basic-settings     *
********************************************/
static char * WiFiOnOff (int onoff, int index)
{
    char * body, *ssid, *i, *f;
    char post[1024];
    char ix[64];
    int  L=0;

    if (!(body=WiFiSetting()))     return FALSE;
    sprintf(ix,"<Index>%d</Index>", index);
    if (!(ssid=strstr(body, ix)))  return FALSE;

    L=sprintf(post, "<?xml version='1.0' encoding='UTF-8'?>\n"
                    "<request>\n"
                    "<Ssids>\n"
                    "<Ssid>\n"
                    "%s\n"
                    "<WifiEnable>%d</WifiEnable>\n", ix, onoff);

    if (!(i=strstr(ssid,"<WifiSsid>")) || !(f=strstr(ssid,"</WifiSsid>")))         return FALSE;
    L+=sprintf(&post[L],"%*.*s\n", f-i+11, f-i+11, i);

    if (!(i=strstr(ssid,"<WifiAuthmode>")) || !(f=strstr(ssid,"</WifiAuthmode>"))) return FALSE;
    L+=sprintf(&post[L],"%*.*s\n", f-i+15, f-i+15, i);

    L+=sprintf(&post[L],"</Ssid>\n"
                        "</Ssids>\n"
                        "<WifiRestart>1</WifiRestart>\n"
                        "</request>");
    strcpy(ix, Token[0]);
    strcpy(Token[0], Token[5]);
    L=POST(post, "/api/wlan/multi-basic-settings");
    strcpy(Token[0], ix);
    if (!L)                                         return FALSE;
    if (!strstr(Buff,"<response>OK</response>"))    return FALSE;

    return Buff;
}

/********************************************   08.10.2018
*                WiFi ON  OFF               *
********************************************/
static char * wifiON()  { return WiFiOnOff(1,0); }

static char * wifiOFF() { return WiFiOnOff(0,0); }

/********************************************
*           POST /api/user/logout           *
********************************************/
static int logout ()
{
    char *post =    "<?xml version='1.0' encoding='UTF-8'?>\n"
                    "<request>\n"
                    "<Logout>1</Logout>\n"
                    "</request>\n";

    if (!POST(post, "/api/user/logout")) return FALSE;
    curl_easy_cleanup(ch);
    curl_global_cleanup();
    ch=NULL;

    if (!strstr(Buff, "<response>OK</response>")) return FALSE;

    return TRUE;
}

