--jeu d'essai pr demonstra° et test fonctionnalités
--script INSERT exécuté apr migrat° w/ structure tables, ici script pas exécuté par doctrine
--scrip executé manuellmnt 1 fois-> psql -U postgres -d vite_et_gourmand2 -f data.sql
--objectif de ce script->test ttes les fonctionnalités avec données, par la suite ajout de données directement w/ l'interface utilisateur (inscription client, création employé, crud menu, modif horaire)

--allergènes affichés sur chaque page menu détaillé
INSERT INTO allergene (nom) VALUES
    ('Gluten'),
    ('Crustacés'),
    ('Oeufs'),
    ('Poissons'),
    ('Arachides'),
    ('Soja'),
    ('Lait'),
    ('Fruit à coque'),
    ('Céleri'),
    ('Moutarde'),
    ('Sulfites'),
    ('Mollusques')
ON CONFLICT (nom) DO NOTHING;        --ON CONFLICT DO NOTHING->pas erreur si déjà présents

--horaires s/ les 7j de la semaine, affiché dans footer w/ appExtension, modif par employé ou admin
INSERT INTO horaire (jour, heure_ouverture, heure_fermeture) VALUES
    ('Lundi',    '00:00', '00:00'), -- =fermé ce jour
    ('Mardi',    '11:00', '22:00'),
    ('Mercredi', '11:00', '22:00'),
    ('Jeudi',    '11:00', '22:00'),
    ('Vendredi', '11:00', '23:00'),
    ('Samedi',   '11:00', '23:00'),
    ('Dimanche', '12:00', '18:00'),
ON CONFLICT (jour) DO NOTHING;       --ON CONFLICT DO NOTHING->pas erreur si déjà présents

--admin, seul compte inser ds bdd, aucun rôle peut créer admin
--id/mail: jose@viteetgourmand.fr
--mdp: Admin@ViteGourmand2026!     (hash généré par php bin/console security:hash-password)
--algo bcrypt (MigratingPasswordHasher Symfony)
INSERT INTO "user" (email, roles, password, nom, prenom, actif) VALUES
   (
     'jose@viteetgourmand.fr',
     '["ROLE_ADMIN"]',
     '$2y$13$PgfPk/6G.ROShFPrg7M2FOmRzY.H4XYHtvL/cbKSMPAht7Yw2BxTO',
     'Cannelet',
     'José',
     TRUE
   )
ON CONFLICT (email) DO NOTHING;       --ON CONFLICT DO NOTHING->pas erreur si déjà présents

--3 menus de démonstration, test des filtres, calcul prix et réduction et affichat plats et allergènes (photo que j'ajoute moi même au code pr ces données là seukement)
INSERT INTO menu (itre, theme, regime, nb_personnes_min, prix, stock, zctif, description, conditions) VALUES

      --Menu 1 
      (
        'Le Classique Intemporel',
        'Classique',
        'Standard',
        2,                 --2pers min, reduc à partir de 7pers (2+5pers)
        50.00,            --50€ pr 2 pers soit 25€/pers
        9999,             -- = illimité
        TRUE,             --se voit s/ le site
        'Les plats emblématiques de la tradition bistrotière française. Un menu sans chichi, généreux et préparé avec les meilleurs produits du terroir. Idéal pour un diner en famille ou un repas entre amis. ',
        'Peut être réchauffé au four ou à la poêle.'
      ),

      --Menu 2, entrée au choix!
      (
        'Le prestige',
        'Évènement',
        'Standard',
        4,            --4pers min, reduc à partir de 9pers (4+5pers)
        160.00,       --160€ pr 4 pers soit (40€/pers)
        8,          --stock limité à 8 commandes
        TRUE,
        'Une expérience culinaire haut de gamme. Ingrédients rares et préparations soignées pour célébrer un moment unique.',
        'Commande 7 jours avant la livraison. Ne pas congeler les produits délicats.'
      ),

      --Menu 3
      (
        'Le Petit Gourmet',
        'Enfant',
        'Enfant',
        '1', 
        15.00,      --15€/enfants
        9999,   --stock illimté
        TRUE,
        'Des plats simples, équilibrés et amusants pour les plus jeunes. Sans épices, mais plein de saveurs qu''ils adorent et des portions adaptées.',
        'Convient aux enfants à partir de 4 ans.'
      )
;

--plats, affichés s/ page détail menu
--associé aux menu via table jointure menu_plat
--id attribués dans ordre d'insertion: 
--menu 1: entrée1=1, entrée2=2, plat=3, dessert=4
--menu 2: entrée1=5, entrée2=6, plat=7, dessert=8
--menu 3: entrée=9, plat=10, dessert=11
INSERT INTRO plat (nom, type, description) VALUES

    --plats menu 1
    ('Pâté en Croûte (veau , poulet et dinde) Maison',
     'Entrée',
     'Pâté en croûte fait maison. Servie avec des cornichons.'),

    ('Soupe à l''Oignon Gratinée',
     'Entrée',
     'Soupe à l''oignon traditionnelle, gratinée au four avec du fromage et des croûtons.'),

    ('Filet Mignon de Boeuf, sauce moutarde à l''ancienne, pommes de terre grenailles',
     'Plat',
     'Filet mignon de boeuf cuit à la perfection, sauce moutarde à l''ancienne, accompagné de pommes de terre grenailles rôties.'),

    ('Île Flottante Classique',
     'Dessert',
     'Blancs en neige pochés sur crème anglaise à la vanille, caramel coulant.'),

    --plats menu 2
    ('Carpaccio de Saint-Jacques, Caviar et Agrumes',
     'Entrée',
     'Noix de Saint-Jacques finement tranchées, caviar d''Aquitaine, zestes d''agrumes et huile d''olive vierge extra.'),

    ('Foie Gras de Canard Mi-Cuit',
     'Entrée',
     'Foie gras de canard mi-cuit maison et fleur de sel.'),

    ('Filet de Veau, Purée Truffée',
     'Plat',
     'Filet de veau cuit en basse température, purée de pommes de terre truffée, jus de veau.'),

    ('Dôme Royal au Chocolat et Noix de Pécan',
     'Dessert',
     'Dôme au chocolat noir au coeur fondant praliné noix de pécan.'),

    --plats menu 3
    ('Tomates cerises et dés de concombre',
     'Entrée',
     'Tomates cerises colorées et dés de concombre frais.'),

    ('Filet de Poulet Pané Maison et Purée de Carottes',
     'Plat',
     'Filet de poulet pané maison croustillant, purée de carottes onctueuse.'),

    ('Brochette de Fruits Frais de Saison',
     'Dessert',
     'Brochette colorée de fruits frais de saison.')
;

--association menus plats, table jointure menu_plat
--chaque menu associé à ses plats
--id correspondant à ordre insert° au dessus
--menu 1-> plats 1, 2, 3, 4
--menu 2->plats 5, 6, 7, 8
--menu 3->plats 9, 10, 11
INSERT INTO menu_plat (menu_id, plat_id) VALUES
    --menu1
    (1, 1),   --entrée choix 1->pâté 
    (1, 2),   --entrée choix 2->soupe
    (1, 3),   --plat->filet
    (1, 4),   --dessert_>ile flottante

    --menu2
    (2, 5),   --entrée choix 1->carpaciio
    (2, 6),   --entrée choix 2->foie gras
    (2, 7),   --plat->filet veau purée
    (2, 8),   --dessert->dôme chocolat

    --menu3
    (3, 9),   --entrée->tomates cerises
    (3, 10),  --plat->poulet pané
    (3, 11)   --dessert->brochette fruits
ON CONFLICT DO NOTHING;

--association plats allergènes, table plat_allergene)
-id allergènes: gluten=1, crustacés=2, Oeufs=3, poissons=4, arachides=5, soja=6, lait=7, fruits à coque=8, céleri=9, moutarde=10, sulfites=11, mollusques=12
INSERT INTO plat_allergene (plat_id, allergene_id) VALUES

    --pâté-> gluten, oeufs
    (1, 1), (1, 3),

    --soupe->gluten, lait
    (2, 1), (2, 7),

    --filtet->moutarde, sulfites
    (3, 10), (3, 11),

    --île glottante-> oeufs, lait
    (4, 3), (4, 7),

    --carpaccio->mollusques
    (5, 12),

    --foie gras->ulfites
    (6, 11),

    --filet et puréee->ait
    (7, 7),

    --dôme chocolat->eufs, lait, fruits à coque, gluten
    (8, 3), (8, 7), (8, 8), (8, 1),

    --tomates cerises->0 allergène dc pas d'inser° nécessaire

    --poulet pané->lait, gluten
    (10, 7), (10, 1)

    --brochette fruits->0 allergène dc pas d'inser° nécessaire
ON CONFLICT DO NOTHING;
