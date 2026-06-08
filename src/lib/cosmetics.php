<?php
/**
 * Taskly — Kosmetik-Helfer: Posen-Auflösung, Inventar, equipped Outfit.
 */
declare(strict_types=1);

const TANUKI_DIR = '/assets/img/tanuki/';

/** Posen-Map (emotion => /pfad.png) aus dem cosmetics.meta-JSON. */
function cosmetic_poses(?string $metaJson): array
{
    $meta = $metaJson ? json_decode($metaJson, true) : null;
    $poses = $meta['poses'] ?? [];
    $out = [];
    foreach ($poses as $emo => $file) {
        $out[$emo] = TANUKI_DIR . $file;
    }
    return $out;
}

/** Vollständiges Kosmetik-Objekt fürs Frontend. */
function cosmetic_dto(array $c): array
{
    return [
        'id'     => (int) $c['id'],
        'name'   => $c['name'],
        'theme'  => $c['theme'],
        'rarity' => $c['rarity'],
        'slug'   => $c['asset_ref'],
        'poses'  => cosmetic_poses($c['meta'] ?? null),
    ];
}

/** Aktuell getragenes Outfit (oder null = Basis-Tanuki). */
function get_equipped(int $userId): ?array
{
    $st = db()->prepare(
        'SELECT c.* FROM tanuki_profile tp
           JOIN cosmetics c ON c.id = tp.equipped_outfit_id
          WHERE tp.user_id = ?'
    );
    $st->execute([$userId]);
    $c = $st->fetch();
    return $c ? cosmetic_dto($c) : null;
}

/** Sammel-Fortschritt eines Themes: [owned, total]. */
function theme_progress(int $userId, string $theme): array
{
    $st = db()->prepare(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN uc.user_id IS NOT NULL THEN 1 ELSE 0 END) AS owned
           FROM cosmetics c
           LEFT JOIN user_cosmetics uc ON uc.cosmetic_id = c.id AND uc.user_id = ?
          WHERE c.theme = ? AND c.category = 'tanuki_outfit'"
    );
    $st->execute([$userId, $theme]);
    $r = $st->fetch();
    return [(int) $r['owned'], (int) $r['total']];
}

/** Aktuell getragener Rahmen-Variant ('default', 'premium', 'samurai'). */
function get_equipped_frame(int $userId): string
{
    $st = db()->prepare(
        'SELECT c.asset_ref FROM tanuki_profile tp
           JOIN cosmetics c ON c.id = tp.equipped_frame_id
          WHERE tp.user_id = ? AND c.category = \'frame\''
    );
    $st->execute([$userId]);
    return (string) ($st->fetchColumn() ?: 'default');
}

/** Inventar des Users (besessene Outfits inkl. equipped-Flag). */
function get_inventory(int $userId): array
{
    $st = db()->prepare(
        "SELECT c.*, uc.equipped
           FROM user_cosmetics uc
           JOIN cosmetics c ON c.id = uc.cosmetic_id
          WHERE uc.user_id = ? AND c.category = 'tanuki_outfit'
          ORDER BY FIELD(c.rarity,'legendaer','episch','selten','gewoehnlich'), c.name"
    );
    $st->execute([$userId]);
    $items = [];
    foreach ($st->fetchAll() as $c) {
        $dto = cosmetic_dto($c);
        $dto['equipped'] = (bool) $c['equipped'];
        $items[] = $dto;
    }
    return $items;
}
