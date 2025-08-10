<?php

namespace block_ai_assistant;
/**
 * Markdown Conversions PHP
 * Converts markdown files to structured nodes
 */

class syllabus_parser
{

    /**
     * Parse metadata from HTML comments at the beginning of markdown files
     *
     * @param string $content Raw markdown content
     * @return array Dictionary containing metadata
     */
    public function parse_markdown_metadata($content)
    {
        $metadata = [];

        // Look for HTML comment at the beginning
        if (preg_match('/<!--\s*\n(.*?)\n-->/s', $content, $matches)) {
            $commentContent = $matches[1];
            $lines = explode("\n", $commentContent);

            foreach ($lines as $line) {
                if (strpos($line, ':') !== false) {
                    list($key, $value) = explode(':', $line, 2);
                    $metadata[trim($key)] = trim($value);
                }
            }
        }

        return $metadata;
    }

    /**
     * Find all section headers (# headers) in markdown content
     *
     * @param string $content Markdown content
     * @return array List of section titles
     */
    public function find_markdown_sections($content)
    {
        $sections = [];

        // Find all headers (# ## ### etc.)
        if (preg_match_all('/^(#{1,6})\s+(.+)$/m', $content, $matches)) {
            foreach ($matches[2] as $title) {
                // Remove markdown formatting from title
                $cleanTitle = preg_replace('/\*\*(.+?)\*\*/', '$1', trim($title));
                $sections[] = $cleanTitle;
            }
        }

        return $sections;
    }

    /**
     * Extract course information section from markdown content
     *
     * @param string $content Markdown content
     * @return string Processed course information text
     */
    public function extract_course_information($content)
    {
        // Find Course Information section
        if (!preg_match('/# Course Information\s*\n(.*?)(?=\n# |\n## |$)/s', $content, $match)) {
            return "";
        }

        $courseInfo = trim($match[1]);

        // Process course information similar to the original docx parser
        $processedInfo = "*Course Information*\n";

        // Replace field patterns with natural language
        $replacements = [
            '/Course Director:\s*\*\*(.+?)\*\*/' => 'The course director (or professor or instructor or teacher) for this course is $1',
            '/Email:\s*\*\*\[(.+?)\]\(mailto:(.+?)\)\*\*/' => "\nYour course director's email is $1",
            '/Email:\s*\*\*(.+?)\*\*/' => "\nYour course director's email is $1",
            '/Semester:\s*\*\*(.+?)\*\*/' => "\nThe current semester (or term) is $1",
            '/Lecture time & day:\s*\*\*(.+?)\*\*/' => "\nThe lecture (or class) is offered on the following day and time: $1",
            '/Lecture room:\s*\*\*(.+?)\*\*/' => "\nIf you're wondering how to get to your lecture, the lecture (or class) takes place in the following classroom (or location): $1",
            '/Zoom \(Lecture\):\s*\*\*\[(.+?)\]\((.+?)\)\*\*/' => "\nSome classes may be offered on Zoom or you may have to attend some classes on Zoom only during unforeseen situations such as snowstorms or the instructor's illness, in which case the Zoom link (or Zoom address) for the lecture will be $2",
            '/eClass:\s*\*\*\[(.+?)\]\((.+?)\)\*\*/' => "\nThere is an eClass site (the course has been uploaded to eClass) and the eClass link (or address or URL) is $2",
            '/Office:\s*\*\*(.+?)\*\*/' => "\nWhat is the course director's (or professor's or instructor's or teacher's) office number (or office address)? Where can I meet him or her? The answer is: $1",
            '/Office Hours:\s*\*\*(.+?)\*\*/' => "\nThe course director's (or professor's or instructor's or teacher's) office hours are $1"
        ];

        foreach ($replacements as $pattern => $replacement) {
            $courseInfo = preg_replace($pattern, $replacement, $courseInfo);
        }

        $processedInfo .= $courseInfo;

        return $processedInfo;
    }

    /**
     * Parse a markdown table into a 2D array
     *
     * @param string $tableContent Markdown table content
     * @return array Array representation of the table
     */
    public function parse_markdown_table($tableContent)
    {
        $lines = array_filter(array_map('trim', explode("\n", trim($tableContent))));

        if (count($lines) < 3) {
            return [];
        }

        // Find the actual header row (first row with meaningful content)
        $header = null;
        $headerIdx = 0;

        foreach ($lines as $i => $line) {
            // Skip separator lines (contain only |, -, :, and spaces)
            if (preg_match('/^[|\-: ]+$/', trim($line))) {
                continue;
            }

            // Parse this row
            $cells = array_map('trim', explode('|', $line));
            // Remove empty cells at start/end that come from leading/trailing |
            if (!empty($cells) && empty($cells[0])) {
                array_shift($cells);
            }
            if (!empty($cells) && empty($cells[count($cells) - 1])) {
                array_pop($cells);
            }

            // Check if this looks like a header (non-empty cells)
            if (!empty($cells) && array_filter($cells, function ($cell) {
                    return trim($cell) !== '';
                })) {
                $header = $cells;
                $headerIdx = $i;
                break;
            }
        }

        if (!$header) {
            return [];
        }

        // Find data rows (after the header and any separator)
        $dataLines = array_slice($lines, $headerIdx + 1);

        // Skip separator lines in data
        $dataLines = array_filter($dataLines, function ($line) {
            return !preg_match('/^[|\-: ]+$/', trim($line));
        });

        $data = [];
        foreach ($dataLines as $line) {
            $row = array_map('trim', explode('|', $line));
            // Remove empty cells at start/end that come from leading/trailing |
            if (!empty($row) && empty($row[0])) {
                array_shift($row);
            }
            if (!empty($row) && empty($row[count($row) - 1])) {
                array_pop($row);
            }

            if (!empty($row) && array_filter($row, function ($cell) {
                    return trim($cell) !== '';
                })) {
                // Pad row to match header length
                while (count($row) < count($header)) {
                    $row[] = '';
                }
                $data[] = array_slice($row, 0, count($header)); // Truncate if too long
            }
        }

        if (empty($data)) {
            return [];
        }

        // Return associative array with header as keys
        $result = ['header' => $header, 'data' => $data];
        return $result;
    }

    /**
     * Extract all tables from markdown content along with their section titles
     *
     * @param string $content Markdown content
     * @return array List of arrays [section_title, table_data]
     */
    public function extract_tables_from_markdown($content)
    {
        $tables = [];

        // Split content into sections
        $sections = preg_split('/\n(#{1,6}\s+.+)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        $currentTitle = "";
        for ($i = 0; $i < count($sections); $i++) {
            $section = $sections[$i];

            if (preg_match('/^#/', $section)) {
                // This is a header
                $currentTitle = preg_replace('/^#{1,6}\s+/', '', trim($section));
                $currentTitle = preg_replace('/\*\*(.+?)\*\*/', '$1', $currentTitle); // Remove bold formatting
            } else {
                // Find complete table blocks by looking for consecutive lines with |
                $lines = explode("\n", $section);
                $j = 0;

                while ($j < count($lines)) {
                    if (strpos($lines[$j], '|') !== false && trim($lines[$j]) !== '') {
                        // Start of a table block
                        $tableLines = [];

                        // Collect all consecutive lines with |
                        while ($j < count($lines) && strpos($lines[$j], '|') !== false) {
                            if (trim($lines[$j]) !== '') {
                                $tableLines[] = $lines[$j];
                            }
                            $j++;
                        }

                        if (count($tableLines) >= 3) { // Need at least header, separator, and one data row
                            $tableContent = implode("\n", $tableLines);
                            $tableData = $this->parse_markdown_table($tableContent);
                            if (!empty($tableData)) {
                                $tables[] = [$currentTitle, $tableData];
                            }
                        }
                    } else {
                        $j++;
                    }
                }
            }
        }

        return $tables;
    }

    /**
     * Render faculty members information table
     */
    public function render_faculty_table($tableData)
    {
        $text = "*Faculty Members Information*\n";

        if (empty($tableData['data'])) {
            return $text;
        }

        foreach ($tableData['data'] as $row) {
            if (count($row) >= 5) {
                $name = $row[0];
                $role = $row[1];
                $email = $row[2];
                $officeHours = $row[3];
                $office = $row[4];

                // Clean up markdown formatting
                $name = preg_replace('/\*\*(.+?)\*\*/', '$1', $name);
                $role = preg_replace('/\*\*(.+?)\*\*/', '$1', $role);
                $email = preg_replace('/\[(.+?)\]\((.+?)\)/', '$1', $email);
                $officeHours = preg_replace('/\*\*(.+?)\*\*/', '$1', $officeHours);
                $office = preg_replace('/\*\*(.+?)\*\*/', '$1', $office);

                $text .= "{$name} is the course's {$role} and has the following email address: ";
                $text .= "{$email} and has the following office hours (time you can meet or appointment time): ";
                $text .= "{$officeHours} and has the following office address or location (where you can meet with your professor or instructor or teacher or TA): ";
                $text .= "{$office}.\n";
            }
        }

        return $text;
    }

    /**
     * Render evaluation/grading table
     */
    public function render_evaluation_table($tableData)
    {
        $text = "*Summary of Evaluation*\n";
        $text .= "This section answers questions about how much an assignment is worth (how much it counts toward the final grade) ";
        $text .= "and when the assignments are due or have to be submitted or handed in (submission date).\n";

        if (empty($tableData['data'])) {
            return $text;
        }

        foreach ($tableData['data'] as $row) {
            if (count($row) >= 4) {
                $activity = preg_replace('/\*\*(.+?)\*\*/', '$1', $row[1]);
                $worth = $row[2];
                $dueDate = $row[3];

                $text .= "The {$activity} is worth {$worth} of the final grade. In other words, it counts for ";
                $text .= "{$worth} of the final grade.\n";
                $text .= "The {$activity} is due on {$dueDate}. In other words, the deadline or due date or submission date for ";
                $text .= "{$activity} is {$dueDate}.\n";
            }
        }

        return $text;
    }

    /**
     * Render grading equivalence table
     */
    public function render_grading_equivalence_table($tableData)
    {
        $text = "*Grading Equivalence*\n";

        if (empty($tableData['data'])) {
            return $text;
        }

        foreach ($tableData['data'] as $row) {
            if (count($row) >= 4) {
                $grade = $row[0];
                $gradePoint = $row[1];
                $percentRange = $row[2];
                $description = $row[3];

                $text .= "{$grade} is the same as a grade point of {$gradePoint}, which falls in the percent range of ";
                $text .= "{$percentRange}%, and is described as '{$description}'.\n";
            }
        }

        return $text;
    }

    /**
     * Render schedule and readings table
     */
    public function render_schedule_table($tableData)
    {
        $text = "*Schedule and Readings*\n";

        if (empty($tableData['data'])) {
            return $text;
        }

        foreach ($tableData['data'] as $row) {
            if (count($row) >= 3) {
                $topic = preg_replace('/\*\*(.+?)\*\*/', '$1', $row[0]);
                $readings = preg_replace('/\*\*(.+?)\*\*/', '$1', $row[1]);
                $date = preg_replace('/\*\*(.+?)\*\*/', '$1', $row[2]);

                $text .= "The topic on {$date} is (or is about) '{$topic}'. In other words, '{$topic}' ";
                $text .= "is presented in class on {$date}.\n";

                if (in_array(strtolower($readings), ['nan', '', 'none'])) {
                    $text .= "There are no readings on {$date}.\n";
                } else {
                    $text .= "The reading(s) for the topic called '{$topic}' on {$date} is (are) the following: {$readings}\n";
                }
            }
        }

        return $text;
    }

    /**
     * Render important dates table
     */
    public function render_important_dates_table($tableData)
    {
        $text = "*Important Dates*\n";

        if (empty($tableData['data'])) {
            return $text;
        }

        foreach ($tableData['data'] as $row) {
            if (count($row) >= 2) {
                $description = $row[0];
                $date = preg_replace('/\*\*(.+?)\*\*/', '$1', $row[1]);

                if (in_array(strtolower($date), ['none', '', 'nan'])) {
                    $text .= "There is no {$description}.\n";
                } else {
                    $text .= "{$description} is on {$date}.\n";
                }
            }
        }

        return $text;
    }

    /**
     * Render definitions of standing table
     */
    public function render_definitions_table($tableData)
    {
        $text = "*Definitions of Standing*\n";

        if (empty($tableData['data'])) {
            return $text;
        }

        foreach ($tableData['data'] as $row) {
            if (count($row) >= 2) {
                $standing = $row[0];
                $definition = $row[1];

                $text .= "A grade considered '{$standing}' means that you have a {$definition}\n";
            }
        }

        return $text;
    }

    /**
     * Render a generic table when no specific renderer is available
     */
    public function render_generic_table($title, $tableData)
    {
        $text = "*{$title}*\n";

        if (empty($tableData['data']) || count($tableData['data']) <= 0) {
            return $text;
        }

        $nbRows = count($tableData['data']);
        $nbColumns = count($tableData['header']);

        foreach ($tableData['data'] as $row) {
            $text .= "The following " . strtolower($tableData['header'][0]) . ": {$row[0]} has ";

            for ($k = 1; $k < $nbColumns - 1; $k++) {
                $text .= "the following " . strtolower($tableData['header'][$k]) . ": {$row[$k]} and has ";
            }

            if ($nbColumns > 1) {
                $text .= "the following " . strtolower($tableData['header'][$nbColumns - 1]) . ": " . trim($row[$nbColumns - 1]) . ".";
            }
        }

        return $text;
    }

    /**
     * Process all tables and convert them to text nodes
     */
    public function process_markdown_tables($tables)
    {
        $nodesText = [];

        foreach ($tables as list($title, $tableData)) {
            if (empty($tableData)) {
                continue;
            }

            $titleLower = strtolower($title);

            if (strpos($titleLower, 'faculty members information') !== false) {
                $nodesText[] = $this->render_faculty_table($tableData);
            } elseif (strpos($titleLower, 'summary of evaluation') !== false || strpos($titleLower, 'evaluation') !== false) {
                $nodesText[] = $this->render_evaluation_table($tableData);
            } elseif (strpos($titleLower, 'grading equivalence') !== false) {
                $nodesText[] = $this->render_grading_equivalence_table($tableData);
            } elseif (strpos($titleLower, 'schedule and readings') !== false) {
                $nodesText[] = $this->render_schedule_table($tableData);
            } elseif (strpos($titleLower, 'important dates') !== false) {
                $nodesText[] = $this->render_important_dates_table($tableData);
            } elseif (strpos($titleLower, 'definitions of standing') !== false) {
                $nodesText[] = $this->render_definitions_table($tableData);
            } else {
                $nodesText[] = $this->render_generic_table($title, $tableData);
            }
        }

        return $nodesText;
    }

    /**
     * Extract non-table text sections from markdown
     */
    public function extract_text_sections($content)
    {
        $sections = [];

        // Split by headers
        $parts = preg_split('/\n(#{1,6}\s+.+)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        $currentTitle = "";
        for ($i = 0; $i < count($parts); $i++) {
            $part = $parts[$i];

            if (preg_match('/^#/', $part)) {
                $currentTitle = preg_replace('/^#{1,6}\s+/', '', trim($part));
                $currentTitle = preg_replace('/\*\*(.+?)\*\*/', '$1', $currentTitle);
            } else {
                // Remove complete table blocks (consecutive lines with |)
                $lines = explode("\n", $part);
                $cleanedLines = [];
                $j = 0;

                while ($j < count($lines)) {
                    if (strpos($lines[$j], '|') !== false && trim($lines[$j]) !== '') {
                        // Skip entire table block
                        while ($j < count($lines) && strpos($lines[$j], '|') !== false) {
                            $j++;
                        }
                    } else {
                        $cleanedLines[] = $lines[$j];
                        $j++;
                    }
                }

                $textWithoutTables = trim(implode("\n", $cleanedLines));

                if ($textWithoutTables && $currentTitle && strtolower($currentTitle) !== 'course information') {
                    // Clean up markdown formatting
                    $cleanText = preg_replace('/\*\*(.+?)\*\*/', '$1', $textWithoutTables);
                    $cleanText = preg_replace('/\[(.+?)\]\((.+?)\)/', '$1', $cleanText);
                    $cleanText = preg_replace('/\n\s*\n/', "\n", $cleanText);

                    if (trim($cleanText)) {
                        $sections[] = "*{$currentTitle}*\n{$cleanText}";
                    }
                }
            }
        }

        return $sections;
    }

    /**
     * Convert markdown content to structured nodes similar to the docx parser
     *
     * @param string $content Raw markdown content
     * @return array List of node dictionaries
     */
    public function convert_markdown_file($content)
    {
        $nodes = [];

        // Extract metadata
        $metadata = $this->parse_markdown_metadata($content);

        // Extract course information
        $courseInfo = $this->extract_course_information($content);
        if ($courseInfo) {
            $nodes[] = [
                "node_number" => count($nodes),
                "type" => "NarrativeText",
                "text" => $courseInfo,
                "metadata" => ["section" => "Course Information"]
            ];
        }

        // Extract and process tables
        $tables = $this->extract_tables_from_markdown($content);
        $tableNodes = $this->process_markdown_tables($tables);

        foreach ($tableNodes as $tableText) {
            if (trim($tableText)) {
                $nodes[] = [
                    "node_number" => count($nodes),
                    "type" => "NarrativeText",
                    "text" => $tableText,
                    "metadata" => ["section" => "Table"]
                ];
            }
        }

        // Extract text sections
        $textSections = $this->extract_text_sections($content);
        foreach ($textSections as $sectionText) {
            if (trim($sectionText)) {
                $nodes[] = [
                    "node_number" => count($nodes),
                    "type" => "NarrativeText",
                    "text" => $sectionText,
                    "metadata" => ["section" => "Text"]
                ];
            }
        }

        return $nodes;
    }

    /**
     * Convert markdown file from file path to structured nodes
     *
     * @param string $filePath Path to markdown file
     * @return array List of node dictionaries
     */
    public function convert_markdown_file_from_path($filePath)
    {
        $content = file_get_contents($filePath);
        return $this->convert_markdown_file($content);
    }

    /**
     * Save the parsed nodes to a markdown file
     *
     * @param array $nodes List of parsed node dictionaries
     * @param string $outputFile Path to output markdown file
     */
    public function save_results_to_markdown($nodes, $outputFile)
    {
        $output = "# Parsed Markdown Results\n\n";
        $output .= "Generated on: " . date('Y-m-d H:i:s') . "\n\n";
        $output .= "Total nodes parsed: " . count($nodes) . "\n\n";
        $output .= "---\n\n";

        foreach ($nodes as $node) {
            $output .= "## Node {$node['node_number']} ({$node['type']})\n\n";

            if (isset($node['metadata']['section'])) {
                $output .= "**Section Type:** {$node['metadata']['section']}\n\n";
            }

            $output .= "**Content:**\n\n";
            $output .= "{$node['text']}\n\n";
            $output .= "---\n\n";
        }

        file_put_contents($outputFile, $output);
    }

    /**
     * Save the parsed nodes to a markdown file with only text content
     *
     * @param array $nodes List of parsed node dictionaries
     * @param string $outputFile Path to output markdown file
     */
    public function save_text_only_to_markdown($nodes, $outputFile)
    {
        $output = "";

        foreach ($nodes as $node) {
            // Extract only the text content without any metadata or structure
            $textContent = trim($node['text']);

            if (!empty($textContent)) {
                // Convert *text*\n patterns to markdown headings
                $textContent = preg_replace('/^\*([^*]+)\*$/m', '## $1', $textContent);

                $output .= $textContent . "\n\n";
            }
        }

        // Remove any trailing whitespace
        $output = rtrim($output);

        file_put_contents($outputFile, $output);
    }

    /**
     * Execute the complete conversion process from input file to output file
     *
     * @param string $inputFilePath Path to input markdown file
     * @param string $outputFilePath Optional path to output file. If not provided, will use input filename with '_converted' suffix
     * @return array Returns the parsed nodes
     * @throws Exception If file operations fail
     */
    public function execute_conversion($inputFilePath, $outputFilePath = null)
    {
        // Check if input file exists
        if (!file_exists($inputFilePath)) {
            throw new Exception("Input file not found: {$inputFilePath}");
        }

        // Generate output file path if not provided
        if ($outputFilePath === null) {
            $pathInfo = pathinfo($inputFilePath);
            $outputFilePath = $pathInfo['dirname'] . DIRECTORY_SEPARATOR .
                             $pathInfo['filename'] . '_converted.' . $pathInfo['extension'];
        }

        // Execute the conversion
        $nodes = $this->convert_markdown_file_from_path($inputFilePath);

        // Save the results
        $this->save_results_to_markdown($nodes, $outputFilePath);

        return [
            'nodes' => $nodes,
            'input_file' => $inputFilePath,
            'output_file' => $outputFilePath,
            'node_count' => count($nodes)
        ];
    }

    /**
     * Execute the complete conversion process and save as text-only markdown
     *
     * @param string $inputFilePath Path to input markdown file
     * @param string $outputFilePath Optional path to output file. If not provided, will use input filename with '_text_only' suffix
     * @return array Returns the parsed nodes and file information
     * @throws Exception If file operations fail
     */
    public function execute_text_only_conversion($inputFilePath, $outputFilePath = null)
    {
        // Check if input file exists
        if (!file_exists($inputFilePath)) {
            throw new Exception("Input file not found: {$inputFilePath}");
        }

        // Generate output file path if not provided
        if ($outputFilePath === null) {
            $pathInfo = pathinfo($inputFilePath);
            $outputFilePath = $pathInfo['dirname'] . DIRECTORY_SEPARATOR .
                             $pathInfo['filename'] . '_text_only.' . $pathInfo['extension'];
        }

        // Execute the conversion
        $nodes = $this->convert_markdown_file_from_path($inputFilePath);

        // Save as text-only
        $this->save_text_only_to_markdown($nodes, $outputFilePath);

        return [
            'nodes' => $nodes,
            'input_file' => $inputFilePath,
            'output_file' => $outputFilePath,
            'node_count' => count($nodes)
        ];
    }
}

// Example usage / test
//if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
//    echo "Testing syllabus_parser.php with Helen-syllabus.md\n";
//    echo str_repeat("=", 60) . "\n";
//
//    try {
//        $parser = new syllabus_parser();
//        $result = $parser->execute_conversion('./Helens-syllabus.md');
//
//        echo "Successfully parsed " . $result['node_count'] . " nodes from " . basename($result['input_file']) . "\n\n";
//        echo "Results saved to: " . basename($result['output_file']) . "\n\n";
//
//        // Display summary
//        $sectionCounts = [];
//        foreach ($result['nodes'] as $node) {
//            $section = isset($node['metadata']['section']) ? $node['metadata']['section'] : 'Unknown';
//            $sectionCounts[$section] = isset($sectionCounts[$section]) ? $sectionCounts[$section] + 1 : 1;
//        }
//
//        echo "Summary by section type:\n";
//        foreach ($sectionCounts as $section => $count) {
//            echo "  {$section}: {$count} nodes\n";
//        }
//        echo "\n";
//
//        // Show first few nodes as preview
//        echo "Preview of first 3 nodes:\n";
//        for ($i = 0; $i < min(3, count($result['nodes'])); $i++) {
//            $node = $result['nodes'][$i];
//            echo "\nNode {$node['node_number']} (Type: {$node['type']}):\n";
//            if (isset($node['metadata']['section'])) {
//                echo "  Section: {$node['metadata']['section']}\n";
//            }
//            echo "  Text preview: " . substr($node['text'], 0, 200) . "...\n";
//        }
//    } catch (Exception $e) {
//        echo "Error processing file: " . $e->getMessage() . "\n";
//        echo $e->getTraceAsString() . "\n";
//    }
//}
