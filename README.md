# Archway-style Collections Search for Archives NZ

(100% unofficial, not endorsed by Archives NZ in any way)

An alternative search system for records held by Archives New Zealand, inspired by the beautiful simplicity of the beloved Archway system.

## Project notes

This is a work in progress and my first public GitHub project.  Please don't hesitate to contact me with any issues you find or things that don't make sense to you.

## Copyright

The code in this project retrieves search results from the backend of the Archives NZ website, with the understanding that this information is covered by Creative Commons BY 2.0, as stated on the Archives NZ website at https://www.archives.govt.nz/copyright

"In cases where we have already published archival material on the archives.govt.nz website or have made it digitally available on Collections search, it is covered by a Creative Commons BY 2.0 license, unless otherwise stated. You are then welcome to use it without seeking permission."

Please use this access responsibly.

## Server requirements: 

* Apache2
* MySQL
* PHP 8.2 or later
* Composer

## Setup Instructions

* Decide where this project will reside on your server (eg /var/www/archway)
* Clone the code with 'git clone https://github.com/luke-keaweb/archway.git'
** By default the project will be placed into a folder /archway, relative to the current directory
* Run 'composer update' inside the folder
* Set /public as the document root in Apache 
** (for example, the full document root path would be /var/www/archway)
* Download the SQL file from https://archway.howison.co.nz/db/archway.sql and import it into a local MySQL database.  Take note of the MySQL user, password and database name.
* Copy .env.example as .env and add local environment details (eg the domain, MySQl details as per the previous point)
* Test if it's working!

## Files and permissions

* Apache should have permission to create and write to a /cache folder and a /cookie.txt file

## Brief code walkthrough

* Public assets like CSS and JS are in /public/assets/

* (Almost all) Pages are served from public/index.php using routes defined in classes/Router.class.php

* The routes refer to classes + methods, eg the homepage '/' calls the method simpleSearch() from the AimsSearch class

* These routes expect to recieve an HTML string; each method places its own content inside a HTML page, using DefaultTemplate

## Overview of classes

* AimsSearch does the heavy lifting for searches

* Entity displays an individual item

* ResultParser translates the JSON search result into an array of information 

* FormatData lays out an array of information from ResultParser into a table

* Form HTML is handled with FormElements and SimpleForm

* The Series, Agency and Flickr classes call the database to, eg, translate a Series ID (like 18805) into a name (like Military Personnel Records).  The database speeds up the site by avoiding a network call to retrieve this info from the Archive NZ backend.

## Customisation

* The overall HTML template (including the top menu) is created in classes/DefaultTemplate.class.php

* You can specify a custom CSS file in the .env file for DefaultTemplate to use.

* You can specify a custom template class in the .env file (eg, classes/MyCustomTemplate.class.php)
