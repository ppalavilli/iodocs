API Explorer set up guide
=================================

BUILD/RUNTIME DEPENDENCIES

   1. Node.js - server-side JS engine
   2. npm - node package manager
   3. Redis - key+value storage engine
   4. Apache - an HTTP server
   5. PHP - Server-side HTML embedded scripting language

   Note: Node and some of the modules require compiler (like gcc). If you are on a Mac, you will need to install XCode. 
   If you're on Linux, you'll need to install build-essentials, or something equivalent.
   
   
INSTALLATION INSTRUCTIONS FOR NODE, NPM, REDIS, APACHE, PHP
--------------------------------------------------------------

   1. Node.js - https://github.com/joyent/node/wiki/Installation
   2. npm (Node package manager) - https://github.com/isaacs/npm
   3. Redis -[linux] - http://redis.io/download
		 -[windows] - https://github.com/rgl/redis/downloads
   4. Apache & PHP - [windows] - http://www.wampserver.com/en/
				- [ubuntu] - https://help.ubuntu.com/community/ApacheMySQLPHP
				- [mac] - http://www.mamp.info/en/index.html
				
   Note: Make sure 'curl' and 'openssl' extensions are enabled in PHP
   
------------------------------------------------------------------------

INSTALLATION INSTRUCTIONS FOR APIExplorer

   From the command line type in:
<pre>
   git clone https://github.com/ganeshx/iodocs.git
   cd iodocs
   npm install
 </pre> 

Cut the folder 'apiexplorer' from the clone and paste it in Apache public root (c:/wamp/www  or /var/www) 

RUNNING APIExplorer
=======================================================
   From command line 'cd' to Redis directory and run redis-server.exe
   
   in another commandline window type in:
   <pre>
   node ./app.js
   </pre>
   Point your browser to: [http://localhost:20000] (http://localhost:20000)
