<?php

if ( ! defined('ENTRY_POSTER_IMDB_ADDON_NAME'))
{
	define('ENTRY_POSTER_IMDB_ADDON_NAME',         'Entry Poster using IMDB');
	define('ENTRY_POSTER_IMDB_ADDON_DESC',      'Create movie entry based on IMDB search results');
    define('ENTRY_POSTER_IMDB_ADDON_VERSION',      '1.0');
}

$config['name'] = ENTRY_POSTER_IMDB_ADDON_NAME;
$config['version'] = ENTRY_POSTER_IMDB_ADDON_VERSION;

$config['nsm_addon_updater']['versions_xml'] = 'http://www.intoeetive.com/index.php/update.rss/306';