import notification from 'core/notification';
import ajax from 'core/ajax';
import * as Str from 'core/str';

export const init = () => {
    display_modules();
    init_popovers();
};

/**
 * Initialise Bootstrap 5 popovers on the supported modules/formats buttons.
 * - data-bs-trigger="focus" dismisses on click away
 * - active class added on show, removed on hide for depressed/highlighted effect
 */
function init_popovers() {
    var popoverElements = document.querySelectorAll('[data-bs-toggle="popover"]');
    popoverElements.forEach(function(el) {
        // eslint-disable-next-line no-undef
        var pop = new bootstrap.Popover(el, {
            html: true,
            trigger: 'focus',
            placement: 'bottom'
        });
        el.addEventListener('show.bs.popover', function() {
            el.classList.add('active');
        });
        el.addEventListener('hide.bs.popover', function() {
            el.classList.remove('active');
        });
    });
}

/**
 * Display course modules
 */
function display_modules() {
    // Check to see if the button exists
    if (!document.getElementById('btn-ai-assistant-train-modules')) {
        return;
    }
    document.getElementById('btn-ai-assistant-train-modules').addEventListener('click', function () {
        // get data-courseid from current element
        var courseid = this.getAttribute('data-courseid');

        var display_modules_ajax = ajax.call([{
            methodname: 'block_ai_assistant_display_course_modules',
            args: {
                'courseid': courseid
            }
        }]);

        display_modules_ajax[0].done(function (results) {
            // Fetch all strings needed for the confirm dialog before showing it
            Str.get_strings([
                {key: 'train_course_assistant', component: 'block_ai_assistant'},
                {key: 'train_selected_modules', component: 'block_ai_assistant'},
                {key: 'cancel', component: 'block_ai_assistant'},
            ]).then(function(strings) {
                // Pop up notification to confirm training
                notification.confirm(strings[0], results, strings[1], strings[2], function () {
                    // Get all checkboxes with class courseModuleCheckbox and store the attribute data-filename,
                    // data-content, datacourseid for each checked checkbox
                    var checkboxes = document.querySelectorAll('.courseModuleCheckbox');
                    // Get the number of boxes checked
                    var selected_modules = [];
                    checkboxes.forEach(function (checkbox) {
                        if (checkbox.checked) {
                            selected_modules.push({
                                'courseid': checkbox.getAttribute('data-courseid'),
                                'cmid': checkbox.getAttribute('data-cmid'),
                                'modname': checkbox.getAttribute('data-modname'),
                                'modtimemodified': checkbox.getAttribute('data-modtimemodified'),
                            });
                        }
                    });
                    //make a new ajax call to a new webservice that calls insert from course_module class
                    //block_ai_assistant_insert_course_modules
                    var insert_modules = ajax.call([{
                        methodname: 'block_ai_assistant_insert_course_modules',
                        args: {
                            'courseid': courseid,
                            'selected_modules': selected_modules
                        }

                    }]);
                    insert_modules[0].done(function (data) {
                        // Fetch localized strings for messages
                        Str.get_strings([
                            {key: 'train_success_message', component: 'block_ai_assistant'},
                            {key: 'unsupported_files_notice', component: 'block_ai_assistant'}
                        ]).then(function(strings) {
                            var successMsg = strings[0];
                            var unsupportedNotice = strings[1];
                            var msg = successMsg;
                            if (data && data.unsupported && data.unsupported.length) {
                                var list = [];
                                data.unsupported.forEach(function (item) {
                                    var prefix = item.modname ? (item.modname + ': ') : '';
                                    list.push(prefix + item.files.join(', '));
                                });
                                msg += '\n\n' + unsupportedNotice + '\n' + list.join('\n');
                            }
                            alert(msg);
                        }).catch(function(err) {
                            notification.exception(err);
                        });
                    }).fail(function (error) {
                        notification.exception(error);
                    });
                });
            }).catch(function(err) {
                notification.exception(err);
            });

            // Time out required so that components can be discovered
            setTimeout(function () {
                // When button with class ai-aisstant-delete-content is clicked, perform ajax call to delete the content
                var deleteButtons = document.querySelectorAll('.ai-aisstant-delete-content');
                deleteButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        // Get the data-cmid of the clicked button
                        var dataBlockAiaCmid = this.getAttribute('data-block_aia_cmid');
                        var uniqueCmid = this.getAttribute('data-cmid');
                        // Add a notification pop up to confirm delete
                        Str.get_strings([
                            {key: 'delete', component: 'block_ai_assistant'},
                            {key: 'confirm_delete_trained_module', component: 'block_ai_assistant'},
                            {key: 'no', component: 'block_ai_assistant'},
                        ]).then(function(strings) {
                            notification.confirm(strings[0], strings[1], strings[0], strings[2], function () {

                                // Perform ajax call to delete the content
                                var delete_content = ajax.call([{
                                    methodname: 'block_ai_assistant_delete_course_modules',
                                    args: {
                                        'bacmid': dataBlockAiaCmid
                                    }
                                }]);

                                delete_content[0].done(function () {
                                        // Hide element with id  block-aia-trained-status-uniqueCmid
                                        var blockAiaTrainedStatus = document.getElementById(
                                            'block-aia-trained-status-' + uniqueCmid);
                                        blockAiaTrainedStatus.style.display = 'none';
                                        // Hide element with id block-aia-delete-button-uniqueCmid
                                        var blockAiaDeleteButton = document.getElementById('block-aia-delete-button-' + uniqueCmid);
                                        blockAiaDeleteButton.style.display = 'none';
                                        // Remove disable form element with id block-aia-uniqueCmid
                                        var blockAiaUniqueCmid = document.getElementById('block-aia-' + uniqueCmid);
                                        blockAiaUniqueCmid.removeAttribute('disabled');
                                }).fail(function (error) {
                                    notification.exception(error);
                                });
                            });
                        }).catch(function(err) {
                            notification.exception(err);
                        });
                    });
                });


                // Get all elements with the class 'blockAiAssistant'
                var blocks = document.querySelectorAll('.blockAiAssistant');
                // Add click event listener to each block
                blocks.forEach(function (block) {
                    block.addEventListener('click', function () {
                        // Get the data-id of the clicked block
                        var dataId = this.getAttribute('data-id');

                        // Construct the class name of the corresponding content block
                        var contentClassName = 'blockAiAssistantContent-' + dataId;

                        // Get the content block element
                        var contentBlock = document.querySelector('.' + contentClassName);

                        var folderIcon = this.querySelector('.blockAiAssistantFolderIcon');

                        // Toggle the display property of the content block
                        if (contentBlock.style.display === 'none' || contentBlock.style.display === '') {
                            contentBlock.style.display = 'block';
                            // Change the icon to a folder open icon
                            folderIcon.classList.remove('fa-folder');
                            folderIcon.classList.add('fa-folder-open');
                        } else {
                            contentBlock.style.display = 'none';
                            // Change the icon to a folder closed icon
                            folderIcon.classList.remove('fa-folder-open');
                            folderIcon.classList.add('fa-folder');
                        }
                    });
                });


            }, 1000);

        }).fail(function (error) {
            notification.exception(error);
        });

    });
}


