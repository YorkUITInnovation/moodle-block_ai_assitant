# AI Course Assistant

The AI Course Assistant is a Moodle block designed to enhance the student experience and reduce the administrative burden on professors. By leveraging AI, this tool provides quick and accurate responses to student inquiries, streamlines course management, and ensures that students have access to essential course information at all times.

## Features ##

### 1. Syllabus Upload
Professors can easily upload their course syllabus, allowing the AI Course Assistant to provide students with detailed information about the course structure, objectives, and policies.

### 2. Q&A File Upload
Professors can upload a Q&A file to address common questions that may not be covered in the syllabus. This ensures that students receive consistent and accurate answers to their queries.

### 3. Automated Training on Course Dates
The AI Course Assistant is automatically trained on all important course dates, including assessments, activities, and assignments. This helps students stay informed about upcoming deadlines and events.

### 4. Training on Existing Course Content (coming soon)
Instructors can train the AI on existing course content, enabling it to provide detailed and context-specific answers to student questions. This feature ensures that the AI Course Assistant is always up-to-date with the latest course materials.

---




## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/blocks/ai_assistant

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## License ##

2022 UIT Innovation  <thibaud@yorku.ca>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.



