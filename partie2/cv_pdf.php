<?php
// cv_pdf.php : génération du CV au format PDF avec la bibliothèque FPDF (lib/fpdf)
// Mise en page : colonne de gauche foncée (photo, contact, compétences, langues, intérêts)
//                et colonne de droite (nom, stages et formations sur une frise).
include 'config.php';
require 'lib/fpdf/fpdf.php';

if (!isset($_SESSION['email'])) {
    header('Location: connexion.php');
    exit;
}

// Quel CV afficher ?
// - un candidat : uniquement le sien ;
// - une entreprise (partie facultative) : celui d'un candidat qui a postulé à une de SES offres.
if ($_SESSION['role'] === 'candidat') {
    $email_cv = $_SESSION['email'];
} else {
    $email_cv = $_GET['email'] ?? '';
    $requete = $bdd->prepare(
        "SELECT COUNT(*) FROM candidature c
         JOIN offre_stage o ON o.id_offre = c.id_offre
         WHERE c.email_candidat = ? AND o.email_entreprise = ?"
    );
    $requete->execute([$email_cv, $_SESSION['email']]);
    if ($requete->fetchColumn() == 0) {
        die("Accès refusé : ce candidat n'a pas postulé à vos offres.");
    }
}

$cv = lireCV($bdd, $email_cv);
if (!cvComplet($cv)) {
    die("Le CV est incomplet : remplissez toutes les rubriques avant de générer le PDF.");
}

// FPDF n'écrit pas l'UTF-8 : on convertit chaque texte en Windows-1252 (accents français)
function texte($chaine)
{
    return iconv('UTF-8', 'windows-1252//TRANSLIT', $chaine);
}

// ---------- Mesures de la page (en millimètres, A4 = 210 x 297) ----------
define('LARGEUR_COTE', 70);    // largeur de la colonne de gauche
define('X_COTE', 8);           // marge intérieure de la colonne de gauche
define('L_COTE', 54);          // largeur utile du texte dans la colonne de gauche
define('X_PRINCIPAL', 80);     // début de la colonne de droite

// On crée notre propre classe à partir de FPDF (héritage) pour redéfinir
// Header() et Footer(), que FPDF appelle automatiquement sur chaque page.
class PDF_CV extends FPDF
{
    // Haut de chaque page : le fond bleu foncé de la colonne de gauche
    function Header()
    {
        $this->SetFillColor(15, 23, 42);
        $this->Rect(0, 0, LARGEUR_COTE, 297, 'F');
    }

    // Bas de chaque page : date de génération et numéro de page
    function Footer()
    {
        $this->SetY(-12);
        $this->SetX(X_PRINCIPAL);
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(0, 5, texte('CV généré le ' . date('d/m/Y') . ' - page ' . $this->PageNo()), 0, 0, 'R');
    }

    // Cercle plein (FPDF n'en a pas : on le dessine avec 4 courbes de Bézier)
    function Cercle($x, $y, $r)
    {
        $l = 4 / 3 * (M_SQRT2 - 1) * $r;
        $k = $this->k;
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x + $r) * $k, ($h - $y) * $k, ($x + $r) * $k, ($h - ($y - $l)) * $k, ($x + $l) * $k, ($h - ($y - $r)) * $k, $x * $k, ($h - ($y - $r)) * $k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x - $l) * $k, ($h - ($y - $r)) * $k, ($x - $r) * $k, ($h - ($y - $l)) * $k, ($x - $r) * $k, ($h - $y) * $k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x - $r) * $k, ($h - ($y + $l)) * $k, ($x - $l) * $k, ($h - ($y + $r)) * $k, $x * $k, ($h - ($y + $r)) * $k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c f',
            ($x + $l) * $k, ($h - ($y + $r)) * $k, ($x + $r) * $k, ($h - ($y + $l)) * $k, ($x + $r) * $k, ($h - $y) * $k));
    }

    // Titre d'une rubrique de la colonne de gauche (blanc, souligné d'un trait gris)
    function TitreCote($titre)
    {
        $this->Ln(5);
        $this->SetX(X_COTE);
        $this->SetFont('Helvetica', 'B', 9.5);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(L_COTE, 6, texte(mb_strtoupper($titre)), 0, 1);
        $this->SetDrawColor(51, 65, 85);
        $this->Line(X_COTE, $this->GetY(), X_COTE + L_COTE, $this->GetY());
        $this->Ln(3);
    }

    // Une compétence ou une langue : libellé, niveau à droite, puis barre de niveau
    function Niveau($libelle, $niveau, $pourcentage)
    {
        $this->SetX(X_COTE);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(241, 245, 249);
        $this->Cell(L_COTE - 22, 5, texte($libelle), 0, 0);
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(22, 5, texte($niveau), 0, 1, 'R');

        $y = $this->GetY() + 0.5;
        $this->SetFillColor(51, 65, 85);                       // fond de la barre
        $this->Rect(X_COTE, $y, L_COTE, 1.6, 'F');
        $this->SetFillColor(96, 165, 250);                     // partie remplie
        $this->Rect(X_COTE, $y, L_COTE * $pourcentage / 100, 1.6, 'F');
        $this->Ln(4.5);
    }

    // Titre d'une rubrique de la colonne de droite (bleu, souligné)
    function TitrePrincipal($titre)
    {
        $this->Ln(4);
        $this->SetX(X_PRINCIPAL);
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(37, 99, 235);
        $this->Cell(0, 7, texte(mb_strtoupper($titre)), 0, 1);
        $this->SetDrawColor(219, 234, 254);
        $this->SetLineWidth(0.4);
        $this->Line(X_PRINCIPAL, $this->GetY(), 195, $this->GetY());
        $this->SetLineWidth(0.2);
        $this->Ln(5);
    }
}

$pdf = new PDF_CV('P', 'mm', 'A4');   // portrait, millimètres, format A4
$pdf->SetTitle(texte('CV - ' . $cv['prenom'] . ' ' . $cv['nom']));
$pdf->SetAuthor(texte($cv['prenom'] . ' ' . $cv['nom']));
$pdf->SetMargins(X_PRINCIPAL, 15, 15);
$pdf->AddPage();

// =====================================================================
// COLONNE DE GAUCHE (on désactive le saut de page automatique)
// =====================================================================
$pdf->SetAutoPageBreak(false);

// ---------- Photo : centrée dans un cadre de 40 x 50 mm, sans la déformer ----------
$chemin_photo = __DIR__ . '/' . $cv['photo'];
$taille = getimagesize($chemin_photo);          // [largeur, hauteur] en pixels
$y_apres_photo = 20;
if ($taille !== false) {
    $l = 40;
    $h = 40 * $taille[1] / $taille[0];          // hauteur proportionnelle
    if ($h > 50) {                              // photo trop haute : on la réduit
        $h = 50;
        $l = 50 * $taille[0] / $taille[1];
    }
    // try/catch : si FPDF ne sait pas lire l'image, le PDF est quand même généré
    try {
        $pdf->Image($chemin_photo, (LARGEUR_COTE - $l) / 2, 14, $l, $h);
        $y_apres_photo = 14 + $h;
    } catch (Exception $e) {
        // photo ignorée
    }
}
$pdf->SetY($y_apres_photo + 2);

// ---------- Contact ----------
$pdf->TitreCote('Contact');
$contacts = ['Email' => $cv['email'], 'Téléphone' => $cv['telephone'], 'Adresse' => $cv['adresse']];
foreach ($contacts as $etiquette => $valeur) {
    $pdf->SetX(X_COTE);
    $pdf->SetFont('Helvetica', 'B', 7.5);
    $pdf->SetTextColor(147, 197, 253);
    $pdf->Cell(L_COTE, 4, texte(mb_strtoupper($etiquette)), 0, 1);
    $pdf->SetX(X_COTE);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(226, 232, 240);
    $pdf->MultiCell(L_COTE, 4.5, texte($valeur), 0, 'L');   // 'L' : aligné à gauche (par défaut FPDF justifie)
    $pdf->Ln(1.5);
}

// ---------- Compétences et langues avec barres de niveau ----------
$pdf->TitreCote('Compétences');
foreach ($cv['competences'] as $c) {
    if ($pdf->GetY() < 280) {                   // sécurité : on ne dépasse pas le bas de la page
        $pdf->Niveau($c['libelle'], $c['niveau'], pourcentageNiveau($c['niveau']));
    }
}

$pdf->TitreCote('Langues');
foreach ($cv['langues'] as $l) {
    if ($pdf->GetY() < 280) {
        $pdf->Niveau($l['nom_langue'], $l['niveau'], pourcentageNiveau($l['niveau']));
    }
}

// ---------- Centres d'intérêt ----------
$pdf->TitreCote("Centres d'intérêt");
$pdf->SetFont('Helvetica', '', 9);
$pdf->SetTextColor(226, 232, 240);
foreach ($cv['interets'] as $i) {
    if ($pdf->GetY() < 284) {
        $pdf->SetX(X_COTE);
        // chr(149) = puce « • » en Windows-1252
        $pdf->Cell(L_COTE, 5, chr(149) . '  ' . texte($i['libelle']), 0, 1);
    }
}

// =====================================================================
// COLONNE DE DROITE (saut de page automatique réactivé)
// =====================================================================
$pdf->SetAutoPageBreak(true, 18);
$pdf->SetXY(X_PRINCIPAL, 22);

// ---------- Nom ----------
$pdf->SetFont('Helvetica', '', 22);
$pdf->SetTextColor(15, 23, 42);
$pdf->MultiCell(0, 10, texte($cv['prenom']));
$pdf->SetFont('Helvetica', 'B', 26);
$pdf->SetTextColor(37, 99, 235);
$pdf->MultiCell(0, 11, texte(mb_strtoupper($cv['nom'])));

$pdf->SetFont('Helvetica', '', 9);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(0, 6, texte('CURRICULUM VITAE'), 0, 1);

// ---------- Stages et formations : frise chronologique ----------
$pdf->TitrePrincipal('Stages et formations');

$x_trait = X_PRINCIPAL + 2;                       // position du trait vertical de la frise
foreach ($cv['experiences'] as $e) {
    // Si l'expérience risque d'être coupée en bas de page, on passe à la page suivante
    if ($pdf->GetY() > 255) {
        $pdf->AddPage();
        $pdf->SetY(18);
    }

    $y_debut = $pdf->GetY();
    $page_debut = $pdf->PageNo();
    $x_texte = X_PRINCIPAL + 8;

    // Dates
    $dates = formaterDate($e['date_debut']) . ' - ' . ($e['date_fin'] ? formaterDate($e['date_fin']) : "aujourd'hui");
    $pdf->SetX($x_texte);
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(0, 5, texte($dates), 0, 1);

    // Type (Stage / Formation) en couleur, puis l'intitulé
    $pdf->SetX($x_texte);
    $pdf->SetFont('Helvetica', 'B', 8);
    if ($e['type'] === 'Stage') {
        $pdf->SetTextColor(37, 99, 235);
    } else {
        $pdf->SetTextColor(124, 58, 237);
    }
    $pdf->Cell(0, 5, texte(mb_strtoupper($e['type'])), 0, 1);

    $pdf->SetX($x_texte);
    $pdf->SetFont('Helvetica', 'B', 11.5);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->MultiCell(0, 6, texte($e['titre']));

    // Organisme et lieu
    $details = implode(' - ', array_filter([$e['organisme'], $e['lieu']]));
    if ($details !== '') {
        $pdf->SetX($x_texte);
        $pdf->SetFont('Helvetica', 'I', 9.5);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->MultiCell(0, 5, texte($details));
    }

    // Description
    if ($e['description']) {
        $pdf->Ln(1);
        $pdf->SetX($x_texte);
        $pdf->SetFont('Helvetica', '', 9.5);
        $pdf->SetTextColor(51, 65, 85);
        $pdf->MultiCell(0, 5, texte($e['description']), 0, 'L');
    }

    // Trait vertical + point de la frise (seulement si l'expérience tient sur une page,
    // sinon le point serait dessiné sur la mauvaise page)
    if ($pdf->PageNo() === $page_debut) {
        $pdf->SetDrawColor(219, 234, 254);
        $pdf->SetLineWidth(0.6);
        $pdf->Line($x_trait, $y_debut + 2, $x_trait, $pdf->GetY() + 4);
        $pdf->SetLineWidth(0.2);
        $pdf->SetFillColor(37, 99, 235);
        $pdf->Cercle($x_trait, $y_debut + 2.5, 1.6);
    }

    $pdf->Ln(6);
}

// Output('I') : affiche le PDF dans le navigateur ('D' le téléchargerait)
$nom_fichier = 'CV_' . preg_replace("/[^a-zA-Z0-9]/", '_', $cv['nom'] . '_' . $cv['prenom']) . '.pdf';
$pdf->Output('I', $nom_fichier);
