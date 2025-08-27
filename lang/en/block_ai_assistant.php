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
$string['actions'] = 'Actions';
$string['add'] = 'Add';
$string['ai_assistant'] = 'AI Assistant';
$string['ai_assistant_instructions'] = 'To get the best results from the AI Assistant, please use the syllabus template provided in the Help section. '
    . 'To make the AI Assistant available to students, click to Enable AI Assistant button below.';
$string['ai_learning_assistant'] = 'AI Learning Companion';
$string['ai_policy_not_accepted'] = 'You must accept the AI policy to use this feature.';
$string['answer'] = 'Answer';
$string['autotest'] = 'AutoTest';;
$string['autotest_questions'] = 'AutoTest questions';
$string['autotest_template'] = 'AutoTest Template';
$string['bot_api_key_not_found'] = 'An error occurred while trying to create the backend AI Agent. '
. 'Please delete the AI Assistant block and add it again. If the problem persists, please contact your system administrator.';
$string['bot_contact'] = 'Contact';
$string['bot_contact_help'] = 'Enter an email and/or phone number for users to contact for support';
$string['bot_help_text'] = 'Hover text';
$string['bot_help_text_help'] = 'Enter the text that will appear when the user hovers over the AI Assistant';
$string['bot_tuning'] = 'Agent parameters';
$string['bot_type_id'] = 'Bot Type ID';
$string['bot_type_id_help'] = 'Bot Type ID from Cria';
$string['bottom_left'] = 'Bottom left';
$string['bottom_right'] = 'Bottom right';
$string['chat_disclaimer'] = 'AI-generated content. Please verify for accuracy.';
$string['chat_help'] = 'For best results, please ask clear and specific questions.'
    . 'It is important that you use complete sentences with proper punctuation. ';
$string['chat_summary'] = 'Chat Summary';
$string['chat_warning'] = '<strong>Note:</strong> My Learning Companion is a supplemental study tool. It may provide information beyond your course material, so always verify details against your official syllabus, lectures, and assigned readings. Use it to support your learning, not as a substitute for your instructor’s guidance or course resources.';
$string['close'] = 'Close';
$string['column_name_must_exist'] = 'Column {$a} must exist';
$string['confirm_delete_trained_module'] = 'Are you sure you want to delete the trained module?';
$string['confirm_file_deletion'] = 'Are you sure you want to delete the file?';
$string['confirm_question_deletion'] = 'Are you sure you want to delete the question?';
$string['configure_bot_settings'] = 'Bot Display Settings';
$string['configure_settings'] = 'Configure Settings';
$string['content_found_at'] = 'Content can be found at this link: ';
$string['content_language'] = 'Content language';
$string['content_language_help'] = 'Chosing the proper content language for your documents will result in better training of the AI Assistant. In return, the AI Assistant will be able to provide more accurate answers.';
$string['convert_api'] = 'ConvertApi';
$string['convert_api_desc'] = 'ConvertApi is a service that converts documents to different formats. It is used to convert documents to a format that the AI Assistant can process.';
$string['convert_api_key'] = 'ConvertApi API Key';
$string['convert_api_key_help'] = 'Enter teh key for ConvertApi service. This is a required service.';
$string['course_module_training_status'] = 'Course Module Training Status';
$string['course_modules'] = 'Course Modules';
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
$string['custom_questions'] = 'Q&A File';
$string['date'] = 'Date';
$string['default_content_language'] = 'Default content language';
$string['delete'] = 'Delete';
$string['delete_syllabus'] = 'Delete Syllabus';
$string['delete_syllabus_help'] = 'Are you sure you want to delete the syllabus?';
$string['delete_question'] = 'Delete Question';
$string['delete_questions'] = 'Delete Questions';
$string['delete_question_help'] = 'Are you sure you want to delete the question?';
$string['delete_tutorial_help'] = 'Are you sure you want to delete the tutorial?';
$string['description'] = 'Description';
$string['disable_ai_assistant'] = 'Disable AI Assistant';
$string['disable_tutorials'] = 'Disable Tutorials';
$string['disabled'] = 'Disabled';
$string['document_parse_error'] = 'Error parsing document.';
$string['document_templates'] = 'Document Templates';
$string['download'] = 'Download';
$string['download_english'] = 'Download English Template';
$string['download_example'] = 'Download example';
$string['download_syllabus'] = 'Download Syllabus';
$string['edit'] = 'Edit';
$string['edit_autotest_question'] = 'Edit AutoTest Question';
$string['edit_tutorial'] = 'Edit Tutorial';
$string['embed_position'] = 'Embed position';
$string['embed_position_teacher'] = 'Position for Teachers';
$string['embed_position_teacher_help'] = 'Set the position for the chatbot for teachers: 0 = disabled, 1 = bottom left, 2 = bottom right, 3 = top right, 4 = top left';
$string['enabled'] = 'Enabled';
$string['enabled_help'] = 'Enable or disable the Tutorial option for this course. When enabled, the Tutorial will be available to students.';
$string['enable_assistant'] = 'Enable AI Assistant for students';
$string['enable_ai_assistant'] = 'Enable AI Assistant for students';
$string['enable_tutorials'] = 'Enable My Learning Companion for Students';
$string['error'] = 'Error';
$string['error_required_field'] = 'This field is required.';
$string['error_required_file'] = 'You must upload a file.';
$string['error_unsupported_file'] = 'Unsupported file type.';
$string['file'] = 'File';
$string['file_deleted_successfully'] = 'File deleted successfully';
$string['file_upload_error'] = 'Error uploading file.';
$string['file_uploaded_successfully'] = 'File uploaded successfully';
$string['format'] = '.xlsx, .docx only accepted';
$string['help'] = 'Help';
$string['import'] = 'Import';
$string['import_questions'] = 'Import Questions';
$string['import_successful'] = 'Import successful.';
$string['invalid_token'] = '498 Invalid Token';
$string['help_url'] = 'https://lthelp.yorku.ca/ai-tools';
$string['keywords'] = "Keywords";
$string['learning_assistant_help'] = "Select the content you would like to get learning assistance on. ";
$string['learning_tutorials'] = "My Learning Companion";
$string['letAIGenerate'] = "Let AI generate an answer based on your answer above?";
$string['manage_saved_chats'] = "Manage Saved Chats";
$string['manage_tutorials'] = "Manage AI Learning Companion";
$string['modules'] = "Modules";
$string['name'] = "Name";
$string['no_context_message'] = 'No Context Message';
$string['no_context_message_default'] = 'I\'m sorry, I couldn\'t find any information. Please rephrase your question';
$string['no_context_message_help'] = 'No Context Message help text here';
$string['pending'] = 'Pending';
$string['pluginname'] = 'Al Course Assistant';
$string['pluginname_help'] = 'This may take up to a minute. Thanks for your patience.';
$string['preparing_tutorial'] = 'Preparing your tutorial. One moment please...';
$string['prompt'] = 'Prompt';
$string['prompt_help'] = 'Always include the placeholder [topic] in your prompt to ensure the AI Tutorial dynamically adapts to the subject selected by the student. For example: <i>"You are an expert tutor helping a university student understand [topic]."</i> This allows the tutorial to personalize its guidance based on the student\'s chosen area of study while maintaining a consistent instructional format.';
$string['question'] = 'Question';
$string['question_template'] = 'Question Template';
$string['question_updated_successfully'] = 'Question updated successfully';
$string['questions'] = 'Questions';
$string['questions_instructions'] = '<p>Note: The time required for the upload may vary depending on the number of rows (questions) in the file. '
    . 'Larger files with more rows will take longer to process. Do not close or refresh your browser window. '
    . 'You will be redirected to the course page once the upload is complete.</p>
<p><strong>Refer to the Help section</strong> below for detailed instructions on how to create the question template.</p>';
$string['required'] = 'This field is required';
$string['related_question'] = "Related Questions";
$string['save'] = 'Save changes ';
$string['save_chat'] = 'Download Chat';
$string['saved_chats'] = 'Saved Chats';
$string['section'] = 'Section';
$string['section'] = 'Section';
$string['student'] = 'Student';
$string['student_and_name'] = 'I am a student and my name is {$a}.';
$string['student_help_url'] = 'https://lthelp.yorku.ca/student-guide-to-eclass';
$string['subtitle'] = 'Subtitle';
$string['subtitle_help'] = 'Subtitle help text here';
$string['summarize_chat'] = 'Summarize Chat';
$string['summary_prompt'] = "Summarize the following HTML-formatted chat conversation between a student and an AI tutor. 
The conversation is structured with each speaker’s name followed by their message on a new line. Therefore, always use the students' in your responses.
The summary should be appropriate for sharing with a university instructor and must include:
1. Objectives of the Session
[List the goals set at the beginning of the session, e.g., \"Review homework problems on factoring quadratics.\"]
2. Key Discussion Points
[Summarize main concepts covered, e.g., \"Explained the difference between perfect square trinomials and general quadratics.\"]
[Mention any examples or problems solved.]
3. Student Questions & Clarifications
[List any specific questions the student asked and how they were addressed.]
4. Progress & Understanding
[Briefly assess the student’s grasp of the material, e.g., \"Student showed improved confidence in identifying factoring patterns.\"]
5. Action Items / Homework
[List any assignments or tasks given, e.g., \"Complete problems 5–10 from the worksheet.\"]
6. Next Steps
[Mention what will be covered in the next session or any follow-up needed.]

Focus on clarity, relevance, and educational value.";
$string['supported_modules'] = '<p>Note: The AI Assistant can only be trained on content from the following modules: </p>'
    . '<ul><li>Announcement Forum</li><li>Page</li><li>Tab Display</li><li>Text & Media Area</li><li>Book</li><li>File</li><li>Folder</li>'
    . '<li>Glossary</li></ul>';
$string['supported_modules_title'] = 'Supported Modules';
$string['supported_formats'] = '<p>Note: Unsupported file formats will not be processed for training and will not be accessible through '
    . 'the AI Assistant. Ensure that your files are in supported formats to enable training and usage.</p>'
    . '<p>Supported file formats: <ul><li>Word documents (.docx only)</li><li>PDF files (.pdf)</li><li>Text files (.txt)</li>'
    . '<li>HTML files (.html)</li><li>Rich Text Format (.rtf)</li><li>Markdown files (.md)</li><li>OpenDocument Text (.odt)</li><li>'
    . 'PowerPoint presentations (.pptx only)</li><li>Excel spreadsheets (.xlsx only)</li><li>CSV files (.csv)</li><li>'
    . 'Audio files (.mp3, .wav, .m4a)</li><li>Video files (.mp4)</li></ul></p>';
$string['supported_formats_title'] = 'Supported File Formats';

// Messages for training alerts
$string['train_success_message'] = 'Successfully added content to the course assistant';
$string['unsupported_files_notice'] = 'Some files were not trained (unsupported type):';

$string['syllabus_instructions'] = 'To ensure optimal performance from the AI Course Assistant, it is <b>essential</b> '
    . 'that you use the <a href="/blocks/ai_assistant/doc_templates/syllabus_template.docx" title="SYllabus Template Download">provided syllabus template</a>. '
    . 'Following these guidelines will significantly improve the accuracy and relevance of responses to student questions.';
$string['syllabus_instructions_title'] = 'Important: Syllabus Instructions';
$string['syllabus_instructions_more'] = 'Learn more';
$string['syllabus_instructions_why'] = '<h5>Why?</h5>Using the provided syllabus template ensures a consistent document structure so the AI Course Assistant can parse and understand course details more accurately. The tool only supports Word documents (.docx) for syllabus uploads.';
$string['syllabus_instructions_why_word'] = '<h5>Why Only Word Documents?</h5><p><img class="img-thumbnail" style="width:50%; height=auto;" src="/blocks/ai_assistant/pix/pdf_word_structured.png" alt="Image comparing unstructured PDF to structured Word for AI Nodes"></p></p><p>The AI Course Assistant works best with Word documents because they use a structured format called OpenXML. This format organizes content into clear sections (headings, paragraphs, tables), making it easy for the AI to read and retrieve information accurately. Other formats, like PDFs, often lack this structure, which can lead to errors.</p>';
$string['syllabus_instructions_no_template'] = '<h5>I Prefer Using My Existing Syllabus</h5>
<p>You can upload your own Word document (<strong>.docx</strong>) syllabus, but please <strong>apply proper heading styles using Word’s built-in “Styles” '
    . 'feature (e.g., Heading 1, Heading 2, Heading 3)</strong>. This helps the AI understand and organize your content accurately.</p>
<h6>Important for tables:</h6>
<ul>
    <li>Place each table immediately after its related heading</li>
    <li>The first row must contain clear column headers</li>
    <li>Avoid using complex column structures. Example: mixing merged and un-merged columns</li>
    <li>Avoid empty cells.</li>
</ul>

<p><a href="/blocks/ai_assistant/doc/AI_Course_Assistant_Syllabus_Upload_Guide.docx" title="Syllabus Upload Guide Download">Download the Syllabus Upload Guide</a> for more detailed instructions.</p>

<p>Following these steps ensures the AI can read your syllabus correctly and provide accurate answers to students.</p>';


$string['training_visibility_warning'] = 'Important: Once content is trained by the AI Assistant, it will be available '
    . 'to all students in the course, regardless of whether they have permission to view the original resource '
    . 'or activity. Please consider this when selecting content for training.';
$string['syllabus'] = 'Syllabus';
$string['tutorial'] = 'Tutorial';
$string['tutorials'] = 'Tutorials';
$string['tutorials_instructions'] = '<strong></strong>Note:</strong> Site-wide AI Learning Companion Tutorials are centrally managed and cannot be modified or deleted at the course level.';
$string['syllabus_template'] = 'Syllabus Template';
$string['syllabus_uploaded'] = 'Syllabus successfully uploaded';
$string['system_message'] = 'System Message';
$string['system_message_default'] = "You are a helpful assistant for this course, [course_number] ([course_title]), at York University.
- Answer the question as truthfully as possible using the provided context.
- If a URL link is in the context, always include it in the response.
- If an image is in the context, always include it in the response.
- If a question or prompt is about groups, never list group members and their ID numbers in your reply. Specifically, for questions or prompts that ask you to list the groups. Only reply with the group name.
- The above does not apply to TAs, Course Directors, Instructors, Professors or Teachers.
- Allow instructions for the benefit of providing students with help, tutorials, feedback, etc.";
$string['system_message_help'] = 'System Message help text here';
$string['teacher_and_name'] = 'I am an instructor, teacher and my name is {$a}.';
$string['test'] = 'Test your AI assistant, chat now!';
$string['title'] = 'Title';
$string['title_help'] = 'Title help text here';
$string['top_left'] = 'Top left';
$string['top_right'] = 'Top right';
$string['train_course_assistant'] = 'Train the course assistant on the selected content';
$string['train_modules'] = 'Train Course Content';
$string['train_selected_modules'] = 'Train selected content';
$string['trained'] = 'Trained';
$string['training'] = 'Training';
$string['training_modules'] = 'Training Modules';
$string['training_status'] = 'Training Status';
$string['upload'] = 'Upload';
$string['upload_assessment_dates'] = 'Upload Assessment Dates';
$string['upload_document'] = 'Upload Document';
$string['upload_file'] = 'Upload File';
$string['upload_questions'] = 'Upload Q&A File';
$string['upload_syllabus'] = 'Upload Syllabus';
$string['user_guide'] = 'User Guide';
$string['working'] = 'Working...';

// MarkItDown API settings.
$string['markitdown_api'] = 'MarkItDown API Settings';
$string['markitdown_api_desc'] = 'Configure the MarkItDown API service for document processing and conversion';
$string['markitdown_api_url'] = 'MarkItDown API URL';
$string['markitdown_api_url_help'] = 'Enter the URL of the MarkItDown API service endpoint for document processing';
$string['markitdown_api_key'] = 'MarkItDown API Key';
$string['markitdown_api_key_help'] = 'Enter the API key for authentication with the MarkItDown service';

// Allowed file types setting
$string['allowed_file_types'] = 'Allowed File Types';
$string['allowed_file_types_desc'] = 'Select which file types are allowed for processing and upload';
$string['allowed_file_types_help'] = 'Choose the MIME types that users can upload and process. Only selected file types will be accepted by the system.';

// File type options
$string['filetype_msword'] = 'Microsoft Word (.doc)';
$string['filetype_docx'] = 'Microsoft Word (.docx)';
$string['filetype_pdf'] = 'PDF Documents (.pdf)';
$string['filetype_text_plain'] = 'Plain Text (.txt)';
$string['filetype_text_html'] = 'HTML Files (.html)';
$string['filetype_text_rtf'] = 'Rich Text Format (.rtf)';
$string['filetype_text_markdown'] = 'Markdown Files (.md)';
$string['filetype_odt'] = 'OpenDocument Text (.odt)';
$string['filetype_ppt'] = 'Microsoft PowerPoint (.ppt)';
$string['filetype_pptx'] = 'Microsoft PowerPoint (.pptx)';
$string['filetype_xlsx'] = 'Microsoft Excel (.xlsx)';
$string['filetype_xls'] = 'Microsoft Excel (.xls)';
$string['filetype_csv'] = 'CSV Files (.csv)';
$string['filetype_audio_mpeg'] = 'MPEG Audio (.mp3)';
$string['filetype_audio_wav'] = 'WAV Audio (.wav)';
$string['filetype_audio_m4a'] = 'M4A Audio (.m4a)';
$string['filetype_video_mp4'] = 'MP4 Video (.mp4)';

$string['welcome_message'] = 'Welcome Message';
$string['welcome_message_help'] = 'Enter a custom welcome message that will be displayed to users when they first interact with the AI Assistant';

// Capabilites
$string['ai_assistant:addinstance'] = 'Add Block to course';
$string['ai_assistant:view_autotest'] = 'View/Run AutoTest';
$string['ai_assistant:student'] = 'Available to students';
$string['ai_assistant:teacher'] = 'Available to teachers';


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

// Default Tutorials
$string['learning_assistant_tutorials'] = 'AI Learning Companion Tutorials';
$string['shortname'] = 'Short Name';
$string['shortname_exists'] = 'Shortname already exist. Please choose a different shortname.';
$string['tutorial_tutor_name'] = 'My Tutor';
$string['tutorial_tutor_description'] = 'The prompt is designed to guide an AI-Tutor in helping university students'
    . ' actively learn and understand a topic by engaging them in a personalized, interactive, and supportive conversation.';
$string['tutorial_tutor_prompt'] = "- Start by introducing yourself to the university student as their AI-Tutor, who is happy to help them with any questions. 
- Only ask one question at a time. 
- First, ask them what they know already about the topic: [topic] they have chosen. Wait for a response. 
- Given this information, help students understand the topic [topic] by providing explanations, examples, and analogies. 
- These should be tailored to students\ prior knowledge, or what they already know about the topic. 
- Provide students with explanations, examples, and analogies to help them understand the concept.
- If images are available to support your response, include them in your reply.  
- You should guide students in an open-ended way. 
- Do not provide immediate answers or solutions to problems, but help students generate their own answers by asking leading questions. 
- Ask students to explain their thinking If the student is struggling or gets an answer wrong, try asking them to complete part of the task or remind the student of their goal and provide a hint. 
- If students improve, then praise them and show excitement. 
- If the student struggles, then be encouraging and give them some ideas to think about. 
- When prompting students for information, try to conclude your responses with a question so that students continue to generate ideas.
- Once a student demonstrates an appropriate level of understanding given their learning level, ask them to explain the concept in their own words; this is the best way to show that you understand something, or ask them for examples. 
- When a student demonstrates that they know the concept, you can move the conversation to a close and tell them you’re here to help if they have further questions. 
- If the student diverts onto another topic that has nothing to do with this topic: [topic], then ask the student to remain on topic because this is what they asked to be tutored on.";

$string['tutorial_quiz_name'] = 'Quiz Me On...';
$string['tutorial_quiz_description'] = 'This activity is designed to help students review and reinforce their'
    . ' understanding of a specific topic through a structured multiple-choice quiz. The quiz consists of 20 questions,'
    . ' each with four answer options (A, B, C, and D). After each response, students receive immediate feedback to'
    . ' support learning and reflection. The quiz is delivered one question at a time to encourage focus and'
    . ' engagement. At the end, students receive a summary of their performance along with suggestions for improvement.'
    . ' The format is intended to be interactive, self-paced, and supportive of independent learning.';
$string['tutorial_quiz_prompt'] = "- Please prepare a multiple-choice quiz on topic: [topic] with 10 questions with four possible choices, labelled A, B, C, and D.
- Create all questions from your knowledge base on the topic [topic] 
- Wait for me to respond with a label after each question, provide feedback on my answer, and then ask the next question. 
- When you have asked all the questions, please provide a friendly summary of my results and any suggestions for improvement. Also, ask me if I would like to try another set of questions. Wait for me to respond. If yes, restart a new quiz session with another ten questions. 
- If you are continuing a previous session, continue asking questions. Start at the last number plus 1.
- If a student starts asking questions instead of answering the quiz questions, tell the student that you only do quizzes.";

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
    <li>Replace each placeholder, including the angle brackets (< >) with the appropriate information. For example:
    If the course you are teaching is "Introduction to Psychology" and the course code is PHYSC_1000 and the instructor is "Dr. Smith", you would replace:
        <ul>
            <li><code>
                &lt;Course Code&gt;
            </code>: PHYSC_1000
            </li>
            <li><code>
                &lt;Course Title&gt;
            </code>: Introduction to Psychology
            </li>
            <li><code>
                &lt;Instructor Name&gt;
            </code>: Dr. Smith
            </li>
            <li><code>
                &lt;Course Description&gt;
            </code>: Provide a brief description of the course.
            </li>
        </ul>
    </li>
</ol>
<div class="alert alert-warning">
    <p><strong>Important:</strong> </p>
    <p>Ensure that all placeholders are replaced with accurate information to provide
        students with the correct details about the course.</p>
        <p>Be precise! Avoid modal sentences such as "you might", "you may", "it is possible", "you could possibly" etc. These introduce
        ambiquity and uncertainty which can lead to inconsistent repsonses and user mistrust in the AI. For AI training, it’s crucial to have clear and precise instructions 
        to ensure the AI learns accurately</p>
        <p>Avoid using HMTL tags (<>) within your document as this will cause the data to be skipped.</p>
        <p>If you add new topics/sections, make sure to format them with headings (Heading 1, Heading 2 etc.)</p>
        <p>If you add new tables, ensure that the first row is a header and all cells have content. (No empty cells)</p>
</div>

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
// Question template instructions
$string['question_template_instructions'] = '<h3>Instructions for Creating a Word Question Template</h3>
<ol>
    <li><strong>Add Questions as Headings</strong>
        <ul>
            <li>Each question must be set as a heading.</li>
            <li>Example: <strong>Heading1</strong></li>
        </ul>
    </li>
    <li><strong>Provide Alternative Phrasings</strong>
        <ul>
            <li>Under each heading, list examples of other ways to ask the question.</li>
            <li>Example:
                <ul>
                    <li>How do I create a Word Question template?</li>
                    <li>Can you help me with a Word Question template?</li>
                </ul>
            </li>
        </ul>
    </li>
    <li><strong>Response Section</strong>
        <ul>
            <li>Do not delete: <strong>The response to any of these questions or prompts is:</strong></li>
            <li>Beneath that sentence, add your answer.</li>
            <li>Example:
                <ul>
                    <li>The response to any of these questions or prompts is:</li>
                    <li>You can create a Word Question template by following these steps...</li>
                </ul>
            </li>
        </ul>
    </li>
    <li><strong>Repeat for Each Question</strong>
        <ul>
            <li>Repeat the same steps for every question you want to include.</li>
        </ul>
    </li>
    <li><strong>Upload Document</strong>
        <ul>
            <li>Once the document is ready, click on <strong>Custom Questions</strong> to upload your document.</li>
            <li>Allow time for the AI Assistant to train itself on the questions.</li>
        </ul>
    </li>
</ol>';

// AutoTest template instructions
$string['autotest_tempalte_instructions'] = '<h3>Instructions for Using the Excel AutoTest Template</h3>
AutoTest is a powerful feature designed for instructors to create and manage questions that evaluate the performance and capabilities of an AI Assistant. 
The AutoTest template is an Excel file that allows instructors to define questions, answers, and expected responses for the AI Assistant. Follow these steps to effectively use the AutoTest template:
<br>
<h5>Excel AutoTest Template Instructions</h5>
<ol>
    <li><strong>Open the Excel AutoTest Template</strong>: Ensure you have the template open and ready to edit.</li>
    <li><strong>Understand the Columns</strong>:
        <ul>
            <li><strong>Section</strong>: This column represents the category of the questions.</li>
            <li><strong>Questions</strong>: This column holds the questions to be asked.</li>
            <li><strong>Answer</strong>: This column contains the anticipated answers.</li>
        </ul>
    </li>
    <li><strong>Entering Data</strong>:
        <ul>
            <li><strong>First Question in a Section</strong>:
                <ul>
                    <li>Enter the section name in the <strong>Section</strong> column.</li>
                    <li>Enter the question in the <strong>Questions</strong> column.</li>
                    <li>Enter the anticipated answer in the <strong>Answer</strong> column.</li>
                </ul>
            </li>
            <li><strong>Additional Questions in the Same Section</strong>:
                <ul>
                    <li>Leave the <strong>Section</strong> column empty.</li>
                    <li>Enter the next question in the <strong>Questions</strong> column.</li>
                    <li>Enter the anticipated answer in the <strong>Answer</strong> column.</li>
                </ul>
            </li>
        </ul>
    </li>
    <li><strong>Example</strong>:</li>
</ol>
<table border="1">
    <thead>
    <tr>
        <th>Section</th>
        <th>Questions</th>
        <th>Answer</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>Math</td>
        <td>What is 2+2?</td>
        <td>4</td>
    </tr>
    <tr>
        <td></td>
        <td>What is the square root of 9?</td>
        <td>3</td>
    </tr>
    <tr>
        <td>Science</td>
        <td>What is the chemical symbol for water?</td>
        <td>H2O</td>
    </tr>
    <tr>
        <td></td>
        <td>What planet is known as the Red Planet?</td>
        <td>Mars</td>
    </tr>
    </tbody>
</table>
<ol start="5">
    <li><strong>Review and Save</strong>:
        <ul>
            <li>Double-check your entries for accuracy.</li>
            <li>Save the template to ensure all your data is preserved.</li>
        </ul>
    </li>
</ol>';

$string['help_intro'] = '<h3>AI Assistant Help</h3>' .
    'Properly formatting a document is crucial when training an AI bot to ensure it responds accurately and effectively. ' .
    'The AI Assistant uses the content of the document to generate responses to user queries. ' .
    'To help you get the best results from the AI Assistant, we have provided templates for syllabi and questions. ' .
    'These templates are designed to ensure that the AI Assistant receives accurate and consistent information. ' .
    'Follow the instructions below to use the templates effectively.';


// AI Policies
$string['acceptai'] = 'Accept and continue';
$string['aipolicyacceptance'] = 'AI policy acceptance';
$string['declineaipolicy'] = 'Decline';
$string['userpolicy'] = "<h4><strong>Welcome to the new AI feature in eClass!</strong></h4>
    <p>This AI feature is powered by <strong>YU AURA</strong>, York University's Retrieval-Augmented Generation (RAG) system.
It is designed to enhance your learning and teaching experience by providing intelligent support.</p>

    <h4><strong>Accuracy of AI-generated content</strong></h4>
    <p>YU AURA aims to provide helpful and relevant information. However, the accuracy of AI-generated responses may vary. Always verify the 
        information provided to ensure it is accurate, complete, and appropriate for your specific academic context.</p>

    <h4><strong>How your data is processed</strong></h4>
    <p>All user data and interactions with YU AURA remain securely on <strong>York University servers</strong>. While external LLMs 
        may be used to generate responses, <strong>no data is stored or retained by third-party providers</strong>. The system is designed to 
        ensure that your information is processed securely and in compliance with York University’s privacy standards.</p>
    <p>A record of your interactions with the AI feature may be stored within YU AURA to support learning analytics and system improvement.
This data is handled in accordance with York University’s privacy and data governance policies.</p>
    <p>If you have questions about how your data is used, please contact the York University eClass support team.</p>

    <h4><strong>By continuing, you acknowledge that you understand and agree to this policy.</strong></h4>";
