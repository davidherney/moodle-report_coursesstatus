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
 * Settings for the coursesstatus report
 *
 * @package    report_coursesstatus
 * @copyright  2017 David Herney Bernal - cirano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$settings = new admin_settingpage('report_coursesstatus', get_string('pluginname', 'report_coursesstatus'));

$ADMIN->add('reports', new admin_externalpage('reportcoursesstatus',
            get_string('coursesstatus', 'report_coursesstatus'),
            $CFG->wwwroot . "/report/coursesstatus/index.php", 'report/coursesstatus:view'));

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('report_coursesstatus_setting', '', get_string('pluginname', 'report_coursesstatus')));

    // Disable web interface evaluation and get predictions.
    $settings->add(new admin_setting_configcheckbox('report_coursesstatus/enablelastmodify',
                                                    get_string('enablelastmodify', 'report_coursesstatus'),
                                                    get_string('enablelastmodify_help', 'report_coursesstatus'), 1));
}