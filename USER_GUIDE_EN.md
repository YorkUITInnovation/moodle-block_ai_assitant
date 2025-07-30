# AI Course Assistant User Guide

## Table of Contents
1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [For Instructors](#for-instructors)
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
- **Automated Testing**: Test AI responses with automated testing functionality
- **Multi-language Support**: Available in multiple languages
- **Real-time Chat**: Instant responses to student inquiries
- **Learning Assistant**: Create custom AI tutorials and prompts for personalized student learning experiences

---

## Getting Started

---

## For Instructors

### Initial Setup

#### 1. Adding the AI Assistant Block
1. Navigate to your course
2. Enable editing mode
3. Click "Add a block"
4. Select "AI Assistant" from available blocks. Please be patient when adding the block as it may take a few seconds to load.
   - This is because when you add it to the course for the first time, it creates a new agent on another server for your course.
   - Once installed, future loads will be faster.
5. The block will automatically create an agentfor your course

#### 2. Basic Configuration
1. Click **"Bot Display Settings"** in the AI Assistant block
2. Customize the following settings:
   - **Bot Name**: Auto-generated but can be modified
   - **Subtitle**: Brief description shown to students
   - **Welcome Message**: First message students see
   - **No Context Message**: Response when AI cannot find an answer
   - **Contact**: Email address for support, displayed to students. This is typically your email address.
   - **Language**: Set content language for better AI training
   - **Embed Position**: Choose where the chat window appears

### Training Your AI Assistant

#### Upload Syllabus
1. Click **"Upload Syllabus"** in the block
2. Select your syllabus file (supported format: .docx only)
3. Click "Upload Syllabus"
4. Wait for training to complete (indicated by status badge)

**💡 Tip**: **Use the provided syllabus template for optimal results.

**💡 Why docx only?** Docx files are structured in a way that allows the AI to extract headings, sections, and other important information more effectively than other formats.

#### Train Course Modules
1. Click **"Train Course Content"** in the block
2. Select modules you want the AI to learn:
   - Announcement forums
   - Pages
   - Text and media areas
   - Books
   - Files
   - Folders
   - Glossaries
3. Note: Module names followed by a red eye with a strikethrough means they are normally not visible to students.
4. Click **"Train Selected Modules"**
5. Monitor training status with colored badges:
   - 🟡 Yellow: Pending
   - 🔵 Blue: Training in progress
   - 🟢 Green: Successfully trained
   - 🔴 Red: Error occurred

**⚠️ Important**: Once content is trained, it becomes available to all students in the course, regardless of their original access permissions.

#### Supported File Formats
The AI can process these file types:
- Documents: .docx, .pdf, .txt, .html, .rtf, .md, .odt
- Presentations: .pptx
- Spreadsheets: .xlsx, .csv
- Media: .mp3, .wav, .m4a, .mp4

### Question Management

#### Upload Q&A Files
1. Click **"Upload Questions"** or **"Questions"**
2. Choose your Q&A file (.docx format)
3. Click "Upload Questions"
4. Questions will be automatically imported and trained

**💡 Tip**: Use the provided questions template for consistent formatting.

### Learning Assistant (Tutorial Creation)

The Learning Assistant feature allows instructors to create custom AI-powered tutorials that provide personalized learning experiences for students. These tutorials can be designed for various educational purposes such as quizzing, concept explanation, problem-solving practice, or guided learning sessions.

#### Creating Tutorial Prompts

1. Click **"Manage Tutorials"** in the AI Assistant block
2. Click the **"Add"** button to create a new tutorial
3. Configure your tutorial settings:
   - **Tutorial Name**: Give your tutorial a descriptive name that students will see
   - **Tutorial Description**: Brief explanation of what the tutorial covers (optional)
   - **Tutorial Prompt**: Write the AI prompt that defines how the tutorial should behave
   - **Enable**: Set to "Yes" to make the tutorial available to students

#### Writing Effective Tutorial Prompts

The tutorial prompt is the instruction that tells the AI how to interact with students. Here are some examples:

**Quiz Tutorial Prompt:**
```
You are a quiz tutor for [topic]. When a student selects a topic, create 5-10 questions about that topic. Ask one question at a time, wait for the student's answer, provide feedback, then move to the next question. Keep track of their score and provide encouragement.
```

**Explanation Tutorial Prompt:**
```
You are a patient tutor who explains concepts in simple terms. When a student asks about the topic: [topic], break it down into easy-to-understand parts with examples. Ask if they need clarification and adjust your explanations based on their responses.
```

**Problem-Solving Tutorial Prompt:**
```
You are a step-by-step problem-solving tutor for the topic: [topic]. Guide students through problems by asking leading questions rather than giving direct answers. Help them think through each step of the solution process.
```

**💡 Tip**: Use placeholders `[topic]` to allow the AI to adapt to different subjects selected.

#### Testing Your Tutorials

1. After creating a tutorial, you can test it by clicking the tutorial name in the block
2. This opens the same interface students will see
3. Select a topic and interact with the AI to ensure it behaves as expected
4. Make adjustments to your prompt if needed and test again

#### Managing Tutorials

- **Edit**: Modify existing tutorial prompts, names, or descriptions
- **Enable/Disable**: Control which tutorials are visible to students
- **Delete**: Remove tutorials that are no longer needed

#### Tutorial Best Practices

1. **Be Specific**: Write clear, detailed prompts that define exactly how the AI should behave
2. **Define the Role**: Tell the AI what kind of tutor it should be (patient, encouraging, challenging, etc.)
3. **Set Boundaries**: Specify what the AI should and shouldn't do (e.g., "don't give direct answers to homework")
4. **Include Examples**: Provide examples in your prompt of the type of responses you want
5. **Test Thoroughly**: Always test your tutorials before making them available to students

**💡 Tip**: Start with simple tutorial prompts and gradually create more complex ones as you become familiar with the feature.

---

## For Students

### Accessing the AI Assistant

1. Navigate to your course page
2. Look for the **"AI Course Assistant"** block at the right side of the page
   - If you don't see it, check with your instructor to ensure it has been enabled for the course
3. Click on the available links.
4. A pop-up will open, allowing you to select a topic to start a conversation with the AI Assistant

### Using the AI Assistant

#### Starting a Conversation
1. Click on the chat interface
2. Type your question in the message box. Make sure your question are clear and specific.
   - Example: "What is the due date for Project 1?"
   - Avoid vague questions such as single words or phrases like "Project" or "Exam"
3. Press Enter or click Send
4. The AI will respond based on trained course content

#### Asking Effective Questions
**Good examples:**
- "When is the midterm exam?"
- "What are the assignment requirements for Project 1?"
- "Whet are assingments worth?"
- "What textbook do I need for this course?"
- "When are office hours?"

**Tips for better responses:**
- Be specific and clear in your questions
- Use keywords related to your course content
- If the first response isn't helpful, try rephrasing your question
- Ask follow-up questions for clarification

#### Understanding AI Responses
- The AI draws information from course syllabi, uploaded documents, and trained content
- Responses may include links to relevant course materials
- If the AI cannot find information, it will display a message to that effect
- The AI is designed to be helpful but may not have access to all course information

### What the AI Can Help With
- Course policies and procedures
- Assignment details and due dates
- Exam information and schedules
- Course materials and textbook information
- General course questions covered in uploaded content
- Navigation help for course resources
- Of course, this always depends on what information the instructor has trained the AI on.

### Using Learning Assistant Tutorials

If your instructor has created Learning Assistant tutorials, you'll see additional tutorial options in the AI Assistant block that provide specialized learning experiences.

#### Accessing Tutorials

1. In the AI Assistant block, look for tutorial options created by your instructor
2. Tutorial names will appear as clickable options (e.g., "Math Quiz Tutor", "Concept Explainer", "Problem Solver")
3. Click on any tutorial that interests you

#### Starting a Tutorial Session

1. When you click on a tutorial, a modal window will open
2. Select the **topic** you want to be tutored on from the available options
3. Click **"Start Tutorial"** or **"Begin Session"**
4. You'll be taken to a dedicated chat page for that tutorial

#### Tutorial Types You Might Encounter

**Quiz Tutorials**
- Interactive quizzes on course topics
- The AI will ask questions one at a time
- Provides immediate feedback on your answers
- Tracks your progress and score

**Explanation Tutorials**
- Detailed explanations of course concepts
- Break down complex topics into simpler parts
- Ask for clarification when you need it
- Provide examples and real-world applications

**Problem-Solving Tutorials**
- Step-by-step guidance through problems
- Asks leading questions to help you think through solutions
- Doesn't give direct answers but guides your thinking
- Helps develop problem-solving skills

**Study Support Tutorials**
- Help with study strategies and techniques
- Review key concepts before exams
- Create study plans and schedules
- Provide learning tips specific to your course

#### Getting the Most from Tutorials

**Be Engaged**
- Actively participate in the conversation
- Answer questions thoughtfully
- Ask for clarification when needed
- Take your time to think through responses

**Use Topic Selection Wisely**
- Choose topics that align with what you're currently studying
- Select areas where you need the most help
- Try different topics to explore various aspects of the course

**Save Important Information**
- Take notes during tutorial sessions
- Save useful explanations for later review
- Apply what you learn in tutorials to your coursework

#### Tutorial Chat Features

- **Conversation History**: Your tutorial conversations are saved so you can return to them later
- **Topic Switching**: You can start new tutorial sessions on different topics
- **Multiple Attempts**: You can repeat tutorials as many times as needed
- **Different Tutorial Types**: Try various tutorials created by your instructor for different learning experiences

**💡 Tip**: Use tutorials regularly as part of your study routine, not just before exams. They're designed to reinforce learning throughout the course.

---

## Troubleshooting

### For Instructors

#### Training Issues
**Problem**: Training status shows "Error" (red badge)
- **Solution**:
  - Check that file format is supported
  - Verify file is not corrupted or password-protected
  - Try uploading a smaller file
  - Contact support if problem persists

**Problem**: AI provides incorrect or outdated responses
- **Solution**:
  - Re-upload updated content
  - Check that relevant materials have been trained
  - Add specific questions for problematic topics
  - Run AutoTest to identify knowledge gaps
  - Update Q&A with correct information

#### Configuration Issues
**Problem**: Settings don't save
- **Solution**:
  - Check that you have appropriate permissions
  - Verify all required fields are completed
  - Try refreshing the page and trying again
  - Contact IT Support if problem continues

**Problem**: Students cannot see the AI Assistant
- **Solution**:
  - Ensure you have clicked "Enable AI Assistant"
  - Check that students have appropriate course access
  - Verify the block is visible on the course page
  - Confirm CRIA services are operational

### For Students

#### Access Issues
**Problem**: Cannot see AI Assistant block
- **Solution**:
  - Confirm you are enrolled in the course
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
  - Check course materials first for the information
  - Ask instructor to train AI on additional content

---

## Best Practices

### For Instructors

#### Content Preparation
1. **Use Templates**: Use provided document templates for consistent formatting
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
2. **Student Feedback**: Ask students about AI usefulness
3. **Update Content**: Refresh training materials as course evolves
4. **Monitor Usage**: Review common questions to identify training gaps

### For Students

#### Effective Usage
1. **Be Specific**: Ask detailed, focused questions
2. **Use Course Terms**: Include terminology from your course
3. **Try Variations**: Rephrase if first attempt doesn't work
4. **Verify Information**: Cross-check important details with official sources

#### When to Use Alternative Resources
- For personal/confidential questions → Contact instructor directly
- For academic emergencies → Use official support channels
- For technical issues → Contact IT support
- For grade interpretation → Discuss with instructor

---

*This user guide is designed to help you get the most out of your AI Course Assistant. For additional questions or technical support, contact your system administrator or course instructor.*
