<?php
declare(strict_types=1);

/* Chen renderer. Assertions below parse the serialized SVG, never the layout arrays. */
const CHEN_MARGIN = 24;
const CHEN_MAX_WIDTH = 1500;
const CHEN_ATTR_HEIGHT = 36;
const CHEN_ENTITY_MIN_HEIGHT = 52;
const CHEN_FONT = '&quot;Microsoft JhengHei&quot;, &quot;Noto Sans TC&quot;, &quot;PingFang TC&quot;, sans-serif';

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
    return $theme === 'dark'
        ? ['bg' => '#151b23', 'fg' => '#e6edf3', 'line' => '#9ab6d3', 'entity' => '#263140',
            'relationship' => '#4a3f2a', 'attribute' => '#1b2734']
        : ['bg' => '#ffffff', 'fg' => '#1c2530', 'line' => '#41566e', 'entity' => '#eef2f6',
            'relationship' => '#fff7df', 'attribute' => '#ffffff'];
}

function chen_width(string $zh, string $en, float $fontSize, float $padding): float
{
    return ceil(max(dg_independent_text_width($zh, $fontSize),
        dg_independent_text_width($en, $fontSize)) + $padding);
}

function chen_box(array $shape): array
{
    return dg_bounds($shape['x'] - $shape['w'] / 2, $shape['y'] - $shape['h'] / 2,
        $shape['w'], $shape['h']);
}

function chen_boundary(array $shape, array $toward): array
{
    return dg_shape_boundary_point($shape['kind'], ['x' => $shape['x'], 'y' => $shape['y']],
        $shape['w'] / 2, $shape['h'] / 2, $toward);
}

function chen_line(array $from, array $to, string $id, string $owner, string $target, array $extra = []): array
{
    return array_merge(['id' => $id, 'owner' => $owner, 'target' => $target,
        'x1' => $from['x'], 'y1' => $from['y'], 'x2' => $to['x'], 'y2' => $to['y']], $extra);
}

function chen_entity_height(array $entity): float
{
    return max(CHEN_ENTITY_MIN_HEIGHT, 8.0 * (count($entity['attributes']) + 1));
}

function chen_entity(array $entity, array $spec, float $columnGap, float $rowGap): array
{
    return ['id' => 'entity:' . $entity['id'], 'kind' => 'rectangle',
        'x' => $spec['grid'][0] * $columnGap, 'y' => $spec['grid'][1] * $rowGap,
        'w' => chen_width(chen_name($entity, 'zh'), chen_name($entity, 'en'), 15, 24),
        'h' => chen_entity_height($entity), 'entity' => $entity, 'side' => $spec['side'],
        'reference' => !empty($spec['reference'])];
}

function chen_attribute(array $entity, array $attribute, array $shape, int $index): array
{
    $width = chen_width(chen_attribute_name($attribute, 'zh'), chen_attribute_name($attribute, 'en'), 13, 24);
    $count = count($entity['attributes']);
    if (in_array($shape['side'], ['top', 'bottom'], true)) {
        $widths = array_map(static fn(array $item): float => chen_width(
            chen_attribute_name($item, 'zh'), chen_attribute_name($item, 'en'), 13, 24
        ), $entity['attributes']);
        $before = array_sum(array_slice($widths, 0, $index)) + 12 * $index;
        $total = array_sum($widths) + 12 * max(0, $count - 1);
        $direction = $shape['side'] === 'top' ? -1 : 1;
        $x = $shape['x'] - $total / 2 + $before + $width / 2;
        $y = $shape['y'] + $direction * ($shape['h'] / 2 + 34 + CHEN_ATTR_HEIGHT / 2);
    } else {
        $direction = $shape['side'] === 'left' ? -1 : 1;
        $total = $count * CHEN_ATTR_HEIGHT + 12 * max(0, $count - 1);
        $x = $shape['x'] + $direction * ($shape['w'] / 2 + 34 + $width / 2);
        $y = $shape['y'] - $total / 2 + $index * (CHEN_ATTR_HEIGHT + 12) + CHEN_ATTR_HEIGHT / 2;
    }
    return ['id' => 'attribute:' . $entity['id'] . ':' . $attribute[0], 'kind' => 'ellipse',
        'x' => $x, 'y' => $y, 'w' => $width, 'h' => CHEN_ATTR_HEIGHT,
        'attribute' => $attribute, 'owner' => $entity['id']];
}

function chen_attribute_line(array $entity, array $attribute, int $index, int $count): array
{
    if (in_array($entity['side'], ['top', 'bottom'], true)) {
        $from = chen_boundary($entity, $attribute);
        $to = chen_boundary($attribute, $entity);
    } else {
        $direction = $entity['side'] === 'left' ? -1 : 1;
        $from = ['x' => $entity['x'] + $direction * $entity['w'] / 2,
            'y' => $entity['y'] - $entity['h'] / 2 + $entity['h'] * ($index + 1) / ($count + 1)];
        /* MSO Get-ColumnAttributeLine: endpoint must be the inner ellipse edge. */
        $to = ['x' => $attribute['x'] - $direction * $attribute['w'] / 2, 'y' => $attribute['y']];
    }
    return chen_line($from, $to, $entity['id'] . '--' . $attribute['id'], $entity['id'], $attribute['id']);
}

function chen_groups(array $shapes, array $diagram): array
{
    $groups = [];
    foreach ($diagram['entities'] as $spec) {
        $boxes = [];
        foreach ($shapes as $shape) {
            if ($shape['id'] === 'entity:' . $spec['id']
                || str_starts_with($shape['id'], 'attribute:' . $spec['id'] . ':')) {
                $boxes[] = chen_box($shape);
            }
        }
        $left = min(array_column($boxes, 'x'));
        $top = min(array_column($boxes, 'y'));
        $right = max(array_map(static fn(array $box): float => $box['x'] + $box['width'], $boxes));
        $bottom = max(array_map(static fn(array $box): float => $box['y'] + $box['height'], $boxes));
        $groups[$spec['id']] = dg_bounds($left, $top, $right - $left, $bottom - $top);
    }
    return $groups;
}

function chen_group_conflict(array $groups): ?array
{
    $ids = array_keys($groups);
    for ($i = 0;
        $i < count($ids);
        $i++) {
        for ($j = $i + 1;
        $j < count($ids);
        $j++) {
            $a = $groups[$ids[$i]];
            $b = $groups[$ids[$j]];
            $x = max(0, max($a['x'] - ($b['x'] + $b['width']), $b['x'] - ($a['x'] + $a['width'])));
            $y = max(0, max($a['y'] - ($b['y'] + $b['height']), $b['y'] - ($a['y'] + $a['height'])));
            if (hypot($x, $y) < 24) {
                return [$ids[$i], $ids[$j]];
            }
        }
    }
    return null;
}

function chen_relationship(array $relationship, array $left, array $right): array
{
    return ['id' => 'relationship:' . $relationship['id'], 'kind' => 'diamond',
        'x' => ($left['x'] + $right['x']) / 2, 'y' => ($left['y'] + $right['y']) / 2,
        'w' => ceil(chen_width(chen_name($relationship, 'zh'), chen_name($relationship, 'en'), 15, 24) / .72),
        'h' => 62, 'relationship' => $relationship];
}

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
        $lines[] = chen_line(chen_boundary($entity, $target), chen_boundary($diamond, $origin),
            $diamond['id'] . ':' . $participant['entity'] . ':' . $stroke, $entity['id'], $diamond['id'],
            ['participant' => $participant, 'stroke' => $stroke]);
    }
    return $lines;
}

function chen_relationship_attribute(array $relationship, array $diamond, array $left, array $right): array
{
    $attribute = $relationship['attributes'][0];
    $width = chen_width(chen_attribute_name($attribute, 'zh'), chen_attribute_name($attribute, 'en'), 13, 24);
    $dx = $right['x'] - $left['x'];
    $dy = $right['y'] - $left['y'];
    $length = hypot($dx, $dy);
    /* MSO normal is (dy/len, -dx/len), keeping Quantity above the Orders attributes. */
    $normalX = $length === 0.0 ? 0.0 : $dy / $length;
    $normalY = $length === 0.0 ? -1.0 : -$dx / $length;
    $offset = max($diamond['w'], $diamond['h']) / 2 + 34 + $width / 2;
    return ['id' => 'attribute:' . $relationship['id'] . ':' . $attribute[0], 'kind' => 'ellipse',
        'x' => $diamond['x'] + $normalX * $offset, 'y' => $diamond['y'] + $normalY * $offset,
        'w' => $width, 'h' => CHEN_ATTR_HEIGHT, 'attribute' => $attribute, 'owner' => $relationship['id']];
}

function chen_label_box(float $x, float $y, string $text): array
{
    $width = dg_independent_text_width($text, 13);
    return dg_bounds($x - $width / 2, $y - 9.5, $width, 19);
}

function chen_labels(array $lines, array $shapes): array
{
    $labels = [];
    foreach ($lines as $line) {
        if (($line['stroke'] ?? -1) !== 0) {
            continue;
        }
        $dx = $line['x2'] - $line['x1'];
        $dy = $line['y2'] - $line['y1'];
        $length = hypot($dx, $dy);
        $unitX = $dx / $length;
        $unitY = $dy / $length;
        $normalX = abs($dx) < .01 ? 1.0 : -$unitY;
        $normalY = abs($dx) < .01 ? 0.0 : $unitX;
        foreach ([14, 26, 38] as $along) {
            foreach ([1, -1] as $side) {
                $x = $line['x1'] + $unitX * $along + $normalX * 15 * $side;
                $y = $line['y1'] + $unitY * $along + $normalY * 15 * $side;
                $box = chen_label_box($x, $y, (string) $line['participant']['cardinality']);
                if (chen_label_blocked($box, $shapes, $lines, $labels)) {
                    continue;
                }
                $labels[] = ['line' => $line['id'], 'value' => (string) $line['participant']['cardinality'],
                    'x' => $x, 'y' => $y + 5, 'box' => $box];
                continue 3;
            }
        }
        chen_fail('C-LABEL', $line['id'], '基數標籤候選位置全部衝突');
    }
    return $labels;
}

function chen_label_blocked(array $box, array $shapes, array $lines, array $labels): bool
{
    foreach ($shapes as $shape) {
        if (dg_bounds_overlap($box, chen_box($shape))) {
            return true;
        }
    }
    foreach ($lines as $line) {
        if (dg_segment_bounds_distance(['x' => $line['x1'], 'y' => $line['y1']],
            ['x' => $line['x2'], 'y' => $line['y2']], $box) < .75) {
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

function chen_extents(array $shapes, array $lines, array $labels): array
{
    $boxes = array_map('chen_box', $shapes);
    foreach ($lines as $line) {
        $boxes[] = dg_bounds(min($line['x1'], $line['x2']), min($line['y1'], $line['y2']),
            abs($line['x1'] - $line['x2']) + 1.5, abs($line['y1'] - $line['y2']) + 1.5);
    }
    foreach ($labels as $label) {
        $boxes[] = $label['box'];
    }
    return [min(array_column($boxes, 'x')), min(array_column($boxes, 'y')),
        max(array_map(static fn(array $box): float => $box['x'] + $box['width'], $boxes)),
        max(array_map(static fn(array $box): float => $box['y'] + $box['height'], $boxes))];
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

function chen_layout(array $model, string $key): array
{
    $diagram = $model['diagrams'][$key];
    $entities = chen_index($model['entities']);
    $relationships = chen_index($model['relationships']);
    /* MSO baseline: never shrink below enough room for a 40px entity-to-diamond segment. */
    $columnGap = 370.0;
    $rowGap = 450.0;
    for ($attempt = 0;
        $attempt < 50;
        $attempt++) {
        $shapes = [];
        $entityShapes = [];
        $attributeLines = [];
        foreach ($diagram['entities'] as $spec) {
            $entity = chen_entity($entities[$spec['id']], $spec, $columnGap, $rowGap);
            $shapes[] = $entity;
            $entityShapes[$spec['id']] = $entity;
            if ($entity['reference']) {
                continue;
            }
            foreach ($entity['entity']['attributes'] as $index => $attribute) {
                $shape = chen_attribute($entity['entity'], $attribute, $entity, $index);
                $shapes[] = $shape;
                $attributeLines[] = chen_attribute_line($entity, $shape, $index,
                    count($entity['entity']['attributes']));
            }
        }
        $conflict = chen_group_conflict(chen_groups($shapes, $diagram));
        if ($conflict !== null) {
            if ($entityShapes[$conflict[0]]['x'] !== $entityShapes[$conflict[1]]['x']) {
                $columnGap += 20;
            }
            if ($entityShapes[$conflict[0]]['y'] !== $entityShapes[$conflict[1]]['y']) {
                $rowGap += 20;
            }
            continue;
        }
        $connectionLines = [];
        foreach ($diagram['relationships'] as $id) {
            $relationship = $relationships[$id];
            $left = $entityShapes[$relationship['participants'][0]['entity']];
            $right = $entityShapes[$relationship['participants'][1]['entity']];
            $diamond = chen_relationship($relationship, $left, $right);
            $shapes[] = $diamond;
            foreach ($relationship['participants'] as $participant) {
                foreach (chen_relationship_lines($entityShapes[$participant['entity']], $diamond, $participant) as $line) {
                    $connectionLines[] = $line;
                }
            }
            if (!empty($relationship['attributes'])) {
                $attribute = chen_relationship_attribute($relationship, $diamond, $left, $right);
                $shapes[] = $attribute;
                $attributeLines[] = chen_line(chen_boundary($diamond, $attribute), chen_boundary($attribute, $diamond),
                    $diamond['id'] . '--' . $attribute['id'], $diamond['id'], $attribute['id']);
            }
        }
        $lines = array_merge($attributeLines, $connectionLines);
        $labels = chen_labels($lines, $shapes);
        [$left, $top, $right, $bottom] = chen_extents($shapes, $lines, $labels);
        $width = (int) ceil($right - $left + CHEN_MARGIN * 2);
        $height = (int) ceil($bottom - $top + CHEN_MARGIN * 2 + 56);
        if ($width > CHEN_MAX_WIDTH) {
            chen_fail('C-CANVAS', $key, "最小寬度 {$width}px 超過 1500px");
        }
        chen_translate($shapes, CHEN_MARGIN - $left, CHEN_MARGIN - $top);
        chen_translate($lines, CHEN_MARGIN - $left, CHEN_MARGIN - $top);
        chen_translate($labels, CHEN_MARGIN - $left, CHEN_MARGIN - $top);
        return compact('diagram', 'key', 'shapes', 'lines', 'labels', 'width', 'height', 'columnGap', 'rowGap');
    }
    chen_fail('C-GROUP', $key, '50 次調整後仍有群組衝突');
}

function chen_notes(string $key, string $locale): array
{
    $notes = [['kind' => 'legend', 'text' => $locale === 'zh'
        ? '矩形＝實體　菱形＝關聯　橢圓＝屬性　底線＝主鍵　雙線＝全部參與'
        : 'Rectangle = entity   Diamond = relationship   Ellipse = attribute   Underline = primary key   Double line = total participation']];
    if ($key === 'system') {
        $notes[] = ['kind' => 'isolated', 'text' => $locale === 'zh'
            ? '系統帳號與往來對象主檔沒有外鍵，不與其他實體相連'
            : 'User accounts and the party master have no foreign keys'];
    }
    if ($key === 'fulfillment') {
        $notes[] = ['kind' => 'reference', 'text' => $locale === 'zh'
            ? '虛線框：參照實體，屬性見訂單核心圖'
            : 'Dashed boxes: referenced entities;
        see the order-core diagram for their attributes'];
    }
    return $notes;
}

function chen_svg_shape(array $shape, string $locale, array $entities, array $palette): array
{
    $label = isset($shape['entity']) ? chen_name($shape['entity'], $locale)
        : (isset($shape['relationship']) ? chen_name($shape['relationship'], $locale)
        : chen_attribute_name($shape['attribute'], $locale));
    $fill = $shape['kind'] === 'rectangle' ? $palette['entity']
        : ($shape['kind'] === 'diamond' ? $palette['relationship'] : $palette['attribute']);
    if ($shape['kind'] === 'rectangle') {
        $svg = sprintf('<rect data-element="%s" x="%s" y="%s" width="%s" height="%s" fill="%s" stroke="%s"%s/>',
            chen_escape($shape['id']), $shape['x'] - $shape['w'] / 2, $shape['y'] - $shape['h'] / 2,
            $shape['w'], $shape['h'], $fill, $palette['line'], !empty($shape['reference']) ? ' stroke-dasharray="6 4"' : '');
    } elseif ($shape['kind'] === 'diamond') {
        $points = sprintf('%s,%s %s,%s %s,%s %s,%s', $shape['x'], $shape['y'] - $shape['h'] / 2,
            $shape['x'] + $shape['w'] / 2, $shape['y'], $shape['x'], $shape['y'] + $shape['h'] / 2,
            $shape['x'] - $shape['w'] / 2, $shape['y']);
        $svg = sprintf('<polygon data-element="%s" points="%s" fill="%s" stroke="%s"/>',
            chen_escape($shape['id']), $points, $fill, $palette['line']);
    } else {
        $svg = sprintf('<ellipse data-element="%s" cx="%s" cy="%s" rx="%s" ry="%s" fill="%s" stroke="%s"/>',
            chen_escape($shape['id']), $shape['x'], $shape['y'], $shape['w'] / 2, $shape['h'] / 2, $fill, $palette['line']);
    }
    $primaryKey = isset($shape['attribute'], $entities[$shape['owner']])
        && $entities[$shape['owner']]['primaryKey'] === $shape['attribute'][0];
    $text = sprintf('<text data-text-for="%s" x="%s" y="%s" text-anchor="middle" font-family="%s" font-size="%d" fill="%s"%s>%s</text>',
        chen_escape($shape['id']), $shape['x'], $shape['y'] + 5, CHEN_FONT, $shape['kind'] === 'ellipse' ? 13 : 15,
        $palette['fg'], $primaryKey ? ' text-decoration="underline"' : '', chen_escape($label));
    return [$svg, $text];
}

function chen_svg(array $layout, string $locale, string $theme, array $model): string
{
    $palette = chen_palette($theme);
    $entities = chen_index($model['entities']);
    $out = ['<?xml version="1.0" encoding="UTF-8"?>', sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" role="img">',
        $layout['width'], $layout['height'], $layout['width'], $layout['height']),
        '<title>' . chen_escape($layout['key'] . ' Chen ER') . '</title>',
        '<desc>' . chen_escape($layout['diagram']['caption'][$locale]) . '</desc>',
        sprintf('<rect data-element="canvas" x="0" y="0" width="%d" height="%d" fill="%s"/>', $layout['width'], $layout['height'], $palette['bg'])];
    foreach ($layout['lines'] as $line) {
        $out[] = sprintf('<line data-line="%s" data-owner="%s" data-target="%s" x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="1.5"/>',
            chen_escape($line['id']), chen_escape($line['owner']), chen_escape($line['target']), $line['x1'], $line['y1'],
            $line['x2'], $line['y2'], $palette['line']);
    }
    foreach ($layout['shapes'] as $shape) {
        foreach (chen_svg_shape($shape, $locale, $entities, $palette) as $part) {
            $out[] = $part;
        }
    }
    foreach ($layout['labels'] as $label) {
        $out[] = sprintf('<text data-cardinality-for="%s" x="%s" y="%s" text-anchor="middle" font-family="%s" font-size="13" fill="%s">%s</text>',
            chen_escape($label['line']), $label['x'], $label['y'], CHEN_FONT, $palette['fg'], chen_escape($label['value']));
    }
    $notes = chen_notes($layout['key'], $locale);
    $noteY = $layout['height'] - 28 * count($notes);
    foreach ($notes as $note) {
        if ($note['kind'] === 'isolated') {
            $out[] = sprintf('<line data-note-separator="true" x1="24" y1="%d" x2="%d" y2="%d" stroke="%s"/>',
                $noteY - 8, $layout['width'] - 24, $noteY - 8, $palette['line']);
        }
        $out[] = sprintf('<text data-note="%s" x="24" y="%d" font-family="%s" font-size="12" fill="%s">%s</text>',
            $note['kind'], $noteY, CHEN_FONT, $palette['fg'], chen_escape($note['text']));
        $noteY += 28;
    }
    $out[] = '</svg>';
    return implode("\n", $out) . "\n";
}

function chen_dom_shapes(DOMDocument $dom): array
{
    $shapes = [];
    foreach ((new DOMXPath($dom))->query('//*[@data-element and @data-element != "canvas"]') as $node) {
        $id = $node->getAttribute('data-element');
        if ($node->localName === 'rect') {
            $shapes[$id] = ['id' => $id, 'kind' => 'rectangle', 'x' => (float) $node->getAttribute('x') + (float) $node->getAttribute('width') / 2,
                'y' => (float) $node->getAttribute('y') + (float) $node->getAttribute('height') / 2, 'w' => (float) $node->getAttribute('width'), 'h' => (float) $node->getAttribute('height')];
        } elseif ($node->localName === 'ellipse') {
            $shapes[$id] = ['id' => $id, 'kind' => 'ellipse', 'x' => (float) $node->getAttribute('cx'), 'y' => (float) $node->getAttribute('cy'),
                'w' => (float) $node->getAttribute('rx') * 2, 'h' => (float) $node->getAttribute('ry') * 2];
        } else {
            preg_match_all('/-?[0-9.]+/', $node->getAttribute('points'), $matches);
            $points = array_map('floatval', $matches[0]);
        $xs = [];
        $ys = [];
            for ($i = 0;
        $i < count($points);
        $i += 2) { $xs[] = $points[$i];
        $ys[] = $points[$i + 1];
        }
            $shapes[$id] = ['id' => $id, 'kind' => 'diamond', 'x' => (min($xs) + max($xs)) / 2, 'y' => (min($ys) + max($ys)) / 2,
                'w' => max($xs) - min($xs), 'h' => max($ys) - min($ys)];
        }
    }
    return $shapes;
}

function chen_dom_lines(DOMDocument $dom): array
{
    $lines = [];
    foreach ((new DOMXPath($dom))->query('//*[local-name()="line" and @data-line]') as $node) {
        $lines[] = ['id' => $node->getAttribute('data-line'), 'owner' => $node->getAttribute('data-owner'), 'target' => $node->getAttribute('data-target'),
            'x1' => (float) $node->getAttribute('x1'), 'y1' => (float) $node->getAttribute('y1'), 'x2' => (float) $node->getAttribute('x2'), 'y2' => (float) $node->getAttribute('y2')];
    }
    return $lines;
}

function chen_boundary_error(array $shape, float $x, float $y): float
{
    $dx = $x - $shape['x'];
        $dy = $y - $shape['y'];
    if ($shape['kind'] === 'ellipse') {
        return abs(($dx / ($shape['w'] / 2)) ** 2 + ($dy / ($shape['h'] / 2)) ** 2 - 1) * min($shape['w'], $shape['h']) / 2;
    }
    if ($shape['kind'] === 'diamond') {
        return abs(abs($dx) / ($shape['w'] / 2) + abs($dy) / ($shape['h'] / 2) - 1) * min($shape['w'], $shape['h']) / 2;
    }
    return min(abs(abs($dx) - $shape['w'] / 2), abs(abs($dy) - $shape['h'] / 2));
}

function chen_assert_model(array $model, array $schema): void
{
    $entities = chen_index($model['entities']);
        $covered = [];
    foreach ($model['relationships'] as $relationship) foreach ($relationship['participants'] as $participant) {
        if (!isset($participant['foreignKey'])) continue;
        $covered[] = $participant['foreignKey'];
        [$table, $column] = explode('.', $participant['foreignKey'], 2);
        $info = null;
        foreach ($schema['tables'][$table]['columns'] ?? [] as $candidate) if ($candidate['name'] === $column) $info = $candidate;
        if ($info === null) chen_fail('C-MODEL', $participant['foreignKey'], 'JSON 外鍵不存在');
        if ($table === 'Contain') {
            if ($participant['participation'] !== 'partial') chen_fail('C-MODEL', $participant['foreignKey'], '資料庫無法保證 M:N 的全部參與');
            if ((int) $info['notnull'] !== 1 || (int) $info['pk'] === 0) chen_fail('C-MODEL', $participant['foreignKey'], 'M:N 外鍵必須 NOT NULL 且在主鍵裡');
        } elseif (($participant['participation'] === 'total') !== ((int) $info['notnull'] === 1)) chen_fail('C-MODEL', $participant['foreignKey'], '全部參與與 NOT NULL 不一致');
    }
    $actual = array_map(static fn(array $fk): string => $fk['fromTable'] . '.' . $fk['fromColumn'], $schema['foreignKeys']);
        sort($covered);
        sort($actual);
    if ($covered !== $actual) chen_fail('C-MODEL', 'foreignKeys', '每條外鍵必須恰被一個關聯端涵蓋');
    foreach ($schema['tables'] as $table => $definition) {
        if ($table === 'Contain') continue;
        if (!isset($entities[$table])) chen_fail('C-MODEL', $table, '資料表未畫成實體');
        $fk = array_flip(array_map(static fn(array $item): string => $item['fromColumn'], array_filter($schema['foreignKeys'], static fn(array $item): bool => $item['fromTable'] === $table)));
        $expected = array_values(array_filter(array_column($definition['columns'], 'name'), static fn(string $name): bool => !isset($fk[$name])));
        if (array_column($entities[$table]['attributes'], 0) !== $expected) chen_fail('C-ORDER', $table, '屬性不是資料庫順序扣外鍵');
    }
}

function chen_assert_svg(string $svg, array $layout, array $model, string $locale): void
{
    $dom = dg_dom($svg);
        $shapes = chen_dom_shapes($dom);
        $lines = chen_dom_lines($dom);
        $xpath = new DOMXPath($dom);
    $width = (float) $dom->documentElement->getAttribute('width');
        $height = (float) $dom->documentElement->getAttribute('height');
    if ($width > CHEN_MAX_WIDTH) chen_fail('C-CANVAS', $layout['key'], '寬度超過 1500px');
    foreach ($shapes as $id => $shape) {
        $box = chen_box($shape);
        if ($box['x'] < 23 || $box['y'] < 23 || $box['x'] + $box['width'] > $width - 23 || $box['y'] + $box['height'] > $height - 23) chen_fail('C-CANVAS', $id, '圖形未落在畫布留白 24±1 內');
    }
    foreach ($xpath->query('//*[local-name()="text" and @data-text-for]') as $text) {
        $id = $text->getAttribute('data-text-for');
        $shape = $shapes[$id] ?? null;
        $space = $shape['kind'] === 'diamond' ? $shape['w'] * .72 - 20 : $shape['w'] - 20;
        if ($shape === null || dg_independent_text_width($text->textContent, (float) $text->getAttribute('font-size')) > $space) chen_fail('C-FIT', $id, '標籤放不進圖形');
    }
    foreach ($xpath->query('//*[local-name()="text" and @data-note]') as $text) if (dg_independent_text_width($text->textContent, 12) > $width - 48) chen_fail('C-FIT', $text->getAttribute('data-note'), '圖例或圖說放不進畫布');
    $ids = array_keys($shapes);
        for ($i = 0;
        $i < count($ids);
        $i++) for ($j = $i + 1;
        $j < count($ids);
        $j++) if (dg_bounds_overlap(chen_box($shapes[$ids[$i]]), chen_box($shapes[$ids[$j]]))) chen_fail('C-OVERLAP', $ids[$i] . '/' . $ids[$j], '圖形外框重疊');
    foreach ($lines as $line) {
        if (!isset($shapes[$line['owner']], $shapes[$line['target']]) || chen_boundary_error($shapes[$line['owner']], $line['x1'], $line['y1']) > .5 || chen_boundary_error($shapes[$line['target']], $line['x2'], $line['y2']) > .5) chen_fail('C-ENDPOINT', $line['id'], '線端未落在真正邊框');
        foreach ($shapes as $id => $shape) if ($id !== $line['owner'] && $id !== $line['target'] && dg_segment_intersects_bounds(['x' => $line['x1'], 'y' => $line['y1']], ['x' => $line['x2'], 'y' => $line['y2']], chen_box($shape))) chen_fail('C-THROUGH', $line['id'], "穿過 $id");
        if (str_starts_with($line['id'], 'relationship:') && hypot($line['x2'] - $line['x1'], $line['y2'] - $line['y1']) < 40) chen_fail('C-LINELEN', $line['id'], '實體到菱形的線段短於 40px');
    }
    for ($i = 0;
        $i < count($lines);
        $i++) for ($j = $i + 1;
        $j < count($lines);
        $j++) {
        $a = $lines[$i];
        $b = $lines[$j];
        if ($a['owner'] === $b['owner'] || $a['owner'] === $b['target'] || $a['target'] === $b['owner'] || $a['target'] === $b['target']) continue;
        if (dg_segments_intersect(['x' => $a['x1'], 'y' => $a['y1']], ['x' => $a['x2'], 'y' => $a['y2']], ['x' => $b['x1'], 'y' => $b['y1']], ['x' => $b['x2'], 'y' => $b['y2']])) chen_fail('C-CROSS', $a['id'] . '/' . $b['id'], '線段交叉');
    }
    $entities = chen_index($model['entities']);
        $underlined = [];
    foreach ($xpath->query('//*[local-name()="text" and @text-decoration="underline"]') as $text) $underlined[] = $text->getAttribute('data-text-for');
    $expected = [];
        foreach ($layout['diagram']['entities'] as $spec) if (empty($spec['reference'])) $expected[] = 'attribute:' . $spec['id'] . ':' . $entities[$spec['id']]['primaryKey'];
        sort($underlined);
        sort($expected);
    if ($underlined !== $expected) chen_fail('C-PK', $layout['key'], '底線文字不恰為主鍵');
    foreach ($xpath->query('//*[local-name()="text" and @data-cardinality-for]') as $text) {
        $box = chen_label_box((float) $text->getAttribute('x'), (float) $text->getAttribute('y') - 5, $text->textContent);
        foreach ($shapes as $shape) if (dg_bounds_overlap($box, chen_box($shape))) chen_fail('C-LABEL', $text->getAttribute('data-cardinality-for'), '基數標籤碰到圖形');
        foreach ($lines as $line) {
            if ($line['id'] === $text->getAttribute('data-cardinality-for')) {
                continue;
            }
            if (dg_segment_bounds_distance(['x' => $line['x1'], 'y' => $line['y1']], ['x' => $line['x2'], 'y' => $line['y2']], $box) < .75) {
                chen_fail('C-LABEL', $text->getAttribute('data-cardinality-for'), '基數標籤碰到線');
            }
        }
    }
    foreach ($xpath->query('//*[local-name()="text"]') as $text) {
        if ($text->getAttribute('font-family') !== html_entity_decode(CHEN_FONT, ENT_QUOTES, 'UTF-8')
            || (float) $text->getAttribute('font-size') < 12) {
            chen_fail('C-VARIANT', $layout['key'], '文字字型或字級不符');
        }
    }
    if ($locale === 'en' && preg_match('/[\x{3000}-\x{303f}\x{3400}-\x{9fff}\x{f900}-\x{faff}\x{ff00}-\x{ffef}]/u', $svg)) chen_fail('C-VARIANT', $layout['key'], '英文 SVG 含 CJK');
    chen_assert_svg_sides($shapes, $layout, $model);
}

function chen_assert_svg_sides(array $shapes, array $layout, array $model): void
{
    $entities = chen_index($model['entities']);
    foreach ($layout['diagram']['entities'] as $spec) {
        if (!empty($spec['reference'])) {
            continue;
        }
        $entity = $shapes['entity:' . $spec['id']];
        $previous = -INF;
        foreach ($entities[$spec['id']]['attributes'] as $attribute) {
            $shape = $shapes['attribute:' . $spec['id'] . ':' . $attribute[0]];
            $gap = in_array($spec['side'], ['left', 'right'], true)
                ? abs($shape['x'] - $entity['x']) - ($shape['w'] + $entity['w']) / 2
                : abs($shape['y'] - $entity['y']) - ($shape['h'] + $entity['h']) / 2;
            if ($gap < 28 || $gap > 40) {
                chen_fail('C-SIDE', $shape['id'], '屬性離實體不在 28～40px');
            }
            $position = in_array($spec['side'], ['left', 'right'], true) ? $shape['y'] : $shape['x'];
            if ($position <= $previous) {
                chen_fail('C-ORDER', $spec['id'], 'SVG 屬性順序不是資料庫欄位順序');
            }
            $previous = $position;
        }
        if (in_array($spec['side'], ['left', 'right'], true)
            && $entity['h'] / (count($entities[$spec['id']]['attributes']) + 1) < 8) {
            chen_fail('C-SIDE', $spec['id'], '扇形接點間距小於 8px');
        }
    }
}

function chen_assert_variant_set(array $variants): void
{
    foreach (['system', 'orders', 'fulfillment'] as $key) {
        foreach (['zh', 'en'] as $locale) {
            $light = $variants["$key-$locale-light"];
            $dark = $variants["$key-$locale-dark"];
            if (dg_strip_colors($light) !== dg_strip_colors($dark)) {
                chen_fail('C-VARIANT', $key, "$locale 亮暗去色後不相同");
            }
            foreach (chen_palette('light') as $name => $color) {
                if ($name !== 'bg' && str_contains($dark, $color)) {
                    chen_fail('C-VARIANT', $key, "深色版含亮色 $name");
                }
            }
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

function chen_generate(array $model, array $schema): array
{
    chen_assert_model($model, $schema);
        $result = [];
        $sets = [];
    foreach (['system', 'orders', 'fulfillment'] as $key) {
        $layout = chen_layout($model, $key);
        foreach (['zh', 'en'] as $locale) foreach (['light', 'dark'] as $theme) {
            $svg = chen_svg($layout, $locale, $theme, $model);
            chen_assert_svg($svg, $layout, $model, $locale);
            $sets["$key-$locale-$theme"] = $svg;
            $result['er-' . $key . '-' . $locale . '-' . $theme . '.svg'] = $svg;
        }
    }
    chen_assert_variant_set($sets);
    return $result;
}
