<?php

// This file is part of the Biploma Certificate module for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Certificate module capability definition
 *
 * @package    mod_biploma
 * @copyright  2020 Beowulf Blockchain.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// include_once $CFG->dirroot . '/_debug/kint.phar';
// function dd($data) {
//     d($data);
//     die;
// }

// $CFG->env_prod = true; // prod
$CFG->env_prod = false; // test

define('BPLM_API_URL_PROD', 'api.biploma.com'); // prod
define('BPLM_API_URL_TEST', 'api-testing.biploma.com'); // test
define('BPLM_URL_PROD', 'biploma.com'); // prod
define('BPLM_URL_TEST', 'testing.biploma.com'); // test

function get_bplm_api_url($test = null) {
    global $CFG;
    if (empty($CFG->env_prod) || null !== $test) {
        return BPLM_API_URL_TEST;
    }
    return BPLM_API_URL_PROD;
}

function get_bplm_url($test = null) {
    global $CFG;
    if (empty($CFG->env_prod) || null !== $test) {
        return BPLM_URL_TEST;
    }
    return BPLM_URL_PROD;
}

function get_bplm_url_https($test = null) {
    return 'https://' . get_bplm_url();
}
