# Module Agenda

Ce module gère la prise de rendez-vous en ligne en fonction :
- des types de soins (services) avec durée en minutes
- du nombre maximum de soins par jour pour chaque type
- des créneaux horaires disponibles générés à partir d'une configuration standard (ou manuelle)
- des réservations effectuées par les membres

## Tables

Deux scénarios :

1. Table interne `services` (si elle n'existe pas déjà) – structure complète ci‑dessous.
2. Table métier existante `prestations` réutilisée (colonnes: id, nom, duree, ...). Dans ce cas on ne modifie pas sa structure : une table complémentaire `service_meta` stocke `max_per_day` et `active` si absents.

### Schéma interne recommandé
```
CREATE TABLE services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  duration_minutes INT NOT NULL DEFAULT 30,
  max_per_day INT NOT NULL DEFAULT 5,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table prestations (existant)
```
prestations (
  id INT PRIMARY KEY,
  nom VARCHAR(255) NOT NULL,
  duree VARCHAR(100) NULL,   -- formats acceptés: "1h30", "45min", "60", "20 min"
  description TEXT,
  prix_ttc DECIMAL(10,2) DEFAULT 0.00,
  poids INT DEFAULT 0,
  grid_id INT DEFAULT 0
)
```
Un parseur convertit `duree` → minutes. Heuristiques:
- `1h30` => 90, `1h` => 60, `45min` / `45` => 45
- `20 min` => 20, `65` => 65
Si parsing impossible: valeur par défaut 30.

### Méta (ajoutée seulement si nécessaire)
```
CREATE TABLE IF NOT EXISTS service_meta (
  service_table VARCHAR(64) NOT NULL,
  service_id INT NOT NULL,
  max_per_day INT NOT NULL DEFAULT 8,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (service_table, service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Slots et rendez-vous
```
CREATE TABLE service_slots (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NOT NULL,
  slot_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  capacity INT NOT NULL DEFAULT 1,
  booked_count INT NOT NULL DEFAULT 0,
  status ENUM('open','closed') DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_service_slot (service_id, slot_date, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE appointments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED NOT NULL,
  slot_id INT UNSIGNED NULL,
  start_datetime DATETIME NOT NULL,
  end_datetime DATETIME NOT NULL,
  status ENUM('pending','confirmed','cancelled') DEFAULT 'pending',
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_appt_user (user_id),
  KEY idx_appt_service (service_id),
  KEY idx_appt_slot (slot_id),
  KEY idx_appt_dates (start_datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Les contraintes FK peuvent être ajoutées si la table source est `services`. Si on utilise `prestations`, les FK directes sont omises pour ne pas forcer sa structure; on peut créer une FK manuelle: 
```
ALTER TABLE service_slots ADD CONSTRAINT fk_slot_prestation FOREIGN KEY (service_id) REFERENCES prestations(id) ON DELETE CASCADE;
ALTER TABLE appointments ADD CONSTRAINT fk_appt_prestation FOREIGN KEY (service_id) REFERENCES prestations(id) ON DELETE CASCADE;
```

## Flux simple
1. Admin configure les services (table interne) ou gère la table métier `prestations`.
2. Génération des créneaux pour une période (script générateur manuel initial).
3. Membre choisit un service puis une date -> API renvoie créneaux disponibles.
4. Réservation (pending), on incrémente `booked_count`, si atteint capacity -> statut slot fermé.

## Fichiers
- `install.sql` : création des tables internes (si utilisées)
- `services.php` (désormais dans `admin/`) : gestion + génération créneaux (lecture seule si source = prestations)
- `api_slots.php` : retourne JSON créneaux disponibles
- `reserve.php` : action de réservation
- `mes_rendezvous.php` : liste utilisateur

## Étapes prochaines
- Gestion modèle hebdo d'horaires
- Annulation avec libération de capacité
- Email de confirmation
