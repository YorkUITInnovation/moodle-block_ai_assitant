# Standard Operating Procedure: AI Assistant Block Plugin

## 1. Introduction
### 1.1 Purpose
This Standard Operating Procedure (SOP) provides detailed instructions for setting up, configuring, and using the AI Assistant block plugin in Moodle. The AI Assistant integrates with CRIA AI services to provide an intelligent chatbot that can answer questions about course content.

### 1.2 Scope
This SOP covers all aspects of the AI Assistant block plugin, including installation, configuration, content training, question management, and student access. It is intended for course instructors, administrators, and support staff.

### 1.3 Definitions
- **CRIA**: The external AI service that powers the AI Assistant
- **Bot**: An AI instance created for a specific course
- **Training**: The process of feeding course content to the AI so it can answer questions
- **Syllabus**: A document containing course information uploaded to the AI
- **Questions**: Pre-defined Q&A pairs that the AI can answer
- **Autotest**: A feature to test the AI's responses to specific questions

## 2. Prerequisites
### 2.1 System Requirements
- Moodle 3.9 or higher
- PHP 7.2 or higher
- Access to CRIA AI services
- Appropriate Moodle permissions

### 2.2 Required Permissions
- block/ai_assistant:teacher - For instructor access
- block/ai_assistant:view_autotest - For autotest functionality

### 2.3 Required Files
- Syllabus document (optional)
- Question document (optional)
- Autotest questions (optional)

## 3. Adding the AI Assistant Block to a Course
### 3.1 Adding the Block
1. Log in to Moodle with instructor permissions
2. Navigate to the desired course
3. Click "Turn editing on" in the top-right corner
4. Click "Add a block" in the bottom-right corner
5. Select "AI Assistant" from the list of available blocks
6. The block will be added to the course page

### 3.2 Initial Configuration
1. The block will automatically create a bot instance for the course
2. Default settings will be applied from the site-wide configuration
3. Small talk questions will be automatically created

## 4. Configuring the AI Assistant
### 4.1 Basic Settings
1. Click the "Configure settings" link in the AI Assistant block
2. Set the following options:
   - Bot name (automatically generated)
   - Subtitle
   - Welcome message
   - No context message (displayed when AI can't find an answer)
   - Embed position
   - Language
3. Click "Save changes"

### 4.2 Advanced Settings
1. Click the "Configure settings" link in the AI Assistant block
2. Set the following options:
   - Bot contact information
   - Bot help text
3. Click "Save changes"

## 5. Training the AI with Course Content
### 5.1 Uploading a Syllabus
1. Click the "Upload syllabus" link in the AI Assistant block
2. Click "Choose file" and select your syllabus document
   - Supported formats: DOCX, PDF
3. Click "Upload syllabus"
4. The syllabus will be uploaded to CRIA for training
5. Training status will be displayed in the block

### 5.2 Training Course Modules
1. Click the "Course modules" link in the AI Assistant block
2. Select the modules you want to train the AI on
3. Click "Train selected modules"
4. The selected modules will be uploaded to CRIA for training
5. Training status will be displayed for each module

### 5.3 Checking Training Status
1. Training status is indicated by colored badges:
   - Yellow: Pending
   - Blue: Training in progress
   - Green: Trained
   - Red: Error
2. Refresh the page to update the training status

## 6. Managing Questions
### 6.1 Uploading Questions
1. Click the "Upload questions" link in the AI Assistant block
2. Click "Choose file" and select your questions document
   - Supported formats: DOCX, XLSX
3. Click "Upload questions"
4. The questions will be uploaded to CRIA for training
5. Training status will be displayed in the block

### 6.2 Creating Individual Questions
1. Click the "Questions" link in the AI Assistant block
2. Click "Add question"
3. Fill in the following fields:
   - Question name
   - Question text
   - Answer
   - Example questions (variations of the main question)
4. Click "Save changes"
5. The question will be created and published to CRIA

### 6.3 Managing Existing Questions
1. Click the "Questions" link in the AI Assistant block
2. View the list of existing questions
3. Click the edit icon to modify a question
4. Click the delete icon to remove a question
5. Changes will be automatically published to CRIA

## 7. Using Autotest
### 7.1 Uploading Autotest Questions
1. Click the "Autotest" link in the AI Assistant block
2. Click "Upload autotest questions"
3. Click "Choose file" and select your autotest questions spreadsheet
4. Click "Upload"
5. The autotest questions will be imported

### 7.2 Creating Autotest Questions
1. Click the "Autotest" link in the AI Assistant block
2. Click "Add question"
3. Fill in the following fields:
   - Section
   - Question
   - Human answer
4. Click "Save changes"
5. The autotest question will be created

### 7.3 Running Autotest
1. Click the "Autotest" link in the AI Assistant block
2. Click "Run autotest"
3. The system will test each question against the AI
4. Results will be displayed showing the AI's answers
5. Compare the AI answers with the human answers to evaluate performance

## 8. Publishing to Students
### 8.1 Reviewing the AI Assistant
1. Test the AI Assistant by asking questions in the chat interface
2. Verify that the AI provides accurate answers based on your course content
3. Make any necessary adjustments to training or questions

### 8.2 Publishing the AI Assistant
1. Click the "Publish to students" button in the AI Assistant block
2. Confirm that you want to make the AI Assistant available to students
3. The AI Assistant will now be visible to students in the course

### 8.3 Unpublishing the AI Assistant
1. Click the "Unpublish" button in the AI Assistant block
2. Confirm that you want to hide the AI Assistant from students
3. The AI Assistant will no longer be visible to students in the course

## 9. Student Experience
### 9.1 Accessing the AI Assistant
1. Students log in to Moodle and navigate to the course
2. The AI Assistant block is visible on the course page
3. Students can interact with the AI by typing questions in the chat interface

### 9.2 Using the AI Assistant
1. Students type questions related to the course content
2. The AI responds with answers based on the trained content
3. If the AI cannot find an answer, it displays the "no context message"

### 9.3 Student Limitations
1. Students cannot access the configuration settings
2. Students cannot view or modify questions
3. Students cannot access autotest functionality

## 10. Troubleshooting
### 10.1 Training Issues
1. If training status shows "Error":
   - Check that the file format is supported
   - Verify that the file is not corrupted
   - Try uploading a smaller file
   - Contact support if the issue persists

### 10.2 AI Response Issues
1. If the AI provides incorrect answers:
   - Check that relevant content has been trained
   - Verify that the content is accurate
   - Add specific questions for problematic topics
   - Run autotest to identify gaps in knowledge

### 10.3 Technical Issues
1. If the AI Assistant block is not working:
   - Check that CRIA services are available
   - Verify that the block is properly installed
   - Check for error messages in the block
   - Contact support if the issue persists

## 11. Maintenance
### 11.1 Updating Content
1. When course content changes, retrain the affected modules
2. Upload new versions of the syllabus as needed
3. Update questions to reflect current course information

### 11.2 Regular Testing
1. Periodically run autotest to verify AI performance
2. Test the AI with common student questions
3. Review and update content as needed

### 11.3 Course Rollover
1. When creating a new course iteration:
   - Add the AI Assistant block to the new course
   - Upload the updated syllabus
   - Train relevant course modules
   - Import questions from the previous course if applicable

## 12. References and Resources
### 12.1 Documentation
- AI Assistant Plugin Documentation
- CRIA API Documentation
- Moodle Block Development Documentation

### 12.2 Templates
- Syllabus template
- Questions template
- Autotest questions template

### 12.3 Support
- Technical support contact information
- CRIA support contact information
- Community forums and resources