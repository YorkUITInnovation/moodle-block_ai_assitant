# Guide d'utilisation de l'Assistant de cours IA

## Table des matières
1. [Introduction](#introduction)
2. [Commencer](#commencer)
3. [Pour les enseignants](#pour-les-enseignants)
4. [Pour les étudiants](#pour-les-étudiants)
5. [Dépannage](#dépannage)
6. [Meilleures pratiques](#meilleures-pratiques)

---

## Introduction

L'Assistant de cours IA est un plugin Moodle puissant qui améliore l'expérience d'apprentissage en fournissant un chatbot intelligent capable de répondre aux questions des étudiants sur le contenu du cours. Alimenté par la technologie CRIA AI, il aide à réduire la charge administrative des instructeurs tout en offrant un support 24h/24 et 7j/7 aux étudiants.

### Fonctionnalités principales
- **Intégration du syllabus** : Téléchargez les plans de cours pour des informations complètes sur le cours
- **Gestion Q&R** : Créez et gérez les questions fréquemment posées
- **Formation de contenu** : Entraînez l'IA sur les matériaux de cours existants
- **Tests automatisés** : Testez les réponses de l'IA avec la fonctionnalité de test automatique
- **Support multilingue** : Disponible en plusieurs langues
- **Chat en temps réel** : Réponses instantanées aux demandes des étudiants

---

## Commencer

---

## Pour les enseignants

### Configuration initiale

#### 1. Ajouter le bloc Assistant IA
1. Naviguez vers votre cours
2. Activez le mode édition
3. Cliquez sur "Ajouter un bloc"
4. Sélectionnez "Assistant IA" dans les blocs disponibles. Veuillez être patient lors de l'ajout du bloc car cela peut prendre quelques secondes à charger.
   - C'est parce que lorsque vous l'ajoutez pour la première fois au cours, il crée une nouvelle instance de bot pour votre cours.
   - Une fois installé, les chargements futurs seront plus rapides.
5. Le bloc créera automatiquement une instance de bot pour votre cours

#### 2. Configuration de base
1. Cliquez sur **"Paramètres d'affichage du bot"** dans le bloc Assistant IA
2. Personnalisez les paramètres suivants :
   - **Nom du bot** : Généré automatiquement mais peut être modifié
   - **Sous-titre** : Brève description montrée aux étudiants
   - **Message de bienvenue** : Premier message que voient les étudiants
   - **Message sans contexte** : Réponse quand l'IA ne peut pas trouver une réponse
   - **Contact** : Adresse email pour le support, affichée aux étudiants. C'est normalement votre adresse email.
   - **Langue** : Définir la langue du contenu pour un meilleur entraînement de l'IA
   - **Position d'intégration** : Choisissez où la fenêtre de chat apparaît

### Entraîner votre Assistant IA

#### Télécharger le syllabus
1. Cliquez sur **"Télécharger le syllabus"** dans le bloc
2. Sélectionnez votre fichier de syllabus (formats supportés : .docx, .pdf)
3. Cliquez sur "Télécharger le syllabus"
4. Attendez que l'entraînement soit terminé (indiqué par les badges de statut)

**💡 Conseil** : **Utilisez le modèle de syllabus fourni pour des résultats optimaux.**

#### Entraîner les modules de cours
1. Cliquez sur **"Entraîner le contenu du cours"** dans le bloc
2. Sélectionnez les modules que vous voulez que l'IA apprenne :
   - Forums d'annonces
   - Pages
   - Zones de texte et média
   - Livres
   - Fichiers
   - Dossiers
   - Glossaires
3. Note : Les noms de modules suivis d'un œil rouge avec une barre signifie qu'ils ne sont normalement pas visibles aux étudiants.
4. Cliquez sur **"Entraîner les modules sélectionnés"**
5. Surveillez le statut d'entraînement avec les badges colorés :
   - 🟡 Jaune : En attente
   - 🔵 Bleu : Entraînement en cours
   - 🟢 Vert : Entraîné avec succès
   - 🔴 Rouge : Erreur survenue

**⚠️ Important** : Une fois le contenu entraîné, il devient disponible à tous les étudiants du cours, indépendamment de leurs permissions d'accès originales.

#### Formats de fichiers supportés
L'IA peut traiter ces types de fichiers :
- Documents : .docx, .pdf, .txt, .html, .rtf, .md, .odt
- Présentations : .pptx
- Tableurs : .xlsx, .csv
- Média : .mp3, .wav, .m4a, .mp4

### Gestion des questions

#### Télécharger des fichiers Q&R
1. Cliquez sur **"Télécharger les questions"** ou **"Questions"**
2. Choisissez votre fichier Q&R (format .docx)
3. Cliquez sur "Télécharger les questions"
4. Les questions seront automatiquement importées et entraînées

**💡 Conseil** : Utilisez le modèle de questions fourni pour un formatage cohérent.

### Publication aux étudiants

#### Activer l'Assistant IA
1. Révisez et testez l'IA en utilisant l'interface de chat
2. Vérifiez que les réponses sont précises et utiles
3. Cliquez sur **"Activer l'Assistant IA"**
4. L'IA devient visible aux étudiants immédiatement

#### Désactiver l'Assistant IA
- Cliquez sur **"Désactiver l'Assistant IA"** pour le cacher aux étudiants
- Utilisez ceci lors de mises à jour ou si des problèmes surviennent

### Modèles de documents

Accédez aux modèles utiles en cliquant sur **"Aide"** :
- **Modèle de syllabus** : Format structuré pour un entraînement optimal de l'IA
- **Modèle de questions** : Format pour les téléchargements Q&R

Chaque modèle inclut des exemples pour guider votre création de contenu.

---

## Pour les étudiants

### Accéder à l'Assistant IA

1. Naviguez vers votre page de cours
2. Cherchez l'icône **"Assistant de cours IA"** généralement en bas à gauche de la page
3. L'assistant IA apparaît comme une interface de chat
4. Si vous ne le voyez pas, l'instructeur ne l'a peut-être pas encore activé

### Utiliser l'Assistant IA

#### Commencer une conversation
1. Cliquez sur l'interface de chat
2. Tapez votre question dans la boîte de message
3. Appuyez sur Entrée ou cliquez sur Envoyer
4. L'IA répondra basé sur le contenu de cours entraîné

#### Poser des questions efficaces
**Bons exemples :**
- "Quand a lieu l'examen de mi-session ?"
- "Quelles sont les exigences du devoir pour le Projet 1 ?"
- "Comment la note finale est-elle calculée ?"
- "Quel manuel ai-je besoin pour ce cours ?"
- "Quand sont les heures de bureau ?"

**Conseils pour de meilleures réponses :**
- Soyez spécifique et clair dans vos questions
- Utilisez des mots-clés liés au contenu de votre cours
- Si la première réponse n'est pas utile, essayez de reformuler votre question
- Posez des questions de suivi pour clarification

#### Comprendre les réponses de l'IA
- L'IA tire ses informations des syllabus de cours, documents téléchargés, et contenu entraîné
- Les réponses peuvent inclure des liens vers les matériaux de cours pertinents
- Si l'IA ne peut pas trouver d'information, elle affichera un message en ce sens
- L'IA est conçue pour être utile mais peut ne pas avoir accès à toutes les informations de cours

### Avec quoi l'IA peut vous aider
- Politiques et procédures de cours
- Détails des devoirs et dates d'échéance
- Information et horaires d'examen
- Information sur les matériaux de cours et manuels
- Questions générales de cours couvertes dans le contenu téléchargé
- Aide à la navigation pour les ressources de cours
- Bien sûr, cela dépend toujours des informations sur lesquelles l'instructeur a entraîné l'IA.

### Ce que l'IA ne peut pas faire
- Accéder à vos notes ou dossiers académiques personnels
- Fournir des réponses aux questions d'examen ou devoirs
- Faire des exceptions aux politiques de cours
- Accéder aux informations en temps réel non présentes dans le contenu entraîné
- Gérer les problèmes techniques avec Moodle
- Fournir des conseils académiques personnalisés

### Obtenir de l'aide supplémentaire
Si l'Assistant IA ne peut pas répondre à votre question :
1. Contactez votre instructeur directement
2. Vérifiez les annonces et ressources de cours
3. Assistez aux heures de bureau
4. Demandez aux camarades de classe dans les forums de discussion
5. Contactez le support technique pour les problèmes système

---

## Dépannage

### Pour les enseignants

#### Problèmes d'entraînement
**Problème** : Le statut d'entraînement affiche "Erreur" (badge rouge)
- **Solution** :
  - Vérifiez que le format de fichier est supporté
  - Vérifiez que le fichier n'est pas corrompu ou protégé par mot de passe
  - Essayez de télécharger un fichier plus petit
  - Assurez-vous que le contenu est dans la langue sélectionnée
  - Contactez le support si le problème persiste

**Problème** : L'IA fournit des réponses incorrectes ou obsolètes
- **Solution** :
  - Re-téléchargez le contenu mis à jour
  - Vérifiez que les matériaux pertinents ont été entraînés
  - Ajoutez des questions spécifiques pour les sujets problématiques
  - Exécutez AutoTest pour identifier les lacunes de connaissances
  - Mettez à jour les Q&R avec les informations correctes

#### Problèmes de configuration
**Problème** : Les paramètres ne se sauvegardent pas
- **Solution** :
  - Vérifiez que vous avez les permissions appropriées
  - Vérifiez que tous les champs requis sont complétés
  - Essayez d'actualiser la page et de réessayer
  - Contactez l'administrateur si le problème continue

**Problème** : Les étudiants ne peuvent pas voir l'Assistant IA
- **Solution** :
  - Assurez-vous d'avoir cliqué sur "Activer l'Assistant IA"
  - Vérifiez que les étudiants ont l'accès approprié au cours
  - Vérifiez que le bloc est visible sur la page de cours
  - Confirmez que les services CRIA sont opérationnels

### Pour les étudiants

#### Problèmes d'accès
**Problème** : Ne peut pas voir le bloc Assistant IA
- **Solution** :
  - Confirmez que vous êtes inscrit au cours
  - Vérifiez avec l'instructeur si l'IA a été activée
  - Actualisez votre page de navigateur
  - Essayez de vous déconnecter et reconnecter

**Problème** : L'IA ne répond pas aux messages
- **Solution** :
  - Vérifiez votre connexion internet
  - Essayez d'actualiser la page
  - Videz le cache du navigateur
  - Rapportez à l'instructeur si le problème persiste

#### Problèmes de qualité de réponse
**Problème** : L'IA donne des réponses inutiles ou "sans contexte"
- **Solution** :
  - Reformulez votre question plus spécifiquement
  - Utilisez différents mots-clés liés à votre sujet
  - Vérifiez d'abord les matériaux de cours pour l'information
  - Demandez à l'instructeur d'entraîner l'IA sur du contenu supplémentaire

---

## Meilleures pratiques

### Pour les enseignants

#### Préparation du contenu
1. **Utilisez les modèles** : Utilisez les modèles de documents fournis pour un formatage cohérent
2. **Organisez l'information** : Structurez le contenu clairement avec des titres et sections
3. **Mettez à jour régulièrement** : Gardez les matériaux d'entraînement à jour avec les changements de cours
4. **Testez minutieusement** : Vérifiez les réponses de l'IA avant publication

#### Stratégie d'entraînement
1. **Commencez avec le syllabus** : Téléchargez d'abord le syllabus de cours comme fondation
2. **Ajoutez le contenu principal** : Entraînez sur les matériaux de cours essentiels
3. **Incluez les FAQ** : Téléchargez les questions et réponses communes
4. **Testez de façon incrémentale** : Testez l'IA après chaque ajout de contenu majeur

#### Assurance qualité
1. **Tests réguliers** : Testez périodiquement les réponses de l'IA pour la précision
2. **Feedback des étudiants** : Demandez aux étudiants sur l'utilité de l'IA
3. **Mettez à jour le contenu** : Rafraîchissez les matériaux d'entraînement selon l'évolution du cours
4. **Surveillez l'usage** : Révisez les questions communes pour identifier les lacunes d'entraînement

### Pour les étudiants

#### Usage efficace
1. **Soyez spécifique** : Posez des questions détaillées et focalisées
2. **Utilisez les termes du cours** : Incluez la terminologie de votre cours
3. **Essayez des variations** : Reformulez si la première tentative ne fonctionne pas
4. **Vérifiez l'information** : Recoupez les détails importants avec les sources officielles

#### Quand utiliser des ressources alternatives
- Pour les questions personnelles/confidentielles → Contactez l'instructeur directement
- Pour les urgences académiques → Utilisez les canaux de support officiels
- Pour les problèmes techniques → Contactez le support informatique
- Pour l'interprétation des notes → Discutez avec l'instructeur

---

*Ce guide d'utilisateur est conçu pour vous aider à tirer le maximum de votre Assistant de cours IA. Pour des questions supplémentaires ou du support technique, contactez votre administrateur système ou instructeur de cours.*
