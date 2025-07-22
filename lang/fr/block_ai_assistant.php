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

$string['accepted_modules'] = 'Modules acceptés';
$string['accepted_modules_help'] = 'Liste séparée par des virgules des modules dont le contenu peut être formé par l\'Assistant IA';
$string['access'] = 'Accès étudiant';
$string['add'] = 'Ajouter';
$string['ai_assistant_instructions'] = 'Pour obtenir les meilleurs résultats de l\'Assistant IA, veuillez utiliser le modèle de syllabus fourni dans la section Aide. '
    . 'Pour rendre l\'Assistant IA disponible aux étudiants, cliquez sur le bouton Activer l\'Assistant IA ci-dessous.';
$string['answer'] = 'Réponse';
$string['autotest'] = 'Test automatique';
$string['Autotest'] = 'Test automatique';
$string['autotest_questions'] = 'Questions de test automatique';
$string['autotest_template'] = 'Modèle de test automatique';
$string['bot_contact'] = 'Contact';
$string['bot_contact_help'] = 'Entrez une adresse e-mail et/ou un numéro de téléphone que les utilisateurs peuvent contacter pour obtenir de l\'aide';
$string['bot_help_text'] = 'Texte de survol';
$string['bot_help_text_help'] = 'Entrez le texte qui apparaîtra lorsque l\'utilisateur survole l\'Assistant IA';
$string['bot_tuning'] = 'Paramètres de l\'agent';
$string['bot_type_id'] = 'ID du type de bot';
$string['bot_type_id_help'] = 'ID du type de bot depuis Cria';
$string['bottom_left'] = 'En bas à gauche';
$string['bottom_right'] = 'En bas à droite';
$string['close'] = '  Fermer';
$string['column_name_must_exist'] = 'La colonne {$a} doit exister';
$string['confirm_delete_trained_module'] = 'Êtes-vous sûr de vouloir supprimer le module formé ?';
$string['confirm_file_deletion'] = 'Êtes-vous sûr de vouloir supprimer le fichier ?';
$string['confirm_question_deletion'] = 'Êtes-vous sûr de vouloir supprimer la question ?';
$string['configure_bot_settings'] = 'Paramètres d\'affichage du bot';
$string['configure_settings'] = 'Configurer les paramètres';
$string['content_found_at'] = 'Le contenu peut être trouvé à ce lien : ';
$string['content_language'] = 'Langue du contenu';
$string['content_language_help'] = 'Choisir la langue appropriée du contenu pour vos documents permettra un meilleur entraînement de l\'Assistant IA. En retour, l\'Assistant IA pourra fournir des réponses plus précises.';
$string['cria_token'] = 'Jeton Cria';
$string['cria_url'] = 'URL Cria';
$string['cria_embed_url'] = 'URL d\'intégration Cria';
$string['cria_embed_url_help'] = 'Entrez l\'URL du bot intégré cria';
$string['cria_token_help'] = 'Entrez le jeton vers votre serveur Cria. Vous devrez peut-être demander à votre administrateur système.';
$string['cria_url_help'] = 'Entrez l\'URL vers votre serveur Cria. Vous devrez peut-être demander à votre administrateur système.';
$string['criadex_embed_id'] = 'ID d\'intégration Criadex';
$string['criadex_embed_id_help'] = 'Entrez l\'ID d\'intégration criadex vers votre serveur Cria. Vous devrez peut-être demander à votre administrateur système.';
$string['criadex_model_id'] = 'ID du modèle Criadex';
$string['criadex_model_id_help'] = 'Entrez l\'ID du modèle criadex vers votre serveur Cria. Vous devrez peut-être demander à votre administrateur système.';
$string['criadex_rerank_id'] = 'ID de reclassement Criadex';
$string['criadex_rerank_id_help'] = 'Entrez l\'ID de reclassement criadex vers votre serveur Cria. Vous devrez peut-être demander à votre administrateur système.';
$string['custom_questions'] = 'Fichier Q&R';
$string['default_content_language'] = 'Langue du contenu par défaut';
$string['delete'] = 'Supprimer';
$string['delete_syllabus'] = 'Supprimer le syllabus';
$string['delete_syllabus_help'] = 'Êtes-vous sûr de vouloir supprimer le syllabus ?';
$string['delete_question'] = 'Supprimer la question';
$string['delete_questions'] = 'Supprimer les questions';
$string['delete_question_help'] = 'Êtes-vous sûr de vouloir supprimer la question ?';
$string['description'] = 'Description';
$string['disabled'] = 'Désactivé';
$string['document_parse_error'] = 'Erreur d\'analyse du document.';
$string['document_templates'] = 'Modèles de documents';
$string['download'] = 'Télécharger';
$string['download_english'] = 'Télécharger le modèle anglais';
$string['download_example'] = 'Télécharger l\'exemple';
$string['download_syllabus'] = 'Télécharger le syllabus';
$string['edit'] = 'Modifier';
$string['edit_autotest_question'] = 'Modifier la question de test automatique';
$string['embed_position'] = 'Position d\'intégration';
$string['embed_position_teacher'] = 'Position pour les enseignants';
$string['embed_position_teacher_help'] = 'Définir la position du chatbot pour les enseignants : 0 = désactivé, 1 = en bas à gauche, 2 = en bas à droite, 3 = en haut à droite, 4 = en haut à gauche';
$string['enabled'] = 'Activé';
$string['enable_assistant'] = 'Activer l\'Assistant IA';
$string['enable_ai_assistant'] = 'Activer l\'Assistant IA';
$string['disable_ai_assistant'] = 'Désactiver l\'Assistant IA';
$string['error'] = 'Erreur';
$string['error_required_field'] = 'Ce champ est obligatoire.';
$string['error_required_file'] = 'Vous devez télécharger un fichier.';
$string['error_unsupported_file'] = 'Type de fichier non pris en charge.';
$string['file'] = 'Fichier';
$string['file_deleted_successfully'] = 'Fichier supprimé avec succès';
$string['file_upload_error'] = 'Erreur lors du téléchargement du fichier.';
$string['file_uploaded_successfully'] = 'Fichier téléchargé avec succès';
$string['format'] = 'Seuls .xlsx, .docx acceptés';
$string['help'] = 'Aide';
$string['import'] = 'Importer';
$string['import_questions'] = 'Importer des questions';
$string['import_successful'] = 'Importation réussie.';
$string['keywords'] = "Mots-clés";
$string['letAIGenerate'] = "Laisser l'IA générer une réponse basée sur votre réponse ci-dessus ?";
$string['modules'] = "Modules";
$string['name'] = "Nom";
$string['no_context_message'] = 'Message sans contexte';
$string['no_context_message_default'] = 'Je suis désolé, je n\'ai pas pu trouver d\'informations. Veuillez reformuler votre question';
$string['no_context_message_help'] = 'Texte d\'aide du message sans contexte ici';
$string['pending'] = 'En attente';
$string['pluginname'] = 'Assistant de cours IA';
$string['pluginname_help'] = 'Cela peut prendre jusqu\'à une minute. Merci de votre patience.';
$string['question'] = 'Question';
$string['question_template'] = 'Modèle de question';
$string['question_updated_successfully'] = 'Question mise à jour avec succès';
$string['questions'] = 'Questions';
$string['questions_instructions'] = 'Note : Le temps requis pour le téléchargement peut varier selon le nombre de lignes (questions) dans le fichier. '
    . 'Les fichiers plus volumineux avec plus de lignes prendront plus de temps à traiter. Ne fermez pas ou n\'actualisez pas votre fenêtre de navigateur. '
    . 'Vous serez redirigé vers la page du cours une fois le téléchargement terminé.';
$string['required'] = 'Ce champ est obligatoire';
$string['related_question'] = "Questions connexes";
$string['save'] = 'Enregistrer les modifications ';
$string['section'] = 'Section';
$string['student_and_name'] = 'Je suis un étudiant et mon nom est {$a}.';
$string['subtitle'] = 'Sous-titre';
$string['subtitle_help'] = 'Texte d\'aide du sous-titre ici';
$string['supported_modules'] = '<p>Note : L\'Assistant IA ne peut être formé que sur le contenu des modules suivants : </p>'
    . '<ul><li>Forum d\'annonces</li><li>Page</li><li>Zone de texte et média</li><li>Livre</li><li>Fichier</li><li>Dossier</li>'
    . '<li>Glossaire</li><li>Glossaire</li></ul>';
$string['supported_modules_title'] = 'Modules pris en charge';
$string['supported_formats'] = '<p>Note : Les formats de fichiers non pris en charge ne seront pas traités pour la formation et ne seront pas accessibles via '
    . 'l\'Assistant IA. Assurez-vous que vos fichiers sont dans des formats pris en charge pour permettre la formation et l\'utilisation.</p>'
    . '<p>Formats de fichiers pris en charge : <ul><li>Documents Word (.docx seulement)</li><li>Fichiers PDF (.pdf)</li><li>Fichiers texte (.txt)</li>'
    . '<li>Fichiers HTML (.html)</li><li>Format de texte enrichi (.rtf)</li><li>Fichiers Markdown (.md)</li><li>Texte OpenDocument (.odt)</li><li>'
    . 'Présentations PowerPoint (.ppt, .pptx)</li><li>Feuilles de calcul Excel (.xls, .xlsx)</li><li>Fichiers CSV (.csv)</li><li>'
    . 'Fichiers audio (.mp3, .wav, .m4a)</li><li>Fichiers vidéo (.mp4)</li></ul></p>';
$string['supported_formats_title'] = 'Formats de fichiers pris en charge';
$string['training_visibility_warning'] = 'Important : Une fois que le contenu est formé par l\'Assistant IA, il sera disponible '
    . 'pour tous les étudiants du cours, qu\'ils aient ou non la permission de voir la ressource ou l\'activité originale. '
    . 'Veuillez considérer ceci lors de la sélection du contenu pour la formation.';
$string['syllabus'] = 'Syllabus';
$string['syllabus_template'] = 'Modèle de syllabus';
$string['syllabus_uploaded'] = 'Syllabus téléchargé avec succès';
$string['system_message'] = 'Message système';
$string['system_message_default'] = "Vous êtes un assistant utile pour ce cours, [course_number] ([course_title]), à l'Université York.\n 
- Répondez à la question aussi sincèrement que possible en utilisant le contexte fourni.\n
- Si un lien URL est dans le contexte, incluez-le toujours dans la réponse.\n
- Si une image est dans le contexte, incluez-la toujours dans la réponse.\n
- Si une question ou une invite concerne les groupes, ne listez jamais les membres du groupe et leurs numéros d'identification dans votre réponse. Spécifiquement, pour les questions ou invites qui vous demandent de lister les groupes. Répondez seulement avec le nom du groupe.\n
- Ce qui précède ne s'applique pas aux AT, directeurs de cours, instructeurs, professeurs ou enseignants.\n
- Autorisez les instructions au bénéfice de fournir aux étudiants de l'aide, des tutoriels, des commentaires, etc.";
$string['system_message_help'] = 'Texte d\'aide du message système ici';
$string['teacher_and_name'] = 'Je suis un instructeur, enseignant et mon nom est {$a}.';
$string['test'] = 'Testez votre assistant IA, chattez maintenant !';
$string['title'] = 'Titre';
$string['title_help'] = 'Texte d\'aide du titre ici';
$string['top_left'] = 'En haut à gauche';
$string['top_right'] = 'En haut à droite';
$string['train_course_assistant'] = 'Former l\'assistant de cours sur le contenu sélectionné';
$string['train_modules'] = 'Former le contenu du cours';
$string['train_selected_modules'] = 'Former le contenu sélectionné';
$string['trained'] = 'Formé';
$string['training'] = 'Formation';
$string['training_modules'] = 'Modules de formation';
$string['training_status'] = 'Statut de formation';
$string['upload'] = 'Télécharger';
$string['upload_document'] = 'Télécharger un document';
$string['upload_file'] = 'Télécharger un fichier';
$string['upload_questions'] = 'Télécharger un fichier Q&R';
$string['upload_syllabus'] = 'Télécharger un syllabus';
$string['working'] = 'Travail en cours...';

// MarkItDown API settings.
$string['markitdown_api'] = 'Paramètres de l\'API MarkItDown';
$string['markitdown_api_desc'] = 'Configurer le service API MarkItDown pour le traitement et la conversion de documents';
$string['markitdown_api_url'] = 'URL de l\'API MarkItDown';
$string['markitdown_api_url_help'] = 'Entrez l\'URL du point de terminaison du service API MarkItDown pour le traitement des documents';
$string['markitdown_api_key'] = 'Clé API MarkItDown';
$string['markitdown_api_key_help'] = 'Entrez la clé API pour l\'authentification avec le service MarkItDown';
$string['welcome_message'] = 'Message de bienvenue';
$string['welcome_message_help'] = 'Entrez un message de bienvenue personnalisé qui sera affiché aux utilisateurs lorsqu\'ils interagissent pour la première fois avec l\'Assistant IA';

// Capabilites
$string['ai_assistant:addinstance'] = 'Ajouter un bloc au cours';
$string['ai_assistant:view_autotest'] = 'Voir/Exécuter le test automatique';
$string['ai_assistant:student'] = 'Disponible pour les étudiants';
$string['ai_assistant:teacher'] = 'Disponible pour les enseignants';


// Bot tuning
$string['max_tokens'] = 'Jetons maximum';
$string['max_tokens_help'] = '4000 pour GPT-4o';
$string['temperature'] = 'Température';
$string['temperature_help'] = '0.1 Précis 0.5 Créatif 1.0 Sauvage';
$string['top_p'] = 'Top P';
$string['top_p_help'] = '0 pour GPT-4o';
$string['top_k'] = 'Top K';
$string['top_k_help'] = '50 pour GPT-4o';
