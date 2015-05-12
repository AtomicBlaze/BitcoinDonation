<?php

/*
Copyright © 2014-2015 Brent "Atomic Blaze" Smith

This file is part of "Bitcoin Donations".

    "Bitcoin Donations" is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    "Bitcoin Donations" is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with "Bitcoin Donations".  If not, see <http://www.gnu.org/licenses/>.
*/

// If SSI.php is in the same place as this file, and SMF isn't defined, this is being run standalone.
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
{
	require_once(dirname(__FILE__) . '/SSI.php');
}
// Sorry, SMF couldn't be found!
elseif (!defined('SMF')){
	die('<b>Error:</b> Cannot install - please verify you put this in the same place as SMF\'s index.php.');
}

global $smcFunc;

db_extend('packages');

$smcFunc['db_insert']('ignore', '{db_prefix}settings',
    array(
        'variable' => 'string',
        'value' => 'string',
    ),
    array(
		array('BitcoinAddress', ''),
		array('BitcoinMethod', 'toshi'),
	),
	array()
);

// More should be added when needed

?>