import notification from 'core/notification';
import ajax from 'core/ajax';
import * as Str from 'core/str';

export const init = () => {
    display_modules();
};

/**
 * Display course modules
 */
function display_modules() {
    document.getElementById('btn-ai-assistant-train-modules').addEventListener('click', function () {
        // get data-courseid from current element
        var courseid = this.getAttribute('data-courseid');

        var display_modules = ajax.call([{
            methodname: 'block_ai_assistant_display_course_modules',
            args: {
                'courseid': courseid
            }
        }]);

        display_modules[0].done(function (results) {
            // Pop up notificaiton to confirm delete
            notification.confirm(Str.get_string('train_course_assistant', 'block_ai_assistant'),
                results,
                Str.get_string('save', 'block_ai_assistant'),
                Str.get_string('cancel', 'block_ai_assistant'), function () {
                    // Get all checkboxes with class courseModuleCheckbox and store the attribute data-filename,
                    // data-content, datacourseid for each checked checkbox
                    var checkboxes = document.querySelectorAll('.courseModuleCheckbox');
                    // Get the number of boxes checked
                    var checkedCount = 0;
                    checkboxes.forEach(function (checkbox) {
                        if (checkbox.checked) {
                            checkedCount++;
                        }
                    });

                    var selected_modules = [];
                    var currentNumberChecked = 0;
                    checkboxes.forEach(function (checkbox) {
                        if (checkbox.checked) {
                            currentNumberChecked++;
                            selected_modules.push({
                                'filename': checkbox.getAttribute('data-filename'),
                                'content': checkbox.getAttribute('data-content'),
                                'courseid': checkbox.getAttribute('data-courseid'),
                                'cmid': checkbox.getAttribute('data-cmid'),
                                'modname': checkbox.getAttribute('data-modname'),
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
                            insert_modules[0].done(function (response) {
                                if (currentNumberChecked === checkedCount) {
                                    alert("Successfully added, record id is: " + response);
                                }
                                // You can now use the data variable for further processing
                            }).fail(function (error) {
                                alert("error in ajax call of insert modules" + error);
                            });
                        }
                    });
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
                        notification.confirm(Str.get_string('delete', 'block_ai_assistant'),
                            Str.get_string('confirm_delete_trained_module', 'block_ai_assistant'),
                            Str.get_string('delete', 'block_ai_assistant'),
                            Str.get_string('no', 'block_ai_assistant'), function () {

                                // Perform ajax call to delete the content
                                var delete_content = ajax.call([{
                                    methodname: 'block_ai_assistant_delete_course_modules',
                                    args: {
                                        'cmid': dataBlockAiaCmid
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
                                        alert("Successfully deleted");
                                }).fail(function (error) {
                                    alert("error in ajax call of delete modules" + error);
                                });
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

        }).fail(function () {
            alert('An error has occurred. Cannot display data');
        });

    });
}


