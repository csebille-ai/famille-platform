<?php

namespace App\Services\AvatarAstro;

class TraitsSurannesMapper
{
    /**
     * @var array<string,list<string>>
     */
    private array $map = [
        'solide' => ['bien campé', 'de forte trempe', 'robuste', 'd’une fermeté d’antan', 'charpenté'],
        'posé' => ['rassis', 'placide', 'de sang-froid', 'mesuré', 'tranquille'],
        'pragmatique' => ['de bon sens', 'pratique', 'terre-à-terre', 'd’esprit concret', 'sans façons'],
        'fiable' => ['sûr', 'digne de confiance', 'constant', 'de parole tenue', 'invariable'],

        'audacieux' => ['hardi', 'intrépide', 'téméraire', 'vaillant', 'preux'],
        'enthousiaste' => ['ardent', 'plein d’allant', 'enjoué', 'fervent', 'bouillonnant'],
        'leader' => ['meneur', 'chef de file', 'capitaine', 'conducteur', 'directeur'],
        'impulsif' => ['impétueux', 'prompt', 'emporté', 'd’un naturel vif', 'sans attendre'],

        'curieux' => ['avide de savoir', 'fureteur', 'inquisiteur', 'en quête d’inédit', 'observateur'],
        'malin' => ['rusé', 'finaud', 'astucieux', 'd’esprit alerte', 'délicat d’esprit'],
        'social' => ['affable', 'mondain', 'sociable', 'de bonne compagnie', 'porté aux échanges'],
        'inventif' => ['ingénieux', 'fécond en idées', 'novateur', 'd’une trouvaille prompte', 'imaginatif'],

        'intuitif' => ['inspiré', 'd’instinct sûr', 'sensible aux signes', 'perspicace', 'à l’oreille fine'],
        'doux' => ['débonnaire', 'tendre', 'moult aimable', 'plein de mansuétude', 'de nature suave'],
        'profond' => ['grave', 'pénétrant', 'de grande profondeur', 'réfléchi', 'd’une pensée dense'],
        'protecteur' => ['tutélaire', 'gardien', 'prévenant', 'soucieux des siens', 'rempart'],

        'stratège' => ['fin stratège', 'calculateur d’antan', 'd’esprit tactique', 'préparé', 'de ruse mesurée'],
        'endurant' => ['tenace', 'de longue haleine', 'infatigable', 'endurci', 'qui ne plie guère'],
        'intrépide' => ['intrépide', 'sans peur', 'vaillant', 'le cœur haut', 'd’un cran rare'],
        'charmant' => ['gracieux', 'aimable', 'plein de charme', 'avenant', 'de belle prestance'],
        'magnétique' => ['captivant', 'd’un attrait singulier', 'fascinant', 'd’une aura', 'qui attire les regards'],
        'lucide' => ['clairvoyant', 'd’esprit net', 'perspicace', 'qui voit juste', 'de jugement sûr'],
        'libre' => ['indépendant', 'sans entraves', 'd’un pas libre', 'qui va son chemin', 'hors des chaînes'],
        'créatif' => ['imaginatif', 'd’inspiration fertile', 'créateur', 'ingénieux', 'inventif'],
        'malicieux' => ['espiègle', 'fripon', 'malicieux', 'd’un sourire en coin', 'taquin'],
        'franc' => ['droit', 'sans détour', 'de parole franche', 'net', 'sans fard'],
        'loyal' => ['fidèle', 'dévoué', 'constant', 'de parole', 'loyal jusqu’au bout'],
        'généreux' => ['large de cœur', 'prodigue', 'libéral', 'magnanime', 'd’une bonté ouverte'],

        'pionnier' => ['éclaireur', 'pionnier', 'avant-coureur', 'ouvre-chemin', 'de l’audace première'],
        'harmonieux' => ['conciliant', 'doux accord', 'faiseur de paix', 'en quête d’équilibre', 'de belle entente'],
        'drôle' => ['plaisant', 'drôle', 'd’esprit railleur', 'bouffon savant', 'gai comme un pinson'],
        'méthodique' => ['ordonné', 'rangé', 'd’une méthode sûre', 'scrupuleux', 'appliqué'],
        'aventurier' => ['voyageur', 'd’âme errante', 'aventureux', 'coureur de routes', 'ami du risque'],
        'bienveillant' => ['bénévole', 'plein de bonté', 'de bonne grâce', 'secourable', 'charitable'],
        'sage' => ['sage', 'pondéré', 'd’esprit mûr', 'philosophe', 'de jugement posé'],
        'ambitieux' => ['aspirant aux grandeurs', 'en quête d’essor', 'avide d’élévation', 'voué à la réussite', 'désireux de haut fait'],
        'inspirant' => ['inspirant', 'élevé', 'qui donne élan', 'porteur de souffle', 'd’un esprit lumineux'],
        'visionnaire' => ['visionnaire', 'qui voit loin', 'prophétique', 'd’une vue d’aigle', 'rêveur lucide'],
        'bâtisseur' => ['bâtisseur', 'faiseur d’ouvrages', 'constructeur', 'de main sûre', 'de long dessein'],

        'utile' => ['de bon secours', 'serviable', 'd’un usage sûr', 'd’un appui fidèle', 'toujours prêt'],
        'droit' => ['droit', 'sans détour', 'rectiligne', 'de juste conduite', 'd’une droiture rare'],
        'constant' => ['constant', 'invariable', 'fidèle au poste', 'd’une tenue égale', 'stable'],
    ];

    public function toSuranne(int $userId, string $traitCanon): string
    {
        $t = trim((string) $traitCanon);
        if ($t === '') {
            return '';
        }

        $opts = $this->map[$t] ?? null;
        if (!is_array($opts) || $opts === []) {
            return $t;
        }

        $h = crc32($userId . '|' . $t);
        $idx = (int) (abs((int) $h) % count($opts));

        return (string) ($opts[$idx] ?? $t);


}

