<?php
declare(strict_types=1);

/*
 * Chen ER diagram generator.
 *
 * Layout (chen_layout and the chen_place_* helpers) builds plain shape/line arrays and chen_svg*
 * serializes them. Every chen_check_<code> function guards one failure code; the SVG checks parse
 * the serialized SVG (never the layout arrays), so a layout bug cannot hide from its own assertion.
 * Codes: C-FIT C-CANVAS C-OVERLAP C-SIDE C-ORDER C-GROUP C-ENDPOINT C-THROUGH C-CROSS C-LABEL
 * C-PK C-MODEL C-VARIANT C-LINELEN C-DOUBLE C-NOTE.
 */
const CHEN_MARGIN = 24;
const CHEN_MAX_WIDTH = 1500;
const CHEN_ATTR_HEIGHT = 36;
const CHEN_ENTITY_MIN_HEIGHT = 52;
const CHEN_FONT = '&quot;Microsoft JhengHei&quot;, &quot;Noto Sans TC&quot;, &quot;PingFang TC&quot;, sans-serif';
const CHEN_CJK_PATTERN = '/[\x{3000}-\x{303f}\x{3400}-\x{9fff}\x{f900}-\x{faff}\x{ff00}-\x{ffef}]/u';

/* Layout search: start gaps, growth per group conflict, and the attempt cap. */
const CHEN_COLUMN_GAP_START = 370.0;
const CHEN_ROW_GAP_START = 450.0;
const CHEN_GAP_STEP = 20;
const CHEN_MAX_ATTEMPTS = 50;

/* Attribute ellipses: distance from the owner's edge and spacing between siblings. */
const CHEN_ATTR_OFFSET = 34;
const CHEN_ATTR_SPACING = 12;

/* Two entity groups closer than this are a conflict. */
const CHEN_GROUP_MIN_DISTANCE = 24;

/* Total participation: two strokes, each this far from the centre line (so 2 * offset apart). */
const CHEN_DOUBLE_OFFSET = 2.0;

/* Cardinality labels keep this far from each other and from every shape; distances are box to box. */
const CHEN_LABEL_GAP = 6;
const CHEN_LABEL_SHAPE_GAP = 2;

/*
 * Note band for isolated entities: the bottom row moves down by CHEN_NOTE_BAND to make room. The separator
 * rule and the note text take CHEN_NOTE_HEIGHT px (text baseline CHEN_NOTE_TEXT_DROP below the rule, 4 px of
 * descender under it) and are centred in the gap, so the gap needs CHEN_NOTE_BAND px of free space.
 * Measured in Chromium over HTTP (er-system-en-dark, getBoundingClientRect): a 12 px caption occupies
 * baseline - 12.8 to baseline + 3.2, so the check uses baseline - 13 to baseline + 4. Result in er-system:
 * lowest non-isolated shape 732, rule 755, caption 760.2-776.2, highest isolated shape 800.
 */
const CHEN_NOTE_BAND = 44;
const CHEN_NOTE_HEIGHT = 22;
const CHEN_NOTE_TEXT_DROP = 18;

/* ---------------------------------------------------------------------------------------------
 * Basic helpers
 * ------------------------------------------------------------------------------------------- */

function chen_fail(string $code, string $id, string $message): never
{
    dg_fail($code, "$id $message");
}

function chen_escape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function chen_model(): array
{
    $raw = file_get_contents(dg_root() . '/docs/diagrams/conceptual-model.json');
    $json = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
    if (!isset($json['conceptual'])) {
        chen_fail('C-MODEL', 'conceptual-model.json', '缺少 conceptual 區段');
    }
    return $json['conceptual'];
}

function chen_index(array $items): array
{
    $result = [];
    foreach ($items as $item) {
        $result[$item['id']] = $item;
    }
    return $result;
}

function chen_name(array $item, string $locale): string
{
    return (string) $item['names'][$locale];
}

function chen_attribute_name(array $attribute, string $locale): string
{
    return (string) $attribute[$locale === 'zh' ? 1 : 2];
}

function chen_palette(string $theme): array
{
    if ($theme === 'dark') {
        return [
            'bg' => '#151b23',
            'fg' => '#e6edf3',
            'line' => '#9ab6d3',
            'entity' => '#263140',
            'relationship' => '#4a3f2a',
            'attribute' => '#1b2734',
        ];
    }
    return [
        'bg' => '#ffffff',
        'fg' => '#1c2530',
        'line' => '#41566e',
        'entity' => '#eef2f6',
        'relationship' => '#fff7df',
        'attribute' => '#ffffff',
    ];
}

/* Width that fits the wider of the zh/en label plus padding. */
function chen_width(string $zh, string $en, float $fontSize, float $padding): float
{
    $widest = max(dg_independent_text_width($zh, $fontSize), dg_independent_text_width($en, $fontSize));
    return ceil($widest + $padding);
}

function chen_attribute_width(array $attribute): float
{
    return chen_width(chen_attribute_name($attribute, 'zh'), chen_attribute_name($attribute, 'en'), 13, 24);
}

function chen_right(array $box): float
{
    return $box['x'] + $box['width'];
}

function chen_bottom(array $box): float
{
    return $box['y'] + $box['height'];
}

/* ---------------------------------------------------------------------------------------------
 * Shape and line primitives
 * ------------------------------------------------------------------------------------------- */

function chen_box(array $shape): array
{
    return dg_bounds(
        $shape['x'] - $shape['w'] / 2,
        $shape['y'] - $shape['h'] / 2,
        $shape['w'],
        $shape['h']
    );
}

/* Point on the shape's outline in the direction of $toward. */
function chen_boundary(array $shape, array $toward): array
{
    return dg_shape_boundary_point(
        $shape['kind'],
        ['x' => $shape['x'], 'y' => $shape['y']],
        $shape['w'] / 2,
        $shape['h'] / 2,
        $toward
    );
}

function chen_line(array $from, array $to, string $id, string $owner, string $target, array $extra = []): array
{
    return array_merge([
        'id' => $id,
        'owner' => $owner,
        'target' => $target,
        'x1' => $from['x'],
        'y1' => $from['y'],
        'x2' => $to['x'],
        'y2' => $to['y'],
    ], $extra);
}

function chen_line_start(array $line): array
{
    return ['x' => $line['x1'], 'y' => $line['y1']];
}

function chen_line_end(array $line): array
{
    return ['x' => $line['x2'], 'y' => $line['y2']];
}

/* Gap between two boxes: 0 when they touch or overlap, otherwise the shortest distance between them. */
function chen_box_distance(array $a, array $b): float
{
    $x = max(0, max($a['x'] - chen_right($b), $b['x'] - chen_right($a)));
    $y = max(0, max($a['y'] - chen_bottom($b), $b['y'] - chen_bottom($a)));
    return hypot($x, $y);
}

/* Half-planes (a*x + b*y <= limit, relative to the shape centre) whose intersection is the outline. */
function chen_outline_planes(array $shape): array
{
    $rx = $shape['w'] / 2;
    $ry = $shape['h'] / 2;
    if ($shape['kind'] === 'diamond') {
        return [
            [1 / $rx, 1 / $ry, 1.0],
            [1 / $rx, -1 / $ry, 1.0],
            [-1 / $rx, 1 / $ry, 1.0],
            [-1 / $rx, -1 / $ry, 1.0],
        ];
    }
    return [[1.0, 0.0, $rx], [-1.0, 0.0, $rx], [0.0, 1.0, $ry], [0.0, -1.0, $ry]];
}

/*
 * Where a ray leaves a rectangle or diamond. Unlike chen_boundary the origin may be any point inside the
 * shape, which is what an offset stroke needs: it starts 2 px beside the centre, not at the centre.
 */
function chen_ray_exit(array $shape, array $origin, array $direction): array
{
    $startX = $origin['x'] - $shape['x'];
    $startY = $origin['y'] - $shape['y'];
    $distance = INF;
    foreach (chen_outline_planes($shape) as [$a, $b, $limit]) {
        $approach = $a * $direction['x'] + $b * $direction['y'];
        if ($approach > 0) {
            $distance = min($distance, ($limit - $a * $startX - $b * $startY) / $approach);
        }
    }
    return [
        'x' => $origin['x'] + $direction['x'] * $distance,
        'y' => $origin['y'] + $direction['y'] * $distance,
    ];
}

/* ---------------------------------------------------------------------------------------------
 * Entity and attribute placement
 * ------------------------------------------------------------------------------------------- */

function chen_entity_height(array $entity): float
{
    return max(CHEN_ENTITY_MIN_HEIGHT, 8.0 * (count($entity['attributes']) + 1));
}

/* $shift moves the entity down; the bottom row of a diagram with isolated entities makes room for the note band. */
function chen_entity(array $entity, array $spec, float $columnGap, float $rowGap, float $shift = 0.0): array
{
    return [
        'id' => 'entity:' . $entity['id'],
        'kind' => 'rectangle',
        'x' => $spec['grid'][0] * $columnGap,
        'y' => $spec['grid'][1] * $rowGap + $shift,
        'w' => chen_width(chen_name($entity, 'zh'), chen_name($entity, 'en'), 15, 24),
        'h' => chen_entity_height($entity),
        'entity' => $entity,
        'side' => $spec['side'],
        'reference' => !empty($spec['reference']),
    ];
}

function chen_is_row_side(array $owner): bool
{
    return in_array($owner['side'], ['top', 'bottom'], true);
}

/* Attributes of a top/bottom entity sit in one row, left to right in column order. */
function chen_row_attribute_center(array $entity, array $owner, float $width, int $index): array
{
    $widths = array_map('chen_attribute_width', $entity['attributes']);
    $count = count($entity['attributes']);
    $before = array_sum(array_slice($widths, 0, $index)) + CHEN_ATTR_SPACING * $index;
    $total = array_sum($widths) + CHEN_ATTR_SPACING * max(0, $count - 1);
    $direction = $owner['side'] === 'top' ? -1 : 1;
    $x = $owner['x'] - $total / 2 + $before + $width / 2;
    $y = $owner['y'] + $direction * ($owner['h'] / 2 + CHEN_ATTR_OFFSET + CHEN_ATTR_HEIGHT / 2);
    return [$x, $y];
}

/* Attributes of a left/right entity sit in one column, top to bottom in column order. */
function chen_column_attribute_center(array $entity, array $owner, float $width, int $index): array
{
    $count = count($entity['attributes']);
    $direction = $owner['side'] === 'left' ? -1 : 1;
    $total = $count * CHEN_ATTR_HEIGHT + CHEN_ATTR_SPACING * max(0, $count - 1);
    $x = $owner['x'] + $direction * ($owner['w'] / 2 + CHEN_ATTR_OFFSET + $width / 2);
    $y = $owner['y'] - $total / 2 + $index * (CHEN_ATTR_HEIGHT + CHEN_ATTR_SPACING) + CHEN_ATTR_HEIGHT / 2;
    return [$x, $y];
}

function chen_attribute(array $entity, array $attribute, array $owner, int $index): array
{
    $width = chen_attribute_width($attribute);
    [$x, $y] = chen_is_row_side($owner)
        ? chen_row_attribute_center($entity, $owner, $width, $index)
        : chen_column_attribute_center($entity, $owner, $width, $index);
    return [
        'id' => 'attribute:' . $entity['id'] . ':' . $attribute[0],
        'kind' => 'ellipse',
        'x' => $x,
        'y' => $y,
        'w' => $width,
        'h' => CHEN_ATTR_HEIGHT,
        'attribute' => $attribute,
        'owner' => $entity['id'],
    ];
}

/* Fan-out line from a left/right entity edge to the inner edge of a column attribute. */
function chen_column_attribute_ends(array $owner, array $attribute, int $index, int $count): array
{
    $direction = $owner['side'] === 'left' ? -1 : 1;
    $from = [
        'x' => $owner['x'] + $direction * $owner['w'] / 2,
        'y' => $owner['y'] - $owner['h'] / 2 + $owner['h'] * ($index + 1) / ($count + 1),
    ];
    /* MSO Get-ColumnAttributeLine: endpoint must be the inner ellipse edge. */
    $to = ['x' => $attribute['x'] - $direction * $attribute['w'] / 2, 'y' => $attribute['y']];
    return [$from, $to];
}

function chen_attribute_line(array $owner, array $attribute, int $index, int $count): array
{
    if (chen_is_row_side($owner)) {
        $from = chen_boundary($owner, $attribute);
        $to = chen_boundary($attribute, $owner);
    } else {
        [$from, $to] = chen_column_attribute_ends($owner, $attribute, $index, $count);
    }
    return chen_line($from, $to, $owner['id'] . '--' . $attribute['id'], $owner['id'], $attribute['id']);
}

/* ---------------------------------------------------------------------------------------------
 * Entity groups (entity plus its attributes) and the conflict test
 * ------------------------------------------------------------------------------------------- */

function chen_group_boxes(array $shapes, string $entityId): array
{
    $boxes = [];
    foreach ($shapes as $shape) {
        if ($shape['id'] === 'entity:' . $entityId || str_starts_with($shape['id'], 'attribute:' . $entityId . ':')) {
            $boxes[] = chen_box($shape);
        }
    }
    return $boxes;
}

function chen_groups(array $shapes, array $diagram): array
{
    $groups = [];
    foreach ($diagram['entities'] as $spec) {
        $boxes = chen_group_boxes($shapes, $spec['id']);
        $left = min(array_column($boxes, 'x'));
        $top = min(array_column($boxes, 'y'));
        $right = max(array_map('chen_right', $boxes));
        $bottom = max(array_map('chen_bottom', $boxes));
        $groups[$spec['id']] = dg_bounds($left, $top, $right - $left, $bottom - $top);
    }
    return $groups;
}

/* First pair of entity groups that sit closer than CHEN_GROUP_MIN_DISTANCE, or null. */
function chen_group_conflict(array $groups): ?array
{
    $ids = array_keys($groups);
    $count = count($ids);
    for ($i = 0; $i < $count; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            if (chen_box_distance($groups[$ids[$i]], $groups[$ids[$j]]) < CHEN_GROUP_MIN_DISTANCE) {
                return [$ids[$i], $ids[$j]];
            }
        }
    }
    return null;
}

/* ---------------------------------------------------------------------------------------------
 * Relationship placement
 * ------------------------------------------------------------------------------------------- */

function chen_relationship(array $relationship, array $left, array $right): array
{
    $textWidth = chen_width(chen_name($relationship, 'zh'), chen_name($relationship, 'en'), 15, 24);
    return [
        'id' => 'relationship:' . $relationship['id'],
        'kind' => 'diamond',
        'x' => ($left['x'] + $right['x']) / 2,
        'y' => ($left['y'] + $right['y']) / 2,
        'w' => ceil($textWidth / .72),
        'h' => 62,
        'relationship' => $relationship,
    ];
}

/*
 * One line per participant. Total participation draws two strokes: the centre line translated by
 * +/- CHEN_DOUBLE_OFFSET along its normal, so they are parallel and 2 * CHEN_DOUBLE_OFFSET apart.
 * Each stroke ends where its own translated line leaves the entity and the diamond (not where a ray
 * from the centres would), otherwise both strokes would start almost at the same point.
 */
function chen_relationship_lines(array $entity, array $diamond, array $participant): array
{
    $length = hypot($diamond['x'] - $entity['x'], $diamond['y'] - $entity['y']);
    $forward = ['x' => ($diamond['x'] - $entity['x']) / $length, 'y' => ($diamond['y'] - $entity['y']) / $length];
    $backward = ['x' => -$forward['x'], 'y' => -$forward['y']];
    $offsets = $participant['participation'] === 'total' ? [-CHEN_DOUBLE_OFFSET, CHEN_DOUBLE_OFFSET] : [0.0];
    $lines = [];
    foreach ($offsets as $stroke => $offset) {
        $normalX = -$forward['y'] * $offset;
        $normalY = $forward['x'] * $offset;
        $fromEntity = ['x' => $entity['x'] + $normalX, 'y' => $entity['y'] + $normalY];
        $fromDiamond = ['x' => $diamond['x'] + $normalX, 'y' => $diamond['y'] + $normalY];
        $lines[] = chen_line(
            chen_ray_exit($entity, $fromEntity, $forward),
            chen_ray_exit($diamond, $fromDiamond, $backward),
            $diamond['id'] . ':' . $participant['entity'] . ':' . $stroke,
            $entity['id'],
            $diamond['id'],
            ['participant' => $participant, 'stroke' => $stroke]
        );
    }
    return $lines;
}

function chen_relationship_attribute(array $relationship, array $diamond, array $left, array $right): array
{
    $attribute = $relationship['attributes'][0];
    $width = chen_attribute_width($attribute);
    $dx = $right['x'] - $left['x'];
    $dy = $right['y'] - $left['y'];
    $length = hypot($dx, $dy);
    /* MSO normal is (dy/len, -dx/len), keeping Quantity above the Orders attributes. */
    $normalX = $length === 0.0 ? 0.0 : $dy / $length;
    $normalY = $length === 0.0 ? -1.0 : -$dx / $length;
    $offset = max($diamond['w'], $diamond['h']) / 2 + CHEN_ATTR_OFFSET + $width / 2;
    return [
        'id' => 'attribute:' . $relationship['id'] . ':' . $attribute[0],
        'kind' => 'ellipse',
        'x' => $diamond['x'] + $normalX * $offset,
        'y' => $diamond['y'] + $normalY * $offset,
        'w' => $width,
        'h' => CHEN_ATTR_HEIGHT,
        'attribute' => $attribute,
        'owner' => $relationship['id'],
    ];
}

/* Diamond, its participant lines and (optionally) its own attribute for one relationship. */
function chen_place_relationship(array $relationship, array $entityShapes): array
{
    $left = $entityShapes[$relationship['participants'][0]['entity']];
    $right = $entityShapes[$relationship['participants'][1]['entity']];
    $diamond = chen_relationship($relationship, $left, $right);
    $part = ['shapes' => [$diamond], 'attributeLines' => [], 'connectionLines' => []];
    foreach ($relationship['participants'] as $participant) {
        $lines = chen_relationship_lines($entityShapes[$participant['entity']], $diamond, $participant);
        $part['connectionLines'] = array_merge($part['connectionLines'], $lines);
    }
    if (!empty($relationship['attributes'])) {
        $attribute = chen_relationship_attribute($relationship, $diamond, $left, $right);
        $part['shapes'][] = $attribute;
        $part['attributeLines'][] = chen_line(
            chen_boundary($diamond, $attribute),
            chen_boundary($attribute, $diamond),
            $diamond['id'] . '--' . $attribute['id'],
            $diamond['id'],
            $attribute['id']
        );
    }
    return $part;
}

function chen_place_relationships(array $diagram, array $relationships, array $placed): array
{
    $placed['connectionLines'] = [];
    foreach ($diagram['relationships'] as $id) {
        $part = chen_place_relationship($relationships[$id], $placed['entityShapes']);
        $placed['shapes'] = array_merge($placed['shapes'], $part['shapes']);
        $placed['attributeLines'] = array_merge($placed['attributeLines'], $part['attributeLines']);
        $placed['connectionLines'] = array_merge($placed['connectionLines'], $part['connectionLines']);
    }
    return $placed;
}

/* ---------------------------------------------------------------------------------------------
 * Cardinality labels
 * ------------------------------------------------------------------------------------------- */

function chen_label_box(float $x, float $y, string $text): array
{
    $width = dg_independent_text_width($text, 13);
    return dg_bounds($x - $width / 2, $y - 9.5, $width, 19);
}

/* "relationship:BILLS:Customer:1" and "...:0" are the two strokes of one participant: same base. */
function chen_stroke_base(string $lineId): string
{
    return substr($lineId, 0, (int) strrpos($lineId, ':'));
}

/* True when the line segment nearest to $box is a stroke of the participant that owns the label. */
function chen_nearest_line_is_own(array $box, string $lineId, array $lines): bool
{
    $base = chen_stroke_base($lineId);
    $own = INF;
    $others = INF;
    foreach ($lines as $line) {
        $distance = dg_segment_bounds_distance(chen_line_start($line), chen_line_end($line), $box);
        if (chen_stroke_base($line['id']) === $base) {
            $own = min($own, $distance);
        } else {
            $others = min($others, $distance);
        }
    }
    return $own < $others;
}

/*
 * A candidate box is blocked when it is closer than CHEN_LABEL_SHAPE_GAP to a shape, closer than
 * CHEN_LABEL_GAP to another label, touches any line, or sits nearer to another participant's line
 * than to its own (the reader would attach the cardinality to the wrong line).
 */
function chen_label_blocked(array $box, array $line, array $shapes, array $lines, array $labels): bool
{
    foreach ($shapes as $shape) {
        if (chen_box_distance($box, chen_box($shape)) < CHEN_LABEL_SHAPE_GAP) {
            return true;
        }
    }
    foreach ($labels as $label) {
        if (chen_box_distance($box, $label['box']) < CHEN_LABEL_GAP) {
            return true;
        }
    }
    foreach ($lines as $other) {
        if (dg_segment_bounds_distance(chen_line_start($other), chen_line_end($other), $box) < .75) {
            return true;
        }
    }
    return !chen_nearest_line_is_own($box, $line['id'], $lines);
}

/* Candidate label centres along the owner end of a line, nearest first, both sides of the line. */
function chen_label_candidates(array $line): array
{
    $dx = $line['x2'] - $line['x1'];
    $dy = $line['y2'] - $line['y1'];
    $length = hypot($dx, $dy);
    $unitX = $dx / $length;
    $unitY = $dy / $length;
    $normalX = abs($dx) < .01 ? 1.0 : -$unitY;
    $normalY = abs($dx) < .01 ? 0.0 : $unitX;
    $candidates = [];
    foreach ([14, 26, 38, 50, 62] as $along) {
        foreach ([1, -1] as $side) {
            $candidates[] = [
                $line['x1'] + $unitX * $along + $normalX * 15 * $side,
                $line['y1'] + $unitY * $along + $normalY * 15 * $side,
            ];
        }
    }
    return $candidates;
}

/* First unblocked candidate position as a label, or null when every candidate collides. */
function chen_place_label(array $line, array $shapes, array $lines, array $labels): ?array
{
    $value = (string) $line['participant']['cardinality'];
    foreach (chen_label_candidates($line) as [$x, $y]) {
        $box = chen_label_box($x, $y, $value);
        if (!chen_label_blocked($box, $line, $shapes, $lines, $labels)) {
            return ['line' => $line['id'], 'value' => $value, 'x' => $x, 'y' => $y + 5, 'box' => $box];
        }
    }
    return null;
}

function chen_check_label_candidates(?array $label, string $lineId): array
{
    if ($label === null) {
        chen_fail('C-LABEL', $lineId, '基數標籤候選位置全部衝突');
    }
    return $label;
}

/* Only the first stroke of each participant line carries a cardinality label. */
function chen_labels(array $lines, array $shapes): array
{
    $labels = [];
    foreach ($lines as $line) {
        if (($line['stroke'] ?? -1) !== 0) {
            continue;
        }
        $labels[] = chen_check_label_candidates(chen_place_label($line, $shapes, $lines, $labels), $line['id']);
    }
    return $labels;
}

/* ---------------------------------------------------------------------------------------------
 * Layout search
 * ------------------------------------------------------------------------------------------- */

/* Smallest bounding rectangle of shapes, lines and labels as [left, top, right, bottom]. */
function chen_extents(array $shapes, array $lines, array $labels): array
{
    $boxes = array_map('chen_box', $shapes);
    foreach ($lines as $line) {
        $boxes[] = dg_bounds(
            min($line['x1'], $line['x2']),
            min($line['y1'], $line['y2']),
            abs($line['x1'] - $line['x2']) + 1.5,
            abs($line['y1'] - $line['y2']) + 1.5
        );
    }
    foreach ($labels as $label) {
        $boxes[] = $label['box'];
    }
    return [
        min(array_column($boxes, 'x')),
        min(array_column($boxes, 'y')),
        max(array_map('chen_right', $boxes)),
        max(array_map('chen_bottom', $boxes)),
    ];
}

function chen_translate(array &$items, float $x, float $y): void
{
    foreach ($items as &$item) {
        foreach (['x', 'x1', 'x2'] as $field) {
            if (isset($item[$field])) {
                $item[$field] += $x;
            }
        }
        foreach (['y', 'y1', 'y2'] as $field) {
            if (isset($item[$field])) {
                $item[$field] += $y;
            }
        }
        if (isset($item['box'])) {
            $item['box']['x'] += $x;
            $item['box']['y'] += $y;
        }
    }
    unset($item);
}

/* Attribute ellipses and their fan lines for one entity: [shapes, lines]. */
function chen_place_entity_attributes(array $owner): array
{
    $shapes = [];
    $lines = [];
    $attributes = $owner['entity']['attributes'];
    foreach ($attributes as $index => $attribute) {
        $shape = chen_attribute($owner['entity'], $attribute, $owner, $index);
        $shapes[] = $shape;
        $lines[] = chen_attribute_line($owner, $shape, $index, count($attributes));
    }
    return [$shapes, $lines];
}

/*
 * Entity rectangles in grid order; every non-reference entity also gets its attributes and fan lines.
 * Rows from $noteRow down (the isolated entities' row, or null) move down by CHEN_NOTE_BAND, so the group
 * conflict test below already sees the reserved band.
 */
function chen_place_entities(
    array $diagram,
    array $entities,
    float $columnGap,
    float $rowGap,
    ?int $noteRow
): array {
    $placed = ['shapes' => [], 'entityShapes' => [], 'attributeLines' => []];
    foreach ($diagram['entities'] as $spec) {
        $shift = $noteRow !== null && $spec['grid'][1] >= $noteRow ? CHEN_NOTE_BAND : 0;
        $owner = chen_entity($entities[$spec['id']], $spec, $columnGap, $rowGap, $shift);
        $placed['shapes'][] = $owner;
        $placed['entityShapes'][$spec['id']] = $owner;
        if ($owner['reference']) {
            continue;
        }
        [$shapes, $lines] = chen_place_entity_attributes($owner);
        $placed['shapes'] = array_merge($placed['shapes'], $shapes);
        $placed['attributeLines'] = array_merge($placed['attributeLines'], $lines);
    }
    return $placed;
}

/* Grow the column gap and/or row gap, depending on which axis the conflicting entities differ on. */
function chen_widen_gaps(array $entityShapes, array $conflict, float $columnGap, float $rowGap): array
{
    $first = $entityShapes[$conflict[0]];
    $second = $entityShapes[$conflict[1]];
    if ($first['x'] !== $second['x']) {
        $columnGap += CHEN_GAP_STEP;
    }
    if ($first['y'] !== $second['y']) {
        $rowGap += CHEN_GAP_STEP;
    }
    return [$columnGap, $rowGap];
}

function chen_check_group(?array $conflict, string $key): void
{
    if ($conflict !== null) {
        chen_fail('C-GROUP', $key, '50 次調整後仍有群組衝突');
    }
}

/* ---------------------------------------------------------------------------------------------
 * Isolated entities and their note band
 * ------------------------------------------------------------------------------------------- */

/* Entities of the diagram that take part in none of the diagram's relationships (derived, never listed). */
function chen_isolated_ids(array $diagram, array $relationships): array
{
    $connected = [];
    foreach ($diagram['relationships'] as $id) {
        foreach ($relationships[$id]['participants'] as $participant) {
            $connected[$participant['entity']] = true;
        }
    }
    $isolated = [];
    foreach ($diagram['entities'] as $spec) {
        if (!isset($connected[$spec['id']])) {
            $isolated[] = $spec['id'];
        }
    }
    return $isolated;
}

/* Grid row of the isolated entities, or null when there are none. They must sit alone in the bottom row. */
function chen_isolated_row(array $diagram, array $isolated, string $key): ?int
{
    if ($isolated === []) {
        return null;
    }
    $isolatedRows = [];
    $otherRows = [];
    foreach ($diagram['entities'] as $spec) {
        if (in_array($spec['id'], $isolated, true)) {
            $isolatedRows[] = $spec['grid'][1];
        } else {
            $otherRows[] = $spec['grid'][1];
        }
    }
    $row = min($isolatedRows);
    if (count(array_unique($isolatedRows)) !== 1 || $otherRows === [] || max($otherRows) >= $row) {
        chen_fail('C-NOTE', $key, '孤立實體必須獨佔最下排，說明帶才放得下');
    }
    return $row;
}

/* Shape ids of an isolated entity's group: the entity itself and its attributes. */
function chen_in_isolated_group(string $shapeId, array $isolated): bool
{
    foreach ($isolated as $entityId) {
        if ($shapeId === 'entity:' . $entityId || str_starts_with($shapeId, 'attribute:' . $entityId . ':')) {
            return true;
        }
    }
    return false;
}

/* Fan lines between an isolated entity and its attributes are owned by the entity. */
function chen_owned_by_isolated(array $line, array $isolated): bool
{
    foreach ($isolated as $entityId) {
        if ($line['owner'] === 'entity:' . $entityId) {
            return true;
        }
    }
    return false;
}

/* [lowest point of everything outside the isolated groups, highest point of the isolated groups]. */
function chen_note_gap(array $shapes, array $lines, array $labels, array $isolated): array
{
    $above = -INF;
    $below = INF;
    foreach ($shapes as $shape) {
        $box = chen_box($shape);
        if (chen_in_isolated_group($shape['id'], $isolated)) {
            $below = min($below, $box['y']);
        } else {
            $above = max($above, chen_bottom($box));
        }
    }
    foreach ($lines as $line) {
        if (chen_owned_by_isolated($line, $isolated)) {
            $below = min($below, $line['y1'], $line['y2']);
        } else {
            $above = max($above, $line['y1'], $line['y2']);
        }
    }
    foreach ($labels as $label) {
        $above = max($above, chen_bottom($label['box']));
    }
    return [$above, $below];
}

function chen_check_note_space(float $above, float $below, string $key): void
{
    if (!is_finite($above) || !is_finite($below) || $below - $above < CHEN_NOTE_BAND) {
        $message = sprintf('孤立實體與上方內容之間放不下 %dpx 的說明帶', CHEN_NOTE_BAND);
        chen_fail('C-NOTE', $key, $message);
    }
}

/* Separator rule and note baseline (canvas coordinates), centred in the gap above the isolated entities. */
function chen_place_note_band(array $shapes, array $lines, array $labels, array $isolated, string $key): ?array
{
    if ($isolated === []) {
        return null;
    }
    [$above, $below] = chen_note_gap($shapes, $lines, $labels, $isolated);
    chen_check_note_space($above, $below, $key);
    $rule = (int) round($above + ($below - $above - CHEN_NOTE_HEIGHT) / 2);
    return ['rule' => $rule, 'text' => $rule + CHEN_NOTE_TEXT_DROP];
}

function chen_check_canvas_width(string $key, int $width): void
{
    if ($width > CHEN_MAX_WIDTH) {
        chen_fail('C-CANVAS', $key, "最小寬度 {$width}px 超過 1500px");
    }
}

/* Labels, extents, canvas size, shift everything so the content starts at the margin, then the note band. */
function chen_finish_layout(
    array $diagram,
    string $key,
    array $placed,
    float $columnGap,
    float $rowGap,
    array $isolated
): array {
    $shapes = $placed['shapes'];
    $lines = array_merge($placed['attributeLines'], $placed['connectionLines']);
    $labels = chen_labels($lines, $shapes);
    [$left, $top, $right, $bottom] = chen_extents($shapes, $lines, $labels);
    $width = (int) ceil($right - $left + CHEN_MARGIN * 2);
    $height = (int) ceil($bottom - $top + CHEN_MARGIN * 2 + 56);
    chen_check_canvas_width($key, $width);
    chen_translate($shapes, CHEN_MARGIN - $left, CHEN_MARGIN - $top);
    chen_translate($lines, CHEN_MARGIN - $left, CHEN_MARGIN - $top);
    chen_translate($labels, CHEN_MARGIN - $left, CHEN_MARGIN - $top);
    return [
        'diagram' => $diagram,
        'key' => $key,
        'shapes' => $shapes,
        'lines' => $lines,
        'labels' => $labels,
        'width' => $width,
        'height' => $height,
        'columnGap' => $columnGap,
        'rowGap' => $rowGap,
        'isolated' => $isolated,
        'band' => chen_place_note_band($shapes, $lines, $labels, $isolated, $key),
    ];
}

function chen_layout(array $model, string $key): array
{
    $diagram = $model['diagrams'][$key];
    $entities = chen_index($model['entities']);
    $relationships = chen_index($model['relationships']);
    $isolated = chen_isolated_ids($diagram, $relationships);
    $noteRow = chen_isolated_row($diagram, $isolated, $key);
    /* MSO baseline: never shrink below enough room for a 40px entity-to-diamond segment. */
    $columnGap = CHEN_COLUMN_GAP_START;
    $rowGap = CHEN_ROW_GAP_START;
    $conflict = null;
    $placed = [];
    for ($attempt = 0; $attempt < CHEN_MAX_ATTEMPTS; $attempt++) {
        $placed = chen_place_entities($diagram, $entities, $columnGap, $rowGap, $noteRow);
        $conflict = chen_group_conflict(chen_groups($placed['shapes'], $diagram));
        if ($conflict === null) {
            break;
        }
        [$columnGap, $rowGap] = chen_widen_gaps($placed['entityShapes'], $conflict, $columnGap, $rowGap);
    }
    chen_check_group($conflict, $key);
    $placed = chen_place_relationships($diagram, $relationships, $placed);
    return chen_finish_layout($diagram, $key, $placed, $columnGap, $rowGap, $isolated);
}

/* ---------------------------------------------------------------------------------------------
 * SVG serialization
 * ------------------------------------------------------------------------------------------- */

/* The note stack at the bottom of the canvas (legend, plus the reference-entity note). */
function chen_notes(string $key, string $locale): array
{
    $legend = $locale === 'zh'
        ? '矩形＝實體　菱形＝關聯　橢圓＝屬性　底線＝主鍵　雙線＝全部參與'
        : ('Rectangle = entity; Diamond = relationship; Ellipse = attribute; '
            . 'Underline = primary key; Double line = total participation');
    $notes = [['kind' => 'legend', 'text' => $legend]];
    if ($key === 'fulfillment') {
        $notes[] = ['kind' => 'reference', 'text' => $locale === 'zh'
            ? '虛線框：參照實體，屬性見訂單核心圖'
            : 'Dashed boxes: referenced entities; see the order-core diagram for their attributes'];
    }
    return $notes;
}

/* Caption of the note band. chen_check_note requires it to name every isolated entity, so it cannot drift. */
function chen_isolated_note(string $locale): string
{
    return $locale === 'zh'
        ? '系統帳號與往來對象主檔沒有外鍵，不與其他實體相連'
        : 'User accounts and the party master have no foreign keys';
}

function chen_shape_label(array $shape, string $locale): string
{
    if (isset($shape['entity'])) {
        return chen_name($shape['entity'], $locale);
    }
    if (isset($shape['relationship'])) {
        return chen_name($shape['relationship'], $locale);
    }
    return chen_attribute_name($shape['attribute'], $locale);
}

function chen_shape_fill(string $kind, array $palette): string
{
    return match ($kind) {
        'rectangle' => $palette['entity'],
        'diamond' => $palette['relationship'],
        default => $palette['attribute'],
    };
}

function chen_svg_rect(array $shape, string $fill, array $palette): string
{
    $dashed = !empty($shape['reference']) ? ' stroke-dasharray="6 4"' : '';
    return sprintf(
        '<rect data-element="%s" x="%s" y="%s" width="%s" height="%s" fill="%s" stroke="%s"%s/>',
        chen_escape($shape['id']),
        $shape['x'] - $shape['w'] / 2,
        $shape['y'] - $shape['h'] / 2,
        $shape['w'],
        $shape['h'],
        $fill,
        $palette['line'],
        $dashed
    );
}

function chen_svg_diamond(array $shape, string $fill, array $palette): string
{
    $points = sprintf(
        '%s,%s %s,%s %s,%s %s,%s',
        $shape['x'],
        $shape['y'] - $shape['h'] / 2,
        $shape['x'] + $shape['w'] / 2,
        $shape['y'],
        $shape['x'],
        $shape['y'] + $shape['h'] / 2,
        $shape['x'] - $shape['w'] / 2,
        $shape['y']
    );
    return sprintf(
        '<polygon data-element="%s" points="%s" fill="%s" stroke="%s"/>',
        chen_escape($shape['id']),
        $points,
        $fill,
        $palette['line']
    );
}

function chen_svg_ellipse(array $shape, string $fill, array $palette): string
{
    return sprintf(
        '<ellipse data-element="%s" cx="%s" cy="%s" rx="%s" ry="%s" fill="%s" stroke="%s"/>',
        chen_escape($shape['id']),
        $shape['x'],
        $shape['y'],
        $shape['w'] / 2,
        $shape['h'] / 2,
        $fill,
        $palette['line']
    );
}

/* Entity-name or attribute-name text, underlined when the attribute is its entity's primary key. */
function chen_svg_shape_text(array $shape, string $label, array $entities, array $palette): string
{
    $primaryKey = isset($shape['attribute'], $entities[$shape['owner']])
        && $entities[$shape['owner']]['primaryKey'] === $shape['attribute'][0];
    return sprintf(
        '<text data-text-for="%s" x="%s" y="%s" text-anchor="middle" font-family="%s" font-size="%d" '
        . 'fill="%s"%s>%s</text>',
        chen_escape($shape['id']),
        $shape['x'],
        $shape['y'] + 5,
        CHEN_FONT,
        $shape['kind'] === 'ellipse' ? 13 : 15,
        $palette['fg'],
        $primaryKey ? ' text-decoration="underline"' : '',
        chen_escape($label)
    );
}

/* The drawn element followed by its text element. */
function chen_svg_shape(array $shape, string $locale, array $entities, array $palette): array
{
    $fill = chen_shape_fill($shape['kind'], $palette);
    if ($shape['kind'] === 'rectangle') {
        $svg = chen_svg_rect($shape, $fill, $palette);
    } elseif ($shape['kind'] === 'diamond') {
        $svg = chen_svg_diamond($shape, $fill, $palette);
    } else {
        $svg = chen_svg_ellipse($shape, $fill, $palette);
    }
    return [$svg, chen_svg_shape_text($shape, chen_shape_label($shape, $locale), $entities, $palette)];
}

function chen_svg_header(array $layout, string $locale, array $palette): array
{
    $width = $layout['width'];
    $height = $layout['height'];
    return [
        '<?xml version="1.0" encoding="UTF-8"?>',
        sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" role="img">',
            $width,
            $height,
            $width,
            $height
        ),
        '<title>' . chen_escape($layout['key'] . ' Chen ER') . '</title>',
        '<desc>' . chen_escape($layout['diagram']['caption'][$locale]) . '</desc>',
        sprintf(
            '<rect data-element="canvas" x="0" y="0" width="%d" height="%d" fill="%s"/>',
            $width,
            $height,
            $palette['bg']
        ),
    ];
}

function chen_svg_line(array $line, array $palette): string
{
    return sprintf(
        '<line data-line="%s" data-owner="%s" data-target="%s" x1="%s" y1="%s" x2="%s" y2="%s" '
        . 'stroke="%s" stroke-width="1.5"/>',
        chen_escape($line['id']),
        chen_escape($line['owner']),
        chen_escape($line['target']),
        $line['x1'],
        $line['y1'],
        $line['x2'],
        $line['y2'],
        $palette['line']
    );
}

function chen_svg_cardinality(array $label, array $palette): string
{
    return sprintf(
        '<text data-cardinality-for="%s" x="%s" y="%s" text-anchor="middle" font-family="%s" font-size="13" '
        . 'fill="%s">%s</text>',
        chen_escape($label['line']),
        $label['x'],
        $label['y'],
        CHEN_FONT,
        $palette['fg'],
        chen_escape($label['value'])
    );
}

function chen_svg_note_text(string $kind, int $y, string $text, array $palette): string
{
    return sprintf(
        '<text data-note="%s" x="24" y="%d" font-family="%s" font-size="12" fill="%s">%s</text>',
        $kind,
        $y,
        CHEN_FONT,
        $palette['fg'],
        chen_escape($text)
    );
}

/* Legend (and reference note) stacked at the bottom of the canvas. */
function chen_svg_notes(array $layout, string $locale, array $palette): array
{
    $notes = chen_notes($layout['key'], $locale);
    $noteY = $layout['height'] - 28 * count($notes);
    $out = [];
    foreach ($notes as $note) {
        $out[] = chen_svg_note_text($note['kind'], $noteY, $note['text'], $palette);
        $noteY += 28;
    }
    return $out;
}

/* The isolated-entity note: a rule across the canvas and the caption under it, above the bottom row. */
function chen_svg_band(array $layout, string $locale, array $palette): array
{
    if ($layout['band'] === null) {
        return [];
    }
    return [
        sprintf(
            '<line data-note-separator="true" x1="24" y1="%d" x2="%d" y2="%d" stroke="%s"/>',
            $layout['band']['rule'],
            $layout['width'] - 24,
            $layout['band']['rule'],
            $palette['line']
        ),
        chen_svg_note_text('isolated', $layout['band']['text'], chen_isolated_note($locale), $palette),
    ];
}

function chen_svg(array $layout, string $locale, string $theme, array $model): string
{
    $palette = chen_palette($theme);
    $entities = chen_index($model['entities']);
    $out = chen_svg_header($layout, $locale, $palette);
    foreach ($layout['lines'] as $line) {
        $out[] = chen_svg_line($line, $palette);
    }
    foreach ($layout['shapes'] as $shape) {
        $out = array_merge($out, chen_svg_shape($shape, $locale, $entities, $palette));
    }
    foreach ($layout['labels'] as $label) {
        $out[] = chen_svg_cardinality($label, $palette);
    }
    $out = array_merge($out, chen_svg_notes($layout, $locale, $palette), chen_svg_band($layout, $locale, $palette));
    $out[] = '</svg>';
    return implode("\n", $out) . "\n";
}

/* ---------------------------------------------------------------------------------------------
 * Reading the serialized SVG back
 * ------------------------------------------------------------------------------------------- */

function chen_dom_rect(string $id, DOMElement $node): array
{
    $width = (float) $node->getAttribute('width');
    $height = (float) $node->getAttribute('height');
    return [
        'id' => $id,
        'kind' => 'rectangle',
        'x' => (float) $node->getAttribute('x') + $width / 2,
        'y' => (float) $node->getAttribute('y') + $height / 2,
        'w' => $width,
        'h' => $height,
    ];
}

function chen_dom_ellipse(string $id, DOMElement $node): array
{
    return [
        'id' => $id,
        'kind' => 'ellipse',
        'x' => (float) $node->getAttribute('cx'),
        'y' => (float) $node->getAttribute('cy'),
        'w' => (float) $node->getAttribute('rx') * 2,
        'h' => (float) $node->getAttribute('ry') * 2,
    ];
}

function chen_dom_diamond(string $id, DOMElement $node): array
{
    preg_match_all('/-?[0-9.]+/', $node->getAttribute('points'), $matches);
    $points = array_map('floatval', $matches[0]);
    $xs = [];
    $ys = [];
    for ($i = 0; $i < count($points); $i += 2) {
        $xs[] = $points[$i];
        $ys[] = $points[$i + 1];
    }
    return [
        'id' => $id,
        'kind' => 'diamond',
        'x' => (min($xs) + max($xs)) / 2,
        'y' => (min($ys) + max($ys)) / 2,
        'w' => max($xs) - min($xs),
        'h' => max($ys) - min($ys),
    ];
}

/* Every entity, relationship and attribute shape (not the canvas), keyed by data-element. */
function chen_dom_shapes(DOMDocument $dom): array
{
    $shapes = [];
    foreach ((new DOMXPath($dom))->query('//*[@data-element and @data-element != "canvas"]') as $node) {
        $id = $node->getAttribute('data-element');
        $shapes[$id] = match ($node->localName) {
            'rect' => chen_dom_rect($id, $node),
            'ellipse' => chen_dom_ellipse($id, $node),
            default => chen_dom_diamond($id, $node),
        };
    }
    return $shapes;
}

function chen_dom_lines(DOMDocument $dom): array
{
    $lines = [];
    foreach ((new DOMXPath($dom))->query('//*[local-name()="line" and @data-line]') as $node) {
        $lines[] = [
            'id' => $node->getAttribute('data-line'),
            'owner' => $node->getAttribute('data-owner'),
            'target' => $node->getAttribute('data-target'),
            'x1' => (float) $node->getAttribute('x1'),
            'y1' => (float) $node->getAttribute('y1'),
            'x2' => (float) $node->getAttribute('x2'),
            'y2' => (float) $node->getAttribute('y2'),
        ];
    }
    return $lines;
}

/* Distance in px between a point and the shape's outline, by the outline's own equation. */
function chen_boundary_error(array $shape, float $x, float $y): float
{
    $dx = $x - $shape['x'];
    $dy = $y - $shape['y'];
    if ($shape['kind'] === 'ellipse') {
        $sum = ($dx / ($shape['w'] / 2)) ** 2 + ($dy / ($shape['h'] / 2)) ** 2;
        return abs($sum - 1) * min($shape['w'], $shape['h']) / 2;
    }
    if ($shape['kind'] === 'diamond') {
        $sum = abs($dx) / ($shape['w'] / 2) + abs($dy) / ($shape['h'] / 2);
        return abs($sum - 1) * min($shape['w'], $shape['h']) / 2;
    }
    return min(abs(abs($dx) - $shape['w'] / 2), abs(abs($dy) - $shape['h'] / 2));
}

/* ---------------------------------------------------------------------------------------------
 * Checks: model against the database (C-MODEL, C-ORDER)
 * ------------------------------------------------------------------------------------------- */

/* The schema column that a "Table.column" reference names, or null when the table or column is missing. */
function chen_schema_column(array $schema, string $reference): ?array
{
    [$table, $column] = explode('.', $reference, 2);
    $info = null;
    foreach ($schema['tables'][$table]['columns'] ?? [] as $candidate) {
        if ($candidate['name'] === $column) {
            $info = $candidate;
        }
    }
    return $info;
}

/* One participant's foreign key must exist and agree with the column's NOT NULL / primary-key flags. */
function chen_check_model_foreign_key(array $participant, array $schema): void
{
    $foreignKey = $participant['foreignKey'];
    $table = explode('.', $foreignKey, 2)[0];
    $info = chen_schema_column($schema, $foreignKey);
    if ($info === null) {
        chen_fail('C-MODEL', $foreignKey, 'JSON 外鍵不存在');
    }
    $notNull = (int) $info['notnull'] === 1;
    if ($table === 'Contain') {
        if ($participant['participation'] !== 'partial') {
            chen_fail('C-MODEL', $foreignKey, '資料庫無法保證 M:N 的全部參與');
        }
        if (!$notNull || (int) $info['pk'] === 0) {
            chen_fail('C-MODEL', $foreignKey, 'M:N 外鍵必須 NOT NULL 且在主鍵裡');
        }
    } elseif (($participant['participation'] === 'total') !== $notNull) {
        chen_fail('C-MODEL', $foreignKey, '全部參與與 NOT NULL 不一致');
    }
}

/* Every database foreign key is covered by exactly one relationship end, and vice versa. */
function chen_check_model_coverage(array $covered, array $schema): void
{
    $actual = array_map(
        static fn(array $fk): string => $fk['fromTable'] . '.' . $fk['fromColumn'],
        $schema['foreignKeys']
    );
    sort($covered);
    sort($actual);
    if ($covered !== $actual) {
        chen_fail('C-MODEL', 'foreignKeys', '每條外鍵必須恰被一個關聯端涵蓋');
    }
}

/* Entity attributes are the table's columns in database order, minus the foreign-key columns. */
function chen_check_order_columns(array $entity, string $table, array $definition, array $foreignKeys): void
{
    $ownKeys = array_filter($foreignKeys, static fn(array $item): bool => $item['fromTable'] === $table);
    $skipped = array_flip(array_map(static fn(array $item): string => $item['fromColumn'], $ownKeys));
    $expected = array_values(array_filter(
        array_column($definition['columns'], 'name'),
        static fn(string $name): bool => !isset($skipped[$name])
    ));
    if (array_column($entity['attributes'], 0) !== $expected) {
        chen_fail('C-ORDER', $table, '屬性不是資料庫順序扣外鍵');
    }
}

function chen_check_model_tables(array $entities, array $schema): void
{
    foreach ($schema['tables'] as $table => $definition) {
        if ($table === 'Contain') {
            continue;
        }
        if (!isset($entities[$table])) {
            chen_fail('C-MODEL', $table, '資料表未畫成實體');
        }
        chen_check_order_columns($entities[$table], $table, $definition, $schema['foreignKeys']);
    }
}

function chen_check_model(array $model, array $schema): void
{
    $covered = [];
    foreach ($model['relationships'] as $relationship) {
        foreach ($relationship['participants'] as $participant) {
            if (isset($participant['foreignKey'])) {
                $covered[] = $participant['foreignKey'];
                chen_check_model_foreign_key($participant, $schema);
            }
        }
    }
    chen_check_model_coverage($covered, $schema);
    chen_check_model_tables(chen_index($model['entities']), $schema);
}

/* ---------------------------------------------------------------------------------------------
 * Checks: the serialized SVG
 * ------------------------------------------------------------------------------------------- */

/* C-CANVAS: width within the cap and every shape inside the 24 +/- 1 px margin. */
function chen_check_canvas(array $shapes, string $key, float $width, float $height): void
{
    if ($width > CHEN_MAX_WIDTH) {
        chen_fail('C-CANVAS', $key, '寬度超過 1500px');
    }
    foreach ($shapes as $id => $shape) {
        $box = chen_box($shape);
        $outside = $box['x'] < 23 || $box['y'] < 23
            || chen_right($box) > $width - 23 || chen_bottom($box) > $height - 23;
        if ($outside) {
            chen_fail('C-CANVAS', $id, '圖形未落在畫布留白 24±1 內');
        }
    }
}

function chen_label_space(array $shape): float
{
    return $shape['kind'] === 'diamond' ? $shape['w'] * .72 - 20 : $shape['w'] - 20;
}

/* The data-* attribute that names what a <text> element belongs to (shape, note or cardinality line). */
function chen_text_id(DOMElement $text): string
{
    foreach (['data-text-for', 'data-note', 'data-cardinality-for'] as $name) {
        if ($text->hasAttribute($name)) {
            return $text->getAttribute($name);
        }
    }
    return '';
}

/* C-FIT (whitespace): SVG collapses a run of spaces into one, so no text may contain two ASCII spaces in a row. */
function chen_check_fit_spaces(DOMXPath $xpath): void
{
    foreach ($xpath->query('//*[local-name()="text"]') as $text) {
        if (str_contains($text->textContent, '  ')) {
            chen_fail('C-FIT', chen_text_id($text), '文字含連續空白，SVG 會合併成一個');
        }
    }
}

/* C-FIT: each label fits its shape with 20 px to spare (diamond: 72% of the width); each note fits the canvas. */
function chen_check_fit(DOMXPath $xpath, array $shapes, float $width): void
{
    chen_check_fit_spaces($xpath);
    foreach ($xpath->query('//*[local-name()="text" and @data-text-for]') as $text) {
        $id = $text->getAttribute('data-text-for');
        $shape = $shapes[$id] ?? null;
        $textWidth = dg_independent_text_width($text->textContent, (float) $text->getAttribute('font-size'));
        if ($shape === null || $textWidth > chen_label_space($shape)) {
            chen_fail('C-FIT', $id, '標籤放不進圖形');
        }
    }
    foreach ($xpath->query('//*[local-name()="text" and @data-note]') as $text) {
        if (dg_independent_text_width($text->textContent, 12) > $width - 48) {
            chen_fail('C-FIT', $text->getAttribute('data-note'), '圖例或圖說放不進畫布');
        }
    }
}

/* C-OVERLAP: no two shape bounding boxes intersect. */
function chen_check_overlap(array $shapes): void
{
    $ids = array_keys($shapes);
    $count = count($ids);
    for ($i = 0; $i < $count; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            if (dg_bounds_overlap(chen_box($shapes[$ids[$i]]), chen_box($shapes[$ids[$j]]))) {
                chen_fail('C-OVERLAP', $ids[$i] . '/' . $ids[$j], '圖形外框重疊');
            }
        }
    }
}

/* C-ENDPOINT: both line ends lie on the real outline of their owner and target (0.5 px). */
function chen_check_endpoint(array $line, array $shapes): void
{
    $owner = $shapes[$line['owner']] ?? null;
    $target = $shapes[$line['target']] ?? null;
    if ($owner === null || $target === null
        || chen_boundary_error($owner, $line['x1'], $line['y1']) > .5
        || chen_boundary_error($target, $line['x2'], $line['y2']) > .5) {
        chen_fail('C-ENDPOINT', $line['id'], '線端未落在真正邊框');
    }
}

/* C-THROUGH: a line never passes through any shape other than its owner and target. */
function chen_check_through(array $line, array $shapes): void
{
    foreach ($shapes as $id => $shape) {
        if ($id === $line['owner'] || $id === $line['target']) {
            continue;
        }
        if (dg_segment_intersects_bounds(chen_line_start($line), chen_line_end($line), chen_box($shape))) {
            chen_fail('C-THROUGH', $line['id'], "穿過 $id");
        }
    }
}

/* C-LINELEN: an entity-to-diamond segment is at least 40 px long. */
function chen_check_linelen(array $line): void
{
    $length = hypot($line['x2'] - $line['x1'], $line['y2'] - $line['y1']);
    if (str_starts_with($line['id'], 'relationship:') && $length < 40) {
        chen_fail('C-LINELEN', $line['id'], '實體到菱形的線段短於 40px');
    }
}

function chen_lines_share_shape(array $a, array $b): bool
{
    return $a['owner'] === $b['owner'] || $a['owner'] === $b['target']
        || $a['target'] === $b['owner'] || $a['target'] === $b['target'];
}

/* C-CROSS: two lines that share no shape never cross. */
function chen_check_cross(array $lines): void
{
    $count = count($lines);
    for ($i = 0; $i < $count; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            $a = $lines[$i];
            $b = $lines[$j];
            if (chen_lines_share_shape($a, $b)) {
                continue;
            }
            $crossing = dg_segments_intersect(
                chen_line_start($a),
                chen_line_end($a),
                chen_line_start($b),
                chen_line_end($b)
            );
            if ($crossing) {
                chen_fail('C-CROSS', $a['id'] . '/' . $b['id'], '線段交叉');
            }
        }
    }
}

/* Participant strokes in the SVG, grouped by "relationship:REL:ENTITY" and ordered by stroke index. */
function chen_stroke_groups(array $lines): array
{
    $groups = [];
    foreach ($lines as $line) {
        if (preg_match('/^(relationship:[^:]+:[^:]+):(\d+)$/', $line['id'], $match)) {
            $groups[$match[1]][(int) $match[2]] = $line;
        }
    }
    return array_map(static function (array $strokes): array {
        ksort($strokes);
        return $strokes;
    }, $groups);
}

/* Strokes the model asks for per participant of the diagram's relationships: 2 when total, otherwise 1. */
function chen_expected_strokes(array $diagram, array $model): array
{
    $relationships = chen_index($model['relationships']);
    $expected = [];
    foreach ($diagram['relationships'] as $id) {
        foreach ($relationships[$id]['participants'] as $participant) {
            $expected['relationship:' . $id . ':' . $participant['entity']] =
                $participant['participation'] === 'total' ? 2 : 1;
        }
    }
    return $expected;
}

/* C-DOUBLE (stroke count): every participant has the expected number of strokes, and nothing extra is drawn. */
function chen_check_double_counts(array $groups, array $expected): void
{
    foreach ($expected as $base => $want) {
        $have = count($groups[$base] ?? []);
        if ($have !== $want) {
            chen_fail('C-DOUBLE', $base, "線數為 {$have}，應為 {$want}（全部參與 2、其餘 1）");
        }
    }
    foreach (array_keys($groups) as $base) {
        if (!isset($expected[$base])) {
            chen_fail('C-DOUBLE', $base, '多出模型沒有的關聯線');
        }
    }
}

/* C-DOUBLE (geometry): the two strokes differ in direction by under 0.5 degrees and sit 4 +/- 0.5 px apart. */
function chen_check_double_pair(string $base, array $strokes): void
{
    [$first, $second] = array_values($strokes);
    $ax = $first['x2'] - $first['x1'];
    $ay = $first['y2'] - $first['y1'];
    $bx = $second['x2'] - $second['x1'];
    $by = $second['y2'] - $second['y1'];
    $angle = rad2deg(atan2(abs($ax * $by - $ay * $bx), $ax * $bx + $ay * $by));
    if ($angle >= 0.5) {
        chen_fail('C-DOUBLE', $base, sprintf('兩條線不平行（夾角 %.2f 度）', $angle));
    }
    $gap = abs($ax * ($second['y1'] - $first['y1']) - $ay * ($second['x1'] - $first['x1'])) / hypot($ax, $ay);
    if (abs($gap - 4) > .5) {
        chen_fail('C-DOUBLE', $base, sprintf('兩線間距 %.2fpx，應為 4±0.5px', $gap));
    }
}

/* NOT NULL foreign keys on the N side of the diagram's 1:N relationships, counted from the database. */
function chen_count_not_null_foreign_keys(array $diagram, array $model, array $schema): int
{
    $relationships = chen_index($model['relationships']);
    $count = 0;
    foreach ($diagram['relationships'] as $id) {
        $participants = $relationships[$id]['participants'];
        $cardinalities = array_column($participants, 'cardinality');
        if (!in_array('1', $cardinalities, true) || !in_array('N', $cardinalities, true)) {
            continue;
        }
        foreach ($participants as $participant) {
            if ($participant['cardinality'] !== 'N') {
                continue;
            }
            $column = chen_schema_column($schema, $participant['foreignKey']);
            $count += $column !== null && (int) $column['notnull'] === 1 ? 1 : 0;
        }
    }
    return $count;
}

/*
 * C-DOUBLE: a participant is drawn with two strokes exactly when it is total; each pair is parallel and 4 px
 * apart; and the number of pairs equals the number of NOT NULL foreign keys on the N side of 1:N relationships.
 */
function chen_check_double(array $lines, array $layout, array $model, array $schema): void
{
    $groups = chen_stroke_groups($lines);
    chen_check_double_counts($groups, chen_expected_strokes($layout['diagram'], $model));
    $pairs = 0;
    foreach ($groups as $base => $strokes) {
        if (count($strokes) === 2) {
            chen_check_double_pair($base, $strokes);
            $pairs++;
        }
    }
    $notNull = chen_count_not_null_foreign_keys($layout['diagram'], $model, $schema);
    if ($pairs !== $notNull) {
        $message = "雙線 {$pairs} 組，但 1:N 的 N 端 NOT NULL 外鍵有 {$notNull} 條";
        chen_fail('C-DOUBLE', $layout['key'], $message);
    }
}

/* C-PK: the underlined texts are exactly the primary keys of the non-reference entities. */
function chen_check_pk(DOMXPath $xpath, array $layout, array $model): void
{
    $entities = chen_index($model['entities']);
    $underlined = [];
    foreach ($xpath->query('//*[local-name()="text" and @text-decoration="underline"]') as $text) {
        $underlined[] = $text->getAttribute('data-text-for');
    }
    $expected = [];
    foreach ($layout['diagram']['entities'] as $spec) {
        if (empty($spec['reference'])) {
            $expected[] = 'attribute:' . $spec['id'] . ':' . $entities[$spec['id']]['primaryKey'];
        }
    }
    sort($underlined);
    sort($expected);
    if ($underlined !== $expected) {
        chen_fail('C-PK', $layout['key'], '底線文字不恰為主鍵');
    }
}

/* Cardinality labels read back from the SVG: the id of the line each belongs to, and its text box. */
function chen_dom_labels(DOMXPath $xpath): array
{
    $labels = [];
    foreach ($xpath->query('//*[local-name()="text" and @data-cardinality-for]') as $text) {
        $x = (float) $text->getAttribute('x');
        $y = (float) $text->getAttribute('y') - 5;
        $labels[] = [
            'line' => $text->getAttribute('data-cardinality-for'),
            'box' => chen_label_box($x, $y, $text->textContent),
        ];
    }
    return $labels;
}

/* C-LABEL (a label on its own): at least 2 px from every shape and 0.75 px from every line but its own. */
function chen_check_label_clearance(array $label, array $shapes, array $lines): void
{
    foreach ($shapes as $shape) {
        if (chen_box_distance($label['box'], chen_box($shape)) < 2) {
            chen_fail('C-LABEL', $label['line'], '基數標籤離圖形不足 2px');
        }
    }
    foreach ($lines as $line) {
        if ($line['id'] === $label['line']) {
            continue;
        }
        if (dg_segment_bounds_distance(chen_line_start($line), chen_line_end($line), $label['box']) < .75) {
            chen_fail('C-LABEL', $label['line'], '基數標籤碰到線');
        }
    }
}

/* C-LABEL (association): the line segment nearest to the label is one of the label's own strokes. */
function chen_check_label_owner(array $label, array $lines): void
{
    if (!chen_nearest_line_is_own($label['box'], $label['line'], $lines)) {
        chen_fail('C-LABEL', $label['line'], '基數標籤最近的線不是自己的線');
    }
}

/* C-LABEL (between labels): any two labels are at least 6 px apart, so "N" and "N" never read as "NN". */
function chen_check_label_spacing(array $labels): void
{
    $count = count($labels);
    for ($i = 0; $i < $count; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            if (chen_box_distance($labels[$i]['box'], $labels[$j]['box']) < 6) {
                chen_fail('C-LABEL', $labels[$i]['line'] . '/' . $labels[$j]['line'], '基數標籤彼此不足 6px');
            }
        }
    }
}

/* C-LABEL: shape and line clearance, correct association, and spacing between labels. */
function chen_check_label(DOMXPath $xpath, array $shapes, array $lines): void
{
    $labels = chen_dom_labels($xpath);
    foreach ($labels as $label) {
        chen_check_label_clearance($label, $shapes, $lines);
        chen_check_label_owner($label, $lines);
    }
    chen_check_label_spacing($labels);
}

/* Entities in the SVG that own no participant line (relationship:REL:ENTITY:n), as entity ids. */
function chen_svg_isolated_ids(array $shapes, array $lines): array
{
    $connected = [];
    foreach ($lines as $line) {
        if (str_starts_with($line['id'], 'relationship:') && !str_contains($line['id'], '--')) {
            $connected[$line['owner']] = true;
        }
    }
    $isolated = [];
    foreach (array_keys($shapes) as $id) {
        if (str_starts_with($id, 'entity:') && !isset($connected[$id])) {
            $isolated[] = substr($id, strlen('entity:'));
        }
    }
    return $isolated;
}

/* The note band as drawn: the rule as a segment, and the bounding box of the rule and the caption text. */
function chen_note_band_geometry(DOMXPath $xpath, string $key): array
{
    $rules = $xpath->query('//*[local-name()="line" and @data-note-separator]');
    $texts = $xpath->query('//*[local-name()="text" and @data-note="isolated"]');
    if ($rules->length !== 1 || $texts->length !== 1) {
        chen_fail('C-NOTE', $key, '孤立實體說明必須恰有一條分隔線與一則文字');
    }
    $rule = $rules->item(0);
    $text = $texts->item(0);
    $start = ['x' => (float) $rule->getAttribute('x1'), 'y' => (float) $rule->getAttribute('y1')];
    $end = ['x' => (float) $rule->getAttribute('x2'), 'y' => (float) $rule->getAttribute('y2')];
    $canvas = (float) $xpath->document->documentElement->getAttribute('width');
    if (abs($end['y'] - $start['y']) > .01 || abs($start['x'] - 24) > 1 || abs($end['x'] - ($canvas - 24)) > 1) {
        chen_fail('C-NOTE', $key, '分隔線必須水平並橫跨畫布（x 從 24 到寬度減 24）');
    }
    $baseline = (float) $text->getAttribute('y');
    $textBox = dg_bounds(
        (float) $text->getAttribute('x'),
        $baseline - 13,
        dg_independent_text_width($text->textContent, 12),
        17
    );
    $top = min($start['y'] - 1, $textBox['y']);
    $bottom = max($start['y'] + 1, chen_bottom($textBox));
    $left = min($start['x'], $textBox['x']);
    $right = max($end['x'], chen_right($textBox));
    return [
        'start' => $start,
        'end' => $end,
        'box' => dg_bounds($left, $top, $right - $left, $bottom - $top),
        'text' => $text->textContent,
    ];
}

/* C-NOTE (caption): the note text names every isolated entity, so it cannot go stale when the model changes. */
function chen_check_note_caption(string $caption, array $isolated, array $model, string $locale, string $key): void
{
    $entities = chen_index($model['entities']);
    foreach ($isolated as $id) {
        if (stripos($caption, chen_name($entities[$id], $locale)) === false) {
            chen_fail('C-NOTE', $key, "說明文字沒有提到孤立實體 {$id}");
        }
    }
}

/* C-NOTE (clearance): the band touches no shape, line or cardinality label; the rule crosses no line. */
function chen_check_note_clear(array $band, array $shapes, array $lines, array $labels): void
{
    foreach ($shapes as $id => $shape) {
        if (chen_box_distance($band['box'], chen_box($shape)) < 2) {
            chen_fail('C-NOTE', $id, '說明帶碰到圖形');
        }
    }
    foreach ($lines as $line) {
        if (dg_segment_bounds_distance(chen_line_start($line), chen_line_end($line), $band['box']) < .75) {
            chen_fail('C-NOTE', $line['id'], '說明帶碰到線');
        }
        if (dg_segments_intersect($band['start'], $band['end'], chen_line_start($line), chen_line_end($line))) {
            chen_fail('C-NOTE', $line['id'], '分隔線穿過線段');
        }
    }
    foreach ($labels as $label) {
        if (chen_box_distance($band['box'], $label['box']) < 2) {
            chen_fail('C-NOTE', $label['line'], '說明帶碰到基數標籤');
        }
    }
}

/*
 * C-NOTE: the entities with no relationship line are the ones the model says are isolated; with none, nothing
 * is drawn. Otherwise one rule and one caption sit strictly between the lowest point of everything outside the
 * isolated groups and the highest point of the isolated groups, and touch nothing.
 */
function chen_check_note(
    DOMXPath $xpath,
    array $shapes,
    array $lines,
    array $layout,
    array $model,
    string $locale
): void {
    $key = $layout['key'];
    $isolated = chen_svg_isolated_ids($shapes, $lines);
    $expected = chen_isolated_ids($layout['diagram'], chen_index($model['relationships']));
    sort($isolated);
    sort($expected);
    if ($isolated !== $expected) {
        chen_fail('C-NOTE', $key, '圖上沒有關聯線的實體與模型推得的孤立實體不一致');
    }
    $drawn = $xpath->query('//*[@data-note-separator or @data-note="isolated"]')->length;
    if ($isolated === []) {
        if ($drawn !== 0) {
            chen_fail('C-NOTE', $key, '沒有孤立實體，卻畫了說明帶');
        }
        return;
    }
    $band = chen_note_band_geometry($xpath, $key);
    chen_check_note_caption($band['text'], $isolated, $model, $locale, $key);
    $labels = chen_dom_labels($xpath);
    [$above, $below] = chen_note_gap($shapes, $lines, $labels, $isolated);
    if (!($above < $band['box']['y'] && chen_bottom($band['box']) < $below)) {
        $message = sprintf(
            '說明帶 %.1f 到 %.1f 不在上方內容底部 %.1f 與孤立實體頂部 %.1f 之間',
            $band['box']['y'],
            chen_bottom($band['box']),
            $above,
            $below
        );
        chen_fail('C-NOTE', $key, $message);
    }
    chen_check_note_clear($band, $shapes, $lines, $labels);
}

/* C-VARIANT (per file): every text uses the shared font at 12 px or larger; English files hold no CJK. */
function chen_check_variant(DOMXPath $xpath, string $svg, string $key, string $locale): void
{
    foreach ($xpath->query('//*[local-name()="text"]') as $text) {
        if ($text->getAttribute('font-family') !== html_entity_decode(CHEN_FONT, ENT_QUOTES, 'UTF-8')
            || (float) $text->getAttribute('font-size') < 12) {
            chen_fail('C-VARIANT', $key, '文字字型或字級不符');
        }
    }
    if ($locale === 'en' && preg_match(CHEN_CJK_PATTERN, $svg)) {
        chen_fail('C-VARIANT', $key, '英文 SVG 含 CJK');
    }
}

/* C-VARIANT (across files): light/dark differ only in colour; zh/en draw the same number of shapes. */
function chen_check_variant_set(array $variants): void
{
    foreach (['system', 'orders', 'fulfillment'] as $key) {
        foreach (['zh', 'en'] as $locale) {
            chen_check_variant_colors($key, $locale, $variants["$key-$locale-light"], $variants["$key-$locale-dark"]);
        }
        foreach (['light', 'dark'] as $theme) {
            $zh = count(chen_dom_shapes(dg_dom($variants["$key-zh-$theme"])));
            $en = count(chen_dom_shapes(dg_dom($variants["$key-en-$theme"])));
            if ($zh !== $en) {
                chen_fail('C-VARIANT', $key, "$theme 中英文圖形數不一致");
            }
        }
    }
}

function chen_check_variant_colors(string $key, string $locale, string $light, string $dark): void
{
    if (dg_strip_colors($light) !== dg_strip_colors($dark)) {
        chen_fail('C-VARIANT', $key, "$locale 亮暗去色後不相同");
    }
    foreach (chen_palette('light') as $name => $color) {
        if ($name !== 'bg' && str_contains($dark, $color)) {
            chen_fail('C-VARIANT', $key, "深色版含亮色 $name");
        }
    }
}

/* ---------------------------------------------------------------------------------------------
 * Checks: attribute placement per entity (C-SIDE, C-ORDER)
 * ------------------------------------------------------------------------------------------- */

/* Per non-reference entity: its spec, its rectangle and its attribute ellipses in database order. */
function chen_attribute_entries(array $layout, array $model, array $shapes): array
{
    $entities = chen_index($model['entities']);
    $entries = [];
    foreach ($layout['diagram']['entities'] as $spec) {
        if (!empty($spec['reference'])) {
            continue;
        }
        $attributes = [];
        foreach ($entities[$spec['id']]['attributes'] as $attribute) {
            $attributes[] = $shapes['attribute:' . $spec['id'] . ':' . $attribute[0]];
        }
        $entries[] = [
            'spec' => $spec,
            'entity' => $shapes['entity:' . $spec['id']],
            'attributes' => $attributes,
        ];
    }
    return $entries;
}

/* C-SIDE: attribute edge 28-40 px from the entity edge; fan connection points at least 8 px apart. */
function chen_check_side(array $entry): void
{
    $entity = $entry['entity'];
    $horizontal = in_array($entry['spec']['side'], ['left', 'right'], true);
    foreach ($entry['attributes'] as $shape) {
        $gap = $horizontal
            ? abs($shape['x'] - $entity['x']) - ($shape['w'] + $entity['w']) / 2
            : abs($shape['y'] - $entity['y']) - ($shape['h'] + $entity['h']) / 2;
        if ($gap < 28 || $gap > 40) {
            chen_fail('C-SIDE', $shape['id'], '屬性離實體不在 28～40px');
        }
    }
    if ($horizontal && $entity['h'] / (count($entry['attributes']) + 1) < 8) {
        chen_fail('C-SIDE', $entry['spec']['id'], '扇形接點間距小於 8px');
    }
}

/* C-ORDER (SVG): attributes run strictly down (left/right) or across (top/bottom) in database order. */
function chen_check_order(array $entry): void
{
    $horizontal = in_array($entry['spec']['side'], ['left', 'right'], true);
    $previous = -INF;
    foreach ($entry['attributes'] as $shape) {
        $position = $horizontal ? $shape['y'] : $shape['x'];
        if ($position <= $previous) {
            chen_fail('C-ORDER', $entry['spec']['id'], 'SVG 屬性順序不是資料庫欄位順序');
        }
        $previous = $position;
    }
}

/* ---------------------------------------------------------------------------------------------
 * Orchestration
 * ------------------------------------------------------------------------------------------- */

function chen_assert_svg(string $svg, array $layout, array $model, array $schema, string $locale): void
{
    $dom = dg_dom($svg);
    $xpath = new DOMXPath($dom);
    $shapes = chen_dom_shapes($dom);
    $lines = chen_dom_lines($dom);
    $width = (float) $dom->documentElement->getAttribute('width');
    $height = (float) $dom->documentElement->getAttribute('height');
    chen_check_canvas($shapes, $layout['key'], $width, $height);
    chen_check_fit($xpath, $shapes, $width);
    chen_check_overlap($shapes);
    foreach ($lines as $line) {
        chen_check_endpoint($line, $shapes);
        chen_check_through($line, $shapes);
        chen_check_linelen($line);
    }
    chen_check_cross($lines);
    chen_check_double($lines, $layout, $model, $schema);
    chen_check_pk($xpath, $layout, $model);
    chen_check_label($xpath, $shapes, $lines);
    chen_check_note($xpath, $shapes, $lines, $layout, $model, $locale);
    chen_check_variant($xpath, $svg, $layout['key'], $locale);
    foreach (chen_attribute_entries($layout, $model, $shapes) as $entry) {
        chen_check_side($entry);
        chen_check_order($entry);
    }
}

/* Returns the 12 er-*.svg files keyed by file name; any failed check throws before anything is returned. */
function chen_generate(array $model, array $schema): array
{
    chen_check_model($model, $schema);
    $result = [];
    $sets = [];
    foreach (['system', 'orders', 'fulfillment'] as $key) {
        $layout = chen_layout($model, $key);
        foreach (['zh', 'en'] as $locale) {
            foreach (['light', 'dark'] as $theme) {
                $svg = chen_svg($layout, $locale, $theme, $model);
                chen_assert_svg($svg, $layout, $model, $schema, $locale);
                $sets["$key-$locale-$theme"] = $svg;
                $result['er-' . $key . '-' . $locale . '-' . $theme . '.svg'] = $svg;
            }
        }
    }
    chen_check_variant_set($sets);
    return $result;
}
