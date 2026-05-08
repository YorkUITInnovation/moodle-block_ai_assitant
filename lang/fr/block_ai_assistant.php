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
$string['accepted_modules_help'] = 'Liste séparée par des virgules des modules dont le contenu peut être entraîné par l\'Assistant IA';
$string['access'] = 'Accès étudiant';
$string['actions'] = 'Actions';
$string['add'] = 'Ajouter';
$string['ai_assistant'] = 'Assistant IA';
$string['ai_assistant_instructions'] = 'Pour obtenir les meilleurs résultats de l\'Assistant IA, veuillez utiliser le modèle de plan de cours fourni dans la section Aide. '
    . 'Pour rendre l\'Assistant IA disponible aux étudiants, cliquez sur le bouton Activer l\'Assistant IA ci-dessous.';
$string['ai_learning_assistant'] = 'Assistant d\'apprentissage IA';
$string['answer'] = 'Réponse';
$string['autotest'] = 'AutoTest';
$string['Autotest'] = 'AutoTest';
$string['autotest_questions'] = 'Questions AutoTest';
$string['autotest_template'] = 'Modèle AutoTest';
$string['bot_api_key_not_found'] = 'Une erreur s\'est produite lors de la tentative de création de l\'Agent IA backend. '
. 'Veuillez supprimer le bloc Assistant IA et l\'ajouter à nouveau. Si le problème persiste, veuillez contacter votre administrateur système.';
$string['bot_contact'] = 'Contact';
$string['bot_contact_help'] = 'Entrez un courriel et/ou un numéro de téléphone pour que les utilisateurs puissent contacter le support';
$string['bot_help_text'] = 'Texte de survol';
$string['bot_help_text_help'] = 'Entrez le texte qui apparaîtra lorsque l\'utilisateur survolera l\'Assistant IA';
$string['bot_tuning'] = 'Paramètres de l\'agent';
$string['bot_type_id'] = 'ID du type de bot';
$string['bot_type_id_help'] = 'ID du type de bot depuis Cria';
$string['bottom_left'] = 'En bas à gauche';
$string['bottom_right'] = 'En bas à droite';
$string['chat_help'] = 'Pour de meilleurs résultats, veuillez poser des questions claires et spécifiques.'
    . 'Il est important d\'utiliser des phrases complètes avec une ponctuation appropriée. ';
$string['chat_summary'] = 'Résumé de la discussion';
$string['cancel'] = 'Annuler';
$string['close'] = 'Fermer';
$string['column_name_must_exist'] = 'La colonne {$a} doit exister';
$string['confirm_delete_trained_module'] = 'Êtes-vous sûr de vouloir supprimer le module entraîné ?';
$string['confirm_file_deletion'] = 'Êtes-vous sûr de vouloir supprimer le fichier ?';
$string['confirm_question_deletion'] = 'Êtes-vous sûr de vouloir supprimer la question ?';
$string['configure_bot_settings'] = 'Paramètres d\'affichage du bot';
$string['configure_settings'] = 'Configurer les paramètres';
$string['content_found_at'] = 'Le contenu peut être trouvé à ce lien : ';
$string['content_language'] = 'Langue du contenu';
$string['content_language_help'] = 'Choisir la bonne langue de contenu pour vos documents permettra un meilleur entraînement de l\'Assistant IA. En retour, l\'Assistant IA sera capable de fournir des réponses plus précises.';
$string['course_module_training_status'] = 'Statut d\'entraînement du module de cours';
$string['course_modules'] = 'Modules de cours';
$string['cria_token'] = 'Jeton Cria';
$string['cria_url'] = 'URL Cria';
$string['cria_embed_url'] = 'URL d\'intégration Cria';
$string['cria_embed_url_help'] = 'Entrez l\'URL du bot d\'intégration cria';
$string['cria_token_help'] = 'Entrez le jeton pour votre serveur Cria. Vous devrez peut-être demander à votre administrateur système.';
$string['cria_url_help'] = 'Entrez l\'URL de votre serveur Cria. Vous devrez peut-être demander à votre administrateur système.';
$string['criadex_embed_id'] = 'ID d\'intégration Criadex';
$string['criadex_embed_id_help'] = 'Entrez l\'ID d\'intégration criadex pour votre serveur Cria. Vous devrez peut-être demander à votre administrateur système.';
$string['criadex_model_id'] = 'ID de modèle Criadex';
$string['criadex_model_id_help'] = 'Entrez l\'ID de modèle criadex pour votre serveur Cria. Vous devrez peut-être demander à votre administrateur système.';
$string['criadex_rerank_id'] = 'ID de reclassement Criadex';
$string['criadex_rerank_id_help'] = 'Entrez l\'ID de reclassement criadex pour votre serveur Cria. Vous devrez peut-être demander à votre administrateur système.';
$string['custom_questions'] = 'Fichier Q&R';
$string['date'] = 'Date';
$string['default_content_language'] = 'Langue de contenu par défaut';
$string['delete'] = 'Supprimer';
$string['delete_syllabus'] = 'Supprimer le plan de cours';
$string['delete_syllabus_help'] = 'Êtes-vous sûr de vouloir supprimer le plan de cours ?';
$string['delete_question'] = 'Supprimer la question';
$string['delete_questions'] = 'Supprimer les questions';
$string['delete_question_help'] = 'Êtes-vous sûr de vouloir supprimer la question ?';
$string['delete_tutorial_help'] = 'Êtes-vous sûr de vouloir supprimer le tutoriel ?';
$string['description'] = 'Description';
$string['disable_ai_assistant'] = 'Désactiver l\'Assistant IA';
$string['disable_tutorials'] = 'Désactiver les tutoriels';
$string['disabled'] = 'Désactivé';
$string['document_parse_error'] = 'Erreur d\'analyse du document.';
$string['document_templates'] = 'Modèles de documents';
$string['download'] = 'Télécharger';
$string['download_english'] = 'Télécharger le modèle anglais';
$string['download_example'] = 'Télécharger l\'exemple';
$string['download_syllabus'] = 'Télécharger le plan de cours';
$string['gradebook'] = 'Carnet de notes';
$string['gradebook_loading'] = 'Chargement...';
$string['gradebook_reset_session'] = 'Réinitialiser la session';
$string['gradebook_reset_session_confirm'] = 'Démarrer une nouvelle session du carnet de notes ? La conversation et la correspondance actuelles seront effacées.';
$string['gradebook_reset_session_tooltip'] = 'La réinitialisation conserve la même session et le contexte extrait, mais efface la proposition, la correspondance et l\'historique de discussion en cours.';
$string['gradebook_delete_session'] = 'Supprimer la session';
$string['gradebook_delete_session_confirm'] = 'Supprimer cette session du carnet de notes et en démarrer une nouvelle ? Cela supprimera également la catégorie « Carnet de notes – Assistante IA » et toutes ses sous-catégories de la configuration des notes du cours. Cette action est irréversible.';
$string['gradebook_delete_session_tooltip'] = 'La suppression retire définitivement la session actuelle du carnet de notes et annule toutes les modifications apportées à la configuration des notes du cours (suppression de l\'arborescence de la catégorie Carnet de notes – Assistante IA), puis démarre une nouvelle session.';
$string['gradebook_delete_nothing'] = 'Rien à supprimer dans la configuration des notes du cours pour le moment. Les catégories de l\'assistante IA n\'ont pas été appliquées à ce cours.';
$string['gradebook_delete_completed'] = 'Session supprimée.';
$string['gradebook_delete_cleanup_only'] = 'Aucune session active n\'a été trouvée, mais la configuration du carnet de notes de l\'assistante IA a bien été supprimée du cours.';
$string['gradebook_session_expired'] = 'La session précédente du carnet de notes a expiré ou est introuvable. Une nouvelle session a été démarrée.';
$string['gradebook_result_heading'] = 'Carnet de notes final';
$string['gradebook_open_setup'] = 'Voir le résultat';
$string['gradebook_download_word'] = 'Télécharger Word';
$string['gradebook_download_pdf'] = 'Télécharger le PDF';
$string['gradebook_download_preparing'] = 'Préparation du document…';
$string['gradebook_download_failed'] = 'Impossible de générer le document. Veuillez réessayer.';
$string['gradebook_export_nostate'] = 'Aucune session de carnet de notes trouvée pour ce cours. Veuillez d’abord finaliser un carnet de notes.';
$string['gradebook_export_badformat'] = 'Format d’exportation non pris en charge.';
$string['gradebook_save_warning'] = 'Impossible d’enregistrer votre session sur le serveur. Vos modifications pourraient être perdues lors d’une actualisation — veuillez vérifier votre connexion.';
$string['gradebook_not_graded'] = '— Non noté —';
$string['gradebook_select_category'] = 'Sélectionnez une catégorie…';
$string['gradebook_mapping_preview'] = 'Aperçu de la correspondance';
$string['gradebook_mapping_hint'] = 'Les éléments marqués « Non noté » sont exclus du carnet de notes.';
$string['gradebook_mapping_empty'] = 'Aucune correspondance pour le moment. Cliquez sur « Générer la correspondance » lorsque la proposition vous convient.';
$string['gradebook_mapping_missing'] = 'Veuillez choisir une catégorie pour les {$a} ligne(s) restante(s), ou les marquer comme non notées.';
$string['gradebook_input_placeholder'] = 'Décrivez votre répartition des notes ou demandez des ajustements...';
$string['gradebook_send'] = 'Envoyer';
$string['gradebook_generate'] = 'Générer le carnet de notes';
$string['gradebook_generate_title'] = 'Créer le carnet de notes à partir de la correspondance ci-dessus (équivalent à Finaliser).';
$string['edit'] = 'Modifier';
$string['edit_autotest_question'] = 'Modifier la question AutoTest';
$string['edit_tutorial'] = 'Modifier le tutoriel';
$string['embed_position'] = 'Position d\'intégration';
$string['embed_position_teacher'] = 'Position pour les enseignants';
$string['embed_position_teacher_help'] = 'Définir la position du chatbot pour les enseignants : 0 = désactivé, 1 = en bas à gauche, 2 = en bas à droite, 3 = en haut à droite, 4 = en haut à gauche';
$string['enabled'] = 'Activé';
$string['enabled_help'] = 'Activer ou désactiver l\'option Tutoriel pour ce cours. Lorsqu\'elle est activée, le Tutoriel sera disponible pour les étudiants.';
$string['enable_assistant'] = 'Activer l\'Assistant IA pour les étudiants';
$string['enable_ai_assistant'] = 'Activer l\'Assistant IA pour les étudiants';
$string['enable_tutorials'] = 'Activer l\'Assistant d\'apprentissage IA pour les étudiants';
$string['error'] = 'Erreur';
$string['error_required_field'] = 'Ce champ est requis.';
$string['error_required_file'] = 'Vous devez téléverser un fichier.';
$string['error_unsupported_file'] = 'Type de fichier non supporté.';
$string['file'] = 'Fichier';
$string['file_deleted_successfully'] = 'Fichier supprimé avec succès';
$string['file_upload_error'] = 'Erreur de téléversement du fichier.';
$string['file_uploaded_successfully'] = 'Fichier téléversé avec succès';
$string['format'] = 'Seuls .xlsx, .docx acceptés';
$string['help'] = 'Aide';
$string['import'] = 'Importer';
$string['import_questions'] = 'Importer les questions';
$string['import_successful'] = 'Importation réussie.';
$string['invalid_token'] = '498 Jeton invalide';
$string['keywords'] = "Mots-clés";
$string['learning_assistant_help'] = "Sélectionnez le contenu sur lequel vous souhaitez obtenir une aide à l\'apprentissage. ";

$string['letAIGenerate'] = "Laisser l\'IA générer une réponse basée sur votre réponse ci-dessus ?";
$string['manage_tutorials'] = "Gérer l\'Assistant d\'apprentissage IA";
$string['modules'] = "Modules";
$string['name'] = "Nom";
$string['no'] = 'Non';
$string['no_context_message'] = 'Message sans contexte';
$string['no_context_message_default'] = 'Je suis désolé, je n\'ai trouvé aucune information. Veuillez reformuler votre question';
$string['no_context_message_help'] = 'Texte d\'aide pour le message sans contexte ici';
$string['pending'] = 'En attente';
$string['pluginname'] = 'Al Assistant de cours';
$string['pluginname_help'] = 'Cela peut prendre jusqu\'à une minute. Merci de votre patience.';
$string['preparing_tutorial'] = 'Préparation de votre tutoriel. Un moment s\'il vous plaît...';
$string['prompt'] = 'Invite';
$string['question'] = 'Question';
$string['question_template'] = 'Modèle de question';
$string['question_updated_successfully'] = 'Question mise à jour avec succès';
$string['questions'] = 'Questions';
$string['questions_instructions'] = 'Note : Le temps requis pour le téléversement peut varier selon le nombre de lignes (questions) dans le fichier. '
    . 'Les fichiers plus volumineux avec plus de lignes prendront plus de temps à traiter. Ne fermez pas ou n\'actualisez pas votre fenêtre de navigateur. '
    . 'Vous serez redirigé vers la page du cours une fois le téléversement terminé.';
$string['required'] = 'Ce champ est requis';
$string['related_question'] = "Questions connexes";
$string['save'] = 'Enregistrer les modifications ';
$string['save_chat'] = 'Télécharger la discussion';
$string['section'] = 'Section';
$string['section'] = 'Section';
$string['student'] = 'Étudiant';
$string['student_and_name'] = 'Je suis un étudiant et mon nom est {$a}.';
$string['subtitle'] = 'Sous-titre';
$string['subtitle_help'] = 'Texte d\'aide pour le sous-titre ici';
$string['summarize_chat'] = 'Résumer la discussion';
$string['summary_prompt'] = "Résumez la conversation de discussion formatée en HTML suivante entre un étudiant et un tuteur IA. 
La conversation est structurée avec le nom de chaque interlocuteur suivi de son message sur une nouvelle ligne. Par conséquent, utilisez toujours les étudiants dans vos réponses.
Le résumé doit être approprié pour être partagé avec un instructeur universitaire et doit inclure :
1. Objectifs de la session
[Listez les objectifs fixés au début de la session, par ex., \"Réviser les problèmes de devoirs sur la factorisation des équations quadratiques.\"]
2. Points de discussion clés
[Résumez les principaux concepts couverts, par ex., \"Expliqué la différence entre les trinômes carrés parfaits et les équations quadratiques générales.\"]
[Mentionnez les exemples ou problèmes résolus.]
3. Questions et clarifications des étudiants
[Listez les questions spécifiques que l\'étudiant a posées et comment elles ont été abordées.]
4. Progrès et compréhension
[Évaluez brièvement la compréhension du matériel par l\'étudiant, par ex., \"L\'étudiant a montré une confiance améliorée dans l\'identification des modèles de factorisation.\"]
5. Éléments d\'action / Devoirs
[Listez les devoirs ou tâches donnés, par ex., \"Compléter les problèmes 5–10 de la feuille de travail.\"]
6. Prochaines étapes
[Mentionnez ce qui sera couvert dans la prochaine session ou tout suivi nécessaire.]

Concentrez-vous sur la clarté, la pertinence et la valeur éducative.";
$string['supported_modules'] = '<p>Note : L\'Assistant IA ne peut être entraîné que sur le contenu des modules suivants : </p>'
    . '<ul><li>Forum d\'annonces</li><li>Page</li><li>Zone de texte et média</li><li>Livre</li><li>Fichier</li><li>Dossier</li>'
    . '<li>Glossaire</li></ul>';
$string['supported_modules_title'] = 'Modules supportés';
$string['supported_formats'] = '<p>Note : Les formats de fichiers non supportés ne seront pas traités pour l\'entraînement et ne seront pas accessibles via '
    . 'l\'Assistant IA. Assurez-vous que vos fichiers sont dans des formats supportés pour permettre l\'entraînement et l\'utilisation.</p>'
    . '<p>Formats de fichiers supportés : <ul><li>Documents Word (.docx seulement)</li><li>Fichiers PDF (.pdf)</li><li>Fichiers texte (.txt)</li>'
    . '<li>Fichiers HTML (.html)</li><li>Format de texte enrichi (.rtf)</li><li>Fichiers Markdown (.md)</li><li>Texte OpenDocument (.odt)</li><li>'
    . 'Présentations PowerPoint (.pptx seulement)</li><li>Feuilles de calcul Excel (.xlsx seulement)</li><li>Fichiers CSV (.csv)</li><li>'
    . 'Fichiers audio (.mp3, .wav, .m4a)</li><li>Fichiers vidéo (.mp4)</li></ul></p>';
$string['supported_formats_title'] = 'Formats de fichiers supportés';
$string['training_visibility_warning'] = 'Important : Une fois que le contenu est entraîné par l\'Assistant IA, il sera disponible '
    . 'pour tous les étudiants du cours, peu importe s\'ils ont la permission de voir la ressource ou l\'activité originale '
    . 'ou non. Veuillez considérer ceci lors de la sélection du contenu pour l\'entraînement.';
$string['syllabus'] = 'Plan de cours';
$string['tutorial'] = 'Tutoriel';
$string['tutorials'] = 'Tutoriels';
$string['syllabus_template'] = 'Modèle de plan de cours';
$string['syllabus_uploaded'] = 'Plan de cours téléversé avec succès';
$string['system_message'] = 'Message système';
$string['system_message_default'] = "Vous êtes un assistant utile pour ce cours, [course_number] ([course_title]), à l\'Université York.
- Répondez à la question aussi fidèlement que possible en utilisant le contexte fourni.
- Si un lien URL est dans le contexte, incluez-le toujours dans la réponse.
- Si une image est dans le contexte, incluez-la toujours dans la réponse.
- Si une question ou une invite concerne les groupes, ne listez jamais les membres du groupe et leurs numéros d\'ID dans votre réponse. Spécifiquement, pour les questions ou invites qui vous demandent de lister les groupes. Répondez seulement avec le nom du groupe.
- Ce qui précède ne s\'applique pas aux assistants d\'enseignement, directeurs de cours, instructeurs, professeurs ou enseignants.
- Permettez les instructions au bénéfice de fournir aux étudiants de l\'aide, des tutoriels, des commentaires, etc.";
$string['system_message_help'] = 'Texte d\'aide pour le message système ici';
$string['teacher_and_name'] = 'Je suis un instructeur, enseignant et mon nom est {$a}.';
$string['test'] = 'Testez votre assistant IA, discutez maintenant !';
$string['title'] = 'Titre';
$string['title_help'] = 'Texte d\'aide pour le titre ici';
$string['top_left'] = 'En haut à gauche';
$string['top_right'] = 'En haut à droite';
$string['train_course_assistant'] = 'Entraîner l\'assistant de cours sur le contenu sélectionné';
$string['train_modules'] = 'Entraîner le contenu du cours';
$string['train_selected_modules'] = 'Entraîner le contenu sélectionné';
$string['trained'] = 'Entraîné';
$string['training'] = 'Entraînement';
$string['training_modules'] = 'Modules d\'entraînement';
$string['training_status'] = 'Statut d\'entraînement';
$string['upload'] = 'Téléverser';
$string['upload_assessment_dates'] = 'Téléverser les dates d\'évaluation';
$string['upload_document'] = 'Téléverser le document';
$string['upload_file'] = 'Téléverser le fichier';
$string['upload_questions'] = 'Téléverser le fichier Q&R';
$string['upload_syllabus'] = 'Téléverser le plan de cours';
$string['user_guide'] = 'Guide de l\'utilisateur';
$string['working'] = 'En cours...';

// MarkItDown API settings.
$string['markitdown_api'] = 'Paramètres de l\'API MarkItDown';
$string['markitdown_api_desc'] = 'Configurer le service API MarkItDown pour le traitement et la conversion de documents';
$string['markitdown_api_url'] = 'URL de l\'API MarkItDown';
$string['markitdown_api_url_help'] = 'Entrez l\'URL du point de terminaison du service API MarkItDown pour le traitement de documents';
$string['markitdown_api_key'] = 'Clé de l\'API MarkItDown';
$string['markitdown_api_key_help'] = 'Entrez la clé API pour l\'authentification avec le service MarkItDown';
$string['welcome_message'] = 'Message de bienvenue';
$string['welcome_message_help'] = 'Entrez un message de bienvenue personnalisé qui sera affiché aux utilisateurs lors de leur première interaction avec l\'Assistant IA';

// Capabilites
$string['ai_assistant:addinstance'] = 'Ajouter un bloc au cours';
$string['ai_assistant:view_autotest'] = 'Voir/Exécuter AutoTest';
$string['ai_assistant:student'] = 'Disponible pour les étudiants';
$string['ai_assistant:teacher'] = 'Disponible pour les enseignants';
$string['ai_assistant:edit_site_tutorials'] = 'Modifier les tutoriels du site';


// Bot tuning
$string['max_tokens'] = 'Jetons maximum';
$string['max_tokens_help'] = '4000 pour GPT-4o';
$string['temperature'] = 'Température';
$string['temperature_help'] = '0.1 Précis 0.5 Créatif 1.0 Sauvage';
$string['top_p'] = 'Top P';
$string['top_p_help'] = '0 pour GPT-4o';
$string['top_k'] = 'Top K';
$string['top_k_help'] = '50 pour GPT-4o';
$string['top_n'] = 'Top N';
$string['top_n_help'] = '10 pour GPT-4o';
$string['min_k'] = 'Min K';
$string['min_k_help'] = '0.6 pour GPT-4o';
$string['min_relevance'] = 'Pertinence minimale';
$string['min_relevance_help'] = '0.8 pour GPT-4o';
$string['max_context'] = 'Contexte maximum';
$string['max_context_help'] = '120000 pour GPT-4o';
$string['no_context_llm_guess'] = 'Supposition LLM sans contexte';
$string['no_context_llm_guess_help'] = 'Permettre au LLM de retourner une réponse lorsqu\'aucun contexte n\'est disponible';
$string['embed_position'] = 'Position d\'intégration';

// Default Tutorials
$string['tutorial_tutor_name'] = 'Mon tuteur';
$string['tutorial_tutor_description'] = 'L\'invite est conçue pour guider un Tuteur-IA dans l\'aide aux étudiants universitaires'
    . ' à apprendre activement et comprendre un sujet en les engageant dans une conversation personnalisée, interactive et soutenante.';
$string['tutorial_tutor_prompt'] = "- Commencez par vous présenter à l\'étudiant universitaire comme leur Tuteur-IA, qui est heureux de les aider avec toutes questions. 
- Ne posez qu\'une question à la fois. 
- D\'abord, demandez-leur ce qu\'ils savent déjà sur le sujet : [topic] qu\'ils ont choisi. Attendez une réponse. 
- Avec cette information, aidez les étudiants à comprendre le sujet [topic] en fournissant des explications, des exemples et des analogies. 
- Celles-ci doivent être adaptées aux connaissances préalables des étudiants, ou ce qu\'ils savent déjà sur le sujet. 
- Fournissez aux étudiants des explications, des exemples et des analogies pour les aider à comprendre le concept.
- Si des images sont disponibles pour soutenir votre réponse, incluez-les dans votre réponse.  
- Vous devriez guider les étudiants de manière ouverte. 
- Ne fournissez pas de réponses ou solutions immédiates aux problèmes, mais aidez les étudiants à générer leurs propres réponses en posant des questions directrices. 
- Demandez aux étudiants d\'expliquer leur raisonnement. Si l\'étudiant a des difficultés ou donne une mauvaise réponse, essayez de lui demander de compléter une partie de la tâche ou rappelez à l\'étudiant son objectif et donnez un indice. 
- Si les étudiants s\'améliorent, alors félicitez-les et montrez de l\'enthousiasme. 
- Si l\'étudiant a des difficultés, alors soyez encourageant et donnez-lui quelques idées auxquelles réfléchir. 
- Lorsque vous demandez des informations aux étudiants, essayez de conclure vos réponses par une question pour que les étudiants continuent à générer des idées.
- Une fois qu\'un étudiant démontre un niveau de compréhension approprié selon son niveau d\'apprentissage, demandez-lui d\'expliquer le concept avec ses propres mots ; c\'est la meilleure façon de montrer que vous comprenez quelque chose, ou demandez-lui des exemples. 
- Lorsqu\'un étudiant démontre qu\'il connaît le concept, vous pouvez terminer la conversation et lui dire que vous êtes là pour l\'aider s\'il a d\'autres questions. 
- Si l\'étudiant dévie sur un autre sujet qui n\'a rien à voir avec ce sujet : [topic], alors demandez à l\'étudiant de rester sur le sujet car c\'est ce sur quoi il a demandé à être tutoré.";

$string['tutorial_quiz_name'] = 'Quiz sur...';
$string['tutorial_quiz_description'] = 'Cette activité est conçue pour aider les étudiants à réviser et renforcer leur'
    . ' compréhension d\'un sujet spécifique à travers un quiz à choix multiples structuré. Le quiz consiste en 20 questions,'
    . ' chacune avec quatre options de réponse (A, B, C et D). Après chaque réponse, les étudiants reçoivent des commentaires immédiats pour'
    . ' soutenir l\'apprentissage et la réflexion. Le quiz est livré une question à la fois pour encourager la concentration et'
    . ' l\'engagement. À la fin, les étudiants reçoivent un résumé de leur performance avec des suggestions d\'amélioration.'
    . ' Le format est destiné à être interactif, auto-rythmé et soutenant l\'apprentissage indépendant.';
$string['tutorial_quiz_prompt'] = "- Veuillez préparer un quiz à choix multiples sur le sujet : [topic] avec 20 questions avec quatre choix possibles, étiquetés A, B, C et D.
- Créez toutes les questions à partir de votre base de connaissances sur le sujet [topic] 
- Attendez que je réponde avec une étiquette après chaque question, fournissez des commentaires sur ma réponse, puis posez la question suivante. 
- Lorsque vous avez posé toutes les questions, veuillez fournir un résumé amical de mes résultats et toute suggestion d\'amélioration.
- Si vous continuez une session précédente, continuez à poser des questions. Commencez au dernier numéro plus 1.
- Si un étudiant commence à poser des questions au lieu de répondre aux questions du quiz, dites à l\'étudiant que vous ne faites que des quiz.";

// Template instructions
$string['syllabus_template_instructions'] = '<h3>Instructions pour utiliser le modèle de plan de cours</h3>
<p>Le modèle de plan de cours est conçu pour assurer l\'exactitude et la cohérence lors de l\'entraînement du bot IA, AL l\'Assistant de cours
    . Le modèle consiste en des espaces réservés qui commencent par <code><</code> et se terminent par <code>></code>. Suivez
    ces étapes pour utiliser efficacement le modèle :</p>

<h4>Étape 1 : Ouvrir le modèle</h4>
<ol>
    <li>Ouvrez le fichier de modèle de plan de cours dans votre éditeur de texte ou traitement de texte préféré.</li>
</ol>

<h4>Étape 2 : Identifier les espaces réservés</h4>
<ol start="2">
    <li>Recherchez les espaces réservés dans le modèle. Ces espaces réservés sont entourés de crochets angulaires, tels que 
        <code>&lt;CourseTitle&gt;</code>, <code>&lt;InstructorName>InstructorName&gt;</code>, etc.
    </li>
</ol>

<h4>Étape 3 : Remplacer les espaces réservés</h4>
<ol start="3">
    <li>Remplacez chaque espace réservé par l\'information appropriée. Par exemple :
        <ul>
            <li><code>
                &lt;Course Code&gt;
            </code> : Entrez le code du cours.
            </li>
            <li><code>
                &lt;Course Title&gt;
            </code> : Entrez le titre du cours.
            </li>
            <li><code>
                &lt;Instructor Name&gt;
            </code> : Entrez le nom de l\'instructeur.
            </li>
            <li><code>
                &lt;Course Description&gt;
            </code> : Fournissez une brève description du cours.
            </li>
        </ul>
    </li>
</ol>
<div class="alert alert-warning">
    <p><strong>Important :</strong> </p>
    <p>Assurez-vous que tous les espaces réservés sont remplacés par des informations exactes pour fournir
        aux étudiants les détails corrects sur le cours.</p>
        <p>Soyez précis ! Évitez les phrases modales telles que "vous pourriez", "vous pouvez", "il est possible", "vous pourriez possiblement" etc. Celles-ci introduisent
        l\'ambiguïté et l\'incertitude qui peuvent mener à des réponses incohérentes et à la méfiance de l\'utilisateur envers l\'IA. Pour l\'entraînement de l\'IA, il est crucial d\'avoir des instructions claires et précises 
        pour s\'assurer que l\'IA apprend avec précision</p>
        <p>Évitez d\'utiliser des balises HTML (<>) dans votre document car cela causera l\'omission des données.</p>
        <p>Si vous ajoutez de nouveaux sujets/sections, assurez-vous de les formater avec des titres (Titre 1, Titre 2 etc.)</p>
        <p>Si vous ajoutez de nouveaux tableaux, assurez-vous que la première ligne est un en-tête et que toutes les cellules ont du contenu. (Pas de cellules vides)</p>
</div>

<h4>Étape 4 : Réviser et sauvegarder</h4>
<ol start="4">
    <li>Révisez soigneusement le modèle rempli pour vous assurer que tous les espaces réservés ont été remplacés par des
        informations exactes.
    </li>
    <li>Sauvegardez le fichier de plan de cours mis à jour avec un nouveau nom pour éviter d\'écraser le modèle original.</li>
</ol>

<h4>Étape 5 : Utiliser le plan de cours</h4>
<ol start="6">
    <li>Utilisez le plan de cours complété pour votre cours. Ce document aidera à s\'assurer qu\'AL l\'Assistant de cours a
        des informations exactes et cohérentes pour aider efficacement les étudiants.
    </li>
</ol>

<p>En suivant ces instructions, vous pouvez vous assurer que le plan de cours est exact et prêt à être utilisé pour entraîner AL
    l\'Assistant de cours.</p>';
// Question template instructions
$string['question_template_instructions'] = '<h3>Instructions pour créer un modèle de question Word</h3>
<ol>
    <li><strong>Ajouter des questions comme titres</strong>
        <ul>
            <li>Chaque question doit être définie comme un titre.</li>
            <li>Exemple : <strong>Titre1</strong></li>
        </ul>
    </li>
    <li><strong>Fournir des formulations alternatives</strong>
        <ul>
            <li>Sous chaque titre, listez des exemples d\'autres façons de poser la question.</li>
            <li>Exemple :
                <ul>
                    <li>Comment créer un modèle de question Word ?</li>
                    <li>Pouvez-vous m\'aider avec un modèle de question Word ?</li>
                </ul>
            </li>
        </ul>
    </li>
    <li><strong>Section de réponse</strong>
        <ul>
            <li>Ne supprimez pas : <strong>La réponse à toutes ces questions ou invites est :</strong></li>
            <li>Sous cette phrase, ajoutez votre réponse.</li>
            <li>Exemple :
                <ul>
                    <li>La réponse à toutes ces questions ou invites est :</li>
                    <li>Vous pouvez créer un modèle de question Word en suivant ces étapes...</li>
                </ul>
            </li>
        </ul>
    </li>
    <li><strong>Répéter pour chaque question</strong>
        <ul>
            <li>Répétez les mêmes étapes pour chaque question que vous voulez inclure.</li>
        </ul>
    </li>
    <li><strong>Téléverser le document</strong>
        <ul>
            <li>Une fois le document prêt, cliquez sur <strong>Questions personnalisées</strong> pour téléverser votre document.</li>
            <li>Accordez du temps à l\'Assistant IA pour s\'entraîner sur les questions.</li>
        </ul>
    </li>
</ol>';

// AutoTest template instructions
$string['autotest_tempalte_instructions'] = '<h3>Instructions pour utiliser le modèle Excel AutoTest</h3>
AutoTest est une fonctionnalité puissante conçue pour les instructeurs pour créer et gérer des questions qui évaluent la performance et les capacités d\'un Assistant IA. 
Le modèle AutoTest est un fichier Excel qui permet aux instructeurs de définir des questions, des réponses et des réponses attendues pour l\'Assistant IA. Suivez ces étapes pour utiliser efficacement le modèle AutoTest :
<br>
<h5>Instructions du modèle Excel AutoTest</h5>
<ol>
    <li><strong>Ouvrir le modèle Excel AutoTest</strong> : Assurez-vous d\'avoir le modèle ouvert et prêt à modifier.</li>
    <li><strong>Comprendre les colonnes</strong> :
        <ul>
            <li><strong>Section</strong> : Cette colonne représente la catégorie des questions.</li>
            <li><strong>Questions</strong> : Cette colonne contient les questions à poser.</li>
            <li><strong>Réponse</strong> : Cette colonne contient les réponses anticipées.</li>
        </ul>
    </li>
    <li><strong>Saisie des données</strong> :
        <ul>
            <li><strong>Première question dans une section</strong> :
                <ul>
                    <li>Entrez le nom de la section dans la colonne <strong>Section</strong>.</li>
                    <li>Entrez la question dans la colonne <strong>Questions</strong>.</li>
                    <li>Entrez la réponse anticipée dans la colonne <strong>Réponse</strong>.</li>
                </ul>
            </li>
            <li><strong>Questions supplémentaires dans la même section</strong> :
                <ul>
                    <li>Laissez la colonne <strong>Section</strong> vide.</li>
                    <li>Entrez la question suivante dans la colonne <strong>Questions</strong>.</li>
                    <li>Entrez la réponse anticipée dans la colonne <strong>Réponse</strong>.</li>
                </ul>
            </li>
        </ul>
    </li>
    <li><strong>Exemple</strong> :</li>
</ol>
<table border="1">
    <thead>
    <tr>
        <th>Section</th>
        <th>Questions</th>
        <th>Réponse</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>Mathématiques</td>
        <td>Combien font 2+2 ?</td>
        <td>4</td>
    </tr>
    <tr>
        <td></td>
        <td>Quelle est la racine carrée de 9 ?</td>
        <td>3</td>
    </tr>
    <tr>
        <td>Sciences</td>
        <td>Quel est le symbole chimique de l\'eau ?</td>
        <td>H2O</td>
    </tr>
    <tr>
        <td></td>
        <td>Quelle planète est connue comme la planète rouge ?</td>
        <td>Mars</td>
    </tr>
    </tbody>
</table>
<ol start="5">
    <li><strong>Réviser et sauvegarder</strong> :
        <ul>
            <li>Vérifiez vos entrées pour l\'exactitude.</li>
            <li>Sauvegardez le modèle pour vous assurer que toutes vos données sont préservées.</li>
        </ul>
    </li>
</ol>';

$string['help_intro'] = '<h3>Aide de l\'Assistant IA</h3>' .
    'Formater correctement un document est crucial lors de l\'entraînement d\'un bot IA pour s\'assurer qu\'il répond avec précision et efficacité. ' .
    'L\'Assistant IA utilise le contenu du document pour générer des réponses aux requêtes des utilisateurs. ' .
    'Pour vous aider à obtenir les meilleurs résultats de l\'Assistant IA, nous avons fourni des modèles pour les plans de cours et les questions. ' .
    'Ces modèles sont conçus pour s\'assurer que l\'Assistant IA reçoit des informations précises et cohérentes. ' .
    'Suivez les instructions ci-dessous pour utiliser efficacement les modèles.';

$string['visible_to_students'] = 'Visible aux étudiants';
