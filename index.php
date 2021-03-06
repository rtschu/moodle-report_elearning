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
 * Displays e-learning statistics data selection form and results.
 * @package    report_elearning
 * @copyright  2015 BFH-TI, Luca Bösch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Moodle E-Learning-Strategie Report
 *
 * Main file for report
 *
 * @see doc/html/ for documentation

 */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/accesslib.php');
require_once($CFG->dirroot . '/lib/statslib.php');
require_once($CFG->dirroot . '/report/elearning/form.php');
require_once($CFG->dirroot . '/report/elearning/locallib.php');
require_once($CFG->dirroot . '/course/lib.php');

global $CFG, $PAGE, $OUTPUT, $USER;
require_login();
$context = context_system::instance();
require_capability('report/elearning:view', $context, $USER);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/elearning/index.php'));
$output = $PAGE->get_renderer('report_elearning');

$mform = new _form(new moodle_url('/report/elearning/'));
// Extract all this data.

if (($mform->is_submitted() && $mform->is_validated()) || (isset($_POST['download']))) {
    // Processing of the submitted form.
    $data = $mform->get_data();
    if (isset($data->elearningvisibility) AND ($data->elearningvisibility == 1)) {
        $elearningvisibility = true;
    } else if (isset($_POST['elearningvisibility']) && $_POST['elearningvisibility'] == 1) {
        $elearningvisibility = true;
    } else {
        $elearningvisibility = false;
    }
    if (isset($data->nonews) && ($data->nonews == 1)) {
        $nonews = true;
    } else if (isset($_POST['nonews']) && $_POST['nonews'] == 1) {
        $nonews = true;
    } else {
        $nonews = false;
    }
    if (isset($_POST['download']) && $_POST['download'] == 1) {
        $download = true;
    } else {
        $download = false;
    }

    $a = new stdClass();
    if (isset($_POST['elearningcategory'])) {
        $a->category = $_POST['elearningcategory'];
        $a->context = get_instancecontext($_POST['elearningcategory']);
        $resultstring = get_string('recap', 'report_elearning', $a);
        $visiblecount = get_coursecategorycoursecount(get_coursecategorypath($_POST['elearningcategory']), true);
        $invisiblecount = get_coursecategorycoursecount(get_coursecategorypath($_POST['elearningcategory']), false);
    } else {
        $a->category = $data->elearningcategory;
        $a->context = get_instancecontext($data->elearningcategory);
        $resultstring = get_string('recap', 'report_elearning', $a);
        $visiblecount = get_coursecategorycoursecount(get_coursecategorypath($data->elearningcategory), true);
        $invisiblecount = get_coursecategorycoursecount(get_coursecategorypath($data->elearningcategory), false);
    }

    if ($elearningvisibility == true) {
        $coursecount = $visiblecount;
    } else {
        $coursecount = $invisiblecount;
        $coursecount += $visiblecount;
    }

    if ($coursecount > 0) {
        // There are results.
        if ($coursecount == 1) {
            $a->count = 1;
            $resultstring .= get_string('courseincategorycount', 'report_elearning', $a);
        } else {
            $a->count = $coursecount;
            $resultstring .= get_string('courseincategorycountplural', 'report_elearning', $a);
        }
        $resultstring .= "<br />&#160;<br />\n";
        // Write a table with 24 columns.
        $table = new html_table();
        // Get the data from the locallib.
        $rawdata = get_data($elearningvisibility, $nonews, $a);
        // The api in the locallib will only return plugins that have any data. However we also want to list no data.
        $rec = get_array_for_categories(-1);
        $plugins = get_all_plugin_names(array("mod", "block"));
        // Get all courses of a category.
        $rec = get_all_courses($rec);

        // Restructuring. Get categories instead of Plugins as main reference.
        foreach ($rawdata as $plugin => $plugindata) {
            foreach ($plugindata as $catid => $count) {
                if (!isset($rec[$catid]->$plugin)) {
                    $rec[$catid]->$plugin = 0;
                }
                $rec[$catid]->$plugin += $count;
            }
        }


        $data1 = array();
        // Added up courses in this category, recursive.
        $totalheaderrow = new html_table_row();
        $totalheadercell = new html_table_cell(get_string('categorytotal', 'report_elearning'));
        $totalheadercell->header = true;
        $totalheadertitles = get_table_headers();
        $totalheadercell->colspan = count($totalheadertitles);
        $totalheadercell->attributes['class'] = 'c0';
        $totalheaderrow->cells = array($totalheadercell);
        $data1[] = $totalheaderrow;

        $headerrow = new html_table_row();
        $totalheadercells = array();
        $totalheadertitlesnice = get_shown_table_headers();
        foreach ($totalheadertitlesnice as $totalheadertitle) {
            $cell = new html_table_cell($totalheadertitle);
            $cell->header = true;
            $totalheadercells[] = $cell;
        }
        $headerrow->cells = $totalheadercells;
        $data1[] = $headerrow;

        if ($a->category == 0) {
            // All courses.
            if ($elearningvisibility == true) {
                $coursesincategorysql = "SELECT id, category"
                    . "                FROM {course}"
                    . "               WHERE visible <> 0"
                    . "                 AND id > 1"
                    . "            ORDER BY sortorder";
            } else {
                $coursesincategorysql = "SELECT id, category"
                    . "                FROM {course}"
                    . "               WHERE id > 1"
                    . "            ORDER BY sortorder";
            }
            $coursesincategory = $DB->get_records_sql($coursesincategorysql, array($a->category));
        } else {
            if ($elearningvisibility == true) {
                $coursesincategorysql = "SELECT id, category"
                    . "                FROM {course}"
                    . "               WHERE category = ?"
                    . "                 AND visible <> 0"
                    . "            ORDER BY sortorder";
            } else {
                $coursesincategorysql = "SELECT id, category"
                    . "                FROM {course}"
                    . "               WHERE category = ?"
                    . "            ORDER BY sortorder";
            }
            $coursesincategory = $DB->get_records_sql($coursesincategorysql, array($a->category));
        }

        // Take the data and push it into the html table.
        $category = $a->category;
        foreach ($rec as $row) {
            if ($category != 0 and $row->id != $category and !(strpos($row->path, "/$category/") !== false)) {
                continue;
            }
            $rowdata = array();
            $total = 0; $totalcleared = 0;
            foreach ($totalheadertitles as $index => $name) {
                if ($name == "id") {// See id is special, we want to have a link there.
                    $rowdata[$index] = "<a href=\"$CFG->wwwroot/course/index.php?categoryid=" . $row->id .
                        "\" target=\"_blank\">" . $row->id . "</a>";
                } else if ($name == "category") {// Same here.
                    $rowdata[$index] = "<a href=\"$CFG->wwwroot/course/index.php?categoryid=" . $row->id .
                        "\" target=\"_blank\">" . get_stringpath($row->path) . "</a><!--(" . $row->path . ")-->";
                } else if ($name == "Sum") { // Sum is also not in the data but rather added up in this loop.
                    $rowdata[$index] = $total;
                } else if ($name == "Sum without files and folders") { // Same old same old.
                    $rowdata[$index] = $totalcleared;
                } else { // Default handling.
                    // If there is data add it else add a default of 0.
                    if (isset($row->$name)) {
                        // If it isn't a folder or resource add it's amount to $totalcleared.
                        if (!($name == "folder" || $name == "resource")) {
                            $totalcleared += $row->$name;
                        }
                        $total += $row->$name;
                        $rowdata[$index] = $row->$name;
                    } else {
                        $rowdata[$index] = 0;
                    }
                }
            }
            // Push the categorysdata as new row to the tabledata.
            $data1[] = $rowdata;
        }
        // And finally push it to the table.
        $table->data = $data1;

        if ($download == true) {
            $filename = "Export-E-Learning-" . date("Y-m-d-H-i-s") . ".xls";
            header("Content-type: application/x-msexcel");
            header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
            echo html_writer::table($table);
            die();
        } else {
            // Display the processed page.
            $PAGE->set_pagelayout('admin');
            $PAGE->set_heading($SITE->fullname);
            $PAGE->set_title($SITE->fullname . ': ' . get_string('pluginname', 'report_elearning'));
            echo $OUTPUT->header();
            echo $OUTPUT->heading(get_string('pluginname', 'report_elearning'));
            echo $OUTPUT->box(get_string('reportelearningdesc', 'report_elearning') . "<br />&#160;", 'generalbox mdl-align');
            $mform->display();
            echo $resultstring;
            echo html_writer::table($table);
            echo $OUTPUT->single_button(new moodle_url($PAGE->url, array('download' => 1,
            'elearningcategory' => $data->elearningcategory, 'elearningvisibility' => $elearningvisibility, 'nonews' => $nonews)),
                get_string('download', 'admin'));
        }
    } else {
        // There are no results.
        $PAGE->set_pagelayout('admin');
        $PAGE->set_heading($SITE->fullname);
        $PAGE->set_title($SITE->fullname . ': ' . get_string('pluginname', 'report_elearning'));
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('pluginname', 'report_elearning'));
        echo $OUTPUT->box(get_string('reportelearningdesc', 'report_elearning') . "<br />&#160;", 'generalbox mdl-align');
        $mform->display();
        echo get_string('nocourseincategory', 'report_elearning', $a);
    }
} else {
    // Form was not submitted yet.
    $PAGE->set_pagelayout('admin');
    $PAGE->set_heading($SITE->fullname);
    $PAGE->set_title($SITE->fullname . ': ' . get_string('pluginname', 'report_elearning'));
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('pluginname', 'report_elearning'));
    echo $OUTPUT->box(get_string('reportelearningdesc', 'report_elearning') . "<br />&#160;", 'generalbox mdl-align');
    $mform->display();
}

/*
 * Debug flag -- if set to TRUE, debug output will be generated.
 */
$debug = false;

if ($debug) {
    ini_set('display_errors', 'On');
    error_reporting(E_ALL);
}



echo $OUTPUT->footer();
// The end.
