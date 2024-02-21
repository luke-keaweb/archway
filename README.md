# Archway-style Collections Search for Archives NZ

(100% unofficial, not endorsed by Archives NZ in any way)

An alternative search system for Archives NZ, inspired by the beautiful simplicity of the beloved Archway system.

# Server requirements: 

* Apache2 running PHP 8.2 or later
* Composer
* MySQL server

# Setup Instructions

* Clone this project into a folder on your server (eg /var/www/archway)
* Run 'composer require'

* Set Apache to serve the /public folder

* Download SQl file from https://archway.howison.co.nz/db/archway.sql and import it into a local MySQL database

* Copy .env.example as .env and add local environmental details (domain, MySQl connection details)

* Test if it's working!