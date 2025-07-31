# AI Course Assistant - Instructor User Guide

## Table of Contents
1. [Overview](#overview)
2. [Getting Started](#getting-started)
3. [Configuration & Settings](#configuration--settings)
4. [Content Training](#content-training)
5. [Question Management](#question-management)
6. [Autotest & Quality Control](#autotest--quality-control)
7. [Tutorial Creation](#tutorial-creation)
8. [Chat Management](#chat-management)
9. [Monitoring & Analytics](#monitoring--analytics)
10. [Best Practices](#best-practices)
11. [Troubleshooting](#troubleshooting)

---

## Overview

The AI Course Assistant is a powerful Moodle plugin that provides instructors with an intelligent teaching assistant powered by YU AURA AI technology. This guide covers all instructor-specific features and capabilities to help you maximize the educational impact of your AI assistant.

### Key Instructor Benefits
- **Reduce Administrative Burden**: Automate responses to common student questions
- **24/7 Student Support**: Provide instant assistance outside office hours
- **Personalized Learning**: Create custom tutorials and learning paths
- **Content Integration**: Seamlessly incorporate existing course materials
- **Quality Control**: Test and validate AI responses before student access

---

## Getting Started

### Adding the AI Assistant to Your Course

1. **Navigate to your course** in Moodle
2. **Enable editing mode** by toggling Edit mode on by using the switch in the top right corner.
3. **Add the block**:
   - Click "Add a block" in the sidebar
   - Select "AI Assistant" from the available blocks
   - **Wait patiently** - The initial setup may take 30-60 seconds as it creates a dedicated AI agent for your course
4. **Verify installation** - The block should appear with basic configuration options

> **Note**: The AI agent is course-specific and maintains conversation context based on material you train it on.

---

## Content Training

It is important to train your AI assistant with relevant course materials to ensure it provides accurate and helpful responses. The AI Assistant 
can only respond to students on trained content. The training process involves uploading your syllabus and other course content.

### Syllabus Upload

The syllabus serves as the foundation of your AI assistant's knowledge:

1. **Prepare your syllabus**:
   - Use .docx format for best results
   - Consider using the provided syllabus template
   - Include clear headings and sections

2. **Upload process**:
   - Click **"Upload Syllabus"** in the AI Assistant block
   - Select your syllabus file
   - Drag & Drop the file into the designated area or click "Choose a file"
   - CLick Save changes to initiate training
   - Monitor the training status badge
   - If you need help, click on the "Help" header below the upload area for more information.
> **Note**: Training can take time. It is based on the document size. The AI will not repsond to student queries until training is complete.

3. **Training indicators**:
   - 🟡 **Yellow**: Queued for processing
   - 🔵 **Blue**: Currently training
   - 🟢 **Green**: Successfully trained
   - 🔴 **Red**: Error occurred (check file format/content)

---

## Question Management

### Creating Q&A Collections

1. **Bulk import via document**:
   - Prepare a .docx file with Q&A pairs
   - Use the provided question template for formatting
   - Click **"Upload Questions"**
   - Select your prepared document
   - Drag & Drop the file into the designated area or click "Choose a file"
   - Click Save changes to initiate training
   - Monitor the training status badge
   - If you need help, click on the "Help" header below the upload area for more information.

> **Note**: Use the provided templates for consistent formatting:
> - **Questions Template**: Structured format for Q&A pairs
> - **Questions Example**: Sample questions to guide your creation


> **Tip**: To retrain the AI Assistant, delete the existing document and re-upload a new version.

---

### Course Module Training

Train your AI on existing Moodle content:

1. **Access training interface**:
   - Click **"Train Course Content"** in the block
   - Review available modules

2. **Select training content**: Only the following modules are supported for training:
   - **Announcement Forums**: Course announcements and updates
   - **Pages**: Static course pages and content
   - **Text and Media Areas**: Embedded content in topics
   - **Books**: Multi-page book resources
   - **Files**: Uploaded documents and resources
   - **Folders**: Organized file collections
   - **Glossaries**: Term definitions and explanations

3. **Visibility considerations**:
   - 👁️‍🗨️ **Red eye with strikethrough**: The module and it's content is hidden to students. However, it can be trained for the AI assistant.
   - ⚠️ **Important**: Once trained, content becomes accessible via AI regardless of original visibility settings. The moodle module remains unaccessible to students.

4. **Supported file formats**:
   - **Documents**: .docx, .pdf, .txt, .html, .rtf, .md, .odt
   - **Presentations**: .pptx
   - **Spreadsheets**: .xlsx, .csv
   - **Audio**: .mp3, .wav, .m4a
   - **Video**: .mp4

### Training Best Practices

- **Start with syllabus**: Upload syllabus first for foundational knowledge
- **Gradual training**: Add content progressively rather than all at once
- **Quality content**: Ensure uploaded materials are current and accurate
- **Regular updates**: When ever you update a module that is already trained, the system automatically retrains the AI assistant to include the latest information

> **Note**: If the trained file has an error badge (red), delete the file and reselect it to retrain. If the error persists, check the file format and content for issues.ß
---

## Configuration & Settings

### Bot Display Settings

Access via the **"Configure Settings"** link in your AI Assistant block:

#### Basic Information
- **Bot Name**: Customize your AI assistant's name (e.g., "Biology Helper", "Math Tutor")
- **Subtitle**: Brief description displayed to students
- **Welcome Message**: First message students see when opening the chat
- **No Context Message**: Response when the AI cannot find relevant information

#### Contact
- **Contact Email**: Your email address for escalated questions
- **Hover text**: Additional information displayed when students hover over the bot

#### Display Options
- **Embed Position**: Choose chat window placement (bottom-right, bottom-left, etc.)
- - **Language**: Set the primary language for optimal AI training and responses

---



## Autotest & Quality Control

> **Note**: Autotest is a powerful tool to ensure your AI assistant provides accurate and relevant responses. If you require auto testing, please contact your system administrator.

---

## Learning Assistant Tutorial Creation

### Learning Assistant Tutorials

Create guided learning experiences for students:

> **Note**: There are two existing tutorials that are available by default and are manged at site level:
> - **My Tutor**: The prompt is designed to guide an AI-Tutor in helping university students actively learn and understand a topic by engaging them in a personalized, interactive, and supportive conversation.
> - **Quiz Me On...**: The prompt is designed to guide an AI-Tutor in helping university students actively learn and understand a topic by engaging them in a personalized, interactive, and supportive conversation.

1. **Access tutorial creation**:
   - Click **"Manage Learning Assistants"** in the AI Assistant block
   - Create new tutorial or edit existing ones

2. **Tutorial components**:
   - **Name**: Define the name of the tutorial that will be displayed to students
   - **Description**: Add a brief overview of the tutorial's purpose
   - **Prompt**: Craft a guiding question or scenario for the tutorial
   - **Enable**: Select **"Yes"** to make the tutorial available to students

> **Note**: As the instructor, you will see and have access to all tutorials, but students will only see those you have enabled. You must also enable the Learning Assistant for students to access it.

3. **Tutorial visibility**:
   - **Enabled tutorials**: Students can access these in the AI Assistant block
   - **Disabled tutorials**: Only visible to instructors for editing

---

## Chat Features

### Chat Summarization

- **Generate summaries**: Create condensed versions of long conversations exported as a pdf.
- **Extract key points**: Identify main topics and resolutions
- **Share insights**: Use summaries for course improvement

### Download Chat

- **Export conversations**: Download chat history as a PDF

---


## Best Practices

### Content Preparation

1. **Use clear, structured documents**: Well-formatted content trains better
2. **Include comprehensive syllabus**: Foundation for all AI knowledge
3. **Regular content audits**: Keep information current and accurate
4. **Progressive training**: Add content gradually for better integration

### Student Communication

1. **Set clear expectations**: Explain AI capabilities and limitations
2. **Provide escalation path**: Clear contact information for complex issues
3. **Encourage appropriate use**: Guide students on effective questioning
4. **Monitor and adjust**: Regularly review and improve based on usage

### Quality Assurance

1. **Periodic manual testing**: Spot-check AI responses
3. **Student feedback integration**: Use feedback for improvements
4. **Continuous training updates**: Keep knowledge base current

### Privacy and Ethics

1. **Respect student privacy**: Never train on sensitive personal data
2. **Transparent AI use**: Clearly communicate AI assistance availability
3. **Academic integrity**: Ensure AI supports learning rather than replacing it
4. **Inclusive design**: Consider diverse learning needs and backgrounds

---

## Troubleshooting

### Common Issues

#### Training Problems
- **File format errors**: Ensure .docx format for documents
- **Large file issues**: Break large documents into smaller sections
- **Training error**: If there is an error message on a trained file, delete the selected file and retrain it.

#### Response Quality Issues
- **Inaccurate answers**: Add more specific training content
- **Irrelevant responses**: Refine question phrasing and context
- **Missing information**: Check if relevant content is trained

#### Technical Issues
- **Block not loading**: Delete and re-add the AI Assistant block

### Getting Support

1. **Check documentation**: Review this guide and SOP documents
2. **Test systematically**: Manualy test to identify specific issues
3. **Contact technical support**: Use the contact email in block settings

---

*This guide is designed to help instructors maximize the educational impact of the AI Course Assistant. Regular updates and improvements are made based on user feedback and new features.*
