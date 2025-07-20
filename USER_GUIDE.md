# AI Course Assistant User Guide

## Table of Contents
1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [For Teachers](#for-teachers)
4. [For Students](#for-students)
5. [Troubleshooting](#troubleshooting)
6. [Best Practices](#best-practices)

---

## Introduction

The AI Course Assistant is a powerful Moodle plugin that enhances the learning experience by providing an intelligent chatbot capable of answering student questions about course content. Powered by CRIA AI technology, it helps reduce administrative burden on instructors while providing 24/7 support to students.

### Key Features
- **Syllabus Integration**: Upload course syllabi for comprehensive course information
- **Q&A Management**: Create and manage frequently asked questions
- **Content Training**: Train the AI on existing course materials
- **Automated Testing**: Test AI responses with autotest functionality
- **Multi-language Support**: Available in multiple languages
- **Real-time Chat**: Instant responses to student inquiries

---

## Getting Started

---

## For Teachers

### Initial Setup

#### 1. Adding the AI Assistant Block
1. Navigate to your course
2. Turn editing on
3. Click "Add a block" 
4. Select "AI Assistant" from the available blocks. Please be patient when adding the block as it may take a few seconds to load.
   - This is because when you first add it to the course, it creates a new bot instance for your course.
   - Once installed, future loads will be faster.
5. The block will automatically create a bot instance for your course

#### 2. Basic Configuration
1. Click **"Bot Display Settings"** in the AI Assistant block
2. Customize the following settings:
   - **Bot Name**: Automatically generated but can be modified
   - **Subtitle**: Brief description shown to students
   - **Welcome Message**: First message students see
   - **No Context Message**: Response when AI can't find an answer
   - **Contact**: Email address for support, displayed to students. This is normaly your email address.
   - **Language**: Set content language for better AI training
   - **Embed Position**: Choose where the chat window appears

### Training Your AI Assistant

#### Upload Syllabus
1. Click **"Upload Syllabus"** in the block
2. Select your syllabus file (supported formats: .docx, .pdf)
3. Click "Upload syllabus"
4. Wait for training to complete (indicated by status badges)


**💡 Tip**: **Use the provided syllabus template for optimal results.**

#### Train Course Modules
1. Click **"Train Course Content"** in the block
2. Select modules you want the AI to learn from:
   - Announcement Forums
   - Pages
   - Text & Media Areas
   - Books
   - Files
   - Folders
   - Glossaries
3. Note: Module names follwed by a red eye with a bar icon means that they are not normlly visible to students. 
4. Click **"Train selected modules"**
5. Monitor training status with colored badges:
   - 🟡 Yellow: Pending
   - 🔵 Blue: Training in progress
   - 🟢 Green: Trained successfully
   - 🔴 Red: Error occurred

**⚠️ Important**: Once content is trained, it becomes available to all students in the course, regardless of their original access permissions.

#### Supported File Formats
The AI can process these file types:
- Documents: .docx, .pdf, .txt, .html, .rtf, .md, .odt
- Presentations: .pptx
- Spreadsheets: .xlsx, .csv
- Media: .mp3, .wav, .m4a, .mp4

### Managing Questions

#### Upload Q&A Files
1. Click **"Upload Questions"** or **"Questions"**
2. Choose your Q&A file (.docx format)
3. Click "Upload questions"
4. Questions will be automatically imported and trained

**💡 Tip**: Use the provided questions template for consistent formatting.

### Publishing to Students

#### Enable AI Assistant
1. Review and test the AI using the chat interface
2. Verify responses are accurate and helpful
3. Click **"Enable AI Assistant"**
4. The AI becomes visible to students immediately

#### Disable AI Assistant
- Click **"Disable AI Assistant"** to hide from students
- Use this when making updates or if issues arise

### Document Templates

Access helpful templates by clicking **"Help"**:
- **Syllabus Template**: Structured format for optimal AI training
- **Questions Template**: Format for Q&A uploads

Each template includes examples to guide your content creation.

---

## For Students

### Accessing the AI Assistant

1. Navigate to your course page
2. Look for the **"AI Course Assistant"** icon usually at the bottom left of the page
3. The AI assistant appears as a chat interface
4. If you don't see it, the instructor may not have enabled it yet

### Using the AI Assistant

#### Starting a Conversation
1. Click on the chat interface
2. Type your question in the message box
3. Press Enter or click Send
4. The AI will respond based on trained course content

#### Asking Effective Questions
**Good Examples:**
- "When is the midterm exam?"
- "What are the assignment requirements for Project 1?"
- "How is the final grade calculated?"
- "What textbook do I need for this course?"
- "When are office hours?"

**Tips for Better Responses:**
- Be specific and clear in your questions
- Use keywords related to your course content
- If the first response isn't helpful, try rephrasing your question
- Ask follow-up questions for clarification

#### Understanding AI Responses
- The AI draws information from course syllabi, uploaded documents, and trained content
- Responses may include links to relevant course materials
- If the AI can't find information, it will display a message as such
- The AI is designed to be helpful but may not have access to all course information

### What the AI Can Help With
- Course policies and procedures
- Assignment details and due dates
- Exam information and schedules
- Course materials and textbook information
- General course questions covered in uploaded content
- Navigation help for course resources
- Of course, this is always dependant on what information the instructor has trained the AI on.

### What the AI Cannot Do
- Access your grades or personal academic records
- Provide answers to exam questions or assignments
- Make exceptions to course policies
- Access real-time information not in trained content
- Handle technical issues with Moodle
- Provide personalized academic advice

### Getting Additional Help
If the AI Assistant can't answer your question:
1. Contact your instructor directly
2. Check course announcements and resources
3. Attend office hours
4. Ask classmates in discussion forums
5. Contact technical support for system issues

---

## Troubleshooting

### For Teachers

#### Training Issues
**Problem**: Training status shows "Error" (red badge)
- **Solution**: 
  - Check file format is supported
  - Verify file isn't corrupted or password-protected
  - Try uploading a smaller file
  - Ensure content is in the selected language
  - Contact support if issue persists

**Problem**: AI provides incorrect or outdated answers
- **Solution**:
  - Re-upload updated content
  - Check that relevant materials have been trained
  - Add specific questions for problematic topics
  - Run AutoTest to identify knowledge gaps
  - Update Q&A with correct information

#### Configuration Issues
**Problem**: Settings won't save
- **Solution**:
  - Check you have proper permissions
  - Verify all required fields are completed
  - Try refreshing the page and attempting again
  - Contact administrator if issue continues

**Problem**: Students can't see the AI Assistant
- **Solution**:
  - Ensure you've clicked "Enable AI Assistant"
  - Check that students have proper course access
  - Verify the block is visible on the course page
  - Confirm CRIA services are operational

### For Students

#### Access Issues
**Problem**: Can't see the AI Assistant block
- **Solution**:
  - Confirm you're enrolled in the course
  - Check with instructor if AI has been enabled
  - Refresh your browser page
  - Try logging out and back in

**Problem**: AI doesn't respond to messages
- **Solution**:
  - Check your internet connection
  - Try refreshing the page
  - Clear browser cache
  - Report to instructor if problem persists

#### Response Quality Issues
**Problem**: AI gives unhelpful or "no context" responses
- **Solution**:
  - Rephrase your question more specifically
  - Use different keywords related to your topic
  - Check course materials for the information first
  - Ask instructor to train AI on additional content

---

## Best Practices

### For Teachers

#### Content Preparation
1. **Use Templates**: Utilize provided document templates for consistent formatting
2. **Organize Information**: Structure content clearly with headings and sections
3. **Update Regularly**: Keep training materials current with course changes
4. **Test Thoroughly**: Verify AI responses before publishing

#### Training Strategy
1. **Start with Syllabus**: Upload course syllabus first as foundation
2. **Add Core Content**: Train on essential course materials
3. **Include FAQs**: Upload common questions and answers
4. **Test Incrementally**: Test AI after each major content addition

#### Quality Assurance
1. **Regular Testing**: Periodically test AI responses for accuracy
2. **Student Feedback**: Ask students about AI helpfulness
3. **Update Content**: Refresh training materials as course evolves
4. **Monitor Usage**: Review common questions to identify training gaps

### For Students

#### Effective Usage
1. **Be Specific**: Ask detailed, focused questions
2. **Use Course Terms**: Include terminology from your course
3. **Try Variations**: Rephrase if first attempt doesn't work
4. **Verify Information**: Cross-check important details with official sources

#### When to Use Alternative Resources
- For personal/confidential matters → Contact instructor directly
- For technical problems → Contact IT support
- For complex academic advice → Schedule office hours
- For urgent issues → Use course email or phone

---

## Support and Resources

### Getting Help
- **Instructors**: Contact your institution's IT support or Moodle administrator
- **Students**: Contact your course instructor or institutional help desk

### Additional Resources
- Course-specific document templates available in the AI Assistant block
- Institutional training materials (if available)
- Moodle documentation and support forums

### Feedback
Help improve the AI Assistant by providing feedback on:
- Response accuracy and helpfulness
- Missing information or topics
- Technical issues or bugs
- Suggestions for new features

---

*This guide covers the AI Course Assistant plugin version for Moodle. Features may vary based on your institution's configuration and available services.*
