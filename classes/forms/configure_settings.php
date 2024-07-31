<?php

namespace block_ai_assistant;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/config.php');


class configure_settings extends \moodleform
{
    protected function definition()
    {
        global $CFG, $OUTPUT;

        $formdata = $this->_customdata['formdata'];
        $mform = &$this->_form;

        $mform->addElement(
            'hidden',
            'id'
        );

        $mform->setType(
            'id',
            PARAM_INT
        );

        $mform->addElement(
            'hidden',
            'courseid'
        );

        $mform->setType(
            'courseid',
            PARAM_INT
        );



        $mform->addElement(
            'header',
            'Configure Settings',
            get_string('configure_settings', 'block_ai_assistant')
        );
        $attributes = array(
            'size' => '20'
        );
        $mform->addElement(
            'text',
            'subtitle',
            get_string('subtitle', 'block_ai_assistant'),
            $attributes
        );
        $mform->setType(
            'subtitle',
            PARAM_TEXT
        );
        $mform->addElement(
            'textarea',
            'welcome_message',
            get_string("welcome_message", "block_ai_assistant"),
            'wrap="virtual" rows="2" cols="20"'
        );
        $mform->setType(
            'welcome_message',
            PARAM_TEXT
        );
        $mform->addElement(
            'textarea',
            'no_context_message',
            get_string("no_context_message", "block_ai_assistant"),
            'wrap="virtual" rows="2" cols="20"'
        );
        $mform->setType(
            'no_context_message',
            PARAM_TEXT
        );
        $options =array(
            1 => get_string('bottom_left', 'block_ai_assistant'),
            2 => get_string('bottom_right', 'block_ai_assistant'),
            3 => get_string('top_right', 'block_ai_assistant'),
            4 => get_string('top_left', 'block_ai_assistant'),
        );
        $mform->addElement(
            'select',
            'embed_position',
            get_string('embed_position', "block_ai_assistant"),
            $options)
        ;
        $mform->setType(
            'embed_position',
            PARAM_INT
        );

        // Language select element
        $languages = array(
            'en' => 'English',
            'fr' => 'French',
            'es' => 'Spanish',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
            'pl' => 'Polish',
            'ru' => 'Russian',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'zh' => 'Chinese',
            'ar' => 'Arabic',
            'tr' => 'Turkish',
            'vi' => 'Vietnamese',
            'th' => 'Thai',
            'id' => 'Indonesian',
            'ms' => 'Malay',
            'fil' => 'Filipino',
            'hi' => 'Hindi',
            'bn' => 'Bengali',
            'gu' => 'Gujarati',
            'kn' => 'Kannada',
            'ml' => 'Malayalam',
            'mr' => 'Marathi',
            'pa' => 'Punjabi',
            'ta' => 'Tamil',
            'te' => 'Telugu',
            'hu' => 'Hungarian',
            'cs' => 'Czech',
            'da' => 'Danish',
            'fi' => 'Finnish',
            'el' => 'Greek',
            'he' => 'Hebrew',
            'no' => 'Norwegian',
            'sv' => 'Swedish',
            'uk' => 'Ukrainian',
            'tr' => 'Turkish',
            'ro' => 'Romanian',
            'sk' => 'Slovak',
            'sl' => 'Slovenian',
            'bg' => 'Bulgarian',
            'hr' => 'Croatian',
            'ca' => 'Catalan',
            'eu' => 'Basque',
            'gl' => 'Galician',
            'mt' => 'Maltese',
            'et' => 'Estonian',
            'lv' => 'Latvian',
            'lt' => 'Lithuanian',
        );
        $mform->addElement(
            'select',
            'lang',
            get_string('content_language', 'block_ai_assistant'),
            $languages
        );
        $mform->setType(
            'lang',
            PARAM_TEXT
        );
        // Help button for language
        $mform->addHelpButton(
            'lang',
            'content_language',
            'block_ai_assistant'
        );
    
        $this->add_action_buttons();

       
    }

}
    

