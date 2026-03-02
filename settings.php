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

defined('MOODLE_INTERNAL') || die();

// Allow access if user is a site admin OR has the S3 sync log read capability.
$hascapability = has_capability('tool/coursearchiver:views3synclog', context_system::instance());

if (!$hassiteconfig && !$hascapability) {
    return;
}

// -------------------------------------------------------------------------
// Create Course Archiver category under Courses
// -------------------------------------------------------------------------
$ADMIN->add(
    'courses',
    new admin_category(
        'toolcoursearchiver',
        get_string('coursearchiver', 'tool_coursearchiver')
    )
);

if ($hassiteconfig) {
    // -------------------------------------------------------------------------
    // Main Course Archiver tool
    // -------------------------------------------------------------------------
    $ADMIN->add(
        'toolcoursearchiver',
        new admin_externalpage(
            'toolcoursearchiver_main',
            get_string('coursearchiver', 'tool_coursearchiver'),
            new moodle_url('/admin/tool/coursearchiver/index.php'),
            'tool/coursearchiver:use'
        )
    );

    // -------------------------------------------------------------------------
    // Archive Run Report page
    // -------------------------------------------------------------------------
    $ADMIN->add(
        'toolcoursearchiver',
        new admin_externalpage(
            'toolcoursearchiverruns',
            'Archive Run Report',
            new moodle_url('/admin/tool/coursearchiver/runreport.php'),
            'tool/coursearchiver:use'
        )
    );

    // -------------------------------------------------------------------------
    // Settings page
    // -------------------------------------------------------------------------
    $settings = new admin_settingpage(
        'tool_coursearchiver',
        get_string('coursearchiver_settings', 'tool_coursearchiver'),
        'tool/coursearchiver:use'
    );

    // Root path.
    $settings->add(
        new admin_setting_configtext(
            'tool_coursearchiver/coursearchiverrootpath',
            get_string('coursearchiverrootpath', 'tool_coursearchiver'),
            get_string('coursearchiverrootpath_help', 'tool_coursearchiver'),
            $CFG->dataroot
        )
    );

    // Archive folder.
    $settings->add(
        new admin_setting_configtext(
            'tool_coursearchiver/coursearchiverpath',
            get_string('coursearchiverpath', 'tool_coursearchiver'),
            get_string('coursearchiverpath_help', 'tool_coursearchiver'),
            'CourseArchives'
        )
    );

    // Archive deletion delay in days.
    $settings->add(new admin_setting_configtext(
        'tool_coursearchiver/delaydeletesetting',
        get_string('archivedeletesetting', 'tool_coursearchiver'),
        get_string('archivedeletesetting_help', 'tool_coursearchiver'),
        7,
        PARAM_INT
    ));

    // Attach settings page.
    $ADMIN->add('toolcoursearchiver', $settings);

    // Attach S3 Sync Page.
    $ADMIN->add(
        'toolcoursearchiver',
        new admin_externalpage(
            'toolcoursearchiver_s3sync',
            'Sync Archives to S3',
            new moodle_url('/admin/tool/coursearchiver/s3sync.php'),
            'tool/coursearchiver:use'
        )
    );
}

// --------------------------------------------------------------------
// S3 Sync Log Page – accessible to users with the views3synclog capability.
// --------------------------------------------------------------------
$ADMIN->add(
    'toolcoursearchiver',
    new admin_externalpage(
        'toolcoursearchiver_s3synclog',
        get_string('s3synclog', 'tool_coursearchiver'),
        new moodle_url('/admin/tool/coursearchiver/s3synclog.php'),
        'tool/coursearchiver:views3synclog'
    )
);

if ($hassiteconfig) {
    $ADMIN->add('toolcoursearchiver', new admin_externalpage(
        'toolcoursearchiver_archivelist',
        'Course Archives',
        new moodle_url('/admin/tool/coursearchiver/archivelist.php'),
        'tool/coursearchiver:use'
    ));
}


