<?php
// This file is part of Moodle - http://moodle.org/.
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
 * External Web Service Template
 *
 * @package    local_myddleware
 * @copyright  2017 Myddleware
 * @author     Myddleware ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/externallib.php");

use core_competency\user_competency;

/**
 * Myddleware external functions
 *
 * @package    local_myddleware
 * @category   external
 * @copyright  2017 Myddleware
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_myddleware_external extends external_api {

    /**
     * Returns description of method parameters.
     * @return external_function_parameters.
     */
    public static function get_users_completion_parameters() {
        return new external_function_parameters(
            array(
                'time_modified' => new external_value(
                                           PARAM_INT, get_string('param_timemodified', 'local_myddleware'), VALUE_DEFAULT, 0),
                'id' => new external_value(PARAM_INT, get_string('param_id'), VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * This function search completion created after the date $timemodified in parameters.
     * @param int $timemodified
     * @param int $id
     * @return an array with the detail of each completion (id,userid,completionstate,timemodified,moduletype,instance,courseid).
     */
    public static function get_users_completion($timemodified, $id) {
        global $USER, $DB;

        // Parameter validation.
        $params = self::validate_parameters(
            self::get_users_completion_parameters(),
            array('time_modified' => $timemodified, 'id' => $id)
        );

        // Context validation.
        $context = context_user::instance($USER->id);
        self::validate_context($context);

        require_capability('moodle/user:viewdetails', $context);

        // Prepare the query condition.
        if (!empty($id)) {
            $where = ' cmc.id = '.$params['id'];
        } else {
            $where = ' cmc.timemodified > '.$params['time_modified'];
        }

        // Retrieve token list (including linked users firstname/lastname and linked services name).
        $sql = "
                SELECT
                    cmc.id,
                    cmc.userid,
                    cmc.completionstate,
                    cmc.timemodified,
                    cm.id coursemoduleid,
                    cm.module moduletype,
                    cm.instance,
                    cm.section,
                    cm.course courseid
                FROM {course_modules_completion} cmc
                INNER JOIN {course_modules} cm
                    ON cm.id = cmc.coursemoduleid
                WHERE
                    ".$where."
                ORDER BY timemodified ASC
                    ";
        $rs = $DB->get_recordset_sql($sql);

        $completions = array();
        if (!empty($rs)) {
            foreach ($rs as $completionrecords) {
                foreach ($completionrecords as $key => $value) {
                    $completion[$key] = $value;
                }
                // Add information about the module.
                $modinfo = get_fast_modinfo($completion['courseid']);
                $cm = $modinfo->get_cm($completion['coursemoduleid']);
                $completion['modulename'] = $cm->modname;
                $completion['coursemodulename'] = $cm->name;
                $completions[] = $completion;
            }
        }
        return $completions;
    }

    /**
     * Returns description of method result value.
     * @return external_description.
     */
    public static function get_users_completion_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, get_string('return_id', 'local_myddleware')),
                    'userid' => new external_value(PARAM_INT, get_string('return_userid', 'local_myddleware')),
                    'instance' => new external_value(PARAM_INT, get_string('return_instance', 'local_myddleware')),
                    'section' => new external_value(PARAM_INT, get_string('return_section', 'local_myddleware')),
                    'courseid' => new external_value(PARAM_INT, get_string('return_courseid', 'local_myddleware')),
                    'coursemoduleid' => new external_value(PARAM_INT, get_string('return_coursemoduleid', 'local_myddleware')),
                    'moduletype' => new external_value(PARAM_INT, get_string('return_moduletype', 'local_myddleware')),
                    'modulename' => new external_value(PARAM_TEXT, get_string('return_modulename', 'local_myddleware')),
                    'coursemodulename' => new external_value(PARAM_TEXT, get_string('return_coursemodulename', 'local_myddleware')),
                    'completionstate' => new external_value(PARAM_INT, get_string('return_completionstate', 'local_myddleware')),
                    'timemodified' => new external_value(PARAM_INT, get_string('return_timemodified', 'local_myddleware'))
                )
            )
        );
    }


    /**
     * Returns description of method parameters.
     * @return external_function_parameters.
     */
    public static function get_users_last_access_parameters() {
        return new external_function_parameters(
            array(
                'time_modified' => new external_value(
                                           PARAM_INT, get_string('param_timemodified', 'local_myddleware'), VALUE_DEFAULT, 0),
                'id' => new external_value(PARAM_INT, get_string('param_id'), VALUE_DEFAULT, 0),
            )
        );
    }



    /**
     * This function search the last access for all users and courses.
     * Only access after the $timemodified are returned.
     * @param int $timemodified
     * @param int $id
     * @return an array with the detail of each access (id, userid, access time and courseid).
     */
    public static function get_users_last_access($timemodified, $id) {
        global $USER, $DB;
        // Parameter validation.
        $params = self::validate_parameters(
            self::get_users_last_access_parameters(),
            array('time_modified' => $timemodified, 'id' => $id)
        );

        // Context validation.
        $context = context_user::instance($USER->id);
        self::validate_context($context);

        require_capability('moodle/user:viewdetails', $context);

        // Prepare the query condition.
        if (!empty($id)) {
            $where = ' la.id = '.$params['id'];
        } else {
            $where = ' la.timeaccess > '.$params['time_modified'];
        }

        $sql = "
                SELECT
                    la.id,
                    la.userid,
                    la.courseid,
                    la.timeaccess lastaccess
                FROM {user_lastaccess} la
                WHERE
                    ".$where."
                ";
        $rs = $DB->get_recordset_sql($sql);

        $lastaccess = array();
        if (!empty($rs)) {
            foreach ($rs as $lastaccessrecords) {
                foreach ($lastaccessrecords as $key => $value) {
                    $access[$key] = $value;
                }
                $lastaccess[] = $access;
            }
        }
        return $lastaccess;
    }



    /**
     * Returns description of method result value.
     * @return external_description.
     */
    public static function get_users_last_access_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, get_string('return_id', 'local_myddleware')),
                    'userid' => new external_value(PARAM_INT, get_string('return_userid', 'local_myddleware')),
                    'courseid' => new external_value(PARAM_INT, get_string('return_courseid', 'local_myddleware')),
                    'lastaccess' => new external_value(PARAM_INT, get_string('return_lastaccess', 'local_myddleware'))
                )
            )
        );
    }

    /**
     * Returns description of method parameters.
     * @return external_function_parameters.
     */
    public static function get_courses_by_date_parameters() {
        return new external_function_parameters(
            array(
                'time_modified' => new external_value(
                                           PARAM_INT, get_string('param_timemodified', 'local_myddleware'), VALUE_DEFAULT, 0),
                'id' => new external_value(PARAM_INT, get_string('param_id'), VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * This function search the courses created or modified after after the datime $timemodified.
     * @param int $timemodified
     * @param int $id
     * @return the list of course.
     */
    public static function get_courses_by_date($timemodified, $id) {
        global $USER, $DB, $CFG;
        require_once($CFG->dirroot . "/course/externallib.php");

        // Parameter validation.
        $params = self::validate_parameters(
            self::get_courses_by_date_parameters(),
            array('time_modified' => $timemodified, 'id' => $id)
        );

        // Prepare the query condition.
        if (!empty($id)) {
            $where = ' id = '.$params['id'];
        } else {
            $where = ' timemodified > '.$params['time_modified'];
        }

        // Select the courses modified after the datime $timemodified. We select them order by timemodified ascending.
        $selectedcourses = $DB->get_records_select('course', $where, array(), ' timemodified ASC ', 'id');

        $returnedcourses = array();
        if (!empty($selectedcourses)) {
            // Call the function get_courses for each course to keep the timemodified order.
            foreach ($selectedcourses as $key => $value) {
                // Call the standard API function to return the course detail.
                $coursedetails = core_course_external::get_courses(array('ids' => array($value->id)));
                $returnedcourses[] = $coursedetails[0];
            }
        }
        return $returnedcourses;
    }


    /**
     * Returns description of method result value.
     * @return external_description.
     */
    public static function get_courses_by_date_returns() {
        global $USER, $DB, $CFG;
        require_once($CFG->dirroot . "/course/externallib.php");
        // We use the same result than the function get_courses.
        return core_course_external::get_courses_returns();
    }




    /**
     * Returns description of method parameters.
     * @return external_function_parameters.
     */
    public static function get_users_by_date_parameters() {
        return new external_function_parameters(
            array(
                'time_modified' => new external_value(
                                           PARAM_INT, get_string('param_timemodified', 'local_myddleware'), VALUE_DEFAULT, 0),
                'id' => new external_value(PARAM_INT, get_string('param_id'), VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * This function search the users created or modified after after the datime $timemodified.
     * @param int $timemodified
     * @param int $id
     * @return the list of user.
     */
    public static function get_users_by_date($timemodified, $id) {
        global $USER, $DB, $CFG;
        require_once($CFG->dirroot . "/user/externallib.php");

        // Parameter validation.
        $params = self::validate_parameters(
            self::get_users_by_date_parameters(),
            array('time_modified' => $timemodified, 'id' => $id)
        );

        // Prepare the query condition.
        if (!empty($id)) {
            $where = ' deleted = 0 AND id = '.$params['id'];
        } else {
            $where = ' deleted = 0 AND timemodified > '.$params['time_modified'];
        }

        // Select the users modified after the datime $timemodified.
        $additionalfields = 'id, timemodified,lastnamephonetic,firstnamephonetic,middlename,alternatename';
        $selectedusers = $DB->get_records_select('user', $where, array(), ' timemodified ASC ', $additionalfields);
        $returnedusers = array();
        if (!empty($selectedusers)) {
            // Call function get_users for each user found.
            foreach ($selectedusers as $user) {
                $userdetails = array();
                $userdetails = core_user_external::get_users(array( 'criteria' => array( 'key' => 'id', 'value' => $user->id ) ));
                // Add fields not returned by standard function.
                $userdetails['users'][0]['timemodified'] = $user->timemodified;
                $userdetails['users'][0]['lastnamephonetic'] = $user->lastnamephonetic;
                $userdetails['users'][0]['firstnamephonetic'] = $user->firstnamephonetic;
                $userdetails['users'][0]['middlename'] = $user->middlename;
                $userdetails['users'][0]['alternatename'] = $user->alternatename;
                $returnedusers[] = $userdetails['users'][0];
            }
        }
        return $returnedusers;
    }


    /**
     * Returns description of method result value.
     * @return external_description.
     */
    public static function get_users_by_date_returns() {
        global $USER, $DB, $CFG;
        require_once($CFG->dirroot . "/user/externallib.php");
        // Add fields not returned by standard function even if exists in the database, table user.
        $timemodified = array(
                    'timemodified' => new external_value(
                        PARAM_INT,
                        get_string('param_timemodified', 'local_myddleware'),
                        VALUE_DEFAULT,
                        0,
                        NULL_NOT_ALLOWED
                    ),
                    'lastnamephonetic' => new external_value(
                        PARAM_TEXT,
                        get_string('param_lastnamephonetic', 'local_myddleware'),
                        VALUE_DEFAULT,
                        0
                    ),
                    'firstnamephonetic' => new external_value(
                        PARAM_TEXT,
                        get_string('param_firstnamephonetic', 'local_myddleware'),
                        VALUE_DEFAULT,
                        0
                    ),
                    'middlename' => new external_value(
                        PARAM_TEXT,
                        get_string('param_middlename', 'local_myddleware'),
                        VALUE_DEFAULT,
                        0
                    ),
                    'alternatename' => new external_value(
                        PARAM_TEXT,
                        get_string('param_alternatename', 'local_myddleware'),
                        VALUE_DEFAULT,
                        0
                    )
                );
        // We use the same structure than in the function get_users.
        $userfields = core_user_external::user_description($timemodified);
        return new external_multiple_structure($userfields);
    }


    /**
     * Returns description of method parameters.
     * @return external_function_parameters.
     */
    public static function get_enrolments_by_date_parameters() {
        return new external_function_parameters(
            array(
                'time_modified' => new external_value(
                                           PARAM_INT, get_string('param_timemodified', 'local_myddleware'), VALUE_DEFAULT, 0),
                'id' => new external_value(PARAM_INT, get_string('param_id'), VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * This function search the enrolments modified after after the datime $timemodified.
     * @param int $timemodified
     * @param int $id
     * @return the list of course.
     */
    public static function get_enrolments_by_date($timemodified, $id) {
        global $USER, $DB, $CFG;
        require_once($CFG->dirroot . "/course/externallib.php");

        // Parameter validation.
        $params = self::validate_parameters(
            self::get_enrolments_by_date_parameters(),
            array('time_modified' => $timemodified, 'id' => $id)
        );

        // Prepare the query condition.
        if (!empty($id)) {
            $where = ' id = '.$params['id'];
        } else {
            $where = ' timemodified > '.$params['time_modified'];
        }

        $returnenrolments = array();
        // Select enrolment modified after the date in input.
        $userenrolments = $DB->get_records_select('user_enrolments', $where, array(),  ' timemodified ASC ', '*');
        if (!empty($userenrolments)) {
            foreach ($userenrolments as $userenrolment) {
                $instance = array();
                // Get the enrolement detail (course, role and method).
                $instance = $DB->get_record('enrol', ['id' => $userenrolment->enrolid], '*', MUST_EXIST);
                // Prepare result.
                if (!empty($instance)) {
                    $userenroldata = [
                        'id'             => $userenrolment->id,
                        'userid'         => $userenrolment->userid,
                        'courseid'         => $instance->courseid,
                        'roleid'         => $instance->roleid,
                        'status'         => $userenrolment->status,
                        'enrol'         => $instance->enrol,
                        'timestart'     => $userenrolment->timestart,
                        'timeend'         => $userenrolment->timeend,
                        'timecreated'    => $userenrolment->timecreated,
                        'timemodified'     => $userenrolment->timemodified,
                    ];
                    $returnenrolments[] = $userenroldata;
                }
            }
        }

        return $returnenrolments;
    }


    /**
     * Returns description of method result value.
     * @return external_description.
     */
    public static function get_enrolments_by_date_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, get_string('return_id', 'local_myddleware')),
                    'userid' => new external_value(PARAM_INT, get_string('return_userid', 'local_myddleware')),
                    'courseid' => new external_value(PARAM_INT, get_string('return_courseid', 'local_myddleware')),
                    'roleid' => new external_value(PARAM_INT, get_string('return_roleid', 'local_myddleware')),
                    'status' => new external_value(PARAM_TEXT, get_string('return_status', 'local_myddleware')),
                    'enrol' => new external_value(PARAM_TEXT, get_string('return_enrol', 'local_myddleware')),
                    'timestart' => new external_value(PARAM_INT, get_string('return_timestart', 'local_myddleware')),
                    'timeend' => new external_value(PARAM_INT, get_string('return_timeend', 'local_myddleware')),
                    'timecreated' => new external_value(PARAM_INT, get_string('return_timecreated', 'local_myddleware')),
                    'timemodified' => new external_value(PARAM_INT, get_string('return_timemodified', 'local_myddleware'))
                )
            )
        );
    }


    /**
     * Returns description of method parameters.
     * @return external_function_parameters.
     */
    public static function get_course_completion_by_date_parameters() {
        return new external_function_parameters(
            array(
                'time_modified' => new external_value(
                                           PARAM_INT, get_string('param_timemodified', 'local_myddleware'), VALUE_DEFAULT, 0),
                'id' => new external_value(PARAM_INT, get_string('param_id'), VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * This function search completion created after the date $timemodified in parameters.
     * @param int $timemodified
     * @param int $id
     * @return an array with the detail of each completion (id,userid,completionstate,timemodified,moduletype,instance,courseid).
     */
    public static function get_course_completion_by_date($timemodified, $id) {
        global $USER, $DB, $CFG;
        require_once($CFG->dirroot . "/course/externallib.php");

        // Parameter validation.
        $params = self::validate_parameters(
            self::get_course_completion_by_date_parameters(),
            array('time_modified' => $timemodified, 'id' => $id)
        );

        // Prepare the query condition.
        if (!empty($id)) {
            $where = ' id = '.$params['id'];
        } else {
            $where = ' timecompleted > '.$params['time_modified']. ' OR reaggregate > 0 ';
        }

        $returncompletions = array();
        // Select enrolment modified after the date in input.
        $selectedcompletions = $DB->get_records_select('course_completions', $where, array(),  ' timecompleted ASC ', '*');
        if (!empty($selectedcompletions)) {
            // Date ref management : date ref is usually the timecompleted.
            // But if reaggregate is not empty we have to keep the smaller value of this field.
            $daterefoverride = -1;
            // Reaggregate could be set.
            // In this case, the timecompleted will be updated with the time in reaggregate field the next time the cron job runs.
            // So we have to keep reaggregate as the reference date.
            // Because we have to read the completion after the next cron job runs.
            foreach ($selectedcompletions as $selectedcompletion) {
                // Keep the smaller value of reaggregateif it exists.
                if (

                        $selectedcompletion->reaggregate > 0
                    && (
                            $selectedcompletion->reaggregate < $daterefoverride
                         || $daterefoverride == -1
                    )
                ) {
                    $daterefoverride = $selectedcompletion->reaggregate;
                }
            }

            // Prepare result.
            foreach ($selectedcompletions as $selectedcompletion) {
                // We keep only completion with timecompleted not null.
                // Ssome completion could have reaggregate not null and timecompleted null.
                if (empty($selectedcompletion->timecompleted)) {
                    continue;
                }
                // Set date_ref_override if there is at least one reaggregate value (-1 second because we use > in Myddleware).
                $completiondata = [
                    'id'                 => $selectedcompletion->id,
                    'userid'             => $selectedcompletion->userid,
                    'courseid'             => $selectedcompletion->course,
                    'timeenrolled'         => $selectedcompletion->timeenrolled,
                    'timestarted'         => $selectedcompletion->timestarted,
                    'timecompleted'     => $selectedcompletion->timecompleted,
                    'date_ref_override' => ($daterefoverride != -1 ? $daterefoverride - 1 : 0)
                ];
                // Prepare result.
                $returncompletions[] = $completiondata;
            }
        }
        return $returncompletions;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_course_completion_by_date_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, get_string('return_id', 'local_myddleware')),
                    'userid' => new external_value(PARAM_INT, get_string('return_userid', 'local_myddleware')),
                    'courseid' => new external_value(PARAM_INT, get_string('return_courseid', 'local_myddleware')),
                    'timeenrolled' => new external_value(PARAM_INT, get_string('return_timeenrolled', 'local_myddleware')),
                    'timestarted' => new external_value(PARAM_INT, get_string('return_timestarted', 'local_myddleware')),
                    'timecompleted' => new external_value(PARAM_INT, get_string('return_timecompleted', 'local_myddleware')),
                    'date_ref_override' => new external_value(PARAM_INT, get_string('return_date_ref_override', 'local_myddleware'))
                )
            )
        );
    }


     /**
      * Returns description of method parameters.
      * @return external_function_parameters.
      */
    public static function get_user_compentencies_by_date_parameters() {
        return new external_function_parameters(
            array(
                'time_modified' => new external_value(
                                           PARAM_INT, get_string('param_timemodified', 'local_myddleware'), VALUE_DEFAULT, 0),
                'id' => new external_value(PARAM_INT, get_string('param_id'), VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * This function search the user competencies created or modified after after the datime $timemodified.
     * @param int $timemodified
     * @param int $id
     * @return the list of course.
     */
    public static function get_user_compentencies_by_date($timemodified, $id) {
        global $USER, $DB;
        // Parameter validation.
        $params = self::validate_parameters(
            self::get_user_compentencies_by_date_parameters(),
            array('time_modified' => $timemodified, 'id' => $id)
        );

        // Context validation.
        $context = context_user::instance($USER->id);
        self::validate_context($context);
        require_capability('moodle/competency:usercompetencyview', $context);

        // Prepare the query condition.
        if (!empty($id)) {
            $where = ' id = '.$params['id'];
        } else {
            $where = ' timemodified > '.$params['time_modified'];
        }

        // Select the user compencies modified after the datime $timemodified. We select them order by timemodified ascending.
        $selectedusercompetencies = $DB->get_records_select('competency_usercomp', $where, array(), ' timemodified ASC ');

        // Prepare result.
        $returnedusercompetencies = array();
        if (!empty($selectedusercompetencies)) {
            foreach ($selectedusercompetencies as $selectedusercompetency) {
                // Add competency header data to the user compentency.
                $competency = user_competency::get_competency_by_usercompetencyid($selectedusercompetency->id);
                $selectedusercompetency->competency_shortname                 = $competency->get('shortname');
                $selectedusercompetency->competency_description             = $competency->get('description');
                $selectedusercompetency->competency_descriptionformat         = $competency->get('descriptionformat');
                $selectedusercompetency->competency_idnumber                 = $competency->get('idnumber');
                $selectedusercompetency->competency_competencyframeworkid     = $competency->get('competencyframeworkid');
                $selectedusercompetency->competency_parentid                 = $competency->get('parentid');
                $selectedusercompetency->competency_path                     = $competency->get('path');
                $selectedusercompetency->competency_sortorder                 = $competency->get('sortorder');
                $selectedusercompetency->competency_ruletype                 = $competency->get('ruletype');
                $selectedusercompetency->competency_ruleoutcome             = $competency->get('ruleoutcome');
                $selectedusercompetency->competency_ruleconfig                 = $competency->get('ruleconfig');
                $selectedusercompetency->competency_scaleid                 = $competency->get('scaleid');
                $selectedusercompetency->competency_scaleconfiguration         = $competency->get('scaleconfiguration');
                $selectedusercompetency->competency_timecreated             = $competency->get('timecreated');
                $selectedusercompetency->competency_timemodified             = $competency->get('timemodified');
                $selectedusercompetency->competency_usermodified            = $competency->get('usermodified');
                $returnedusercompetencies[] = $selectedusercompetency;
            }
        }
        return $returnedusercompetencies;
    }


    /**
     * Returns description of method result value.
     * @return external_description.
     */
    public static function get_user_compentencies_by_date_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, get_string('return_id', 'local_myddleware')),
                    'userid' => new external_value(PARAM_INT, get_string('return_userid', 'local_myddleware')),
                    'competencyid' => new external_value(PARAM_INT, get_string('return_competencyid', 'local_myddleware')),
                    'status' => new external_value(PARAM_INT, get_string('return_status', 'local_myddleware')),
                    'reviewerid' => new external_value(PARAM_INT, get_string('return_reviewerid', 'local_myddleware')),
                    'proficiency' => new external_value(PARAM_INT, get_string('return_proficiency', 'local_myddleware')),
                    'grade' => new external_value(PARAM_INT, get_string('return_grade', 'local_myddleware')),
                    'timecreated' => new external_value(PARAM_INT, get_string('return_timecreated', 'local_myddleware')),
                    'timemodified' => new external_value(PARAM_INT, get_string('return_timemodified', 'local_myddleware')),
                    'usermodified' => new external_value(PARAM_INT, get_string('return_usermodified', 'local_myddleware')),
                    'competency_shortname' => new external_value(
                        PARAM_TEXT, get_string('return_competency_shortname', 'local_myddleware')),
                    'competency_description' => new external_value(
                        PARAM_TEXT, get_string('return_competency_description', 'local_myddleware')),
                    'competency_descriptionformat' => new external_value(
                        PARAM_INT, get_string('return_competency_descriptionformat', 'local_myddleware')),
                    'competency_idnumber' => new external_value(
                        PARAM_TEXT, get_string('return_competency_idnumber', 'local_myddleware')),
                    'competency_competencyframeworkid' => new external_value(
                        PARAM_INT, get_string('return_competency_competencyframeworkid', 'local_myddleware')),
                    'competency_parentid' => new external_value(
                        PARAM_INT, get_string('return_competency_parentid', 'local_myddleware')),
                    'competency_path' => new external_value(
                        PARAM_TEXT, get_string('return_competency_path', 'local_myddleware')),
                    'competency_sortorder' => new external_value(
                        PARAM_INT, get_string('return_competency_sortorder', 'local_myddleware')),
                    'competency_ruletype' => new external_value(
                        PARAM_TEXT, get_string('return_competency_ruletype', 'local_myddleware')),
                    'competency_ruleoutcome' => new external_value(
                        PARAM_INT, get_string('return_competency_ruleoutcome', 'local_myddleware')),
                    'competency_ruleconfig' => new external_value(
                        PARAM_TEXT, get_string('return_competency_ruleconfig', 'local_myddleware')),
                    'competency_scaleid' => new external_value(
                        PARAM_INT, get_string('return_competency_scaleid', 'local_myddleware')),
                    'competency_scaleconfiguration' => new external_value(
                        PARAM_TEXT, get_string('return_competency_scaleconfiguration', 'local_myddleware')),
                    'competency_timecreated' => new external_value(
                        PARAM_INT, get_string('return_competency_timecreated', 'local_myddleware')),
                    'competency_timemodified' => new external_value(
                        PARAM_INT, get_string('return_competency_timemodified', 'local_myddleware')),
                    'competency_usermodified' => new external_value(
                        PARAM_INT, get_string('return_competency_usermodified', 'local_myddleware'))
                )
            )
        );
    }

     /**
      * Returns description of method parameters.
      * @return external_function_parameters.
      */
    public static function get_competency_module_completion_by_date_parameters() {
        return new external_function_parameters(
            array(
                'time_modified' => new external_value(
                                           PARAM_INT, get_string('param_timemodified', 'local_myddleware'), VALUE_DEFAULT, 0),
                'id' => new external_value(PARAM_INT, get_string('param_id'), VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * This function search the user competencies created or modified after after the datime $timemodified.
     * @param int $timemodified
     * @param int $id
     * @return the list of course.
     */
    public static function get_competency_module_completion_by_date($timemodified, $id) {
        global $USER, $DB;
        // Parameter validation.
        $params = self::validate_parameters(
            self::get_user_compentencies_by_date_parameters(),
            array('time_modified' => $timemodified, 'id' => $id)
        );

        // Context validation.
        $context = context_user::instance($USER->id);
        self::validate_context($context);
        require_capability('moodle/competency:usercompetencyview', $context);

        // Prepare the query condition.
        if (!empty($id)) {
            $where = ' id = '.$params['id'];
        } else {
            $where = ' timemodified > '.$params['time_modified'];
        }

        // Select the user compencies modified after the datime $timemodified. We select them order by timemodified ascending.
        $selectedcompetencymodulecompletions = $DB->get_records_select(
                                                         'competency_modulecomp', $where, array(), ' timemodified ASC ');

        // Prepare result.
        $returnedcompetencymodulecompletion = array();
        if (!empty($selectedcompetencymodulecompletions)) {
            foreach ($selectedcompetencymodulecompletions as $selectedcompetencymodulecompletion) {
                $competencymodulecompletion = [
                    'id'           => $selectedcompetencymodulecompletion->id,
                    'cmid'         => $selectedcompetencymodulecompletion->cmid,
                    'timecreated'  => $selectedcompetencymodulecompletion->timecreated,
                    'timemodified' => $selectedcompetencymodulecompletion->timemodified,
                    'usermodified' => $selectedcompetencymodulecompletion->usermodified,
                    'sortorder'    => $selectedcompetencymodulecompletion->sortorder,
                    'competencyid' => $selectedcompetencymodulecompletion->competencyid,
                    'ruleoutcome'  => $selectedcompetencymodulecompletion->ruleoutcome
                ];
                // Get the course id.
                $selectedcoursemodule = $DB->get_records_select(
                                                 'course_modules', 'id = '.$selectedcompetencymodulecompletion->cmid, array(), '');
                $competencymodulecompletion['courseid'] = current($selectedcoursemodule)->course;
                if (!empty($competencymodulecompletion['courseid'])) {
                    // Add information about the module.
                    $modinfo = get_fast_modinfo($competencymodulecompletion['courseid']);
                    $cm = $modinfo->get_cm($selectedcompetencymodulecompletion->cmid);
                    $competencymodulecompletion['modulename'] = $cm->modname;
                    $competencymodulecompletion['coursemodulename'] = $cm->name;
                }
                // Prepare result.
                $returnedcompetencymodulecompletion[] = $competencymodulecompletion;
            }
        }
        return $returnedcompetencymodulecompletion;
    }


    /**
     * Returns description of method result value.
     * @return external_description.
     */
    public static function get_competency_module_completion_by_date_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, get_string('return_id', 'local_myddleware')),
                    'cmid' => new external_value(PARAM_INT, get_string('return_coursemoduleid', 'local_myddleware')),
                    'timecreated' => new external_value(PARAM_INT, get_string('return_timecreated', 'local_myddleware')),
                    'timemodified' => new external_value(PARAM_INT, get_string('return_timemodified', 'local_myddleware')),
                    'usermodified' => new external_value(PARAM_INT, get_string('return_usermodified', 'local_myddleware')),
                    'sortorder' => new external_value(PARAM_INT, get_string('return_sortorder', 'local_myddleware')),
                    'competencyid' => new external_value(PARAM_INT, get_string('return_competencyid', 'local_myddleware')),
                    'ruleoutcome' => new external_value(PARAM_INT, get_string('return_ruleoutcome', 'local_myddleware')),
                    'courseid' => new external_value(PARAM_INT, get_string('return_courseid', 'local_myddleware')),
                    'modulename' => new external_value(PARAM_TEXT, get_string('return_modulename', 'local_myddleware')),
                    'coursemodulename' => new external_value(PARAM_TEXT, get_string('return_coursemodulename', 'local_myddleware'))
                )
            )
        );
    }

    /**
     * Returns description of method parameters.
     * @return external_function_parameters.
     */
    public static function get_user_grades_parameters() {
        return new external_function_parameters(
            array(
                'time_modified' => new external_value(
                                           PARAM_INT, get_string('param_timemodified', 'local_myddleware'), VALUE_DEFAULT, 0),
                'id' => new external_value(PARAM_INT, get_string('param_id'), VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * This function search completion created after the date $timemodified in parameters.
     * @param int $timemodified
     * @param int $id
     * @return an array with the detail of each completion (id,userid,completionstate,timemodified,moduletype,instance,courseid).
     */
    public static function get_user_grades($timemodified, $id) {
        global $USER, $DB;

        // Parameter validation.
        $params = self::validate_parameters(
            self::get_users_completion_parameters(),
            array('time_modified' => $timemodified, 'id' => $id)
        );

        // Context validation.
        $context = context_user::instance($USER->id);
        self::validate_context($context);

        require_capability('moodle/user:viewdetails', $context);

        // Prepare the query condition.
        if (!empty($id)) {
            $where = ' grd.id = '.$params['id'];
        } else {
            $where = ' grd.timemodified > '.$params['time_modified'];
        }

        // Retrieve token list (including linked users firstname/lastname and linked services name).
        $sql = "
                SELECT
                    grd.id,
                    grd.itemid,
                    grd.userid,
                    grd.rawgrade,
                    grd.rawgrademax,
                    grd.rawgrademin,
                    grd.rawscaleid,
                    grd.usermodified,
                    grd.finalgrade,
                    grd.hidden,
                    grd.locked,
                    grd.locktime,
                    grd.exported,
                    grd.overridden,
                    grd.excluded,
                    grd.feedback,
                    grd.feedbackformat,
                    grd.information,
                    grd.informationformat,
                    grd.timecreated,
                    grd.timemodified,
                    grd.aggregationstatus,
                    grd.aggregationweight,
                    itm.courseid,
                    itm.itemname,
                    crs.fullname course_fullname,
                    crs.shortname course_shortname
                FROM {grade_grades} grd
                INNER JOIN {grade_items} itm
                    ON grd.itemid = itm.id
                LEFT OUTER JOIN {course} crs
                    ON itm.courseid = crs.id
                WHERE
                    ".$where."
                ORDER BY grd.timemodified ASC, grd.id ASC
                    ";
        $rs = $DB->get_recordset_sql($sql);

        $grades = array();
        if (!empty($rs)) {
            foreach ($rs as $graderecords) {
                foreach ($graderecords as $key => $value) {
                    $grade[$key] = $value;
                }
                $grades[] = $grade;
            }
        }
        return $grades;
    }

     /**
      * Returns description of method result value.
      * @return external_description.
      */
    public static function get_user_grades_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, get_string('return_id', 'local_myddleware')),
                    'itemid' => new external_value(PARAM_INT, get_string('return_itemid', 'local_myddleware')),
                    'userid' => new external_value(PARAM_INT, get_string('return_userid', 'local_myddleware')),
                    'rawgrade' => new external_value(PARAM_FLOAT, get_string('return_rawgrade', 'local_myddleware')),
                    'rawgrademax' => new external_value(PARAM_FLOAT, get_string('return_rawgrademax', 'local_myddleware')),
                    'rawgrademin' => new external_value(PARAM_FLOAT, get_string('return_rawgrademin', 'local_myddleware')),
                    'rawscaleid' => new external_value(PARAM_INT, get_string('return_rawscaleid', 'local_myddleware')),
                    'usermodified' => new external_value(PARAM_INT, get_string('return_usermodified', 'local_myddleware')),
                    'finalgrade' => new external_value(PARAM_FLOAT, get_string('return_finalgrade', 'local_myddleware')),
                    'hidden' => new external_value(PARAM_INT, get_string('return_hidden', 'local_myddleware')),
                    'locked' => new external_value(PARAM_INT, get_string('return_locked', 'local_myddleware')),
                    'locktime' => new external_value(PARAM_INT, get_string('return_locktime', 'local_myddleware')),
                    'exported' => new external_value(PARAM_INT, get_string('return_exported', 'local_myddleware')),
                    'overridden' => new external_value(PARAM_INT, get_string('return_overridden', 'local_myddleware')),
                    'excluded' => new external_value(PARAM_INT, get_string('return_excluded', 'local_myddleware')),
                    'feedback' => new external_value(PARAM_TEXT, get_string('return_feedback', 'local_myddleware')),
                    'feedbackformat' => new external_value(PARAM_INT, get_string('return_feedbackformat', 'local_myddleware')),
                    'information' => new external_value(PARAM_TEXT, get_string('return_information', 'local_myddleware')),
                    'informationformat' => new external_value(
                        PARAM_INT, get_string('return_informationformat', 'local_myddleware')),
                    'timecreated' => new external_value(PARAM_INT, get_string('return_timecreated', 'local_myddleware')),
                    'timemodified' => new external_value(PARAM_INT, get_string('return_timemodified', 'local_myddleware')),
                    'aggregationstatus' => new external_value(
                        PARAM_TEXT, get_string('return_aggregationstatus', 'local_myddleware')),
                    'aggregationweight' => new external_value(
                        PARAM_FLOAT, get_string('return_aggregationweight', 'local_myddleware')),
                    'courseid' => new external_value(PARAM_INT, get_string('return_courseid', 'local_myddleware')),
                    'itemname' => new external_value(PARAM_TEXT, get_string('return_itemname', 'local_myddleware')),
                    'course_fullname' => new external_value(PARAM_TEXT, get_string('return_fullname', 'local_myddleware')),
                    'course_shortname' => new external_value(PARAM_TEXT, get_string('return_shortname', 'local_myddleware'))
                )
            )
        );
    }

}
