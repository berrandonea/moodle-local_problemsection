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
 * Initially developped for :
 * Université de Cergy-Pontoise
 * 33, boulevard du Port
 * 95011 Cergy-Pontoise cedex
 * FRANCE
 *
 * Adds to the course a section where the teacher can submit a problem to groups of students
 * and give them various collaboration tools to work together on a solution.
 *
 * @package   local_problemsection
 * @copyright 2016 Brice Errandonea <brice.errandonea@u-cergy.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * File : problemsection.php
 * To create a problem section
 */

require_once("../../config.php");
require_once("lib.php");
require_once("problemsection_form.php");
require_once($CFG->libdir.'/formslib.php');

// Access control.
$courseid = required_param('id', PARAM_INT);
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);
require_capability('local/problemsection:addinstance', $context);

// Header code.
$pageurl = new moodle_url('/local/problemsection/problemsection.php', array('id' => $courseid));
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_course($course);
$title = get_string('problemsection:addinstance', 'local_problemsection');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->navbar->add(get_string('manage', 'local_problemsection'), new moodle_url("/local/problemsection/manage.php?id=$courseid"));
$PAGE->navbar->add(get_string('problemsection:addinstance', 'local_problemsection'), new moodle_url($pageurl));


// Prepare datas for the form.
$potentialtools = local_problemsection_potentialtools();
$tools = array();
foreach ($potentialtools as $potentialtool) {
    $enabled = $DB->record_exists('modules', array('name' => $potentialtool, 'visible' => 1));
    if ($enabled) {
        if (has_capability("mod/$potentialtool:addinstance", $context)) {
            $tools[] = $potentialtool;
        }
    }
}
$coursegroupings = $DB->get_records('groupings', array('courseid' => $courseid));
$groupingoptions = array();
$groupingoptions[0] = ' - ';
foreach ($coursegroupings as $coursegrouping) {
    $groupingoptions[$coursegrouping->id] = $coursegrouping->name;
}

// Form instanciation.
$customdatas = array('courseid' => $courseid, 'tools' => $tools, 'copygrouping' => $groupingoptions);
$mform = new problemsection_form($pageurl, $customdatas);

// Three possible states.
if ($mform->is_cancelled()) {
    $courseurl = new moodle_url('/course/view.php', array('id' => $courseid));
    redirect($courseurl);
} else if ($submitteddata = $mform->get_data()) {
    $problemsectionid = local_problemsection_create($submitteddata);
    header("Location: groups.php?id=$courseid&psid=$problemsectionid");
} else {
    echo $OUTPUT->header();
    $mform->display();
    echo $OUTPUT->footer();
}
