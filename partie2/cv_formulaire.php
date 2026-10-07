<?php
// cv_formulaire.php : saisie du CV (Question 1). Toutes les rubriques sont obligatoires.
include 'config.php';
exigerConnexion('candidat');

// Après une erreur, cv_traitement.php renvoie ici la saisie et les erreurs (dans la session).
// Sinon, on pré-remplit le formulaire avec le CV déjà enregistré dans la base.
if (isset($_SESSION['cv_saisie'])) {
    $cv = $_SESSION['cv_saisie'];
    unset($_SESSION['cv_saisie']);
} else {
    $cv = lireCV($bdd, $_SESSION['email']);
}

$erreurs = [];
if (isset($_SESSION['erreurs'])) {
    $erreurs = $_SESSION['erreurs'];
    unset($_SESSION['erreurs']);
}

// Chaque rubrique contient au moins une ligne vide pour commencer la saisie
foreach (['experiences', 'competences', 'langues', 'interets'] as $rubrique) {
    if (empty($cv[$rubrique])) {
        $cv[$rubrique] = [[]];
    }
}

// Affiche la valeur d'un champ en la protégeant (ou rien si elle n'existe pas)
function valeur($tableau, $cle)
{
    return htmlspecialchars($tableau[$cle] ?? '');
}

// Affiche les <option> d'une liste, avec la valeur choisie sélectionnée
function afficherOptions($choix, $valeur_choisie)
{
    foreach ($choix as $option) {
        $selection = ($option === $valeur_choisie) ? 'selected' : '';
        echo '<option value="' . htmlspecialchars($option) . '" ' . $selection . '>' . htmlspecialchars($option) . '</option>';
    }
}

// En-tête d'une section : numéro, icône, titre et phrase d'aide
function enteteSection($numero, $nom_icone, $titre, $aide)
{
    ?>
    <div class="section-entete">
        <span class="section-numero"><?php echo $numero; ?></span>
        <div>
            <h2><?php echo icone($nom_icone) . ' ' . $titre; ?></h2>
            <p><?php echo $aide; ?></p>
        </div>
    </div>
    <?php
}

// Bouton ✕ qui retire une ligne (géré par script.js)
function boutonRetirer()
{
    echo '<button type="button" class="btn-retirer" title="Retirer cette ligne">' . icone('x') . '</button>';
}

// --- Une ligne de chaque rubrique (utilisée aussi dans les <template> copiés par script.js) ---
// Les [] dans les name envoient un tableau à cv_traitement.php (une case par ligne).

function ligneExperience($types, $e = [])
{
    ?>
    <div class="ligne-liste carte-ligne">
        <?php boutonRetirer(); ?>
        <div class="grille-champs">
            <label class="champ">Type
                <select name="exp_type[]"><?php afficherOptions($types, $e['type'] ?? 'Stage'); ?></select>
            </label>
            <label class="champ"><span>Intitulé (stage ou diplôme) <span class="obligatoire">*</span></span>
                <input type="text" name="exp_titre[]" maxlength="100" placeholder="Ex. : Stage développeur web"
                       value="<?php echo valeur($e, 'titre'); ?>">
            </label>
            <label class="champ">Entreprise / établissement
                <input type="text" name="exp_organisme[]" maxlength="100" placeholder="Ex. : Faculté des sciences"
                       value="<?php echo valeur($e, 'organisme'); ?>">
            </label>
            <label class="champ">Lieu
                <input type="text" name="exp_lieu[]" maxlength="100" placeholder="Ex. : Tanger"
                       value="<?php echo valeur($e, 'lieu'); ?>">
            </label>
            <label class="champ"><span>Date de début <span class="obligatoire">*</span></span>
                <input type="date" name="exp_debut[]" value="<?php echo valeur($e, 'date_debut'); ?>">
            </label>
            <label class="champ"><span>Date de fin <small>(vide si en cours)</small></span>
                <input type="date" name="exp_fin[]" value="<?php echo valeur($e, 'date_fin'); ?>">
            </label>
            <label class="champ champ-large">Description
                <textarea name="exp_description[]" rows="2" placeholder="Missions, technologies utilisées, résultats..."><?php echo valeur($e, 'description'); ?></textarea>
            </label>
        </div>
    </div>
    <?php
}

function ligneCompetence($niveaux, $c = [])
{
    ?>
    <div class="ligne-liste ligne-simple">
        <input type="text" name="comp_libelle[]" maxlength="100" placeholder="Ex. : PHP, travail en équipe"
               value="<?php echo valeur($c, 'libelle'); ?>">
        <select name="comp_niveau[]"><?php afficherOptions($niveaux, $c['niveau'] ?? ''); ?></select>
        <?php boutonRetirer(); ?>
    </div>
    <?php
}

function ligneLangue($niveaux, $l = [])
{
    ?>
    <div class="ligne-liste ligne-simple">
        <input type="text" name="langue_nom[]" maxlength="50" placeholder="Ex. : Anglais"
               value="<?php echo valeur($l, 'nom_langue'); ?>">
        <select name="langue_niveau[]"><?php afficherOptions($niveaux, $l['niveau'] ?? ''); ?></select>
        <?php boutonRetirer(); ?>
    </div>
    <?php
}

function ligneInteret($i = [])
{
    ?>
    <div class="ligne-liste ligne-simple">
        <input type="text" name="interet_libelle[]" maxlength="100" placeholder="Ex. : Lecture, football"
               value="<?php echo valeur($i, 'libelle'); ?>">
        <?php boutonRetirer(); ?>
    </div>
    <?php
}

afficherEntete('Mon CV', 'Remplissez chaque rubrique : toutes sont obligatoires pour générer le PDF.');
?>

<?php if (count($erreurs) > 0) {
    afficherMessage("Certains champs sont manquants ou incorrects. Corrigez les messages en rouge.", 'erreur');
} ?>

<!-- Sommaire : liens vers chaque section de la page (ancres #...) -->
<nav class="sommaire">
    <a href="#coordonnees"><span>1</span> Coordonnées</a>
    <a href="#photo-section"><span>2</span> Photo</a>
    <a href="#parcours"><span>3</span> Stages et formations</a>
    <a href="#competences"><span>4</span> Compétences et langues</a>
    <a href="#interets"><span>5</span> Centres d'intérêt</a>
</nav>

<form action="cv_traitement.php" method="post" enctype="multipart/form-data">

    <div class="grille-cv">

        <!-- ===== 1. Nom et coordonnées ===== -->
        <section class="carte" id="coordonnees">
            <?php enteteSection(1, 'user', 'Nom et coordonnées', 'Comment les recruteurs vous contacteront.'); ?>

            <div class="champ-duo">
                <div class="champ">
                    <label for="nom">Nom <span class="obligatoire">*</span></label>
                    <input type="text" id="nom" name="nom" required maxlength="50"
                           pattern="[a-zA-ZÀ-ÿ' \-]+" title="Lettres uniquement"
                           value="<?php echo valeur($cv, 'nom'); ?>">
                    <?php afficherErreur($erreurs, 'nom'); ?>
                </div>
                <div class="champ">
                    <label for="prenom">Prénom <span class="obligatoire">*</span></label>
                    <input type="text" id="prenom" name="prenom" required maxlength="50"
                           pattern="[a-zA-ZÀ-ÿ' \-]+" title="Lettres uniquement"
                           value="<?php echo valeur($cv, 'prenom'); ?>">
                    <?php afficherErreur($erreurs, 'prenom'); ?>
                </div>
            </div>

            <!-- L'email est l'identifiant du compte : affiché mais non modifiable -->
            <div class="champ">
                <label for="email">Email <small>(identifiant du compte)</small></label>
                <div class="champ-icone">
                    <?php echo icone('mail'); ?>
                    <input type="email" id="email" value="<?php echo htmlspecialchars($_SESSION['email']); ?>" disabled>
                </div>
            </div>

            <div class="champ">
                <label for="telephone">Téléphone <span class="obligatoire">*</span></label>
                <div class="champ-icone">
                    <?php echo icone('phone'); ?>
                    <input type="tel" id="telephone" name="telephone" required maxlength="13"
                           pattern="(0|\+212)[5-7][0-9]{8}" title="Ex. : 0612345678 ou +212612345678"
                           placeholder="0612345678" value="<?php echo valeur($cv, 'telephone'); ?>">
                </div>
                <?php afficherErreur($erreurs, 'telephone'); ?>
            </div>

            <div class="champ">
                <label for="adresse">Adresse <span class="obligatoire">*</span></label>
                <div class="champ-icone">
                    <?php echo icone('pin'); ?>
                    <input type="text" id="adresse" name="adresse" required maxlength="255"
                           placeholder="N°, rue, ville" value="<?php echo valeur($cv, 'adresse'); ?>">
                </div>
                <?php afficherErreur($erreurs, 'adresse'); ?>
            </div>
        </section>

        <!-- ===== 2. Photo ===== -->
        <section class="carte" id="photo-section">
            <?php enteteSection(2, 'camera', 'Photo', 'Une photo d\'identité nette, fond uni de préférence.'); ?>

            <!-- Zone cliquable : le <label for="photo"> ouvre le choix du fichier -->
            <label for="photo" class="zone-photo">
                <?php if (!empty($cv['photo'])) : ?>
                    <img src="<?php echo htmlspecialchars($cv['photo']); ?>" alt="Photo actuelle" id="apercu-photo">
                <?php else : ?>
                    <img src="" alt="" id="apercu-photo" hidden>
                    <span class="zone-photo-vide" id="photo-vide"><?php echo icone('camera'); ?></span>
                <?php endif; ?>
                <span class="zone-photo-texte">
                    <strong><?php echo empty($cv['photo']) ? 'Choisir une photo' : 'Changer la photo'; ?></strong>
                    JPG ou PNG, 2 Mo maximum
                </span>
            </label>
            <!-- Un champ fichier ne peut pas être pré-rempli : required seulement s'il n'y a pas encore de photo -->
            <input type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png" class="champ-cache"
                   <?php if (empty($cv['photo'])) echo 'required'; ?>>
            <p class="info" id="nom-photo"></p>
            <?php afficherErreur($erreurs, 'photo'); ?>
        </section>

        <!-- ===== 3. Stages et formations ===== -->
        <section class="carte bloc-large" id="parcours">
            <?php enteteSection(3, 'briefcase', 'Stages et formations', 'Du plus récent au plus ancien : ils seront triés automatiquement.'); ?>

            <div id="liste-experiences">
                <?php foreach ($cv['experiences'] as $experience) {
                    ligneExperience($types_experience, $experience);
                } ?>
            </div>
            <?php afficherErreur($erreurs, 'experiences'); ?>

            <template id="modele-experience"><?php ligneExperience($types_experience); ?></template>
            <button type="button" class="btn btn-ajouter" data-modele="modele-experience" data-liste="liste-experiences">
                <?php echo icone('plus'); ?> Ajouter un stage ou une formation
            </button>
        </section>

        <!-- ===== 4. Compétences et langues ===== -->
        <section class="carte" id="competences">
            <?php enteteSection(4, 'star', 'Compétences', 'Techniques ou personnelles, avec votre niveau.'); ?>

            <div id="liste-competences">
                <?php foreach ($cv['competences'] as $competence) {
                    ligneCompetence($niveaux_competence, $competence);
                } ?>
            </div>
            <?php afficherErreur($erreurs, 'competences'); ?>

            <template id="modele-competence"><?php ligneCompetence($niveaux_competence); ?></template>
            <button type="button" class="btn btn-ajouter" data-modele="modele-competence" data-liste="liste-competences">
                <?php echo icone('plus'); ?> Ajouter une compétence
            </button>
        </section>

        <section class="carte">
            <?php enteteSection(4, 'globe', 'Langues', 'Niveau du cadre européen (A1 à C2) ou langue maternelle.'); ?>

            <div id="liste-langues">
                <?php foreach ($cv['langues'] as $langue) {
                    ligneLangue($niveaux_langue, $langue);
                } ?>
            </div>
            <?php afficherErreur($erreurs, 'langues'); ?>

            <template id="modele-langue"><?php ligneLangue($niveaux_langue); ?></template>
            <button type="button" class="btn btn-ajouter" data-modele="modele-langue" data-liste="liste-langues">
                <?php echo icone('plus'); ?> Ajouter une langue
            </button>
        </section>

        <!-- ===== 5. Centres d'intérêt ===== -->
        <section class="carte bloc-large" id="interets">
            <?php enteteSection(5, 'heart', 'Centres d\'intérêt', 'Sport, associations, lecture... ce qui vous ressemble.'); ?>

            <div id="liste-interets" class="liste-colonnes">
                <?php foreach ($cv['interets'] as $interet) {
                    ligneInteret($interet);
                } ?>
            </div>
            <?php afficherErreur($erreurs, 'interets'); ?>

            <template id="modele-interet"><?php ligneInteret(); ?></template>
            <button type="button" class="btn btn-ajouter" data-modele="modele-interet" data-liste="liste-interets">
                <?php echo icone('plus'); ?> Ajouter un centre d'intérêt
            </button>
        </section>

    </div>

    <!-- Barre fixée en bas de l'écran : le bouton Enregistrer reste toujours visible -->
    <div class="barre-actions">
        <a href="cv_apercu.php" class="btn btn-secondaire">Annuler</a>
        <button type="submit" class="btn btn-principal"><?php echo icone('check'); ?> Enregistrer mon CV</button>
    </div>
</form>

<?php afficherPied(); ?>
