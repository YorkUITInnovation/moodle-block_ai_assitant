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
        var selector = '.cria-launcher';

        var syncState = function() {
            $(selector).toggleClass('disabled', !isPublished);
        };

        $(document).ready(function() {
            syncState();

            var attempts = 0;
            var intervalId = setInterval(function() {
                attempts += 1;
                syncState();
                if ($(selector).length > 0 || attempts >= 50) {
                    clearInterval(intervalId);
                }
            }, 200);

            if (window.MutationObserver) {
                var observer = new MutationObserver(function() {
                    syncState();
                });

                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
        });
    };

    return {
        init: init
    };
});
