<?php
declare(strict_types=1);

/*
 * Chen ER diagram generator.
 *
 * Layout (chen_layout and the chen_place_* helpers) builds plain shape/line arrays and chen_svg*
 * serializes them. Every chen_check_<code> function guards one failure code; the SVG checks parse
 * the serialized SVG (never the layout arrays), so a layout bug cannot hide from its own assertion.
 * Codes: C-FIT C-CANVAS C-OVERLAP C-SIDE C-ORDER C-GROUP C-ENDPOINT C-THROUGH C-CROSS C-LABEL
 * C-PK C-MODEL C-VARIANT C-LINELEN.
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

/* ---------------------------------------------------------------------------------------------
 * Entity and attribute placement
 * ------------------------------------------------------------------------------------------- */

function chen_entity_height(array $entity): float
{
    return max(CHEN_ENTITY_MIN_HEIGHT, 8.0 * (count($entity['attributes']) + 1));
}

function chen_entity(array $entity, array $spec, float $columnGap, float $rowGap): array
{
    return [
        'id' => 'entity:' . $entity['id'],
        'kind' => 'rectangle',
        'x' => $spec['grid'][0] * $columnGap,
        'y' => $spec['grid'][1] * $rowGap,
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

function chen_group_distance(array $a, array $b): float
{
    $x = max(0, max($a['x'] - chen_right($b), $b['x'] - chen_right($a)));
    $y = max(0, max($a['y'] - chen_bottom($b), $b['y'] - chen_bottom($a)));
    return hypot($x, $y);
}

/* First pair of entity groups that sit closer than CHEN_GROUP_MIN_DISTANCE, or null. */
function chen_group_conflict(array $groups): ?array
{
    $ids = array_keys($groups);
    $count = count($ids);
    for ($i = 0; $i < $count; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            if (chen_group_distance($groups[$ids[$i]], $groups[$ids[$j]]) < CHEN_GROUP_MIN_DISTANCE) {
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

/* One line per participant; total participation draws two parallel strokes. */
function chen_relationship_lines(array $entity, array $diamond, array $participant): array
{
    $dx = $diamond['x'] - $entity['x'];
    $dy = $diamond['y'] - $entity['y'];
    $length = hypot($dx, $dy);
    $offsets = $participant['participation'] === 'total' ? [-2.0, 2.0] : [0.0];
    $lines = [];
    foreach ($offsets as $stroke => $offset) {
        $normalX = $length === 0.0 ? 0.0 : -$dy / $length * $offset;
        $normalY = $length === 0.0 ? 0.0 : $dx / $length * $offset;
        $origin = ['x' => $entity['x'] + $normalX, 'y' => $entity['y'] + $normalY];
        $target = ['x' => $diamond['x'] + $normalX, 'y' => $diamond['y'] + $normalY];
        $lines[] = chen_line(
            chen_boundary($entity, $target),
            chen_boundary($diamond, $origin),
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

function chen_label_blocked(array $box, array $shapes, array $lines, array $labels): bool
{
    foreach ($shapes as $shape) {
        if (dg_bounds_overlap($box, chen_box($shape))) {
            return true;
        }
    }
    foreach ($lines as $line) {
        if (dg_segment_bounds_distance(chen_line_start($line), chen_line_end($line), $box) < .75) {
            return true;
        }
    }
    foreach ($labels as $label) {
        if (dg_bounds_overlap($box, $label['box'])) {
            return true;
        }
    }
    return false;
}

/* Candidate label centres near the owner end of a line, nearest first, both sides of the line. */
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
    foreach ([14, 26, 38] as $along) {
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
        if (!chen_label_blocked($box, $shapes, $lines, $labels)) {
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

/* Entity rectangles in grid order; every non-reference entity also gets its attributes and fan lines. */
function chen_place_entities(array $diagram, array $entities, float $columnGap, float $rowGap): array
{
    $placed = ['shapes' => [], 'entityShapes' => [], 'attributeLines' => []];
    foreach ($diagram['entities'] as $spec) {
        $owner = chen_entity($entities[$spec['id']], $spec, $columnGap, $rowGap);
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

function chen_check_canvas_width(string $key, int $width): void
{
    if ($width > CHEN_MAX_WIDTH) {
        chen_fail('C-CANVAS', $key, "最小寬度 {$width}px 超過 1500px");
    }
}

/* Labels, extents, canvas size, then shift everything so the content starts at the margin. */
function chen_finish_layout(array $diagram, string $key, array $placed, float $columnGap, float $rowGap): array
{
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
    ];
}

function chen_layout(array $model, string $key): array
{
    $diagram = $model['diagrams'][$key];
    $entities = chen_index($model['entities']);
    $relationships = chen_index($model['relationships']);
    /* MSO baseline: never shrink below enough room for a 40px entity-to-diamond segment. */
    $columnGap = CHEN_COLUMN_GAP_START;
    $rowGap = CHEN_ROW_GAP_START;
    $conflict = null;
    $placed = [];
    for ($attempt = 0; $attempt < CHEN_MAX_ATTEMPTS; $attempt++) {
        $placed = chen_place_entities($diagram, $entities, $columnGap, $rowGap);
        $conflict = chen_group_conflict(chen_groups($placed['shapes'], $diagram));
        if ($conflict === null) {
            break;
        }
        [$columnGap, $rowGap] = chen_widen_gaps($placed['entityShapes'], $conflict, $columnGap, $rowGap);
    }
    chen_check_group($conflict, $key);
    $placed = chen_place_relationships($diagram, $relationships, $placed);
    return chen_finish_layout($diagram, $key, $placed, $columnGap, $rowGap);
}

/* ---------------------------------------------------------------------------------------------
 * SVG serialization
 * ------------------------------------------------------------------------------------------- */

function chen_notes(string $key, string $locale): array
{
    $legend = $locale === 'zh'
        ? '矩形＝實體　菱形＝關聯　橢圓＝屬性　底線＝主鍵　雙線＝全部參與'
        : ('Rectangle = entity   Diamond = relationship   Ellipse = attribute   '
            . 'Underline = primary key   Double line = total participation');
    $notes = [['kind' => 'legend', 'text' => $legend]];
    if ($key === 'system') {
        $notes[] = ['kind' => 'isolated', 'text' => $locale === 'zh'
            ? '系統帳號與往來對象主檔沒有外鍵，不與其他實體相連'
            : 'User accounts and the party master have no foreign keys'];
    }
    if ($key === 'fulfillment') {
        $notes[] = ['kind' => 'reference', 'text' => $locale === 'zh'
            ? '虛線框：參照實體，屬性見訂單核心圖'
            : 'Dashed boxes: referenced entities; see the order-core diagram for their attributes'];
    }
    return $notes;
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

/* Legend and notes stacked at the bottom; the isolated-entity note gets a separator rule. */
function chen_svg_notes(array $layout, string $locale, array $palette): array
{
    $notes = chen_notes($layout['key'], $locale);
    $noteY = $layout['height'] - 28 * count($notes);
    $out = [];
    foreach ($notes as $note) {
        if ($note['kind'] === 'isolated') {
            $out[] = sprintf(
                '<line data-note-separator="true" x1="24" y1="%d" x2="%d" y2="%d" stroke="%s"/>',
                $noteY - 8,
                $layout['width'] - 24,
                $noteY - 8,
                $palette['line']
            );
        }
        $out[] = sprintf(
            '<text data-note="%s" x="24" y="%d" font-family="%s" font-size="12" fill="%s">%s</text>',
            $note['kind'],
            $noteY,
            CHEN_FONT,
            $palette['fg'],
            chen_escape($note['text'])
        );
        $noteY += 28;
    }
    return $out;
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
    $out = array_merge($out, chen_svg_notes($layout, $locale, $palette));
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

/* One participant's foreign key must exist and agree with the column's NOT NULL / primary-key flags. */
function chen_check_model_foreign_key(array $participant, array $schema): void
{
    $foreignKey = $participant['foreignKey'];
    [$table, $column] = explode('.', $foreignKey, 2);
    $info = null;
    foreach ($schema['tables'][$table]['columns'] ?? [] as $candidate) {
        if ($candidate['name'] === $column) {
            $info = $candidate;
        }
    }
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

/* C-FIT: each label fits its shape with 20 px to spare (diamond: 72% of the width); each note fits the canvas. */
function chen_check_fit(DOMXPath $xpath, array $shapes, float $width): void
{
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

/* C-LABEL: each cardinality text clears every shape and every other line (0.75 px). */
function chen_check_label(DOMXPath $xpath, array $shapes, array $lines): void
{
    foreach ($xpath->query('//*[local-name()="text" and @data-cardinality-for]') as $text) {
        $lineId = $text->getAttribute('data-cardinality-for');
        $x = (float) $text->getAttribute('x');
        $y = (float) $text->getAttribute('y') - 5;
        $box = chen_label_box($x, $y, $text->textContent);
        foreach ($shapes as $shape) {
            if (dg_bounds_overlap($box, chen_box($shape))) {
                chen_fail('C-LABEL', $lineId, '基數標籤碰到圖形');
            }
        }
        foreach ($lines as $line) {
            if ($line['id'] === $lineId) {
                continue;
            }
            if (dg_segment_bounds_distance(chen_line_start($line), chen_line_end($line), $box) < .75) {
                chen_fail('C-LABEL', $lineId, '基數標籤碰到線');
            }
        }
    }
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

function chen_assert_svg(string $svg, array $layout, array $model, string $locale): void
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
    chen_check_pk($xpath, $layout, $model);
    chen_check_label($xpath, $shapes, $lines);
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
                chen_assert_svg($svg, $layout, $model, $locale);
                $sets["$key-$locale-$theme"] = $svg;
                $result['er-' . $key . '-' . $locale . '-' . $theme . '.svg'] = $svg;
            }
        }
    }
    chen_check_variant_set($sets);
    return $result;
}
