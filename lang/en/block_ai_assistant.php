<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     block_ai_assistant
 * @category    string
 * @copyright   2022 UIT Innovation  <thibaud@yorku.ca>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accepted_modules'] = 'Accepted Modules';
$string['accepted_modules_help'] = 'Comma seperated list of modules that can have their content trained by the AI Assistant';
$string['access'] = 'Student Access';
$string['ai_assistant_instructions'] = 'To get the best results from the AI Assistant, please use the syllabus template provided in the Document Templates';
$string['answer'] = 'Answer';
$string['autotest']= 'AutoTest';
$string['autotest_questions']= 'AutoTest questions';
$string['bot_tuning'] = 'Agent parameters';
$string['bot_type_id'] = 'Bot Type ID';
$string['bot_type_id_help'] = 'Bot Type ID from Cria';
$string['bottom_left'] = 'Bottom left';
$string['bottom_right'] = 'Bottom right';
$string['close'] = '  Close';
$string['column_name_must_exist'] = 'Column {$a} must exist';
$string['confirm_file_deletion'] = 'Are you sure you want to delete the file?';
$string['confirm_question_deletion'] = 'Are you sure you want to delete the question?';
$string['configure_bot_settings'] = 'Configure Bot Settings';
$string['configure_settings'] = 'Configure Settings';
$string['content_language'] = 'Content language';
$string['content_language_help'] = 'Chosing the proper content language for your documents will result in better training of the AI Assistant. In return, the AI Assistant will be able to provide more accurate answers.';
$string['cria_token'] = 'Cria Token';
$string['cria_url'] = 'Cria URL';
$string['cria_embed_url'] = 'Cria embed URL';
$string['cria_embed_url_help'] = 'Enter the URL of the cria embed bot';
$string['cria_token_help'] = 'Enter the token to your Cria server. You might have to ask your system administrator.';
$string['cria_url_help'] = 'Enter the URL to your Cria server. You might have to ask your system administrator.';
$string['criadex_embed_id'] = 'Criadex Embed ID';
$string['criadex_embed_id_help'] = 'Enter the criadex embed id to your Cria server. You might have to ask your system administrator.';
$string['criadex_model_id'] = 'Criadex Model ID';
$string['criadex_model_id_help'] = 'Enter the criadex model id to your Cria server. You might have to ask your system administrator.';
$string['criadex_rerank_id'] = 'Criadex Rerank ID';
$string['criadex_rerank_id_help'] = 'Enter the criadex rerank id to your Cria server. You might have to ask your system administrator.';
$string['custom_questions'] = 'Custom Questions';
$string['default_content_language'] = 'Default content language';
$string['delete'] = 'Delete';
$string['delete_syllabus'] = 'Delete Syllabus';
$string['delete_syllabus_help'] = 'Are you sure you want to delete the syllabus?';
$string['delete_question'] = 'Delete Question';
$string['delete_questions'] = 'Delete Questions';
$string['delete_question_help'] = 'Are you sure you want to delete the question?';
$string['document_templates'] = 'Document Templates';
$string['download'] = 'Download';
$string['download_english'] = 'Download (English)';
$string['download_example'] = 'Download example';
$string['download_syllabus'] = 'Download Syllabus';
$string['download_questions'] = 'Download Questions';
$string['edit_question'] = "Edit question";
$string['edit_questions'] = "Edit questions";
$string['file_deleted_successfully'] = 'File deleted successfully';
$string['file_uploaded_successfully'] = 'File uploaded successfully';
$string['format'] = '.xlsx, .docx only accepted';
$string['keywords'] = "Keywords";
$string['letAIGenerate'] = "Let AI generate an answer based on your answer above?";
$string['modules'] = "Modules";
$string['name'] = "Name";
$string['no_context_message'] = 'No Context Message';
$string['no_context_message_default'] = 'I\'m sorry, I couldn\'t find any information. Please rephrase your question';
$string['no_context_message_help'] = 'No Context Message help text here';
$string['pluginname'] = 'Al Course Assistant';
$string['pluginname_help'] = 'This may take up to a minute. Thanks for your patience.';
$string['questions'] = 'Questions';
$string['questions_instructions'] = 'Note: The time required for the upload may vary depending on the number of rows (questions) in the file. '
    . 'Larger files with more rows will take longer to process. Do not close or refresh your browser window. '
    . 'You will be redirected to the course page once the upload is complete.';
$string['question'] = 'Question';
$string['question_updated_successfully'] = 'Question updated successfully';
$string['required'] = 'This field is required';
$string['related_question'] = "Related Questions";
$string['save'] = 'Save changes ';
$string['section'] = 'Section';
$string['subtitle'] = 'Subtitle';
$string['subtitle_help'] = 'Subtitle help text here';
$string['syllabus'] = 'Syllabus';
$string['syllabus_template'] = 'Syllabus Template';
$string['syllabus_uploaded'] = 'Syllabus successfully uploaded';
$string['system_message'] = 'System Message';
$string['system_message_default'] = 'You are a Factual AI Assistant dedicated to providing accurate information based on the information available in the knowledge base as your only source. Your response must be in the same language as my message.';
$string['system_message_help'] = 'System Message help text here';
$string['test'] = 'Test your AI assistant, chat now!';
$string['title'] = 'Title';
$string['title_help'] = 'Title help text here';
$string['top_left'] = 'Top left';
$string['top_right'] = 'Top right';
$string['train_modules'] = 'Add activities to the AI Assistant';
$string['update_successful'] = 'Update succssful';
$string['upload_syllabus'] = 'Upload Syllabus';
$string['upload_questions'] = 'Upload question file';
$string['welcome_message'] = 'Welcome Message';
$string['welcome_message_help'] = 'Welcome Message help text here';

// Bot tuning
$string['max_tokens'] = 'Max tokens';
$string['max_tokens_help'] = '4000 for GPT-4o';
$string['temperature'] = 'Temperature';
$string['temperature_help'] = '0.1 Precise 0.5 Creative 1.0 Wild';
$string['top_p'] = 'Top P';
$string['top_p_help'] = '0 for GPT-4o';
$string['top_k'] = 'Top K';
$string['top_k_help'] = '50 for GPT-4o';
$string['top_n'] = 'Top N';
$string['top_n_help'] = '10 for GPT-4o';
$string['min_k'] = 'Min K';
$string['min_k_help'] = '0.6 for GPT-4o';
$string['min_relevance'] = 'Min Relevance';
$string['min_relevance_help'] = '0.8 for GPT-4o';
$string['max_context'] = 'Max Context';
$string['max_context_help'] = '120000 for GPT-4o';
$string['no_context_llm_guess'] = 'No Context LLM Guess';
$string['no_context_llm_guess_help'] = 'Allow the LLM to return an answer when no context is available';
$string['embed_position'] = 'Embed Position';

// Template instructions
$string['syllabus_template_instructions'] = '<h3>Instructions for Using the Syllabus Template</h3>
<p>The syllabus template is designed to ensure accuracy and consistency when training the AI bot, AL the Course
    Assistant. The template consists of placeholders that start with <code><</code> and end with <code>></code>. Follow
    these steps to effectively use the template:</p>

<h4>Step 1: Open the Template</h4>
<ol>
    <li>Open the syllabus template file in your preferred text editor or word processor.</li>
</ol>

<h4>Step 2: Identify Placeholders</h4>
<ol start="2">
    <li>Look for placeholders within the template. These placeholders are enclosed in angle brackets, such as 
        <code>&lt;CourseTitle&gt;</code>, <code>&lt;InstructorName>InstructorName&gt;</code>, etc.
    </li>
</ol>

<h4>Step 3: Replace Placeholders</h4>
<ol start="3">
    <li>Replace each placeholder with the appropriate information. For example:
        <ul>
            <li><code>
                &lt;Course Code&gt;
            </code>: Enter the code for the course.
            </li>
            <li><code>
                &lt;Course Title&gt;
            </code>: Enter the title of the course.
            </li>
            <li><code>
                &lt;Instructor Name&gt;
            </code>: Enter the name of the instructor.
            </li>
            <li><code>
                &lt;Course Description&gt;
            </code>: Provide a brief description of the course.
            </li>
        </ul>
    </li>
</ol>

<h4>Step 4: Review and Save</h4>
<ol start="4">
    <li>Carefully review the filled-in template to ensure all placeholders have been replaced with accurate
        information.
    </li>
    <li>Save the updated syllabus file with a new name to avoid overwriting the original template.</li>
</ol>

<h4>Step 5: Use the Syllabus</h4>
<ol start="6">
    <li>Use the completed syllabus for your course. This document will help ensure that AL the Course Assistant has
        accurate and consistent information to assist students effectively.
    </li>
</ol>

<p>By following these instructions, you can ensure that the syllabus is accurate and ready for use in training AL the
    Course Assistant.</p>';
