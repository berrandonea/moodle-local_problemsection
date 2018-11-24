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
 * File : manage.php
 * To manage the problem sections in this course.
 */

require_once("../../config.php");
require_once("lib.php");

// Arguments.
$courseid = required_param('id', PARAM_INT);
$deletedproblemsectionid = optional_param('delete', 0, PARAM_INT);

// Access control.
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:update', $context);
require_capability('local/problemsection:addinstance', $context);

// Header code.
$manageurl = new moodle_url('/local/problemsection/manage.php', array('id' => $courseid));
if ($deletedproblemsectionid) {
    $pageurl = new moodle_url('/local/problemsection/manage.php',
            array('id' => $courseid, 'delete' => $deletedproblemsectionid));
} else {
    $pageurl = $manageurl;
}
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_course($course);
$title = get_string('manage', 'local_problemsection');
$PAGE->set_title($title);
$PAGE->set_heading($title);

// Deleting the problem section.
if ($deletedproblemsectionid && confirm_sesskey()) {
    $deletionparams = array('id' => $deletedproblemsectionid, 'courseid' => $courseid);
    $deletedproblemsection = $DB->get_record('local_problemsection', $deletionparams);
    if ($deletedproblemsection) {
        $deletedsection = $DB->get_record('course_sections', array('id' => $deletedproblemsection->sectionid));
        // Get section_info object with all availability options.
        $sectionnum = $deletedsection->section;
        $sectioninfo = get_fast_modinfo($course)->get_section_info($sectionnum);

        if (course_can_delete_section($course, $sectioninfo)) {
            $confirm = optional_param('confirm', false, PARAM_BOOL) && confirm_sesskey();
            if ($confirm) {
                local_problemsection_delete($deletedproblemsection, $course, $sectioninfo);
                redirect($manageurl);
            } else {
                $strdelete = get_string('deleteproblemsection', 'local_problemsection');
                $PAGE->navbar->add($strdelete);
                $PAGE->set_title($strdelete);
                $PAGE->set_heading($course->fullname);
                echo $OUTPUT->header();
                echo $OUTPUT->box_start('noticebox');
                $optionsyes = array('id' => $courseid, 'confirm' => 1,
                    'delete' => $deletedproblemsectionid, 'sesskey' => sesskey());
                $deleteurl = new moodle_url('/local/problemsection/manage.php', $optionsyes);
                $formcontinue = new single_button($deleteurl, get_string('deleteproblemsection', 'local_problemsection'));
                $formcancel = new single_button($manageurl, get_string('cancel'), 'get');
                echo $OUTPUT->confirm(get_string('warningdelete', 'local_problemsection',
                    $deletedproblemsection->name), $formcontinue, $formcancel);
                echo $OUTPUT->box_end();
                echo $OUTPUT->footer();
                exit;
            }
        } else {
            notice(get_string('nopermissions', 'error', get_string('deletesection')), $manageurl);
        }
    }
}

$problemsections = $DB->get_records('local_problemsection', array('courseid' => $courseid));
$addurl = "problemsection.php?id=$courseid";
$commongroupsurl = "groups.php?id=$courseid&psid=";
$commonsubmissionsurl = "$CFG->wwwroot/mod/assign/view.php?action=grading&id=";
$commondeleteurl = "manage.php?id=$courseid&sesskey=".s(sesskey())."&delete=";
echo $OUTPUT->header();
echo "<a href='$addurl'><button class='btn'>".get_string('problemsection:addinstance', 'local_problemsection')."</button></a>";
if ($problemsections) {
    echo '<table>';
    echo '<tr>';
    echo '<th>'.get_string('name').'</th>';
    echo '<th>'.get_string('groups').'</th>';
    echo '<th>'.get_string('submissions', 'local_problemsection').'</th>';
    echo '<th>'.get_string('allowsubmissionsfromdate', 'assign').'</th>';
    echo '<th>'.get_string('duedate', 'assign').'</th>';
    echo '<th></th>';
    echo '</tr>';
    foreach ($problemsections as $problemsection) {
        $nbgroups = $DB->count_records('groupings_groups',
                array('groupingid' => $problemsection->groupingid));
        $groupsurl = $commongroupsurl.$problemsection->id;
        $assigncm = local_problemsection_get_assigncm($problemsection);
        echo '<tr>';
        echo "<td>$problemsection->name</td>";
        echo "<td style='text-align:center'><a href='$groupsurl'>$nbgroups</a></td>";
        if ($assigncm) {
            $submissionsurl = $commonsubmissionsurl.$assigncm->id;
            $assign = $DB->get_record('assign', array('id' => $assigncm->instance));
            $nbsubmissions = $DB->count_records('assign_submission', array('assignment' => $assign->id));
            echo "<td style='text-align:center'><a href='$submissionsurl'>$nbsubmissions</a></td>";
            echo '<td>'.date('d/m/Y H:i:s', $assign->allowsubmissionsfromdate).'</td>';
            echo '<td>'.date('d/m/Y H:i:s', $assign->duedate).'</td>';
        } else {
            echo '<td></td><td></td><td></td>';
        }
        echo "<td><a href='".$commondeleteurl.$problemsection->id."'><button class='btn'>"
                .get_string('deleteproblemsection', 'local_problemsection')."</button></a></td>";
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p>'.get_string('noproblemyet', 'local_problemsection').'</p>';
}
echo "<a href='$CFG->wwwroot/course/view.php?id=$courseid'><button class='btn'>".get_string('back')."</button></a>";
echo $OUTPUT->footer();
