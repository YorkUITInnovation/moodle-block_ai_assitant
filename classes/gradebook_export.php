<?php

namespace block_ai_assistant;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/pdflib.php');

/**
 * Build a single "document model" from the persisted gradebook state and render
 * it as either a PDF (via Moodle's built-in TCPDF wrapper) or a Word-compatible
 * .doc file (well-formed HTML blob that opens natively in Word/LibreOffice).
 *
 * Both formats share one model so the two documents always have the same seven
 * standard-gradebook sections:
 *
 *   1. Cover block       — course, instructor, generation time
 *   2. Executive summary — counts, total weight, create-categories flag
 *   3. Grade categories  — category / weight / #items / items
 *   4. Activity mapping  — activity / type / category / CMID (+ "Not graded")
 *   5. Decisions         — chat turns where weights/structure were adjusted
 *   6. Transcript        — full chat log, small type
 *   7. Footer            — provenance
 */
class gradebook_export
{
    /** @var int */
    protected $courseid;

    /** @var int */
    protected $userid;

    /** @var array State row as returned by cria::gradebook_get_state. */
    protected $state;

    /** @var array Cached document model. */
    protected $model = null;

    public function __construct(int $courseid, int $userid, array $state)
    {
        $this->courseid = $courseid;
        $this->userid = $userid;
        $this->state = $state;
    }

    /**
     * Return a safe filename without the extension dot for a given format.
     */
    public function filename(string $ext): string
    {
        $model = $this->build_model();
        $name = $model['course']['shortname'] ?: ('course-' . $this->courseid);
        $name = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $name);
        $stamp = date('Ymd-His', $model['generated_at']);
        return "gradebook-{$name}-{$stamp}.{$ext}";
    }

    /**
     * Build the canonical document model used by both renderers.
     */
    public function build_model(): array
    {
        if ($this->model !== null) {
            return $this->model;
        }

        global $DB;

        $course = $DB->get_record('course', ['id' => $this->courseid], 'id, fullname, shortname', IGNORE_MISSING);
        $user = $DB->get_record('user', ['id' => $this->userid], 'id, firstname, lastname', IGNORE_MISSING);

        $result = self::decode_json($this->state['result_json'] ?? '');
        $confirmed = self::decode_json($this->state['confirmed_mapping_json'] ?? '');
        $transcript = self::decode_json($this->state['chat_history_json'] ?? '');

        $proposal = is_array($result) && isset($result['proposal']) && is_array($result['proposal'])
            ? $result['proposal']
            : [];
        $content_mapping = is_array($result) && isset($result['content_mapping']) && is_array($result['content_mapping'])
            ? $result['content_mapping']
            : [];
        $summary_in = is_array($result) && isset($result['summary']) && is_array($result['summary'])
            ? $result['summary']
            : [];

        $confirmed_rows = is_array($confirmed) ? $confirmed : [];

        // ---- Categories (from proposal.categories) -----------------------
        // Note: items_by_category is populated AFTER activities are resolved, below,
        // so the final doc reflects the user's confirmed mapping (not the stale
        // proposal.items list which is often empty).
        $categories = [];
        $proposal_categories = isset($proposal['categories']) && is_array($proposal['categories'])
            ? $proposal['categories']
            : [];
        $total_weight = 0.0;
        foreach ($proposal_categories as $cat) {
            $name = isset($cat['name']) ? (string)$cat['name'] : '';
            $weight = isset($cat['weight']) ? (float)$cat['weight'] : 0.0;
            $total_weight += $weight;

            // Per-category settings.
            $drop_lowest = isset($cat['drop_lowest']) ? (int)$cat['drop_lowest'] : 0;
            $keep_highest = isset($cat['keep_highest']) ? (int)$cat['keep_highest'] : 0;
            $hidden = !empty($cat['hidden']);
            $hidden_until = isset($cat['hidden_until']) && $cat['hidden_until'] > 0
                ? date('Y-m-d', (int)$cat['hidden_until']) : null;
            $extra_credit = !empty($cat['extra_credit']);

            // Subcategories.
            $subcategories = [];
            if (isset($cat['subcategories']) && is_array($cat['subcategories'])) {
                foreach ($cat['subcategories'] as $sub) {
                    $subcategories[] = [
                        'name' => isset($sub['name']) ? (string)$sub['name'] : '',
                        'weight' => isset($sub['weight']) ? (float)$sub['weight'] : 0.0,
                    ];
                }
            }

            $categories[] = [
                'name' => $name,
                'weight' => $weight,
                'items' => [],
                'drop_lowest' => $drop_lowest,
                'keep_highest' => $keep_highest,
                'hidden' => $hidden,
                'hidden_until' => $hidden_until,
                'extra_credit' => $extra_credit,
                'subcategories' => $subcategories,
            ];
        }

        // ---- Effects (from proposal notes with "Effect:" prefix) ----------
        $effects = [];
        $proposal_notes = isset($proposal['notes']) && is_array($proposal['notes'])
            ? $proposal['notes'] : [];
        // Deduplicate effects by topic key (newest = last occurrence wins).
        $effect_log = [];
        foreach ($proposal_notes as $note) {
            if (is_string($note) && strncmp($note, 'Effect:', 7) === 0) {
                $effect_log[] = trim(substr($note, 7));
            }
        }
        // Newest first, deduplicate by simple prefix (category name).
        $seen_effects = [];
        foreach (array_reverse($effect_log) as $eff) {
            $key = strtolower($eff);
            if (!isset($seen_effects[$key])) {
                $seen_effects[$key] = true;
                $effects[] = $eff;
            }
        }

        // ---- Aggregation method ------------------------------------------
        $aggregation_method_code = isset($proposal['aggregation_method'])
            ? (int)$proposal['aggregation_method'] : 13;
        $aggregation_method_names = [
            0 => 'Mean of grades',
            10 => 'Weighted mean of grades',
            11 => 'Simple weighted mean of grades',
            12 => 'Mean of grades (with extra credits)',
            13 => 'Natural',
        ];
        $aggregation_method_name = $aggregation_method_names[$aggregation_method_code]
            ?? ('Method ' . $aggregation_method_code);

        // ---- Formulas (from categories) ----------------------------------
        $formulas = [];
        foreach ($proposal_categories as $cat) {
            if (isset($cat['calculation_formula']) && !empty($cat['calculation_formula'])) {
                $cat_name = isset($cat['name']) ? (string)$cat['name'] : '';
                $formula = (string)$cat['calculation_formula'];
                $refs = isset($cat['formula_item_refs']) && is_array($cat['formula_item_refs'])
                    ? $cat['formula_item_refs']
                    : [];
                if ($cat_name && $formula) {
                    $formulas[] = [
                        'category' => $cat_name,
                        'formula' => $formula,
                        'referenced_items' => $refs,
                    ];
                }
            }
        }

        // ---- Activities + Not-graded -------------------------------------
        $activities_by_cmid = [];
        if (isset($content_mapping['mappings']) && is_array($content_mapping['mappings'])) {
            foreach ($content_mapping['mappings'] as $m) {
                $cmid = isset($m['moodle_cmid']) ? (int)$m['moodle_cmid'] : 0;
                if ($cmid <= 0) {
                    continue;
                }
                $activities_by_cmid[$cmid] = [
                    'cmid' => $cmid,
                    'name' => isset($m['name']) ? (string)$m['name'] : '',
                    'type' => isset($m['type']) ? (string)$m['type'] : '',
                    'category' => isset($m['category']) ? (string)$m['category'] : '',
                ];
            }
        }

        // Overlay user-confirmed categories on top of the proposal-derived mapping.
        foreach ($confirmed_rows as $row) {
            $cmid = isset($row['moodle_cmid']) ? (int)$row['moodle_cmid'] : 0;
            if ($cmid <= 0) {
                continue;
            }
            if (!isset($activities_by_cmid[$cmid])) {
                $activities_by_cmid[$cmid] = [
                    'cmid' => $cmid,
                    'name' => isset($row['name']) ? (string)$row['name'] : '',
                    'type' => isset($row['type']) ? (string)$row['type'] : '',
                    'category' => '',
                ];
            }
            $activities_by_cmid[$cmid]['category'] = isset($row['category']) ? (string)$row['category'] : '';
            if (!empty($row['name']) && empty($activities_by_cmid[$cmid]['name'])) {
                $activities_by_cmid[$cmid]['name'] = (string)$row['name'];
            }
            if (!empty($row['type']) && empty($activities_by_cmid[$cmid]['type'])) {
                $activities_by_cmid[$cmid]['type'] = (string)$row['type'];
            }
        }

        // Enrich any missing activity name/type by asking Moodle's course_modules table.
        $missing_cmids = [];
        foreach ($activities_by_cmid as $cmid => $row) {
            if (empty($row['name']) || empty($row['type'])) {
                $missing_cmids[] = $cmid;
            }
        }
        if ($missing_cmids) {
            try {
                list($sql, $params) = $DB->get_in_or_equal($missing_cmids, SQL_PARAMS_NAMED);
                $params['course'] = $this->courseid;
                $records = $DB->get_records_sql("
                    SELECT cm.id AS cmid, m.name AS modname, cm.instance
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module
                     WHERE cm.id $sql
                       AND cm.course = :course
                ", $params);
                foreach ($records as $rec) {
                    $cmid = (int)$rec->cmid;
                    if (!isset($activities_by_cmid[$cmid])) {
                        continue;
                    }
                    if (empty($activities_by_cmid[$cmid]['type'])) {
                        $activities_by_cmid[$cmid]['type'] = (string)$rec->modname;
                    }
                    if (empty($activities_by_cmid[$cmid]['name']) && $rec->modname && $rec->instance) {
                        $inst = $DB->get_record($rec->modname, ['id' => (int)$rec->instance], 'id, name', IGNORE_MISSING);
                        if ($inst && !empty($inst->name)) {
                            $activities_by_cmid[$cmid]['name'] = (string)$inst->name;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Enrichment is best-effort; the export still renders with whatever we have.
            }
        }

        $activities = [];
        $not_graded = [];
        foreach ($activities_by_cmid as $row) {
            $cat = trim((string)$row['category']);
            if ($cat === '' || strcasecmp($cat, 'Not graded') === 0) {
                $not_graded[] = $row;
            } else {
                $activities[] = $row;
            }
        }
        // Stable sort: by category then name.
        usort($activities, function ($a, $b) {
            return [$a['category'], $a['name']] <=> [$b['category'], $b['name']];
        });
        usort($not_graded, function ($a, $b) {
            return [$a['type'], $a['name']] <=> [$b['type'], $b['name']];
        });

        // ---- Populate category -> items from the confirmed activity mapping.
        // This is what makes the "# of items" and "Items" columns meaningful.
        $items_by_cat = [];
        foreach ($activities as $row) {
            $cname = (string)$row['category'];
            if (!isset($items_by_cat[$cname])) {
                $items_by_cat[$cname] = [];
            }
            $items_by_cat[$cname][] = [
                'name' => (string)$row['name'],
                'type' => (string)$row['type'],
                'cmid' => (int)$row['cmid'],
            ];
        }
        foreach ($categories as &$c) {
            $c['items'] = $items_by_cat[$c['name']] ?? [];
        }
        unset($c);

        // ---- Decisions (extracted from chat transcript) ------------------
        $decisions = $this->extract_decisions(is_array($transcript) ? $transcript : []);

        // ---- Summary -----------------------------------------------------
        $summary = [
            'category_count' => count($categories),
            'graded_count' => isset($summary_in['graded_count'])
                ? (int)$summary_in['graded_count']
                : count($activities),
            'not_graded_count' => isset($summary_in['not_graded_count'])
                ? (int)$summary_in['not_graded_count']
                : count($not_graded),
            'total_weight' => isset($summary_in['total_weight']) && $summary_in['total_weight'] !== null
                ? (float)$summary_in['total_weight']
                : round($total_weight, 2),
            'create_categories' => isset($summary_in['create_categories'])
                ? (bool)$summary_in['create_categories']
                : true,
            'finalized_at' => isset($summary_in['finalized_at']) ? (string)$summary_in['finalized_at'] : '',
        ];

        $this->model = [
            'course' => [
                'id' => $course ? (int)$course->id : $this->courseid,
                'fullname' => $course ? (string)$course->fullname : '',
                'shortname' => $course ? (string)$course->shortname : '',
            ],
            'author' => [
                'fullname' => $user ? trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? '')) : '',
            ],
            'generated_at' => time(),
            'summary' => $summary,
            'categories' => $categories,
            'activities' => $activities,
            'not_graded' => $not_graded,
            'decisions' => $decisions,
            'transcript' => is_array($transcript) ? $transcript : [],
            'effects' => $effects,
            'formulas' => $formulas,
            'aggregation_method' => $aggregation_method_name,
        ];

        return $this->model;
    }

    /**
     * Very simple heuristic to pull out decision-like human turns from the chat.
     * Anything that contains a weight/percentage, the word "category", or a
     * directive verb ("make", "change", "set", "keep", "rename") is kept.
     *
     * The assistant's follow-up text is distilled to a single "delta" line
     * (e.g. "Updated: Assignments 20%, Labs 20%") so the Decisions section
     * stays scannable instead of turning into a wall of text.
     */
    protected function extract_decisions(array $transcript): array
    {
        $decisions = [];
        $last_human = null;
        foreach ($transcript as $turn) {
            $role = isset($turn['role']) ? (string)$turn['role'] : '';
            $text = isset($turn['text']) ? trim((string)$turn['text']) : '';
            if ($text === '') {
                continue;
            }

            if ($role === 'human' || $role === 'user') {
                $last_human = self::strip_markdown($text);
                if ($this->looks_like_decision($text)) {
                    $decisions[] = $last_human;
                }
            } else {
                // For the bot turn immediately following a human directive, distill
                // a compact summary of weight/category changes instead of copying prose.
                if ($last_human !== null) {
                    $delta = $this->summarize_weight_changes($text);
                    if ($delta !== '') {
                        $decisions[] = '→ ' . $delta;
                    }
                    $last_human = null;
                }
            }
        }
        // Deduplicate while preserving order.
        $seen = [];
        $out = [];
        foreach ($decisions as $d) {
            $k = mb_strtolower(trim($d));
            if ($k === '' || isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $out[] = $d;
        }
        return $out;
    }

    /**
     * Remove common Markdown syntax (``**``, ``__``, leading ``-``/``*``, backticks, ``•``)
     * from a chat message so it reads as clean prose in the PDF/Word.
     */
    protected static function strip_markdown(string $text): string
    {
        $out = $text;
        // Bold / italic markers.
        $out = preg_replace('/\*\*(.+?)\*\*/us', '$1', $out);
        $out = preg_replace('/__(.+?)__/us', '$1', $out);
        $out = preg_replace('/(?<!\*)\*(?!\s)([^*]+?)\*(?!\*)/us', '$1', $out);
        // Inline code.
        $out = preg_replace('/`([^`]+)`/us', '$1', $out);
        // Bullet lines: strip leading "- ", "* ", "• " on their own lines.
        $out = preg_replace('/^\s*[-*•]\s+/mu', '• ', $out);
        // Collapse trailing whitespace on each line.
        $out = preg_replace("/[\t ]+\n/u", "\n", $out);
        return trim((string)$out);
    }

    /**
     * Parse an assistant reply like "- **Assignments** (20.0%): 23 items" and return
     * a compact delta line suitable for the Decisions section. Returns '' if nothing
     * parseable is found.
     */
    protected function summarize_weight_changes(string $text): string
    {
        $pairs = [];
        if (preg_match_all('/\*{0,2}([A-Za-z][A-Za-z \-]{1,30})\*{0,2}\s*\(\s*(\d+(?:\.\d+)?)\s*%\s*\)/u', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $name = trim($m[1]);
                $pct = rtrim(rtrim(number_format((float)$m[2], 1, '.', ''), '0'), '.');
                if ($name !== '') {
                    $pairs[$name] = $pct . '%';
                }
            }
        }
        if (!$pairs) {
            return '';
        }
        $parts = [];
        foreach ($pairs as $name => $pct) {
            $parts[] = "{$name} {$pct}";
        }
        return 'Updated weights: ' . implode(', ', $parts);
    }

    protected function looks_like_decision(string $text): bool
    {
        if (preg_match('/\b\d+(\.\d+)?\s*%/u', $text)) {
            return true;
        }
        if (preg_match('/\b(make|change|set|keep|rename|add|remove|use|weight|category|categories|assignment|assignments|lab|labs|quiz|quizzes|exam|exams|midterm|final|participation|homework)\b/iu', $text)) {
            return true;
        }
        return false;
    }

    protected function looks_like_confirmation(string $text): bool
    {
        return (bool)preg_match('/\b(updated|set|changed|ok|okay|done|applied|noted|got it)\b/iu', $text);
    }

    protected function first_sentence(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return '';
        }
        if (preg_match('/^(.{1,200}?[\.\!\?])(\s|$)/u', $text, $m)) {
            return $m[1];
        }
        return mb_substr($text, 0, 200);
    }

    // =================================================================
    // PDF renderer
    // =================================================================

    /**
     * @return string Raw PDF bytes.
     */
    public function render_pdf(): string
    {
        $model = $this->build_model();

        $footer_left = $model['course']['shortname'] ?: ($model['course']['fullname'] ?: 'Gradebook');
        $footer_right = 'Generated by Cria AI Assistant';
        $pdf = new gradebook_pdf($footer_left, $footer_right);
        $pdf->SetCreator('Cria AI Assistant');
        $pdf->SetAuthor($model['author']['fullname'] ?: 'Cria AI Assistant');
        $pdf->SetTitle('Gradebook — ' . ($model['course']['fullname'] ?: 'Course ' . $this->courseid));
        $pdf->SetSubject('Gradebook proposal');
        $pdf->SetMargins(18, 22, 18);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);

        $pdf->AddPage();

        $html = $this->pdf_html($model);
        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('', 'S');
    }

    protected function pdf_html(array $m): string
    {
        $css = '<style>
            h1 { color: #1a365d; font-size: 20pt; margin: 0 0 4pt 0; font-weight: bold; }
            h2 { color: #2a4365; font-size: 13pt; margin: 14pt 0 6pt 0; border-bottom: 1px solid #cbd5e0; padding-bottom: 2pt; font-weight: bold; }
            h3 { color: #2a4365; font-size: 11pt; margin: 10pt 0 4pt 0; font-weight: bold; }
            .muted { color: #718096; font-size: 9pt; }
            table { border-collapse: collapse; width: 100%; font-size: 10pt; margin: 6pt 0 10pt 0; }
            th { background-color: #edf2f7; color: #2d3748; padding: 6pt; border: 1px solid #cbd5e0; text-align: left; font-weight: bold; }
            td { padding: 6pt; border: 1px solid #cbd5e0; vertical-align: top; }
            .not-graded td { color: #718096; background-color: #f7fafc; }
            .right { text-align: right; }
            .center { text-align: center; }
            ul { margin: 4pt 0 4pt 12pt; padding: 0; }
            li { margin: 2pt 0; }
            .kvp { margin: 4pt 0; padding: 4pt 0; }
            .kvp b { color: #2a4365; }
            code { background: #f5f5f5; padding: 2pt 4pt; font-family: "Courier New", monospace; font-size: 8.5pt; border-radius: 2pt; }
            .formula-block { background: #fafafa; padding: 6pt; margin: 4pt 0; border-left: 3px solid #4299e1; }
            .tscript { font-size: 8.5pt; }
            .tscript .role { font-weight: bold; color: #2a4365; }
            .tscript .ts { color: #a0aec0; font-size: 7.5pt; }
            .tscript p { margin: 3pt 0; }
        </style>';

        $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $g = userdate($m['generated_at']);

        $out = $css;

        // 1) Cover
        $out .= '<h1>' . $h($m['course']['fullname'] ?: ('Course ' . $m['course']['id'])) . '</h1>';
        $out .= '<div class="muted">Gradebook proposal</div>';
        $out .= '<div class="kvp"><b>Course short name:</b> ' . $h($m['course']['shortname']) . '</div>';
        if ($m['author']['fullname']) {
            $out .= '<div class="kvp"><b>Prepared by:</b> ' . $h($m['author']['fullname']) . '</div>';
        }
        $out .= '<div class="kvp"><b>Generated:</b> ' . $h($g) . '</div>';

        // 2) Executive summary
        $out .= '<h2>Executive summary</h2>';
        $s = $m['summary'];
        $total_weight_str = $s['total_weight'] !== null ? number_format((float)$s['total_weight'], 2) . '%' : '—';
        $create_cats = $s['create_categories'] ? 'Yes' : 'No';
        $out .= '<p>This gradebook proposal defines <b>' . (int)$s['category_count'] . '</b> grade categor' . ((int)$s['category_count'] === 1 ? 'y' : 'ies')
            . ', covering <b>' . (int)$s['graded_count'] . '</b> graded activit' . ((int)$s['graded_count'] === 1 ? 'y' : 'ies')
            . ' and <b>' . (int)$s['not_graded_count'] . '</b> non-graded activit' . ((int)$s['not_graded_count'] === 1 ? 'y' : 'ies')
            . '. Total weight across categories: <b>' . $h($total_weight_str) . '</b>. '
            . 'Grade aggregation method: <b>' . $h($m['aggregation_method']) . '</b>. '
            . 'Grade categories will ' . ($s['create_categories'] ? '' : '<b>not</b> ') . 'be created in Moodle: <b>' . $create_cats . '</b>.</p>';

        // 3) Categories table
        $out .= '<h2>1. Grade categories</h2>';
        if (!empty($m['categories'])) {
            $out .= '<table><thead><tr>'
                . '<th>Category</th><th class="right">Weight (%)</th><th class="right"># of items</th><th>Settings</th><th>Items / Subcategories</th>'
                . '</tr></thead><tbody>';
            foreach ($m['categories'] as $c) {
                $items = array_map(fn($i) => $h($i['name']), $c['items']);
                $settings = [];
                if ($c['drop_lowest'] > 0) {
                    $settings[] = 'Drop lowest ' . $c['drop_lowest'];
                }
                if ($c['keep_highest'] > 0) {
                    $settings[] = 'Keep top ' . $c['keep_highest'];
                }
                if ($c['extra_credit']) {
                    $settings[] = 'Extra credit';
                }
                if ($c['hidden']) {
                    $settings[] = $c['hidden_until'] ? 'Hidden until ' . $h($c['hidden_until']) : 'Hidden';
                }

                // Check if this category has a formula
                $has_formula = false;
                foreach ($m['formulas'] as $f) {
                    if ($f['category'] === $c['name']) {
                        $has_formula = true;
                        $settings[] = 'Has formula';
                        break;
                    }
                }

                $settings_str = $settings ? implode('<br/>', $settings) : '<span class="muted">—</span>';

                $subs_html = '';
                if (!empty($c['subcategories'])) {
                    $sub_parts = [];
                    foreach ($c['subcategories'] as $sub) {
                        $sub_parts[] = $h($sub['name']) . ' (' . number_format((float)$sub['weight'], 1) . '%)';
                    }
                    $subs_html = '<i>Subcategories:</i> ' . implode(', ', $sub_parts);
                    if ($items) {
                        $subs_html .= '<br/>' . implode('<br/>', $items);
                    }
                } else {
                    $subs_html = $items ? implode('<br/>', $items) : '<span class="muted">—</span>';
                }

                $out .= '<tr>'
                    . '<td><b>' . $h($c['name']) . '</b></td>'
                    . '<td class="right">' . $h(number_format((float)$c['weight'], 2)) . '</td>'
                    . '<td class="right">' . count($items) . '</td>'
                    . '<td>' . $settings_str . '</td>'
                    . '<td>' . $subs_html . '</td>'
                    . '</tr>';
            }
            $out .= '</tbody></table>';
        } else {
            $out .= '<p class="muted">No grade categories defined yet.</p>';
        }

        // 4) Activity mapping
        $out .= '<h2>2. Activity mapping</h2>';
        if (!empty($m['activities'])) {
            $out .= '<table><thead><tr>'
                . '<th>Activity</th><th>Type</th><th>Category</th><th class="right">CMID</th>'
                . '</tr></thead><tbody>';
            foreach ($m['activities'] as $a) {
                $out .= '<tr>'
                    . '<td>' . $h($a['name']) . '</td>'
                    . '<td>' . $h($a['type']) . '</td>'
                    . '<td>' . $h($a['category']) . '</td>'
                    . '<td class="right">' . (int)$a['cmid'] . '</td>'
                    . '</tr>';
            }
            $out .= '</tbody></table>';
        } else {
            $out .= '<p class="muted">No graded activities were mapped.</p>';
        }

        if (!empty($m['not_graded'])) {
            $out .= '<h3>Not graded</h3>';
            $out .= '<table><thead><tr>'
                . '<th>Activity</th><th>Type</th><th class="right">CMID</th>'
                . '</tr></thead><tbody>';
            foreach ($m['not_graded'] as $a) {
                $out .= '<tr class="not-graded">'
                    . '<td>' . $h($a['name']) . '</td>'
                    . '<td>' . $h($a['type']) . '</td>'
                    . '<td class="right">' . (int)$a['cmid'] . '</td>'
                    . '</tr>';
            }
            $out .= '</tbody></table>';
        }

        // 5) Formulas (if any)
        if (!empty($m['formulas'])) {
            $out .= '<h2>3. Calculation formulas</h2>';
            $out .= '<p class="muted">Categories with custom Excel-style formulas for grade calculation:</p>';
            foreach ($m['formulas'] as $f) {
                $out .= '<div class="formula-block">';
                $out .= '<b>' . $h($f['category']) . ':</b><br/>';
                $out .= '<code>' . $h($f['formula']) . '</code>';
                if (!empty($f['referenced_items'])) {
                    $out .= '<br/><span class="muted">References: ' . $h(implode(', ', $f['referenced_items'])) . '</span>';
                }
                $out .= '</div>';
            }
        }

        // 6) Effects
        $out .= '<h2>4. Applied effects &amp; configuration</h2>';
        if (!empty($m['effects'])) {
            $out .= '<ul>';
            foreach ($m['effects'] as $eff) {
                $out .= '<li>' . $h($eff) . '</li>';
            }
            $out .= '</ul>';
        } else {
            $out .= '<p class="muted">No effects or configuration changes recorded.</p>';
        }

        // 7) Decisions
        $out .= '<h2>5. Decisions from the conversation</h2>';
        if (!empty($m['decisions'])) {
            $out .= '<ul>';
            foreach ($m['decisions'] as $d) {
                $out .= '<li>' . $h($d) . '</li>';
            }
            $out .= '</ul>';
        } else {
            $out .= '<p class="muted">No explicit adjustments were made during the conversation.</p>';
        }

        // 8) Transcript
        $out .= '<h2>6. Appendix: conversation transcript</h2>';
        if (!empty($m['transcript'])) {
            $out .= '<div class="tscript">';
            foreach ($m['transcript'] as $t) {
                $role = isset($t['role']) ? (string)$t['role'] : '';
                $text = self::strip_markdown(isset($t['text']) ? (string)$t['text'] : '');
                $ts = isset($t['ts']) ? (int)$t['ts'] : 0;
                $label = $role === 'human' || $role === 'user' ? 'Instructor' : 'Assistant';
                $time_str = $ts > 0 ? userdate($ts) : '';
                $out .= '<p><span class="role">' . $h($label) . '</span>'
                    . ($time_str ? ' <span class="ts">(' . $h($time_str) . ')</span>' : '')
                    . '<br/>' . nl2br($h($text)) . '</p>';
            }
            $out .= '</div>';
        } else {
            $out .= '<p class="muted">Transcript unavailable.</p>';
        }

        return $out;
    }

    // =================================================================
    // Word (HTML .doc) renderer
    // =================================================================

    /**
     * @return string Raw bytes of a well-formed HTML document that Word / LibreOffice
     *               will open as a native .doc. No external dependencies.
     */
    public function render_docx(): string
    {
        $m = $this->build_model();
        $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $title = 'Gradebook — ' . ($m['course']['fullname'] ?: ('Course ' . $m['course']['id']));
        $g = userdate($m['generated_at']);

        // Important: this is legacy Word-compatible HTML (downloaded as .doc).
        // Some Word installations (and Word Online) fail to open XHTML/XML-style headers.
        // Keep it simple: HTML 4.0 Transitional + Word's conditional XML block.
        $out = "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.0 Transitional//EN\">\n"
            . "<html xmlns:o=\"urn:schemas-microsoft-com:office:office\" xmlns:w=\"urn:schemas-microsoft-com:office:word\" xmlns=\"http://www.w3.org/TR/REC-html40\">\n"
            . "<head>\n"
            . "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />\n"
            . "<meta charset=\"utf-8\" />\n"
            . "<meta name=\"ProgId\" content=\"Word.Document\" />\n"
            . "<meta name=\"Generator\" content=\"Cria AI Assistant\" />\n"
            . "<title>" . $h($title) . "</title>\n"
            . "<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->\n"
            . "<style type=\"text/css\">\n"
            . "@page WordSection1 { size: 8.5in 11in; margin: 1in 1in 1in 1in; }\n"
            . "div.WordSection1 { page: WordSection1; }\n"
            . "body { font-family: Calibri, 'Segoe UI', Arial, sans-serif; font-size: 11pt; color: #1a202c; line-height: 1.4; }\n"
            . "h1 { color: #1a365d; font-size: 22pt; margin: 0 0 6pt 0; font-weight: bold; }\n"
            . "h2 { color: #2a4365; font-size: 14pt; margin: 18pt 0 8pt 0; border-bottom: 1px solid #cbd5e0; padding-bottom: 3pt; font-weight: bold; }\n"
            . "h3 { color: #2a4365; font-size: 12pt; margin: 10pt 0 6pt 0; font-weight: bold; }\n"
            . ".muted { color: #718096; font-size: 9.5pt; }\n"
            . "p { margin: 6pt 0; line-height: 1.5; }\n"
            . "table { border-collapse: collapse; width: 100%; font-size: 10.5pt; margin: 8pt 0 12pt 0; }\n"
            . "th { background-color: #edf2f7; color: #2d3748; padding: 8pt; border: 1px solid #cbd5e0; text-align: left; font-weight: bold; }\n"
            . "td { padding: 8pt; border: 1px solid #cbd5e0; vertical-align: top; }\n"
            . "tr.not-graded td { color: #718096; background-color: #f7fafc; }\n"
            . ".right { text-align: right; }\n"
            . "ul { margin: 6pt 0 6pt 20pt; padding: 0; }\n"
            . "li { margin: 3pt 0; }\n"
            . ".kvp { margin: 4pt 0; padding: 4pt 0; }\n"
            . ".kvp b { color: #2a4365; }\n"
            . "code { background: #f5f5f5; padding: 3pt 5pt; font-family: 'Courier New', monospace; font-size: 10pt; display: block; margin: 4pt 0; border-left: 3px solid #4299e1; padding-left: 8pt; }\n"
            . ".formula-block { background: #fafafa; padding: 8pt; margin: 6pt 0; border-left: 3px solid #4299e1; }\n"
            . ".tscript p { margin: 4pt 0; font-size: 10pt; line-height: 1.3; }\n"
            . ".tscript .role { font-weight: bold; color: #2a4365; }\n"
            . ".tscript .ts { color: #a0aec0; font-size: 9pt; }\n"
            . "</style>\n"
            . "</head>\n"
            . "<body>\n"
            . "<div class=\"WordSection1\">\n";

        // Cover
        $out .= '<h1>' . $h($m['course']['fullname'] ?: ('Course ' . $m['course']['id'])) . '</h1>';
        $out .= '<div class="muted">Gradebook proposal</div>';
        $out .= '<div class="kvp"><b>Course short name:</b> ' . $h($m['course']['shortname']) . '</div>';
        if ($m['author']['fullname']) {
            $out .= '<div class="kvp"><b>Prepared by:</b> ' . $h($m['author']['fullname']) . '</div>';
        }
        $out .= '<div class="kvp"><b>Generated:</b> ' . $h($g) . '</div>';

        // Executive summary
        $s = $m['summary'];
        $total_weight_str = $s['total_weight'] !== null ? number_format((float)$s['total_weight'], 2) . '%' : '—';
        $out .= '<h2>Executive summary</h2>';
        $out .= '<p>This gradebook proposal defines <b>' . (int)$s['category_count'] . '</b> grade categor' . ((int)$s['category_count'] === 1 ? 'y' : 'ies')
            . ', covering <b>' . (int)$s['graded_count'] . '</b> graded activit' . ((int)$s['graded_count'] === 1 ? 'y' : 'ies')
            . ' and <b>' . (int)$s['not_graded_count'] . '</b> non-graded activit' . ((int)$s['not_graded_count'] === 1 ? 'y' : 'ies')
            . '. Total weight across categories: <b>' . $h($total_weight_str) . '</b>. '
            . 'Grade aggregation method: <b>' . $h($m['aggregation_method']) . '</b>. '
            . 'Grade categories will ' . ($s['create_categories'] ? '' : '<b>not</b> ') . 'be created in Moodle: <b>' . ($s['create_categories'] ? 'Yes' : 'No') . '</b>.</p>';

        // Categories
        $out .= '<h2>1. Grade categories</h2>';
        if (!empty($m['categories'])) {
            $out .= '<table><thead><tr><th>Category</th><th class="right">Weight (%)</th><th class="right"># of items</th><th>Settings</th><th>Items / Subcategories</th></tr></thead><tbody>';
            foreach ($m['categories'] as $c) {
                $items = array_map(fn($i) => $h($i['name']), $c['items']);
                $settings = [];
                if ($c['drop_lowest'] > 0) {
                    $settings[] = 'Drop lowest ' . $c['drop_lowest'];
                }
                if ($c['keep_highest'] > 0) {
                    $settings[] = 'Keep top ' . $c['keep_highest'];
                }
                if ($c['extra_credit']) {
                    $settings[] = 'Extra credit';
                }
                if ($c['hidden']) {
                    $settings[] = $c['hidden_until'] ? 'Hidden until ' . $h($c['hidden_until']) : 'Hidden';
                }

                // Check if this category has a formula
                $has_formula = false;
                foreach ($m['formulas'] as $f) {
                    if ($f['category'] === $c['name']) {
                        $has_formula = true;
                        $settings[] = 'Has formula';
                        break;
                    }
                }

                $settings_str = $settings ? implode('<br/>', $settings) : '<span class="muted">—</span>';

                $subs_html = '';
                if (!empty($c['subcategories'])) {
                    $sub_parts = [];
                    foreach ($c['subcategories'] as $sub) {
                        $sub_parts[] = $h($sub['name']) . ' (' . number_format((float)$sub['weight'], 1) . '%)';
                    }
                    $subs_html = '<i>Subcategories:</i> ' . implode(', ', $sub_parts);
                    if ($items) {
                        $subs_html .= '<br/>' . implode('<br/>', $items);
                    }
                } else {
                    $subs_html = $items ? implode('<br/>', $items) : '<span class="muted">—</span>';
                }

                $out .= '<tr><td><b>' . $h($c['name']) . '</b></td>'
                    . '<td class="right">' . $h(number_format((float)$c['weight'], 2)) . '</td>'
                    . '<td class="right">' . count($items) . '</td>'
                    . '<td>' . $settings_str . '</td>'
                    . '<td>' . $subs_html . '</td></tr>';
            }
            $out .= '</tbody></table>';
        } else {
            $out .= '<p class="muted">No grade categories defined yet.</p>';
        }

        // Activities
        $out .= '<h2>2. Activity mapping</h2>';
        if (!empty($m['activities'])) {
            $out .= '<table><thead><tr><th>Activity</th><th>Type</th><th>Category</th><th class="right">CMID</th></tr></thead><tbody>';
            foreach ($m['activities'] as $a) {
                $out .= '<tr><td>' . $h($a['name']) . '</td><td>' . $h($a['type']) . '</td><td>' . $h($a['category']) . '</td><td class="right">' . (int)$a['cmid'] . '</td></tr>';
            }
            $out .= '</tbody></table>';
        } else {
            $out .= '<p class="muted">No graded activities were mapped.</p>';
        }
        if (!empty($m['not_graded'])) {
            $out .= '<h3>Not graded</h3>';
            $out .= '<table><thead><tr><th>Activity</th><th>Type</th><th class="right">CMID</th></tr></thead><tbody>';
            foreach ($m['not_graded'] as $a) {
                $out .= '<tr class="not-graded"><td>' . $h($a['name']) . '</td><td>' . $h($a['type']) . '</td><td class="right">' . (int)$a['cmid'] . '</td></tr>';
            }
            $out .= '</tbody></table>';
        }

        // Formulas (if any)
        if (!empty($m['formulas'])) {
            $out .= '<h2>3. Calculation formulas</h2>';
            $out .= '<p class="muted">Categories with custom Excel-style formulas for grade calculation:</p>';
            foreach ($m['formulas'] as $f) {
                $out .= '<div class="formula-block">';
                $out .= '<b>' . $h($f['category']) . ':</b><br/>';
                $out .= '<code>' . $h($f['formula']) . '</code>';
                if (!empty($f['referenced_items'])) {
                    $out .= '<p class="muted">References: ' . $h(implode(', ', $f['referenced_items'])) . '</p>';
                }
                $out .= '</div>';
            }
        }

        // Effects
        $out .= '<h2>' . (!empty($m['formulas']) ? '4' : '3') . '. Applied effects &amp; configuration</h2>';
        if (!empty($m['effects'])) {
            $out .= '<ul>';
            foreach ($m['effects'] as $eff) {
                $out .= '<li>' . $h($eff) . '</li>';
            }
            $out .= '</ul>';
        } else {
            $out .= '<p class="muted">No effects or configuration changes recorded.</p>';
        }

        // Decisions
        $out .= '<h2>' . (!empty($m['formulas']) ? '5' : '4') . '. Decisions from the conversation</h2>';
        if (!empty($m['decisions'])) {
            $out .= '<ul>';
            foreach ($m['decisions'] as $d) {
                $out .= '<li>' . $h($d) . '</li>';
            }
            $out .= '</ul>';
        } else {
            $out .= '<p class="muted">No explicit adjustments were made during the conversation.</p>';
        }

        // Transcript
        $out .= '<h2>' . (!empty($m['formulas']) ? '6' : '5') . '. Appendix: conversation transcript</h2>';
        if (!empty($m['transcript'])) {
            $out .= '<div class="tscript">';
            foreach ($m['transcript'] as $t) {
                $role = isset($t['role']) ? (string)$t['role'] : '';
                $text = self::strip_markdown(isset($t['text']) ? (string)$t['text'] : '');
                $ts = isset($t['ts']) ? (int)$t['ts'] : 0;
                $label = $role === 'human' || $role === 'user' ? 'Instructor' : 'Assistant';
                $time_str = $ts > 0 ? userdate($ts) : '';
                $out .= '<p><span class="role">' . $h($label) . '</span>'
                    . ($time_str ? ' <span class="ts">(' . $h($time_str) . ')</span>' : '')
                    . '<br/>' . nl2br($h($text)) . '</p>';
            }
            $out .= '</div>';
        } else {
            $out .= '<p class="muted">Transcript unavailable.</p>';
        }

        $out .= '<p class="muted" style="margin-top:18pt;">Generated by Cria AI Assistant.</p>';

        $out .= "</div>\n</body>\n</html>\n";

        return $out;
    }

    // =================================================================

    protected static function decode_json($value)
    {
        if (is_array($value) || is_object($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return $decoded === null ? [] : $decoded;
    }
}
