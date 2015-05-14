<?php

/*
Copyright © 2015 Brent "Atomic Blaze" Smith

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

if (!defined('SMF'))
	die('Hacking attempt...');

function toshi_get_address($address)
{
	global $modSettings; 
	
	// Let's make sure cURL is enabled and there is an address set
	if(!function_exists(curl_init()) || $modSettings['BitcoinAddress'] == '')
		return;

	// We made it!
	$ch = curl_init();
		
	// Set some options
	curl_setopt($ch, CURL_URL, "https://bitcoin.toshi.io/api/v0/addresses/$address");
	curl_setopt($ch, CURL_HEADER, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)");
	curl_setopt($ch, CURL_RETURNTRANSFER, true);
	
	// Execute the exchange
	curl_exec($ch);
	
	// Close the connection, this saves memory and valuable CPU cycles
	curl_close($ch);
}

function get_qrcode($address)
{
	// This function will house the (likely massive) QR generation code.
}