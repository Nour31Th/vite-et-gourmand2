--script créa° BDD PostgreSQL, généré depuis entité Doctrine du projet
--stack=symfony8 et postgresql 14
--usage-> psql -U postgres -d vite_et_gourmand2 -f schema.sql


--table user (utilisaters de l'appli->clients, employés, admin), rôle stockés en json : ["ROLE_USER"], ["ROLE_EMPLOYE"], ["ROLE_ADMIN"]
CREATE TABLE IF NOT EXISTS "user" (
    id            SERIAL PRIMARY KEY,
    email         VARCHAR(180)  NOT NULL UNIQUE,
    roles         JSON          NOT NULL DEFAULT '[]',
    password      VARCHAR(255)  NOT NULL,
    nom           VARCHAR(50)   NOT NULL,
    prenom        VARCHAR(50)   NOT NULL,
    gsm           VARCHAR(20),
    adresse       VARCHAR(255),
    ville         VARCHAR(100),
    code_postal   VARCHAR(10),
    actif         BOOLEAN       NOT NULL DEFAULT TRUE
);

--table menu, stock->9999=illimité
CREATE TABLE IF NOT EXISTS menu (
    id                   SERIAL PRIMARY KEY,
    titre                VARCHAR(100)   NOT NULL,
    description          TEXT,
    theme                VARCHAR(50),
    regime               VARCHAR(50),
    nb_personnes_min     INT            NOT NULL,
    prix                 DECIMAL(10,2)  NOT NULL,
    stock                INT            NOT NULL DEFAULT 9999,
    conditions           TEXT,
    actif                BOOLEAN        NOT NULL DEFAULT TRUE
);

--table plat (entrée plat dessert)
CREATE TABLE IF NOT EXISTS plat (
    id          SERIAL PRIMARY KEY,
    nom         VARCHAR(100) NOT NULL,
    type        VARCHAR(50)  NOT NULL,
    description TEXT
);

--table allergène (allergènes majeurs règlmnta° UE)
CREATE TABLE IF NOT EXISTS allergene (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL UNIQUE
);

--table image menu, photo associé à un menu (relation manytoone) et url->chemin relatif depuis/ public/images/menus/
CREATE TABLE IF NOT EXISTS image_menu (
    id      SERIAL PRIMARY KEY,
    menu_id INT          NOT NULL REFERENCES menu(id) ON DELETE CASCADE,
    url     VARCHAR(255) NOT NULL
);

--table commande
CREATE TABLE IF NOT EXISTS commande (
    id                  SERIAL PRIMARY KEY,
    utilisateur_id      INT            NOT NULL REFERENCES "user"(id),
    menu_id             INT            NOT NULL REFERENCES menu(id),
    numero_commande     VARCHAR(50)    NOT NULL UNIQUE,
    date_commande       TIMESTAMP      NOT NULL DEFAULT NOW,
    date_prestation     DATE           NOT NULL,
    heure_livraison     TIME           NOT NULL,
    adresse_livraison   VARCHAR(255)   NOT NULL,
    ville_livraison     VARCHAR(100)   NOT NULL,
    nb_personnes        INT            NOT NULL,
    prix_menu           DECIMAL(10,2)  NOT NULL,
    prix_livraison      DECIMAL(10,2)  NOT NULL DEFAULT 0,
    prix total          DECIMAL(10,2)  NOT NULL,
    statut              VARCHAR(50)    NOT NULL DEFAULT 'en attente',
    pret_materiel       BOOLEAN        NOT NULL DEFAULT FALSE,
    materiel_restitue   BOOLEAN        NOT NULL DEFAULT FALSE
);

--table avis, valide=false par défaut car valida° employé, onetoone w/ commande (1avis max/commande)
CREATE TABLE IF NOT EXISTS avis (
    id              SERIAL PRIMARY KEY,
    utilisateur_id  INT   NOT NULL REFERENCES "user"(id),
    commande_id     INT   NOT NULL UNIQUE REFERENCES commande(id),
    note            INT   NOT NULL CHECK (note BETWEEN 1 AND 5),
    commentaire     TEXT,
    valide          BOOLEAN   NOT NULL DEFAULT FALSE,
    date_avis       TIMESTAMP NOT NULL DEFAULT NOW()
);

--table historique statut (changement statu et mode contact mail, gsm, sms...)
CREATE TABLE IF NOT EXISTS historique_statut (
    id            SERIAL PRIMARY KEY, 
    commande_id   INT       NOT NULL REFERENCES commande(id),
    satut         VARCHAR(50) NOT NULL,
    date_heure    TIMESTAMPS NOT NULL DEFAULT NOW();
    commentaire   TEXT,
    mode_contact  VARCHAR(50)
);

--table menu_plat, jointure manytomany entre menu et plat (menu contient plusieurs plat et plat peut être dans plusieurs menus)
CREATE TABLE IF NOT EXISTS menu_plats (
    menu_id INT NOT NULL REFRENCES menu(id) ON DELETE CASCADE,
    plat_id INT NOT NULL REFRENCES plat(id) ON DELETE CASCADE,
    PRIMARY KEY (menu_id, plat_id)
);

--table plat_ellergene, jointure manytomany entre plat et allergene(plat peut avoir plusieurs allergènes)
CREATE TABLE IF NOT EXISTS plat_allergene (
    plat_id      INT NOT NULL REFRENCES plat(id) ON DELETE CASCADE,
    allergene_id INT NOT NULL REFRENCES allergene(id) ON DELETE CASCADE,
    PRIMARY KEY (plat_id, allergene_id)
);

--table horaire, ouverture/jour,heure 00:00/00:00->fermé ce jour, modif° w/ espace employé
CREATE TABLE IF NOT EXISTS horaire (
    id              SERIAL PRIMARY KEY,
    jour            VARCHAR(15) NOT NULL UNIQUE,
    heure_ouverture TIME    NOT NULL,
    heure_fermeture TIME    NOT NULL
);

--table contact, msg recu via formulaire, sauvegarde bdd+notif mail brevo
CREATE TABLE IF NOT EXISTS contact (
    id          SERIAL PRIMARY KEY,
    titre       VARCHAR(150) NOT NULL,
    description TEXT         NOT NULL,
    email       VARCHAR(180) NOT NULL,
    date        TIMESTAMP    NOT NULL DEFAULT NOW() 
);

--table newsletter (incriotion et mail liste brevo)
CREATE TABLE IF NOT EXISTS newsletter (
    id               SERIAL PRIMARY KEY,
    email            VARCHAR(180) NOT NULL UNIQUE,
    date_inscription TIMESTAMP    NOT NULL DEFAULT NOW()
);

--table reset password token (token réinitlsa° mdp)
--selector->partie publique ds url
--hashed_token->hash SHA-256 du token secret (jamais clair)
--expires_at->expiration ap 1h
CREATE TABLE IF NOT EXISTS reset_password_token (
    id              SERIAL PRIMARY KEY,
    utilisateur_id  INT            NOT NULL REFERENCES "user"(id),
    selector        VARCHAR(20)    NOT NULL,
    hashed_token    VARCHAR(100)   NOT NULL,
    expires_at      TIMESTAMP      NOT NULL
);


