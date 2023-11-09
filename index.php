<?php

/*
Plugin Name: Gravity Forms - Limit Submissions
Description: Limits Gravity Forms submissions to one per user indefinitely.
Version: 1.2
Author: byFZK
*/

// Hook into Gravity Forms to add our settings field
add_filter('gform_form_settings', 'gf_limit_submissions_form_settings', 10, 2);
function gf_limit_submissions_form_settings($settings, $form) {
    // Add a checkbox setting to the form settings to enable the limit
    $isChecked = rgar($form, 'limitSubmissions') ? 'checked="checked"' : '';
    $settings['Form Basics']['limitSubmissions'] = '
        <tr>
            <th><label for="limitSubmissions">Limit Submissions</label></th>
            <td><input type="checkbox" id="limitSubmissions" name="limitSubmissions" value="1" ' . $isChecked . ' /> Limit form submissions to one per user.</td>
        </tr>';
    $settings['Form Basics']['submission_reset'] = '
        <tr>
            <th><label for="submission_reset">Reset Submissions</label></th>
            <td><input type="checkbox" id="submission_reset" name="submission_reset" value="1" /> Reset form submissions.</td>
        </tr>';

    return $settings;
}


add_filter('gform_pre_form_settings_save', 'gf_limit_submissions_save_form_settings');
function gf_limit_submissions_save_form_settings($form) {
    $form['limitSubmissions'] = rgpost('limitSubmissions');
    
    if (rgpost('submission_reset')) {
        // Define the meta key to look for
        $meta_key = 'gf_submission_count_form_' . $form['id'];

        // Query only the users with a submission count greater than 0
        $user_query = new WP_User_Query(array(
            'meta_key' => $meta_key,
            'meta_value' => '0',
            'meta_compare' => '>',
            'fields' => 'ids' // Only get the user IDs to improve performance
        ));

        $users_with_submissions = $user_query->get_results();

        if (!empty($users_with_submissions)) {
            foreach ($users_with_submissions as $user_id) {
                // Reset the submission count to 0
                update_user_meta($user_id, $meta_key, 0);
            }
        }

        // Set the form flag to false after reset
        $form['submission_reset_done'] = false;
    } else {
        // If the reset checkbox wasn't checked, do nothing
        $form['submission_reset_done'] = false;
    }

    return $form;
}





add_action('gform_after_submission', 'gf_increment_submission_count', 10, 2);
function gf_increment_submission_count($entry, $form) {
    if (rgar($form, 'limitSubmissions')) {
        $user_id = get_current_user_id();
        if ($user_id > 0) {
            $meta_key = 'gf_submission_count_form_' . $form['id'];
            // Retrieve the current submission count
            $submission_count = (int)get_user_meta($user_id, $meta_key, true);
            // Increment the submission count
            $submission_count++;
            // Update the user meta with the new count
            update_user_meta($user_id, $meta_key, $submission_count);

            // Unset the submission_reset_done flag after allowing a new submission
            if (isset($form['submission_reset_done']) && $form['submission_reset_done']) {
                // Save the updated form settings
                $form['submission_reset_done'] = false;
                GFAPI::update_form($form);
            }
        }
    }
}





add_filter('gform_get_form_filter', 'replace_form_with_message', 10, 2);
function replace_form_with_message($form_string, $form) {
    if (rgar($form, 'limitSubmissions') && !rgar($form, 'submission_reset_done')) {
        $user_id = get_current_user_id();
        if ($user_id > 0) {
            $meta_key = 'gf_submission_count_form_' . $form['id'];
            $submission_count = (int) get_user_meta($user_id, $meta_key, true);
            if ($submission_count > 0) {
                // Replace the entire form with a custom message
                return '<div class="form-limit-error">You have already filled out this form. If you believe this to be an error, please contact support.</div>';
            }
        }
    }
    return $form_string; // return the original form if no submissions have been made
}


?>
