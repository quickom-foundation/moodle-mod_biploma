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

require_once $CFG->dirroot . '/mod/biploma/basic_env.php';
require_once $CFG->dirroot . '/mod/biploma/locallib.php';

/**
 * Add certificate instance.
 *
 * @param array $certificate
 * @return array $certificate new certificate object
 */
function biploma_add_instance($post) {
    global $DB, $CFG;

    $course = $DB->get_record('course', ['id' => $post->course], '*', MUST_EXIST);

    $group_id = sync_course_with_biploma($course, $post->instance, null);
    $template_id = null;

    // Issue certs
    if (isset($post->users)) {
        // Checklist array from the form comes in the format:
        // int user_id => boolean issue_certificate
        foreach ($post->users as $user_id => $issue_certificate) {
            if ($issue_certificate) {
                $user = $DB->get_record('user', ['id' => $user_id], '*', MUST_EXIST);

                $credential = local_create_credential($user, $group_id, $template_id);
            }
        }
    }

    // Set completion activitied to 0 if unchecked
    if (!property_exists($post, 'completionactivities')) {
        $post->completionactivities = 0;
    }

    // Save record
    $db_record = new stdClass();
    $db_record->completionactivities = $post->completionactivities;
    $db_record->name = $post->name;
    $db_record->course = $post->course;
    $db_record->finalquiz = $post->finalquiz;
    $db_record->passinggrade = $post->passinggrade;
    $db_record->timecreated = time();
    $db_record->groupid = $group_id;
    if (property_exists($post, 'templateid')) {
        $db_record->templateid = $post->templateid;
    }

    return $DB->insert_record('biploma', $db_record);
}

/**
 * Update certificate instance.
 *
 * @param stdClass $post
 * @return stdClass $certificate updated
 */
function biploma_update_instance($post) {
    // To update your certificate details, go to biploma.com.
    global $DB, $CFG;

    // don't know what this is
    $biploma_cm = get_coursemodule_from_id('biploma', $post->coursemodule, 0, false, MUST_EXIST);

    $biploma_certificate = $DB->get_record('biploma', ['id' => $post->instance], '*', MUST_EXIST);

    $course = $DB->get_record('course', ['id' => $post->course], '*', MUST_EXIST);

    // Update the group if we have one to sync with
    if ($biploma_certificate->groupid) {
        sync_course_with_biploma($course, $post->instance, $post->groupid);
    }

    $groupid = $biploma_certificate->groupid;
    $templateid = $biploma_certificate->templateid;

    // Issue certs for unissued users
    if (isset($post->unissuedusers)) {
        // Checklist array from the form comes in the format:
        // int user_id => boolean issue_certificate

        foreach ($post->unissuedusers as $user_id => $issue_certificate) {
            if ($issue_certificate) {
                $user = $DB->get_record('user', ['id' => $user_id], '*', MUST_EXIST);
                $completed_timestamp = biploma_manual_issue_completion_timestamp($biploma_certificate, $user);
                $completed_date = date('Y-m-d', (int) $completed_timestamp);
                if ($biploma_certificate->groupid) {
                    // Create the credential
                    $credential = local_create_credential($user, $groupid, $templateid, null, $completed_date);
                    // $credential_id = $credential->record_id;
                    // // Log the creation
                    // $event = biploma_log_creation(
                    //     $credential_id,
                    //     $user->id,
                    //     null,
                    //     $post->coursemodule
                    // );
                    // $event->trigger();
                }
            }
        }
    }

    // Issue certs
    if (isset($post->users)) {
        // Checklist array from the form comes in the format:
        // int user_id => boolean issue_certificate
        foreach ($post->users as $user_id => $issue_certificate) {
            if ($issue_certificate) {
                $user = $DB->get_record('user', ['id' => $user_id], '*', MUST_EXIST);
                $completed_timestamp = biploma_manual_issue_completion_timestamp($biploma_certificate, $user);
                $completed_date = date('Y-m-d', (int) $completed_timestamp);
                $credential = local_create_credential($user, $groupid, $templateid, null, $completed_date);
                // $credential_id = $credential->record_id;
                // // Log the creation
                // $event = biploma_log_creation(
                //     $credential_id,
                //     $user_id,
                //     null,
                //     $post->coursemodule
                // );
                // $event->trigger();
            }
        }
    }

    // If the group was changed we should save that
    if ($post->groupid) {
        $groupid = $post->groupid;
    } else {
        $groupid = $biploma_certificate->groupid;
    }

    // Set completion activitied to 0 if unchecked
    if (!property_exists($post, 'completionactivities')) {
        $post->completionactivities = 0;
    }

    // Save record
    $db_record = new stdClass();
    $db_record->id = $post->instance;
    $db_record->name = $post->name;
    $db_record->finalquiz = $post->finalquiz;
    $db_record->passinggrade = $post->passinggrade;
    $db_record->completionactivities = $post->completionactivities;

    $db_record->course = $post->course;
    $db_record->timecreated = time();
    $db_record->groupid = $groupid;

    if (property_exists($post, 'templateid')) {
        $db_record->templateid = $post->templateid;
    }

    return $DB->update_record('biploma', $db_record);
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance.
 *
 * @param int $id
 * @return bool true if successful
 */
function biploma_delete_instance($id) {
    global $DB;

    // Ensure the certificate exists
    if (!$certificate = $DB->get_record('biploma', ['id' => $id])) {
        return false;
    }

    return $DB->delete_records('biploma', ['id' => $id]);
}

/**
 * Supported feature list
 *
 * @uses FEATURE_MOD_INTRO
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function biploma_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:return false;
        default:return null;
    }
}