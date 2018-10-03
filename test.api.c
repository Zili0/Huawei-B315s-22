/*  Done on 1° Ottobre 2018
 * 
 *  To install libssl use:  apt-get install libssl-dev
 * 
 *  Compile  with [ gcc -Wall -Wno-unused-function -o test.api.exe test.api.c -lssl -lcrypto -lcurl ]
*/
#include <stdio.h>
#include "huawei.api.c"

int main()
{
	if (!login("UserID", "Password")) { printf("Login error\n%s",Buff); return 0; } else printf("login OK\n\n");

//          activate one, or more, functions that you want to try
    
    printf ("\nTrafficStatistics = \n%s\n",     TrafficStatistics());
//  printf ("\nCurrentPlmn = \n%s\n",           CurrentPlmn());
//  printf ("\nDeviceInformation = \n%s\n",     DeviceInformation());
    printf ("\nSmsCount = \n%s\n",              SmsCount());
//  printf ("\nDeleteSms = \n%s\n",             DeleteSms(40001));
//  printf ("\nSendSms = \n%s\n",               SendSms("1234567890","Testo dell'SMS"));
//  printf ("\nListSmsIn = \n%s\n",             ListSmsIn());
//  printf ("\nListSmsOut = \n%s\n",            ListSmsOut());
//  printf ("\nreboot = \n%s\n",                reboot());  return 0;
    
    if (!logout()) { printf("Logout error\n"); return 0; }
    printf ("\n\tTutto OK !\n");
    return 0;
}
