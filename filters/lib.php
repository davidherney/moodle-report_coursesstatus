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
 * This file contains the Course status report filter API.
 *
 * @package    report_coursesstatus
 * @copyright 2017 David Herney Bernal - cirano
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once('filters/text.php');
require_once('filters/date.php');
require_once('filters/select.php');
require_once('filters/simpleselect.php');
require_once('filters/yesno.php');
require_once('filters/coursesstatus_filter_forms.php');

/**
 * Courses status filtering wrapper class.
 *
 * @copyright 2017 David Herney Bernal - cirano
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursesstatus_filtering {
    /** @var array */
    public $_fields;

    /** @var \coursesstatus_add_filter_form */
    public $_addform;

    /** @var \coursesstatus_active_filter_form */
    public $_activeform;

    /**
     * Contructor
     * @param array $fieldnames array of visible fields
     * @param string $baseurl base url used for submission/return, null if the same of current page
     * @param array $extraparams extra page parameters
     */
    public function __construct($fieldnames = null, $baseurl = null, $extraparams = null) {
        global $SESSION;

        if (!isset($SESSION->coursesstatus_filtering)) {
            $SESSION->coursesstatus_filtering = array();
        }

        if (empty($fieldnames)) {
            $fieldnames = array('fullname' => 0, 'category' => 1, 'shortname' => 1, 'idnumber' => 1, 'startdate' => 1,
                                'timecreated' => 1, 'timemodified' => 1, 'visible' => 1);
        }

        $this->_fields  = array();

        foreach ($fieldnames as $fieldname => $advanced) {
            if ($field = $this->get_field($fieldname, $advanced)) {
                $this->_fields[$fieldname] = $field;
            }
        }

        // Fist the new filter form.
        $this->_addform = new coursesstatus_add_filter_form($baseurl,
                                array('fields' => $this->_fields, 'extraparams' => $extraparams));

        if ($adddata = $this->_addform->get_data()) {
            foreach ($this->_fields as $fname => $field) {
                $data = $field->check_data($adddata);
                if ($data === false) {
                    continue; // Nothing new.
                }
                if (!array_key_exists($fname, $SESSION->coursesstatus_filtering)) {
                    $SESSION->coursesstatus_filtering[$fname] = array();
                }
                $SESSION->coursesstatus_filtering[$fname][] = $data;
            }
            // Clear the form.
            $_POST = array();
            $this->_addform = new coursesstatus_add_filter_form($baseurl,
                                    array('fields' => $this->_fields, 'extraparams' => $extraparams));
        }

        // Now the active filters.
        $this->_activeform = new coursesstatus_active_filter_form($baseurl,
                                    array('fields' => $this->_fields, 'extraparams' => $extraparams));

        if ($adddata = $this->_activeform->get_data()) {
            if (!empty($adddata->removeall)) {
                $SESSION->coursesstatus_filtering = array();

            } else if (!empty($adddata->removeselected) and !empty($adddata->filter)) {
                foreach ($adddata->filter as $fname => $instances) {
                    foreach ($instances as $i => $val) {
                        if (empty($val)) {
                            continue;
                        }
                        unset($SESSION->coursesstatus_filtering[$fname][$i]);
                    }
                    if (empty($SESSION->coursesstatus_filtering[$fname])) {
                        unset($SESSION->coursesstatus_filtering[$fname]);
                    }
                }
            }
            // Clear+reload the form.
            $_POST = array();
            $this->_activeform = new coursesstatus_active_filter_form($baseurl,
                                    array('fields' => $this->_fields, 'extraparams' => $extraparams));
        }
        // Now the active filters.
    }

    /**
     * Creates known coursesstatus filter if present
     * @param string $fieldname
     * @param boolean $advanced
     * @return object filter
     */
    public function get_field($fieldname, $advanced) {
        global $USER, $CFG, $DB, $SITE;

        switch ($fieldname) {
            case 'fullname':
                return new coursesstatus_filter_text('fullname', get_string('course'), $advanced, 'fullname');
            case 'category':
                $categories = array();
                $catnames = $DB->get_records('course_categories', null, "name", 'id, name, idnumber');
                if ($catnames) {
                    foreach($catnames as $one) {
                        $categories[$one->id] = $one->name . ' (' . ($one->idnumber ? $one->idnumber : '-') . ')';
                    }
                }
                return new coursesstatus_filter_select('category', get_string('category'), $advanced, 'category', $categories);
            case 'shortname':
                return new coursesstatus_filter_text('shortname', get_string('shortname'), $advanced, 'shortname');
            case 'idnumber':
                return new coursesstatus_filter_text('idnumber', get_string('idnumber'), $advanced, 'idnumber');
            case 'startdate':
                return new coursesstatus_filter_date('startdate',
                                get_string('startdate', 'report_coursesstatus'), $advanced, 'startdate');
            case 'timecreated':
                return new coursesstatus_filter_date('timecreated',
                                get_string('timecreated', 'report_coursesstatus'), $advanced, 'timecreated');
            case 'timemodified':
                return new coursesstatus_filter_date('timemodified',
                                get_string('timemodified', 'report_coursesstatus'), $advanced, 'timemodified');
            case 'visible':
                return new coursesstatus_filter_yesno('visible', get_string('visible'), $advanced, 'visible');

            default:
                return null;
        }
    }

    /**
     * Returns sql where statement based on active filters
     * @param string $extra sql
     * @param array $params named params (recommended prefix ex)
     * @return array sql string and $params
     */
    public function get_sql_filter($extra='', array $params=null) {
        global $SESSION;

        $sqls = array();
        if ($extra != '') {
            $sqls[] = $extra;
        }
        $params = (array)$params;

        if (!empty($SESSION->coursesstatus_filtering)) {
            foreach ($SESSION->coursesstatus_filtering as $fname => $datas) {
                if (!array_key_exists($fname, $this->_fields)) {
                    continue; // Filter not used.
                }
                $field = $this->_fields[$fname];
                foreach ($datas as $i => $data) {
                    list($s, $p) = $field->get_sql_filter($data);
                    $sqls[] = $s;
                    $params = $params + $p;
                }
            }
        }

        if (empty($sqls)) {
            return array('', array());
        } else {
            $sqls = implode(' AND ', $sqls);
            return array($sqls, $params);
        }
    }

    /**
     * Print the add filter form.
     */
    public function display_add() {
        $this->_addform->display();
    }

    /**
     * Print the active filter form.
     */
    public function display_active() {
        $this->_activeform->display();
    }

}

/**
 * The base filter class. All abstract classes must be implemented.
 *
 * @copyright 2017 David Herney Bernal - cirano
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursesstatus_filter_type {
    /**
     * The name of this filter instance.
     * @var string
     */
    public $_name;

    /**
     * The label of this filter instance.
     * @var string
     */
    public $_label;

    /**
     * Advanced form element flag
     * @var bool
     */
    public $_advanced;

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     */
    public function __construct($name, $label, $advanced) {
        $this->_name     = $name;
        $this->_label    = $label;
        $this->_advanced = $advanced;
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    public function get_sql_filter($data) {
        print_error('mustbeoveride', 'debug', '', 'get_sql_filter');
    }

    /**
     * Retrieves data from the form data
     * @param stdClass $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    public function check_data($formdata) {
        print_error('mustbeoveride', 'debug', '', 'check_data');
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param moodleform $mform a MoodleForm object to setup
     */
    public function setupForm(&$mform) {
        print_error('mustbeoveride', 'debug', '', 'setupForm');
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    public function get_label($data) {
        print_error('mustbeoveride', 'debug', '', 'get_label');
    }
}
