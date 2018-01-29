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
 * A report to display the courses status (stats, counters, general information)
 *
 * @package    report_coursesstatus
 * @copyright 2017 David Herney Bernal - cirano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once('locallib.php');
require_once('filters/lib.php');

$categoryid     = optional_param('categoryid', 0, PARAM_INT);
$sort           = optional_param('sort', 'fullname', PARAM_ALPHANUM);
$dir            = optional_param('dir', 'ASC', PARAM_ALPHA);
$page           = optional_param('page', 0, PARAM_INT);
$perpage        = optional_param('perpage', 30, PARAM_INT);        // how many per page
$format         = optional_param('format', '', PARAM_ALPHA);


admin_externalpage_setup('reportcoursesstatus', '', null, '', array('pagelayout' => 'report'));

$baseurl = new moodle_url('/report/coursesstatus/index.php', array('sort' => $sort, 'dir' => $dir, 'perpage' => $perpage));

// Create the filter form.
$filtering = new coursesstatus_filtering();

list($extrasql, $params) = $filtering->get_sql_filter();

if ($format) {
    $perpage = 0;
}

$withlastmodify = false;
if (strpos($extrasql, 'l.lastmodify') !== false) {
    $withlastmodify = true;
    $extrasql = $extrasql ? ' WHERE ' . $extrasql : '';

    $sql = "SELECT c.*, l.lastmodify
        FROM {course} c
        LEFT JOIN (SELECT MAX(timecreated) AS lastmodify, courseid FROM {logstore_standard_log} WHERE crud <> 'r'
        GROUP BY courseid) l ON l.courseid = c.id
        " . $extrasql . " ORDER BY " . $sort . ' ' . $dir;
    $courses = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

    $sql = "SELECT COUNT('x')
        FROM {course} c
        LEFT JOIN (SELECT MAX(timecreated) AS lastmodify, courseid FROM {logstore_standard_log} WHERE crud <> 'r'
        GROUP BY courseid) l ON l.courseid = c.id
        " . $extrasql;

    $coursesearchcount = $DB->count_records_sql($sql, $params);

} else {
    $courses = $DB->get_records_select('course', $extrasql, $params, $sort . ' ' . $dir, '*', $page * $perpage, $perpage);
    $coursesearchcount = $DB->count_records_select('course', $extrasql, $params);
}

$coursecount = $DB->count_records('course');

if ($courses) {

    $categories = $DB->get_records('course_categories');

    $stringcolumns = array(
        'id' => 'id',
        'fullname' => get_string('course'),
        'shortname' => get_string('shortname'),
        'idnumber' => get_string('idnumber'),
        'startdate' => get_string('startdate', 'report_coursesstatus'),
        'timecreated' => get_string('timecreated', 'report_coursesstatus'),
        'timemodified' => get_string('timemodified', 'report_coursesstatus'),
        'lastmodify' => get_string('lastmodify', 'report_coursesstatus'),
        'roleassignments' => get_string('roleassignments'),
        'category' => get_string('category'),
        'visible' => get_string('visible'),
        'format' => get_string('format'),
        'groupmode' => get_string('groupmode'),
        'groupmodeforce' => get_string('groupmodeforce'),
        'lang' => get_string('language'),
        'enablecompletion' => get_string('enablecompletion', 'completion')
    );

    $strcsystem = get_string('categorysystem', 'report_coursesstatus');
    $strftimedate = get_string('strftimedatetimeshort');
    $strfdate = get_string('strftimedatefullshort');
    $strnever = get_string('never');

    // Only download data.
    if ($format) {
        $columns = array('id', 'fullname', 'shortname', 'idnumber', 'startdate', 'timecreated', 'timemodified', 'lastmodify',
        'visible', 'format', 'groupmode', 'groupmodeforce', 'lang', 'enablecompletion');

        $fields = array();
        foreach ($columns as $column) {
            $fields[$column] = $stringcolumns[$column];
        }

        $data = array();
        $maxcats = 1;
        $coursescats = array();

        $rolenames = $DB->get_records('role', null, 'id, name, shortname');

        foreach ($rolenames as $result) {
            $fieldname = 'role' . $result->id;
            $fields[$fieldname] = empty($result->name) ? $result->shortname : $result->name;
        }

        foreach ($courses as $row) {
            // ToDo: Build with log_manager.
            if ($withlastmodify) {
                $lastchange = $row->lastmodify;
            } else {
                $sql = "SELECT MAX(timecreated) AS time FROM {logstore_standard_log} WHERE courseid = ? AND crud <> 'r'";
                $lastchange = $DB->get_field_sql($sql, array($row->id));
            }

            if ($lastchange) {
                $row->lastmodify = userdate($lastchange, $strftimedate);
            } else {
                $row->lastmodify = '';
            }

            $datarow = new stdClass();
            foreach ($columns as $column) {
                if (in_array($column, array('timecreated', 'timemodified'))) {
                    $datarow->$column = userdate($row->$column, $strftimedate);
                } else if ($column == 'startdate') {
                    $datarow->$column = userdate($row->$column, $strfdate);
                } else {
                    $datarow->$column = $row->$column;
                }
            }

            $coursecontext = context_course::instance($row->id);

            if (!$row->category) {
                $textcats = $strcsystem;
            } else {
                $cats = trim($categories[$row->category]->path, '/');
                $cats = explode('/', $cats);
                foreach ($cats as $key => $cat) {
                    if (!empty($cat)) {
                        $cats[$key] = $categories[$cat]->name;
                    }
                }

                if (count($cats) > $maxcats) {
                    $maxcats = count($cats);
                }

                $coursescats[$row->id] = $cats;
            }


            $sql = 'SELECT ra.roleid, COUNT(ra.id) AS rolecount
                        FROM {role_assignments} ra
                        WHERE ra.contextid = :contextid
                    GROUP BY ra.roleid';
            $rolecounts = $DB->get_records_sql($sql, array('contextid' => $coursecontext->id));

            foreach ($rolecounts as $result) {
                $fieldname = 'role' . $result->roleid;
                $datarow->$fieldname = $result->rolecount;
            }

            $data[$row->id] = $datarow;
        }

        for ($i = 1; $i <= $maxcats; $i++) {
            $fieldname = 'cat' . $i;
            $fields[$fieldname] = $stringcolumns['category'] . ' ' . $i;
        }

        foreach ($coursescats as $courseid => $cats) {
            $i = 1;
            foreach ($cats as $value) {
                $fieldname = 'cat' . $i;
                $data[$courseid]->$fieldname = $value;
                $i++;
            }
        }

        switch ($format) {
            case 'csv' : coursesstatus_download_csv($fields, $data);
            case 'ods' : coursesstatus_download_ods($fields, $data);
            case 'xls' : coursesstatus_download_xls($fields, $data);

        }
        die;
    }
    // End download data.
}

echo $OUTPUT->header();

flush();


$table = null;

if ($courses) {

    $columns = array('fullname', 'shortname', 'idnumber', 'startdate', 'timecreated', 'timemodified', 'lastmodify',
                    'roleassignments', 'category');

    $table = new html_table;
    $table->head = array();

    foreach ($columns as $column) {

        if (in_array($column, array('lastmodify', 'roleassignments', 'category'))) {
            $table->head[] = $stringcolumns[$column];

        } else {
            if ($sort != $column) {
                $columnicon = "";
                if ($column == "lastaccess") {
                    $columndir = "DESC";
                } else {
                    $columndir = "ASC";
                }
            } else {
                $columndir = $dir == "ASC" ? "DESC":"ASC";
                if ($column == "lastaccess") {
                    $columnicon = ($dir == "ASC") ? "sort_desc" : "sort_asc";
                } else {
                    $columnicon = ($dir == "ASC") ? "sort_asc" : "sort_desc";
                }
                $columnicon = "<img class='iconsort' src=\"" . $OUTPUT->pix_url('t/' . $columnicon) . "\" alt=\"\" />";

            }
            $table->head[] = "<a href=\"index.php?sort=$column&amp;dir=$columndir\">".$stringcolumns[$column]."</a>$columnicon";
        }
    }

    $table->attributes = array('class' => 'generaltable coursesstatus-report');
    $table->data = array();

    foreach ($courses as $row) {

        $coursecontext = context_course::instance($row->id);

        // ToDo: Build with log_manager
        if ($withlastmodify) {
            $lastchange = $row->lastmodify;
        } else {
            $sql = "SELECT MAX(timecreated) AS time FROM {logstore_standard_log} WHERE courseid = ? AND crud <> 'r'";
            $lastchange = $DB->get_field_sql($sql, array($row->id));
        }

        // Prepare a cell to display the status of the entry.
        $statusclass = '';
        if (!$row->visible) {
            $statusclass = 'dimmed_text';
        }

        $category = '';

        if (!$row->category) {
            $textcats = $strcsystem;
        } else {
            $cats = trim($categories[$row->category]->path, '/');
            $cats = explode('/', $cats);
            foreach ($cats as $key => $cat) {
                if (!empty($cat)) {
                    $cats[$key] = html_writer::tag('a',
                                    html_writer::tag('span', $categories[$cat]->name, array('class' => 'singleline')),
                                    array('href' => new moodle_url('/course/index.php',
                                                        array('categoryid' => $categories[$cat]->id)))
                                );
                }
            }

            $textcats = implode(' / ', $cats);
        }

        $format = $row->format == 'site' ? get_string('default') : get_string('pluginname', 'format_' . $row->format);

        $coursename = html_writer::tag('a', $row->fullname,
                        array('href' => new moodle_url('/course/view.php', array('id' => $row->id))));

        if ($lastchange) {
            $lastmodify = userdate($lastchange, $strftimedate);
        } else {
            $lastmodify = $strnever;
        }

        $names = role_get_names($coursecontext);
        $sql = 'SELECT ra.roleid, COUNT(ra.id) AS rolecount
                    FROM {role_assignments} ra
                    WHERE ra.contextid = :contextid
                GROUP BY ra.roleid';
        $rolecounts = $DB->get_records_sql($sql, array('contextid' => $coursecontext->id));
        $roleassignments = html_writer::start_tag('ul');
        foreach ($rolecounts as $result) {
            $a = new stdClass();
            $a->role = $names[$result->roleid]->localname;
            $a->count = $result->rolecount;
            $roleassignments .= html_writer::tag('li', get_string('assignedrolecount', 'moodle', $a),
                                    array('class' => 'singleline'));
        }
        $roleassignments .= html_writer::end_tag('ul');

        // Create the row and add it to the table.
        $cells = array(
            $coursename, $row->shortname, $row->idnumber,
            userdate($row->startdate, $strfdate),
            userdate($row->timecreated, $strftimedate),
            userdate($row->timemodified, $strftimedate),
            $lastmodify, $roleassignments,  $textcats
        );

        $tablerow = new html_table_row($cells);
        $tablerow->attributes['class'] = $statusclass;
        $table->data[] = $tablerow;
    }

}

if ($extrasql !== '') {
    echo $OUTPUT->heading("$coursesearchcount / $coursecount " . get_string('courses'));
    $coursecount = $coursesearchcount;
} else {
    echo $OUTPUT->heading($coursecount . ' ' . get_string('courses'));
}

echo $OUTPUT->paging_bar($coursecount, $page, $perpage, $baseurl);

// Add filters.
$filtering->display_add();
$filtering->display_active();

if (!empty($table)) {
    echo $OUTPUT->box_start();

    $sql = "SELECT MIN(timecreated) AS time FROM {logstore_standard_log}";
    $oldchange = $DB->get_field_sql($sql);
    echo $OUTPUT->notification(get_string('lastmodifynote', 'report_coursesstatus', userdate($oldchange, $strftimedate)),
                    'notifymessage');

    echo html_writer::table($table);

    echo $OUTPUT->box_end();

    echo $OUTPUT->paging_bar($coursecount, $page, $perpage, $baseurl);


    // Download form.
    echo $OUTPUT->heading(get_string('download', 'admin'));

    echo $OUTPUT->box_start();
    echo '<ul>';
    echo '    <li><a href="' . $baseurl . '&format=csv">'.get_string('downloadtext').'</a></li>';
    echo '    <li><a href="' . $baseurl . '&format=ods">'.get_string('downloadods').'</a></li>';
    echo '    <li><a href="' . $baseurl . '&format=xls">'.get_string('downloadexcel').'</a></li>';
    echo '</ul>';
    echo $OUTPUT->box_end();

} else {
    echo $OUTPUT->heading(get_string('notcoursesfound', 'report_coursesstatus'), 3);
}

echo $OUTPUT->footer();
