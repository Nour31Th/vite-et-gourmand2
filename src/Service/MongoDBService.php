<?php
//service accès mongodb atlas, composants accès à mongodb ds classe dédiée//

namespace App\Service;

use MongoDB\Collection;

//rôle :enregistrer stats commandes passée, récup stats pour dashboard admin
//collec° MongoDB utilisée : 'commandes_stats'
//chaque doc  représente commande w/ données analytiques
/*
 * Utilisation dans CommandeController :
 *   $this->mongoService->saveCommandeStat([...]);
 *
 * Utilisation dans AdminController :
 *   $stats = $this->mongoService->getStatsByMenu();
 */
class MongoDBService
{
    //collec° MongoDB 'commandes_stats', dtockée en propriété pr éviter de recréer connex° à chaque appel
    private ?Collection $collection = null;

    /**
     *constructeur initialise connexion MongoDB
     *URI MongoDB recuo depuis variable d'envrnnmnt, définie dans .env.local (dev) et dans Railway (prod).
     *en dévppmnt local sans MongoDB installé, méthodes protégées par try/catch pr pas bloquer devppmnt
     */
    public function __construct()
    {
        try {
            if (empty($_ENV['MONGODB_URI']) || empty($_ENV['MONGODB_DB'])) {
                return;
            }
            //connex° cluster MongoDB Atlas
            $client = new \MongoDB\Client($_ENV['MONGODB_URI']);

            //select° bdd & collection
            $this->collection = $client->selectCollection(
                $_ENV['MONGODB_DB'],    // ex: 'vite_et_gourmand_stats'
                'commandes_stats'       // nom collection
            );
        } catch (\Exception $e) {
            //local sans MongoDB->log erreur sans bloquer
            error_log('MONGODB CONNECTION ERROR: ' . $e->getMessage());
        }
    }

    /**
     * enregistre stats commande dans MongoDB
     *call depuis CommandeController après chaque nouvelle commande.
     *données dupliquées depuis PostgreSQL prr permettre analyses rapides sans JOIN complexes.
     *structure du doc inséré :
     * {
     *   commande_id: 42,
     *   numero_commande: "VG-ABC123",
     *   menu_id: 3,
     *   menu_titre: "Le Classique",
     *   prix_total: 150.00,
     *   nb_personnes: 6,
     *   ville_livraison: "Bordeaux",
     *   statut: "en_attente",
     *   date: ISODate("2026-07-01T..."),
     * }
     *
     * @param array $data données commande
     */
    public function saveCommandeStat(array $data): void
    {
        if ($this->collection) return;
        try {
            $this->collection->insertOne([
                'commande_id'     => $data['id'],
                'numero_commande' => $data['numero_commande'],
                'menu_id'         => $data['menu_id'],
                'menu_titre'      => $data['menu_titre'],
                'prix_total'      => (float) $data['prix_total'],
                'nb_personnes'    => (int) $data['nb_personnes'],
                'ville_livraison' => $data['ville_livraison'],
                'statut'          => $data['statut'],
                // UTCDateTime->format date natif MongoDB
                'date'            => new \MongoDB\BSON\UTCDateTime(),
            ]);
        } catch (\Exception $e) {
            //MongoDB est indisponible->commande reste sauvegardée
            //en PostgreSQL MongoDB optionnel pr stats
            error_log('MONGODB INSERT ERROR: ' . $e->getMessage());
        }
    }

    /**
     * récup stats groupées/menu
     *utilisé par AdminController::stats() pr graphique Chart.js.
     *utilise le pipeline d'agrégation MongoDB : $group->regroupe par menu_id et calcule nb_commandes + ca_total, $sort->trie par nb_commandes décroissant, $limit->retourne les 10 menus les plus commandés
     *
     * @return array Ex: [
     *   ['_id' => 3, 'titre' => 'Le Classique', 'nb_commandes' => 12, 'ca_total' => 1800.00],
     *   ...
     * ]
     */
    public function getStatsByMenu(): array
    {
        if ($this->collection) return [];
        try {
            return $this->collection->aggregate([
                //1ère étape grouper/menu_id
                ['$group' => [
                    '_id'          => '$menu_id',
                    'titre'        => ['$first' => '$menu_titre'],
                    'nb_commandes' => ['$sum' => 1],
                    'ca_total'     => ['$sum' => '$prix_total'],
                ]],
                //2ème étape trier/nb commandes décroissant
                ['$sort' => ['nb_commandes' => -1]],
                //3ème étape limiter aux 3 menus les + populaires
                ['$limit' => 3],
            ])->toArray();
        } catch (\Exception $e) {
            error_log('MONGODB AGGREGATE ERROR: ' . $e->getMessage());
            return []; //retourne tableau vide si MongoDB indisponible
        }
    }

    /**
     *recup stat groupées/mois
     *utilisé par AdminController::stats() pr graphique "Commandes par mois" en + des données PostgreSQL.
     *
     * @return array Ex: [
     *   ['mois' => '2026-01', 'nb' => 8, 'ca' => 1200.00],
     *   ...
     * ]
     */
    public function getStatsByMois(): array
    {
        if (!$this->collection) return [];
        try {
            return $this->collection->aggregate([
                //1ère étape extraire année et mois de la date
                ['$group' => [
                    '_id' => [
                        'annee' => ['$year'  => '$date'],
                        'mois'  => ['$month' => '$date'],
                    ],
                    'nb' => ['$sum' => 1],
                    'ca' => ['$sum' => '$prix_total'],
                ]],
                //2ème étape trier chronolgqmnt
                ['$sort' => ['_id.annee' => 1, '_id.mois' => 1]],
            ])->toArray();
        } catch (\Exception $e) {
            error_log('MONGODB STATS MOIS ERROR: ' . $e->getMessage());
            return [];
        }
    }

    /**
     *recup CA total depuis MongoDB
     *
     * @return float Somme de ts les prix_total des commandes
     */
    public function getCaTotal(): float
    {
        if (!$this->collection) return 0.0;
        try {
            $result = $this->collection->aggregate([
                ['$group' => [
                    '_id' => null,
                    'ca_total' => ['$sum' => '$prix_total'],
                ]],
            ])->toArray();

            return $result[0]['ca_total'] ?? 0.0;
        } catch (\Exception $e) {
            error_log('MONGODB CA ERROR: ' . $e->getMessage());
            return 0.0;
        }
    }
}
