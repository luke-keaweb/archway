# Archway-style Collections Search for Archives NZ

(100% unofficial, not endorsed by Archives NZ in any way)

An alternative search system for Archives NZ, inspired by the beautiful simplicity of the beloved Archway system.

## Project notes

This is a work in progress and my first public GitHub project.  Please don't hesitate to contact me with any issues you find or things that don't make sense to you.

## Server requirements: 

* Apache2
* MySQL
* PHP 8.2 or later
* Composer

## Setup Instructions

* Clone this project into a folder on your server (eg /var/www/archway)
* Run 'composer update' inside the folder
* Set /public as the document root in Apache
* Download SQL file from https://archway.howison.co.nz/db/archway.sql and import it into a local MySQL database
* Copy .env.example as .env and add local environment details (domain, MySQl connection details)
* Test if it's working!

## Files and permissions

* Apache should have permission to create and write to a /cache folder and a /cookie.txt file

## Brief code walkthrough

* Public assets like CSS and JS are in /public/assets/

* (Almost all) Pages are served from public/index.php using routes defined in classes/Router.class.php

* The routes refer to classes + methods, eg the homepage '/' calls the method simpleSearch() from the AimsSearch class

* These routes expect to recieve an HTML string; each method places its own content inside a HTML page, using DefaultTemplate

## Class Overview

* AimsSearch does the heavy lifting for searches

* Entity displays an individual item

* Helper classes ResultParser translates the JSON search result into an array of information 

* FormatData lays out an array of information from ResultParser into a table

* Form HTML is handled with FormElements and SimpleForm

* Series, Agency and Flickr classes call the database to, eg, translate a Series ID (like 18805) into a name (like Military Personnel Records).  The database speeds up the site by avoiding a network call to retrieve this info from the Archive NZ backend.

## Customisation

* The HTML template is created in classes/DefaultTemplate.class.PHP

* You can specify a CSS file the .env file for DefaultTemplate to use.

* You can specify a custom template in the .env file (eg, classes/MyCustomTemplate.class.php)
