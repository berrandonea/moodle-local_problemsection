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
 * File : lib.php
 * Library functions
 */

defined('MOODLE_INTERNAL') || die();
require_once("$CFG->dirroot/course/lib.php");
require_once($CFG->dirroot.'/group/lib.php');

/**
 * Adds the plugin to the Course administration menu.
 * @param settings_navigation $nav
 * @param context $context
 */
function local_problemsection_extend_settings_navigation(settings_navigation $nav, context $context) {
    global $COURSE, $DB;
    $requiredcapabilities = array('moodle/course:update', 'local/problemsection:addinstance');
    if (has_all_capabilities($requiredcapabilities, $context)) {
        $branch = $nav->get('courseadmin');
        // Core plugin mod_assign must be enabled.
        $assignmodule = $DB->get_record('modules', array('name' => 'assign', 'visible' => 1));
        if ($branch && $assignmodule) {
            $params = array('id' => $COURSE->id);
            $manageurl = new moodle_url('/local/problemsection/manage.php', $params);
            $managetext = get_string('manage', 'local_problemsection');
            $icon = new pix_icon('problemsections', $managetext, 'local_problemsection');
            $branch->add($managetext, $manageurl, $nav::TYPE_CONTAINER, null, null, $icon);
        }
    }
}

/**
 * Returns the assign course_modules record for this problem section.
 * @global object $DB
 * @param object $problemsection
 * @return object
 */
function local_problemsection_get_assigncm($problemsection) {
    global $DB;
    $sequence = explode(',', $problemsection->sequence);
    $assigncm = $DB->get_record('course_modules', array('id' => $sequence[0]));
    return $assigncm;
}

/**
 * Creates the groups or uses those of the mentionned grouping.
 * @param object $data The submitted datas.
 * @param context $context The current course context.
 * @param int $groupingid This problemsection's grouping.
 */
function local_problemsection_addgroups($data, $context, $groupingid) {
    if ($data->copygrouping) {
        local_problemsection_copygrouping($data->copygrouping, $groupingid);
    } else {
        if (!is_numeric($data->nbgroups) || ($data->nbgroups < 1)) {
            $nbgroups = 1;
        } else {
            $nbgroups = floor($data->nbgroups);
        }
        local_problemsection_creategroups($nbgroups, $data->courseid, $context, $groupingid, $data->name);
    }
}

/**
 * Creates the problem section.
 * @param object $data
 * @return int
 */
function local_problemsection_create($data) {
    $context = context_course::instance($data->courseid);
    require_capability('moodle/course:update', $context);
    require_capability('local/problemsection:addinstance', $context);
    $section = local_problemsection_createsection($data->name, $data->courseid, $data->summary);
    $groupingid = local_problemsection_creategrouping($data->name, $data->courseid, $section->id);
    local_problemsection_addgroups($data, $context, $groupingid);
    $sequence = local_problemsection_createmodules($section, $groupingid, $data, $context);
    $problemsectionid = local_problemsection_record($data, $section, $groupingid, $sequence);
    return $problemsectionid;
}

/**
 * Records a problem section in the database.
 * @global object $DB
 * @param type $data
 * @param type $section
 * @param type $groupingid
 * @param type $sequence
 * @return type
 */
function local_problemsection_record($data, $section, $groupingid, $sequence) {
    global $DB;
    $problemsection = new stdClass();
    $problemsection->courseid = $data->courseid;
    $problemsection->sectionid = $section->id;
    $problemsection->groupingid = $groupingid;
    $problemsection->name = $data->name;
    $problemsection->sequence = $sequence;
    $problemsection->id = $DB->insert_record('local_problemsection', $problemsection);
    return $problemsection->id;
}

/**
 * Deletes a problem section. Its grouping, its section, its modules,
 * and its groups remain within the course, as ordinary ones.
 * @global object $DB
 * @param type $problemsection
 * @param type $course
 * @param type $sectioninfo
 */
function local_problemsection_delete($problemsection, $course, $sectioninfo) {
    global $DB;
    $DB->delete_records('local_problemsection', array('id' => $problemsection->id));
}

/**
 * Adds a section to the course, to host the problem. Then returns its id.
 * @param string $name
 * @param int $courseid
 * @return int
 */
function local_problemsection_createsection($name, $courseid, $summary) {
    global $DB;
    $sql = "SELECT MAX(section) FROM {course_sections} WHERE course = $courseid";
    $maxsection = $DB->get_field_sql($sql);
    course_create_sections_if_missing($courseid, $maxsection + 1);
    $section = $DB->get_record('course_sections',
               array('section' => ($maxsection + 1), 'course' => $courseid));
    return $section;
}

/**
 * Creates a new grouping for this problem.
 * @param string $name
 * @param int $courseid
 * @param int $sectionid
 * @return int
 */
function local_problemsection_creategrouping($name, $courseid) {
    $grouping = new stdClass();
    $grouping->courseid = $courseid;
    $grouping->name = $name;
    $grouping->description = get_string('groupsworking', 'local_problemsection').' '.$name;
    $grouping->id = groups_create_grouping($grouping);
    return $grouping->id;
}

/**
 * Copy all the groups from a grouping to another.
 * @global object $DB
 * @param int $copiedgroupingid
 * @param int $pastedgroupingid
 */
function local_problemsection_copygrouping($copiedgroupingid, $pastedgroupingid) {
    global $DB;
    $copiedgroups = $DB->get_records('groupings_groups', array('groupingid' => $copiedgroupingid));
    foreach ($copiedgroups as $copiedgroup) {
        groups_assign_grouping($pastedgroupingid, $copiedgroup->groupid);
    }
}

/**
 * Creates and populates the wanted number of new groups into the grouping.
 * @param int $nbgroups
 * @param int $courseid
 * @param int $contextid
 * @param int $groupingid
 * @param string $name
 */
function local_problemsection_creategroups($nbgroups, $courseid, $context, $groupingid, $name) {
    $studentids = local_problemsection_get_studentids($context);
    shuffle($studentids);
    $nbstudents = count($studentids);
    $nbstudentspergroup = ceil($nbstudents / $nbgroups);
    for ($groupnum = 1; $groupnum <= $nbgroups; $groupnum++) {
        $newgroupid = local_problemsection_creategroup($courseid, $name, $groupnum, $groupingid);
        for ($nbstudentsingroup = 0; $nbstudentsingroup < $nbstudentspergroup; $nbstudentsingroup++) {
            if ($studentids) {
                $studentid = array_shift($studentids);
                groups_add_member($newgroupid, $studentid);
            }
        }
    }
}

/**
 * Creates a new group empty group and assign it the mentionned grouping.
 * @param type $courseid
 * @param type $name
 * @param type $groupnum
 * @param type $groupingid
 * @return type
 */
function local_problemsection_creategroup($courseid, $name, $groupnum, $groupingid) {
    $newgroup = new stdClass();
    $newgroup->courseid = $courseid;
    $newgroup->name = $name.' - '.get_string('group').' '.$groupnum;
    $newgroupid = groups_create_group($newgroup);
    groups_assign_grouping($groupingid, $newgroupid);
    return $newgroupid;
}

/**
 * Get the ids of all the course users who can take this problem.
 * @param context $context
 * @return array of int
 */
function local_problemsection_get_studentids($context) {
    $students = get_enrolled_users($context, 'local/problemsection:take');
    $studentids = array();
    foreach ($students as $student) {
        $studentids[] = $student->id;
    }
    return $studentids;
}

/**
 * Get the list of communication tools a teacher can choose from when creating a problem section.
 * @global object $DB
 * @param object $coursecontext
 * @return array of strings
 */
function local_problemsection_potentialtools($coursecontext) {
    global $DB;
    $potentialtools = array();
    $enabledmods = $DB->get_records('modules', array('visible' => 1));
    foreach ($enabledmods as $mod) {
        $showmod = get_config('local_problemsection', $mod->name);
        $canadd = has_capability("mod/$mod->name:addinstance", $coursecontext);
        if ($showmod && $canadd) {
            $potentialtools[] = $mod->name;
        }
    }
    return $potentialtools;
}

/**
 * Creates the wanted modules inside the problem section and links them to the grouping
 * @global object $DB
 * @param int $sectionid
 * @param int $groupingid
 * @param object $data
 */
function local_problemsection_createmodules($section, $groupingid, $data, $coursecontext) {
    global $DB;
    $assigncmid = local_problemsection_createtool('assign', $data, $section, $groupingid);
    $sequence = $assigncmid;
    $potentialtools = local_problemsection_potentialtools($coursecontext);
    foreach ($potentialtools as $tool) {
        if (isset($data->$tool)) {
            if ($data->$tool) {
                $toolcmid = local_problemsection_createtool($tool, $data, $section, $groupingid);
                $sequence .= ",$toolcmid";
            }
        }
    }
    $DB->set_field('course_sections', 'sequence', $sequence, array('id' => $section->id));
    return $sequence;
}

/**
 * Creates a tool for the students and links it to the grouping
 * @global object $CFG
 * @param string $tool
 * @param object $data
 * @param object $section
 * @param int $groupingid
 * @return int
 */
function local_problemsection_createtool($tool, $data, $section, $groupingid) {
    global $CFG;
    $moduleinfo = new stdClass();
    $moduleinfo->modulename = $tool;
    $moduleinfo->name = get_string('pluginname', "mod_$tool");
    $moduleinfo->course = $data->courseid;
    $moduleinfo->cmidnumber = '';
    $moduleinfo->section = $section->section;
    $moduleinfo->visible = 1;
    $moduleinfo->groupmode = 1;
    $moduleinfo->groupingid = $groupingid;

    if ($tool == 'assign') {
        $moduleinfo->introeditor = $data->directions;
        $moduleinfo->introeditor['itemid'] = file_get_submitted_draft_itemid('introeditor');
    } else {
        $introtext = get_string('pleaseuse', 'local_problemsection').' '.$data->name;
        $moduleinfo->introeditor = array('text' => $introtext,
                                         'format' => FORMAT_HTML, 'itemid' => 0);
    }

    switch ($tool) {
        case 'assign':
            $moduleinfo->duedate = $data->dateto;
            $moduleinfo->gradingduedate = $data->gradingduedate;
            $moduleinfo->allowsubmissionsfromdate = $data->datefrom;
            $moduleinfo->cutoffdate = 0;
            $moduleinfo->submissiondrafts = 0;
            $moduleinfo->sendnotifications = 0;
            $moduleinfo->sendlatenotifications = 0;
            $moduleinfo->requiresubmissionstatement = 0;
            $moduleinfo->grade = 100;
            $moduleinfo->teamsubmission = true;
            $moduleinfo->teamsubmissiongroupingid = $groupingid;
            $moduleinfo->requireallteammemberssubmit = false;
            $moduleinfo->blindmarking = false;
            $moduleinfo->markingworkflow = 0;
            $moduleinfo->markingallocation = 0;
            break;

        case 'chat':
            $moduleinfo->chattime = time();
            $moduleinfo->schedule = 0;
            break;

        case 'forum':
            $moduleinfo->type = 'general';
            break;

        case 'publication':
            require_once("$CFG->dirroot/mod/publication/locallib.php");
            $moduleinfo->obtainteacherapproval = 1; // In mod_publication, 1 means no teacher approval needed.
            break;

        case 'glossary':
            $moduleinfo->displayformat = 'dictionary';
            break;
    }

    $createdmoduleinfo = create_module($moduleinfo);
    return $createdmoduleinfo->coursemodule;
}
