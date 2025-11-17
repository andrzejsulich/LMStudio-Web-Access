# LMStudio-Web-Access
Web Access for LMStudio

Ready-made HTML code that provides the user with a webinterface to the locally installed LMStudio.
Requirements:
-LMStudio running on the same machine (no code update)
-LMStudio installed on remote machine (require to update chat_proxy.php and models_proxy.php - simple edit, change localhost to target IP and save on the server, may require to open firewall port 1234)
LMStudio in both situations require to start server on devtools.

configuration and some logic is in PHP file, so it will not work without PHP Server, tested on WAMP Server, works fine
