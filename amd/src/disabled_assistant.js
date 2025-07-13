/**
 * Module to handle disabled AI Assistant visual indicator
 *
 * @module     block_ai_assistant/disabled_assistant
 * @copyright  2022 UIT Innovation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {

    /**
     * Initialize the disabled assistant handler
     * @param {boolean} isPublished Whether the AI Assistant is published for students
     */
    var init = function(isPublished) {

        // Function to add disabled class to cria-launcher
        var addDisabledClass = function() {
            var criaLauncher = $('.cria-launcher');
            if (criaLauncher.length > 0) {
                criaLauncher.addClass('disabled');
            } else {
                // If element doesn't exist yet, wait and try again
                setTimeout(addDisabledClass, 100);
            }
        };

        // Only add disabled class if the assistant is not published
        if (!isPublished) {
            // Wait for the DOM to be ready and the cria-launcher to be created
            $(document).ready(function() {
                // Use a slight delay to ensure the cria-launcher element is created by the embed script
                setTimeout(addDisabledClass, 500);
            });
        }
    };

    return {
        init: init
    };
});
