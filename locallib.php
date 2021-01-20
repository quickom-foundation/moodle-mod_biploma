<?php

// This file is part of the Biploma Certificate module for Moodle - http://moodle.org/
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
 * Certificate module core interaction API
 *
 * @package    mod_biploma
 * @copyright  2020 Beowulf Blockchain.
 * @copyright  based on work by Accredible <dev@accredible.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// For composer dependencies
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/api.php';
require_once $CFG->dirroot . '/mod/biploma/basic_env.php';

use BPLM\Api;

/**
 * Sync the selected course information with a group on Biploma - returns a group ID. Optionally takes a group ID so we can set it and change the assigned group.
 *
 * @param stdClass $course
 * @param int|null $instance_id
 * @return int $groupid
 */
function sync_course_with_biploma($course, $instance_id = null, $group_id = null) {
    global $DB, $CFG;

    $api = new Api($CFG->biploma_api_key);

    $description = Html2Text\Html2Text::convert($course->summary);
    if (empty($description)) {
        $description = "Recipient has compeleted the achievement.";
    }

    // Just use the saved group ID
    if ($group_id == null) {
        // $group_id = $biploma_certificate->groupid;
    }

    // Update an existing
    if (null != $instance_id) {
        // get the group id
        $biploma_certificate = $DB->get_record('biploma', ['id' => $instance_id], '*', MUST_EXIST);

        try {
            // Update the group
            $group = $api->update_group($group_id, null, $course->fullname, $description, new moodle_url('/course/view.php', ['id' => $course->id]));

            return $group->group_id;
        } catch (ClientException $e) {
            // throw API exception
            // include the achievement id that triggered the error
            // direct the user to biploma's support
            // dump the achievement id to debug_info
            throw new moodle_exception('groupsyncerror', 'biploma', 'https://help.biploma.com/hc/en-us', $course->id, $course->id);
        }
        // making a new group
    } else {
        try {
            // Make a new Group on Biploma - use a random number to deal with duplicate course names.
            $group = $api->create_group($course->shortname . mt_rand(), $course->fullname, $description, new moodle_url('/course/view.php', ['id' => $course->id]));

            return $group->group_id;
        } catch (ClientException $e) {
            // throw API exception
            // include the achievement id that triggered the error
            // direct the user to biploma's support
            // dump the achievement id to debug_info
            throw new moodle_exception('groupsyncerror', 'biploma', 'https://help.biploma.com/hc/en-us', $course->id, $course->id);
        }
    }
}

/**
 * List all of the certificates with a specific achievement id
 *
 * @param string $group_id Limit the returned Credentials to a specific group ID.
 * @param string $template_id Limit the returned Credentials to a specific template ID.
 * @param string|null $email Limit the returned Credentials to a specific recipient's email address.
 * @return array[stdClass] $credentials
 */
function biploma_get_credentials($group_id, $template_id = null, $email = null) {
    global $CFG;

    $api = new Api($CFG->biploma_api_key);

    try {
        $credentials = $api->get_credentials($group_id, $template_id, $email);
        return $credentials;
    } catch (ClientException $e) {
        // throw API exception
        // include the achievement id that triggered the error
        // direct the user to biploma's support
        // dump the achievement id to debug_info
        $exceptionparam = new stdClass();
        $exceptionparam->group_id = $group_id;
        $exceptionparam->email = $email;
        $exceptionparam->last_response = $credentials_page;
        throw new moodle_exception('getcredentialserror', 'biploma', 'https://help.biploma.com/hc/en-us', $exceptionparam);
    }
}

/**
 * Check's if a credential exists for an email in a particular group
 * @param String $group_id
 * @param String $template_id
 * @param String $email
 * @return array[stdClass] || false
 */
function biploma_check_for_existing_credential($group_id, $template_id, $email) {
    global $DB, $CFG;

    $api = new Api($CFG->biploma_api_key);

    try {
        $credentials = $api->get_credentials_with_email($group_id, $template_id, $email);

        if ($credentials and $credentials[0]) {
            return $credentials[0];
        } else {
            return false;
        }

    } catch (ClientException $e) {
        // throw API exception
        // include the achievement id that triggered the error
        // direct the user to biploma's support
        // dump the achievement id to debug_info
        throw new moodle_exception('groupsyncerror', 'biploma', 'https://help.biploma.com/hc/en-us', $group_id, $group_id);
    }
}

/**
 * Checks if a user has earned a specific credential according to the activity settings
 * @param stdObject $record An Biploma activity record
 * @param stdObject $course
 * @param stdObject user
 * @return bool
 */
function biploma_check_if_cert_earned($record, $course, $user) {
    global $DB;

    $earned = false;

    // check for the existence of an activity instance and an auto-issue rule
    if ($record and ($record->finalquiz or $record->completionactivities)) {

        if ($record->finalquiz) {
            $quiz = $DB->get_record('quiz', ['id' => $record->finalquiz], '*', MUST_EXIST);

            // create that credential if it doesn't exist
            $users_grade = min((quiz_get_best_grade($quiz, $user->id) / $quiz->grade) * 100, 100);
            $grade_is_high_enough = ($users_grade >= $record->passinggrade);

            // check for pass
            if ($grade_is_high_enough) {
                // Student earned certificate through final quiz
                $earned = true;
            }
        }

        $completion_activities = local_unserialize_completion_array($record->completionactivities);
        // if this quiz is in the completion activities
        if (isset($completion_activities[$quiz->id])) {
            $completion_activities[$quiz->id] = true;
            $quiz_attempts = $DB->get_records('quiz_attempts', ['userid' => $user->id, 'state' => 'finished']);
            foreach ($quiz_attempts as $quiz_attempt) {
                // if this quiz was already attempted, then we shouldn't be issuing a certificate
                if ($quiz_attempt->quiz == $quiz->id && $quiz_attempt->attempt > 1) {
                    return null;
                }
                // otherwise, set this quiz as completed
                if (isset($completion_activities[$quiz_attempt->quiz])) {
                    $completion_activities[$quiz_attempt->quiz] = true;
                }
            }

            // but was this the last required activity that was completed?
            $course_complete = true;
            foreach ($completion_activities as $is_complete) {
                if (!$is_complete) {
                    $course_complete = false;
                }
            }
            // if it was the final activity
            if ($course_complete) {
                // Student earned certificate by completing completion activities
                $earned = true;
            }
        }

    }
    return $earned;
}

/**
 * Create a credential given a user and an existing group
 * @param stdObject $user
 * @param int $group_id
 * @return stdObject
 */
function local_create_credential($user, $group_id, $template_id, $event = null, $issued_on = null) {
    global $CFG;

    $api = new Api($CFG->biploma_api_key);

    try {
        $credential = $api->create_credential(fullname($user), $user->email, $group_id, $template_id, $issued_on);

        // log an event now we've created the credential if possible
        if ($event != null) {
            $certificate_event = \mod_biploma\event\certificate_created::create([
                'objectid' => $credential->record_id,
                'context' => context_module::instance($event->contextinstanceid),
                'relateduserid' => $event->relateduserid,
                'issued_on' => $issued_on,
            ]);
            $certificate_event->trigger();
        }

        return $credential;

    } catch (ClientException $e) {
        // throw API exception
        // include the achievement id that triggered the error
        // direct the user to biploma's support
        // dump the achievement id to debug_info
        throw new moodle_exception('credentialcreateerror', 'biploma', 'https://help.biploma.com/hc/en-us', $user->email, $group_id);
    }
}

/**
 * Get the groups for the issuer
 * @return type
 */
function biploma_get_groups() {
    global $CFG;

    $api = new Api($CFG->biploma_api_key);

    try {
        $response = $api->get_groups(10000, 1);

        $groups = [];
        for ($i = 0, $size = count($response); $i < $size; ++$i) {
            $groups[$response[$i]->group_id] = $response[$i]->group_name;
        }
        return $groups;

    } catch (ClientException $e) {
        // throw API exception
        // include the achievement id that triggered the error
        // direct the user to biploma's support
        // dump the achievement id to debug_info
        throw new moodle_exception('getgroupserror', 'biploma', 'https://help.biploma.com/hc/en-us');
    }
}

/**
 * Get the templates for the issuer
 * @return type
 */
function biploma_get_templates_2() {
    global $CFG;

    $api = new Api($CFG->biploma_api_key);

    try {
        $response = $api->get_templates(10000, 1);
        $templates = [];
        for ($i = 0, $size = count($response); $i < $size; ++$i) {
            $templates[$response[$i]->template_id] = $response[$i]->description;
        }
        return $templates;

    } catch (ClientException $e) {
        // throw API exception
        // include the achievement id that triggered the error
        // direct the user to biploma's support
        // dump the achievement id to debug_info
        throw new moodle_exception('getgroupserror', 'biploma', 'https://help.biploma.com/hc/en-us');
    }
}

// old below here

/**
 * List all of the issuer's templates
 *
 * @return array[stdClass] $templates
 */
function biploma_get_templates() {
    global $CFG;

    $curl = curl_init('https://api.biploma.com/v1/issuer/templates');
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Token token="' . $CFG->biploma_api_key . '"']);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    if (!$result = json_decode(curl_exec($curl))) {
        // throw API exception
        // direct the user to biploma's support
        // dump the achievement id to debug_info
        throw new moodle_exception('gettemplateserror', 'biploma', 'https://help.biploma.com/hc/en-us');
    }
    curl_close($curl);
    $templates = [];
    for ($i = 0, $size = count($result->templates); $i < $size; ++$i) {
        $templates[$result->templates[$i]->name] = $result->templates[$i]->name;
    }
    return $templates;
}

/*
 * biploma_log_creation
 */
function biploma_log_creation($certificate_id, $user_id, $course_id, $cm_id) {
    global $DB;

    // Get context
    $biploma_mod = $DB->get_record('modules', ['name' => 'biploma'], '*', MUST_EXIST);
    if ($cm_id) {
        $cm = $DB->get_record('course_modules', ['id' => (int) $cm_id], '*');
    } else {
        // this is an activity add, so we have to use $course_id
        $course_modules = $DB->get_records('course_modules', ['course' => $course_id, 'module' => $biploma_mod->id]);
        $cm = end($course_modules);
    }
    $context = context_module::instance($cm->id);

    return \mod_biploma\event\certificate_created::create([
        'objectid' => $certificate_id,
        'context' => $context,
        'relateduserid' => $user_id,
    ]);
}

/*
 * Quiz submission handler (checks for a completed course)
 *
 * @param core/event $event quiz mod attempt_submitted event
 */
function biploma_quiz_submission_handler($event) {
    global $DB, $CFG;
    require_once $CFG->dirroot . '/mod/quiz/lib.php';

    $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);

    $quiz = $event->get_record_snapshot('quiz', $attempt->quiz);
    $user = $DB->get_record('user', ['id' => $event->relateduserid]);
    if ($biploma_certificate_records = $DB->get_records('biploma', ['course' => $event->courseid])) {
        foreach ($biploma_certificate_records as $record) {
            // check for the existence of an activity instance and an auto-issue rule
            if ($record and ($record->finalquiz or $record->completionactivities)) {

                // Check if we have a group mapping - if not use the old logic
                if ($record->groupid) {
                    // check which quiz is used as the deciding factor in this course
                    if ($quiz->id == $record->finalquiz) {
                        // check for an existing certificate
                        $existing_certificate = biploma_check_for_existing_credential($record->groupid, $record->templateid, $user->email);

                        // create that credential if it doesn't exist...
                        if (!$existing_certificate) {
                            $users_grade = min((quiz_get_best_grade($quiz, $user->id) / $quiz->grade) * 100, 100);
                            $grade_is_high_enough = ($users_grade >= $record->passinggrade);

                            // check for pass
                            if ($grade_is_high_enough) {
                                // issue a ceritificate
                                local_create_credential($user, $record->groupid, $record->templateid);
                            }
                        }
                    }

                    $completion_activities = local_unserialize_completion_array($record->completionactivities);
                    // if this quiz is in the completion activities
                    if (isset($completion_activities[$quiz->id])) {
                        $completion_activities[$quiz->id] = true;
                        $quiz_attempts = $DB->get_records('quiz_attempts', ['userid' => $user->id, 'state' => 'finished']);
                        foreach ($quiz_attempts as $quiz_attempt) {
                            // if this quiz was already attempted, then we shouldn't be issuing a certificate
                            if ($quiz_attempt->quiz == $quiz->id && $quiz_attempt->attempt > 1) {
                                return null;
                            }
                            // otherwise, set this quiz as completed
                            if (isset($completion_activities[$quiz_attempt->quiz])) {
                                $completion_activities[$quiz_attempt->quiz] = true;
                            }
                        }

                        // but was this the last required activity that was completed?
                        $course_complete = true;
                        foreach ($completion_activities as $is_complete) {
                            if (!$is_complete) {
                                $course_complete = false;
                            }
                        }
                        // if it was the final activity
                        if ($course_complete) {
                            $existing_certificate = biploma_check_for_existing_credential($record->groupid, $record->templateid, $user->email);
                            // make sure there isn't already a certificate
                            if (!$existing_certificate) {
                                // issue a ceritificate
                                local_create_credential($user, $record->groupid, $record->templateid);
                            }
                        }
                    }

                }

            }
        }
    }
}

/*
 * Course completion handler
 *
 * @param core/event $event
 */
function biploma_course_completed_handler($event) {

    global $DB, $CFG;

    $user = $DB->get_record('user', ['id' => $event->relateduserid]);

    // Check we have a course record
    if ($biploma_certificate_records = $DB->get_records('biploma', ['course' => $event->courseid])) {
        foreach ($biploma_certificate_records as $record) {
            // check for the existence of an activity instance and an auto-issue rule
            if ($record and ($record->completionactivities && $record->completionactivities != 0)) {

                // Check if we have a group mapping - if not use the old logic
                if ($record->groupid) {
                    // create the credential
                    local_create_credential($user, $record->groupid, $record->templateid);
                }

            }
        }
    }
}

function local_serialize_completion_array($completion_array) {
    return base64_encode(serialize((array) $completion_array));
}

function local_unserialize_completion_array($completion_object) {
    return (array) unserialize(base64_decode($completion_object));
}

/* biploma_manual_issue_completion_timestamp()
 *
 *  Get a timestamp for when a student completed a course. This is
 *  used when manually issuing certs to get a proper issue date and
 *  for the course duration item. Currently checking for the date of
 *  the highest quiz attempt for the final quiz specified for that
 *  biploma activity.
 */
function biploma_manual_issue_completion_timestamp($biploma_record, $user) {
    global $DB;

    $completed_timestamp = false;

    if ($biploma_record->finalquiz) {
        // If there is a finalquiz set, that governs when the course is complete.

        $quiz = $DB->get_record('quiz', ['id' => $biploma_record->finalquiz], '*', MUST_EXIST);
        $totalrawscore = $quiz->sumgrades;
        $highest_attempt = null;

        $quiz_attempts = $DB->get_records('quiz_attempts', ['userid' => $user->id, 'state' => 'finished', 'quiz' => $biploma_record->finalquiz]);
        foreach ($quiz_attempts as $quiz_attempt) {
            if (!isset($highest_attempt)) {
                // First attempt in the loop, so currently the highest.
                $highest_attempt = $quiz_attempt;
                continue;
            }

            if ($quiz_attempt->sumgrades >= $highest_attempt->sumgrades) {
                // Compare raw sumgrades from attempt. It seems that moodle
                // doesn't allow the amount that questions are worth in a quiz
                // to change so this should be ok - the scale should be constant
                // across attempts
                $highest_attempt = $quiz_attempt;
            }
        }

        if (isset($highest_attempt)) {
            // At least one attempt was found
            $attemptrawscore = $highest_attempt->sumgrades;
            $grade = ($attemptrawscore / $totalrawscore) * 100;
            // Check if the grade is passing, and if so set completion time to the attempt timefinish
            if ($grade >= $biploma_record->passinggrade) {
                $completed_timestamp = $highest_attempt->timefinish;
            }
        }

    }

    // TODO: When is the completion if there are completion activities set?

    // Set timestamp to now if no good timestamp was found.
    if ($completed_timestamp === false) {
        $completed_timestamp = time();
    }

    return (int) $completed_timestamp;
}

function local_number_ending($number) {
    return ($number > 1) ? 's' : '';
}

function local_seconds_to_str($seconds) {
    $hours = floor(($seconds %= 86400) / 3600);
    if ($hours) {
        return $hours . ' hour' . local_number_ending($hours);
    }
    $minutes = floor(($seconds %= 3600) / 60);
    if ($minutes) {
        return $minutes . ' minute' . local_number_ending($minutes);
    }
    return $seconds . ' second' . local_number_ending($seconds);
}