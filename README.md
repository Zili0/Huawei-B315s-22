# Huawei-B315s-22
PHP and C/C++ API for Huawei-B315s-22 modem/router. 
### About
I have this modem installed in a **_unattended_** site. It use a SIM for internet connection. 

A Raspberry server, various cameras and some sensors are connected to the LAN.
In this situation it is very important for me to be able to control the server via SMS.
These APIs allow the Raspberry to access incoming SMS messages via the modem.
Many other features are also available, such as:
- sending a text message
- reboot of the modem
- delete incoming / outgoing SMS
#### Model and firmware info

| Device name:      | B315s-22         |
| :---              | :---             |
| Hardware version: | WL1B310FM01      |
| Software version: | 21.328.01.00.983 |
| Web UI version:   | 17.100.09.00.03  |
#### Warning
The software has been tried, and it works, on the **Raspberry pi 3 model B with Raspbian Jessie**.
