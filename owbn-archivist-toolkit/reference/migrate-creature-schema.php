<?php
/**
 * Phase 2 Migration: Creature Schema Restructure.
 *
 * Adds creature_genre + creature_variant columns, populates them,
 * backfills creature_sub_type for non-vampires, extracts variants
 * from parentheticals, and strips prefixes from creature_type.
 *
 * Re-runnable and idempotent. Run manually, verify, then deploy.
 *
 * Usage:
 *   DRY_RUN=1 wp eval-file /tmp/migrate-creature-schema.php --url=https://archivist.owbn.net
 *   wp eval-file /tmp/migrate-creature-schema.php --url=https://archivist.owbn.net
 *
 * Steps:
 *   1. Add creature_genre and creature_variant columns (if missing)
 *   2. Populate creature_genre from _genre_map
 *   3. Populate creature_sub_type for non-vampire types (faction backfill)
 *   4. Extract variants from parentheticals/suffixes into creature_variant
 *   5. Rename creature_type — strip prefixes (Tradition:, Kithain -, etc.)
 */

global $wpdb;

$dry_run = in_array( '--dry-run', $GLOBALS['argv'] ?? [], true )
        || getenv( 'DRY_RUN' ) === '1';
$table   = $wpdb->prefix . 'oat_characters';
$now     = time();

// ─── Load taxonomy ────────────────────────────────────────────────────────
$taxonomy_path = __DIR__ . '/creature-type-taxonomy.json';
$taxonomy      = json_decode( file_get_contents( $taxonomy_path ), true );

if ( ! $taxonomy || ! isset( $taxonomy['_genre_map']['map'] ) ) {
	echo "ERROR: Could not load taxonomy from {$taxonomy_path}\n";
	return;
}

$genre_map = $taxonomy['_genre_map']['map'];

// ─── Rule definitions (shared by both dry-run simulation and live run) ────

// Faction rules: creature_type exact match → creature_sub_type value.
$faction_rules = [
	'Garou' => [
		'Black Fury', 'Black Spiral Dancer', 'Bone Gnawer', 'Child of Gaia', 'Croatan',
		'Fianna', 'Get of Fenris', 'Glass Walker', 'Red Talon', 'Shadow Lord',
		'Silent Strider', 'Silver Fang', 'Skin Dancers', 'Stargazer', 'Uktena',
		'Wendigo', 'White Howler', 'Tribe Unknown',
	],
	'Fera' => [
		'Ajaba', 'Ananasi', 'Corax', 'Mokole', 'Nuwisha', 'Ratkin', 'Rokea', 'Camazotz',
		'Boli Zouhisze', 'Changing Breed / Fera',
	],
	'Hengeyokai' => [
		'Hakken', 'Kitsune', 'Kumo', 'Nezumi', 'Same-Bito', 'Tengu', 'Zhong Lung',
	],
	'Independent' => [
		'Independent: Orphan', 'Independent: Nephandi', 'Independent: Marauders',
	],
	'Guilds' => [
		'Artificer', 'Chanteur', 'Ferryman', 'Harbinger', 'Haunter', 'Masquer',
		'Monitor', 'Oracle', 'Pardoner', 'Proctor', 'Puppeteer', 'Sandman',
		'Solicitor', 'Spook', 'Usurer', 'Guild Unknown',
	],
	'Spectres' => [
		'Shadow Essence 1', 'Shadow Essence 2', 'Shadow Essence 3', 'Shadow Essence 4',
		'Shadow Essence 5', 'Shadow Essence 6', 'Shadow Essence 7', 'Shadow Essence 8',
		'Shadow Essence 9', 'Shadow Essence 10', 'Malfean', 'Apepnu (Bane)',
		'Nokhomi (Bitter-Grins)',
	],
	'Hierarchy' => [
		'Hierarchy', 'Wraith', 'Mortwight', 'Mnemoi',
	],
	'Kithain' => [
		'Kinain', 'Changeling - Custom Kith',
	],
];

// Prefix-based faction rules.
$faction_prefix_rules = [
	'Tradition:'     => 'Traditions',
	'Technocracy:'   => 'Technocracy',
	'Craft:'         => 'Crafts',
	'Kithain -'      => 'Kithain',
	'Nunnehi -'      => 'Nunnehi',
	'Thallain -'     => 'Thallain',
	'Bastet -'       => 'Bastet',
	'Gurahl -'       => 'Fera',
	'Kinfolk ('      => 'Kinfolk',
	'Laibon Legacy:' => 'Laibon',
];

// Variant extraction rules: old creature_type => [ new_type, variant, new_sub_type (null = keep) ]
$variant_rules = [
	'Assamite (Warrior)'               => [ 'Assamite',           'Warrior',        null ],
	'Assamite (Sorcerer)'              => [ 'Assamite',           'Sorcerer',       null ],
	'Assamite (Vizier)'                => [ 'Assamite',           'Vizier',         null ],
	'Assamite (Caste Unknown)'         => [ 'Assamite',           'Caste Unknown',  null ],
	'Assamite (Deva Caste)'            => [ 'Assamite',           'Deva Caste',     null ],
	'Assamite (Warrior) AntiTribu'     => [ 'Assamite AntiTribu', 'Warrior',        'Sabbat' ],
	'Assamite (Sorcerer) AntiTribu'    => [ 'Assamite AntiTribu', 'Sorcerer',       'Sabbat' ],
	'Assamite (Vizier) AntiTribu'      => [ 'Assamite AntiTribu', 'Vizier',         'Sabbat' ],
	'Setite (Warrior)'                 => [ 'Setite',             'Warrior',        null ],
	'Setite (Citizen)'                 => [ 'Setite',             'Citizen',        null ],
	'Setite (Priest)'                  => [ 'Setite',             'Priest',         null ],
	'Gangrel (Country)'                => [ 'Gangrel',            'Country',        null ],
	'Gangrel (City)'                   => [ 'Gangrel',            'City',           null ],
	'Gangrel (Ghost Singer)'           => [ 'Gangrel',            'Ghost Singer',   null ],
	'Gangrel (Greek)'                  => [ 'Gangrel',            'Greek',          null ],
	'Gangrel (Country) AntiTribu'      => [ 'Gangrel AntiTribu',  'Country',        'Sabbat' ],
	'Ravnos (Brahmin)'                 => [ 'Ravnos',             'Brahmin',        null ],
	'Kithain - Sidhe (Arcadian)'       => [ 'Sidhe',              'Arcadian',       'Kithain' ],
	'Kithain - Sidhe (Autumn)'         => [ 'Sidhe',              'Autumn',         'Kithain' ],
	'Gurahl - Forest Walker'           => [ 'Gurahl',             'Forest Walker',  'Fera' ],
	'Gurahl - Ice Stalker'             => [ 'Gurahl',             'Ice Stalker',    'Fera' ],
	'Gurahl - Mountain Guardian'       => [ 'Gurahl',             'Mountain Guardian', 'Fera' ],
	'Gurahl - River Keeper'            => [ 'Gurahl',             'River Keeper',   'Fera' ],
	'Shadow Essence 1'                 => [ 'Shadow Essence',     '1',              'Spectres' ],
	'Shadow Essence 2'                 => [ 'Shadow Essence',     '2',              'Spectres' ],
	'Shadow Essence 3'                 => [ 'Shadow Essence',     '3',              'Spectres' ],
	'Shadow Essence 4'                 => [ 'Shadow Essence',     '4',              'Spectres' ],
	'Shadow Essence 5'                 => [ 'Shadow Essence',     '5',              'Spectres' ],
	'Shadow Essence 6'                 => [ 'Shadow Essence',     '6',              'Spectres' ],
	'Shadow Essence 7'                 => [ 'Shadow Essence',     '7',              'Spectres' ],
	'Shadow Essence 8'                 => [ 'Shadow Essence',     '8',              'Spectres' ],
	'Shadow Essence 9'                 => [ 'Shadow Essence',     '9',              'Spectres' ],
	'Shadow Essence 10'                => [ 'Shadow Essence',     '10',             'Spectres' ],
	'Namaru (Devil)'                   => [ 'Namaru',             'Devil',          null ],
	'Asharu (Scourge)'                 => [ 'Asharu',             'Scourge',        null ],
	'Annunaki (Malefactor)'            => [ 'Annunaki',           'Malefactor',     null ],
	'Neberu (Fiends)'                  => [ 'Neberu',             'Fiends',         null ],
	'Lammasu (Defiler)'                => [ 'Lammasu',            'Defiler',        null ],
	'Halaku (Slayer)'                  => [ 'Halaku',             'Slayer',         null ],
	'Rabisu (Devourer)'                => [ 'Rabisu',             'Devourer',       null ],
	'Ghoul (Vampire)'                  => [ 'Ghoul',              '',               null ],
	'Dhampyr (Vampire)'                => [ 'Dhampyr',            '',               null ],
	'Abomination (Multiple)'           => [ 'Abomination',        '',               null ],
	'Sorcerer (Mage)'                  => [ 'Sorcerer',           '',               null ],
	'Psychic (Hunter)'                 => [ 'Psychic',            '',               null ],
	'Risen (Wraith)'                   => [ 'Risen',              '',               null ],
	'Kami (Spirit)'                    => [ 'Kami',               '',               null ],
	'Fomori (Spirit)'                  => [ 'Fomori',             '',               null ],
	'Apepnu (Bane)'                    => [ 'Apepnu',             'Bane',           'Spectres' ],
	'Nokhomi (Bitter-Grins)'           => [ 'Nokhomi',            'Bitter-Grins',   'Spectres' ],
	'Ahadi  - Kucha Ekundu'            => [ 'Ahadi',              'Kucha Ekundu',   'Ahadi' ],
	'Kinfolk (Werewolf)'               => [ 'Kinfolk',            'Werewolf',       'Kinfolk' ],
	'Kinfolk (Bastet)'                 => [ 'Kinfolk',            'Bastet',         'Kinfolk' ],
	'Kinfolk (Gurahl)'                 => [ 'Kinfolk',            'Gurahl',         'Kinfolk' ],
	'Kinfolk (Kitsune)'                => [ 'Kinfolk',            'Kitsune',        'Kinfolk' ],
	'Kinfolk (Mokole)'                 => [ 'Kinfolk',            'Mokole',         'Kinfolk' ],
	'Kinfolk (Rokea)'                  => [ 'Kinfolk',            'Rokea',          'Kinfolk' ],
	'Kinfolk (Zhong Lung)'             => [ 'Kinfolk',            'Zhong Lung',     'Kinfolk' ],
];

// Prefix strip rules.
$prefix_strip_rules = [
	'Tradition: '      => null,
	'Technocracy: '    => null,
	'Craft: '          => null,
	'Independent: '    => null,
	'Kithain - '       => null,
	'Nunnehi - '       => null,
	'Thallain - '      => null,
	'Bastet - '        => null,
	'Laibon Legacy: '  => null,
	'Changeling - '    => null,
];

// ─── Helper: simulate all rules for one creature_type + sub_type ──────────
function simulate_row( $orig_type, $orig_sub, $genre_map, $faction_rules, $faction_prefix_rules, $variant_rules, $prefix_strip_rules ) {
	$genre   = $genre_map[ $orig_type ] ?? 'Other';
	$sub     = $orig_sub;
	$type    = $orig_type;
	$variant = '';

	// Step 3: Faction backfill (only if sub is empty).
	if ( $sub === '' || $sub === null ) {
		foreach ( $faction_rules as $faction => $types ) {
			if ( is_array( $types ) && in_array( $orig_type, $types, true ) ) {
				$sub = $faction;
				break;
			}
		}
		if ( $sub === '' || $sub === null ) {
			foreach ( $faction_prefix_rules as $prefix => $faction ) {
				if ( strpos( $orig_type, $prefix ) === 0 ) {
					$sub = $faction;
					break;
				}
			}
		}
	}

	// Step 4: Variant extraction.
	if ( isset( $variant_rules[ $orig_type ] ) ) {
		list( $new_type, $new_variant, $new_sub ) = $variant_rules[ $orig_type ];
		$type = $new_type;
		if ( $new_variant !== '' ) {
			$variant = $new_variant;
		}
		if ( $new_sub !== null ) {
			$sub = $new_sub;
		}
	}

	// Step 5: Prefix strip.
	if ( ! isset( $variant_rules[ $orig_type ] ) ) {
		foreach ( $prefix_strip_rules as $prefix => $unused ) {
			if ( strpos( $type, $prefix ) === 0 ) {
				$stripped = substr( $type, strlen( $prefix ) );
				if ( $stripped !== '' ) {
					$type = $stripped;
				}
				break;
			}
		}
	}

	return [
		'creature_genre'    => $genre,
		'creature_sub_type' => $sub ?: '',
		'creature_type'     => $type,
		'creature_variant'  => $variant,
	];
}

// ═══════════════════════════════════════════════════════════════════════════
// DRY RUN — simulate everything in-memory, show every row with all fields.
// ═══════════════════════════════════════════════════════════════════════════
if ( $dry_run ) {
	echo "=== DRY RUN — no changes will be made ===\n\n";
	echo "Loaded genre map: " . count( $genre_map ) . " entries\n\n";

	$rows = $wpdb->get_results(
		"SELECT creature_type, creature_sub_type, COUNT(*) as cnt
		 FROM {$table}
		 WHERE creature_type IS NOT NULL AND creature_type != ''
		 GROUP BY creature_type, creature_sub_type
		 ORDER BY creature_type ASC, creature_sub_type ASC"
	);

	$total = 0;
	foreach ( $rows as $row ) {
		$orig_type = $row->creature_type;
		$orig_sub  = $row->creature_sub_type ?? '';
		$cnt       = (int) $row->cnt;
		$total    += $cnt;

		$result = simulate_row( $orig_type, $orig_sub, $genre_map, $faction_rules, $faction_prefix_rules, $variant_rules, $prefix_strip_rules );

		echo "--- {$cnt} rows: {$orig_type}" . ( $orig_sub ? " (current sub: {$orig_sub})" : '' ) . " ---\n";
		echo "  creature_genre    = {$result['creature_genre']}\n";
		echo "  creature_sub_type = " . ( $result['creature_sub_type'] ?: '(empty)' ) . "\n";
		echo "  creature_type     = {$result['creature_type']}\n";
		echo "  creature_variant  = " . ( $result['creature_variant'] ?: '(empty)' ) . "\n";
		echo "\n";
	}

	echo "=== Total: {$total} rows across " . count( $rows ) . " distinct type+sub combinations ===\n";
	echo "=== DRY RUN COMPLETE — no changes were made ===\n";
	return;
}

// ═══════════════════════════════════════════════════════════════════════════
// LIVE RUN — actually modify the database.
// ═══════════════════════════════════════════════════════════════════════════

// ─── Step 1: Schema ───────────────────────────────────────────────────────
echo "=== Step 1: Schema ===\n";

$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );

if ( ! in_array( 'creature_genre', $columns, true ) ) {
	$wpdb->query( "ALTER TABLE {$table} ADD COLUMN creature_genre VARCHAR(50) NOT NULL DEFAULT '' AFTER status" );
	echo "Added creature_genre column\n";
} else {
	echo "creature_genre column already exists\n";
}

if ( ! in_array( 'creature_variant', $columns, true ) ) {
	$wpdb->query( "ALTER TABLE {$table} ADD COLUMN creature_variant VARCHAR(100) NOT NULL DEFAULT '' AFTER creature_sub_type" );
	echo "Added creature_variant column\n";
} else {
	echo "creature_variant column already exists\n";
}

$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'idx_creature_genre'", ARRAY_A );
if ( empty( $indexes ) ) {
	$wpdb->query( "ALTER TABLE {$table} ADD KEY idx_creature_genre (creature_genre)" );
	echo "Added idx_creature_genre index\n";
} else {
	echo "idx_creature_genre index already exists\n";
}

// ─── Step 2: Populate creature_genre ──────────────────────────────────────
echo "\n=== Step 2: Populate creature_genre ===\n";

$distinct_types = $wpdb->get_col( "SELECT DISTINCT creature_type FROM {$table} WHERE creature_type IS NOT NULL AND creature_type != ''" );
$genre_updated = 0;

foreach ( $distinct_types as $ct ) {
	$genre = $genre_map[ $ct ] ?? 'Other';
	$affected = $wpdb->query( $wpdb->prepare(
		"UPDATE {$table} SET creature_genre = %s, updated_at = %d WHERE creature_type = %s AND (creature_genre = '' OR creature_genre IS NULL)",
		$genre, $now, $ct
	) );
	$genre_updated += $affected;
}
echo "Genre populated: {$genre_updated} rows\n";

// ─── Step 3: Faction backfill ─────────────────────────────────────────────
echo "\n=== Step 3: Faction backfill ===\n";

$faction_updated = 0;

foreach ( $faction_rules as $faction => $types ) {
	if ( empty( $types ) ) { continue; }
	$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
	$args = array_merge( [ $faction, $now ], $types );
	$affected = $wpdb->query( $wpdb->prepare(
		"UPDATE {$table} SET creature_sub_type = %s, updated_at = %d WHERE creature_type IN ({$placeholders}) AND (creature_sub_type IS NULL OR creature_sub_type = '')",
		...$args
	) );
	if ( $affected > 0 ) {
		echo "creature_sub_type={$faction}: {$affected} rows\n";
		$faction_updated += $affected;
	}
}

foreach ( $faction_prefix_rules as $prefix => $faction ) {
	$like = $wpdb->esc_like( $prefix ) . '%';
	$affected = $wpdb->query( $wpdb->prepare(
		"UPDATE {$table} SET creature_sub_type = %s, updated_at = %d WHERE creature_type LIKE %s AND (creature_sub_type IS NULL OR creature_sub_type = '')",
		$faction, $now, $like
	) );
	if ( $affected > 0 ) {
		echo "creature_sub_type={$faction} (prefix '{$prefix}'): {$affected} rows\n";
		$faction_updated += $affected;
	}
}
echo "Faction backfill: {$faction_updated} rows\n";

// ─── Step 4: Extract variants ─────────────────────────────────────────────
echo "\n=== Step 4: Extract variants ===\n";

$variant_updated = 0;

foreach ( $variant_rules as $old_type => $rule ) {
	list( $new_type, $variant, $new_sub ) = $rule;

	$count = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$table} WHERE creature_type = %s", $old_type
	) );
	if ( $count == 0 ) { continue; }

	$set_parts = [ 'creature_type = %s', 'updated_at = %d' ];
	$set_args  = [ $new_type, $now ];

	if ( $variant !== '' ) {
		$set_parts[] = 'creature_variant = %s';
		$set_args[]  = $variant;
	}
	if ( $new_sub !== null ) {
		$set_parts[] = 'creature_sub_type = %s';
		$set_args[]  = $new_sub;
	}

	$set_args[] = $old_type;
	$set_sql = implode( ', ', $set_parts );

	$affected = $wpdb->query( $wpdb->prepare(
		"UPDATE {$table} SET {$set_sql} WHERE creature_type = %s",
		...$set_args
	) );
	if ( $affected > 0 ) {
		echo "'{$old_type}' → type={$new_type}" . ( $variant ? ", variant={$variant}" : '' ) . ( $new_sub !== null ? ", sub={$new_sub}" : '' ) . ": {$affected} rows\n";
		$variant_updated += $affected;
	}
}
echo "Variant extraction: {$variant_updated} rows\n";

// ─── Step 5: Strip prefixes ──────────────────────────────────────────────
echo "\n=== Step 5: Strip prefixes ===\n";

$rename_updated = 0;

foreach ( $prefix_strip_rules as $prefix => $unused ) {
	$like = $wpdb->esc_like( $prefix ) . '%';
	$len  = strlen( $prefix );

	$matches = $wpdb->get_results( $wpdb->prepare(
		"SELECT DISTINCT creature_type FROM {$table} WHERE creature_type LIKE %s", $like
	) );

	foreach ( $matches as $row ) {
		$old      = $row->creature_type;
		$new_name = substr( $old, $len );
		if ( empty( $new_name ) ) { continue; }

		$affected = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET creature_type = %s, updated_at = %d WHERE creature_type = %s",
			$new_name, $now, $old
		) );
		if ( $affected > 0 ) {
			echo "'{$old}' → '{$new_name}': {$affected} rows\n";
			$rename_updated += $affected;
		}
	}
}
echo "Prefix stripping: {$rename_updated} rows\n";

// ─── Verification ─────────────────────────────────────────────────────────
echo "\n=== Final state: all rows ===\n";

$results = $wpdb->get_results(
	"SELECT creature_genre, creature_sub_type, creature_type, creature_variant, COUNT(*) as cnt
	 FROM {$table}
	 WHERE creature_type IS NOT NULL AND creature_type != ''
	 GROUP BY creature_genre, creature_sub_type, creature_type, creature_variant
	 ORDER BY creature_genre, creature_sub_type, creature_type, creature_variant"
);

foreach ( $results as $r ) {
	$cnt = $r->cnt;
	echo "--- {$cnt} rows ---\n";
	echo "  creature_genre    = {$r->creature_genre}\n";
	echo "  creature_sub_type = " . ( $r->creature_sub_type ?: '(empty)' ) . "\n";
	echo "  creature_type     = {$r->creature_type}\n";
	echo "  creature_variant  = " . ( $r->creature_variant ?: '(empty)' ) . "\n";
	echo "\n";
}

echo "\nMigration complete.\n";
