<?php
// This file is part of Moodle - http://moodle.org/
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
 * Version details for Course Archiver tool.
 *
 * @package    tool_coursearchiver
 * @copyright  2015 Matthew Davidson
 * @modifiedby 2025 Mujahidul Islam
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'tool_coursearchiver';

// Internal upgrade version (monotonically increasing).
// NOTE: This is NOT a release date; do not interpret as calendar month.
$plugin->version   = 2025121003;

// Human-readable release information shown in Site administration.
$plugin->release   = '2025.12';

// Required Moodle version (adjust only if needed).
$plugin->requires  = 2024042200; // Moodle 4.5

// Plugin maturity level.
$plugin->maturity  = MATURITY_STABLE;

// Optional: Declare this plugin as non-cron dependent (tasks still use cron).
$plugin->cron      = 0;

