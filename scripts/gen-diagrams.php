<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/diagram-geometry.php';
require_once __DIR__ . '/lib/chen-diagrams.php';

const DG_MARGIN = 24;
const DG_CANVAS_MAX_WIDTH = 1400;
const DG_LABEL_X = 168;
const DG_LABEL_WIDTH = 132;
const DG_FIELD_X = 324;
const DG_CELL_HEIGHT = 34;
const DG_FIELD_GAP = 0;
const DG_ROW_GAP = 34;
const DG_FIELD_PADDING = 20;
const DG_FIELD_MIN_WIDTH = 70;
const DG_LANE_X = 32;
const DG_LANE_GAP = 12;
const DG_TITLE_FONT = '&quot;Microsoft JhengHei&quot;, &quot;Noto Sans TC&quot;, &quot;PingFang TC&quot;, sans-serif';
const DG_MONO_FONT = 'Consolas, Menlo, &quot;DejaVu Sans Mono&quot;, monospace';
const DG_TITLE_SIZE = 14;
const DG_FIELD_SIZE = 12;

const DG_PALETTES = [
    'light' => ['background' => '#ffffff', 'foreground' => '#1c2530', 'line' => '#41566e', 'label' => '#eef2f6'],
    'dark' => ['background' => '#151b23', 'foreground' => '#e6edf3', 'line' => '#9ab6d3', 'label' => '#263140'],
];

function dg_fail(string $code, string $message): never
{
    throw new RuntimeException("DIAGRAMS FAIL [$code] $message");
}

function dg_root(): string
{
    return dirname(__DIR__);
}

function dg_load_model(): array
{
    $path = dg_root() . '/docs/diagrams/conceptual-model.json';
    $model = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!isset($model['relationalSchema']) || !is_array($model['relationalSchema'])) {
        dg_fail('R-ORDER', 'conceptual-model.json 缺少 relationalSchema');
    }
    return $model['relationalSchema'];
}

function dg_open_database(): PDO
{
    $path = dg_root() . '/data/fiance2024.sqlite';
    if (!is_file($path)) {
        throw new RuntimeException("Database is missing: $path; run scripts/create.php first.");
    }
    $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}

function dg_read_schema(PDO $pdo): array
{
    $sql = "SELECT name FROM sqlite_master WHERE type = 'table' "
        . "AND name NOT LIKE 'sqlite_%' ORDER BY name";
    $names = $pdo->query($sql)
        ->fetchAll(PDO::FETCH_COLUMN);
    $tables = [];
    $foreignKeys = [];
    foreach ($names as $name) {
        $quoted = str_replace('"', '""', (string) $name);
        $columns = $pdo->query("PRAGMA table_info(\"$quoted\")")->fetchAll(PDO::FETCH_ASSOC);
        $tables[$name] = ['name' => $name, 'columns' => $columns];
        $keys = $pdo->query("PRAGMA foreign_key_list(\"$quoted\")")->fetchAll(PDO::FETCH_ASSOC);
        usort($keys, static fn(array $a, array $b): int => [$a['id'], $a['seq']] <=> [$b['id'], $b['seq']]);
        foreach ($keys as $key) {
            $foreignKeys[] = [
                'fromTable' => $name,
                'fromColumn' => $key['from'],
                'toTable' => $key['table'],
                'toColumn' => $key['to'],
                'onDelete' => strtoupper((string) $key['on_delete']),
            ];
        }
    }
    return ['tables' => $tables, 'foreignKeys' => $foreignKeys];
}

function dg_validate_order(array $schema, array $model): array
{
    $order = $model['tableOrder'] ?? [];
    $tableNames = array_keys($schema['tables']);
    sort($tableNames, SORT_STRING);
    $actualOrder = $order;
    sort($actualOrder, SORT_STRING);
    if ($actualOrder !== $tableNames || count($order) !== count(array_unique($order))) {
        dg_fail('R-ORDER', 'tableOrder 必須恰好涵蓋 SQLite 的所有資料表');
    }
    $positions = array_flip($order);
    foreach ($schema['foreignKeys'] as $foreignKey) {
        if ($positions[$foreignKey['toTable']] >= $positions[$foreignKey['fromTable']]) {
            dg_fail(
                'R-ORDER',
                "{$foreignKey['toTable']} 必須排在 {$foreignKey['fromTable']} 的上方"
            );
        }
    }
    return $order;
}

function dg_cell_width(string $text): int
{
    return max(DG_FIELD_MIN_WIDTH, (int) ceil(dg_estimated_text_width($text, DG_FIELD_SIZE) + DG_FIELD_PADDING));
}

function dg_layout(array $schema, array $order): array
{
    $layout = ['tables' => [], 'fields' => [], 'separator' => null, 'maxX' => DG_FIELD_X];
    $isolated = ['User', 'admin'];
    $y = 98;
    foreach ($order as $tableName) {
        if ($tableName === $isolated[0]) {
            $layout['separator'] = $y + 6;
            $y += 34;
        }
        $startY = $y;
        $x = DG_FIELD_X;
        $lineY = $y;
        $rows = 1;
        foreach ($schema['tables'][$tableName]['columns'] as $column) {
            $name = (string) $column['name'];
            $width = dg_cell_width($name);
            if ($x + $width > DG_CANVAS_MAX_WIDTH - DG_MARGIN) {
                $x = DG_FIELD_X;
                $lineY += DG_CELL_HEIGHT;
                $rows++;
            }
            $layout['fields']["$tableName.$name"] = [
                'x' => $x,
                'y' => $lineY,
                'width' => $width,
                'height' => DG_CELL_HEIGHT,
                'pk' => (int) $column['pk'] > 0,
            ];
            $layout['maxX'] = max($layout['maxX'], $x + $width);
            $x += $width + DG_FIELD_GAP;
        }
        $height = $rows * DG_CELL_HEIGHT;
        $layout['tables'][$tableName] = [
            'x' => DG_LABEL_X,
            'y' => $startY,
            'width' => $layout['maxX'] - DG_LABEL_X,
            'height' => $height,
            'labelWidth' => DG_LABEL_WIDTH,
        ];
        $y = $startY + $height + DG_ROW_GAP;
    }
    $layout['height'] = $y + 48;
    $layout['width'] = max(900, $layout['maxX'] + DG_MARGIN);
    if ($layout['width'] > DG_CANVAS_MAX_WIDTH) {
        dg_fail('R-CANVAS', "圖寬 {$layout['width']}px 超過 1400px");
    }
    return $layout;
}

function dg_escape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function dg_route_foreign_keys(array $foreignKeys, array $layout): array
{
    $routeOrder = array_keys($foreignKeys);
    usort($routeOrder, static function (int $left, int $right) use ($foreignKeys, $layout): int {
        $leftSource = $layout['fields']["{$foreignKeys[$left]['fromTable']}.{$foreignKeys[$left]['fromColumn']}"];
        $leftTarget = $layout['fields']["{$foreignKeys[$left]['toTable']}.{$foreignKeys[$left]['toColumn']}"];
        $rightSource = $layout['fields']["{$foreignKeys[$right]['fromTable']}.{$foreignKeys[$right]['fromColumn']}"];
        $rightTarget = $layout['fields']["{$foreignKeys[$right]['toTable']}.{$foreignKeys[$right]['toColumn']}"];
        $leftDistance = abs(($leftSource['y'] + $leftSource['height']) - ($leftTarget['y'] + $leftTarget['height']));
        $rightDistance = abs(($rightSource['y'] + $rightSource['height']) - ($rightTarget['y'] + $rightTarget['height']));
        return [$rightDistance, $leftSource['y'], $left] <=> [$leftDistance, $rightSource['y'], $right];
    });
    $laneRanks = array_flip($routeOrder);
    $incoming = [];
    $outgoing = [];
    foreach ($foreignKeys as $index => $foreignKey) {
        $source = $layout['fields']["{$foreignKey['fromTable']}.{$foreignKey['fromColumn']}"];
        $target = $layout['fields']["{$foreignKey['toTable']}.{$foreignKey['toColumn']}"];
        $outgoing[$source['y']][] = $index;
        $incoming[$target['y']][] = $index;
    }
    foreach ([$incoming, $outgoing] as &$sets) {
        foreach ($sets as &$set) {
            usort($set, static fn(int $a, int $b): int => $laneRanks[$a] <=> $laneRanks[$b]);
        }
    }
    unset($sets, $set);
    $routes = [];
    foreach ($foreignKeys as $index => $foreignKey) {
        $source = $layout['fields']["{$foreignKey['fromTable']}.{$foreignKey['fromColumn']}"];
        $target = $layout['fields']["{$foreignKey['toTable']}.{$foreignKey['toColumn']}"];
        $sourceRank = array_search($index, $outgoing[$source['y']], true);
        $targetRank = array_search($index, $incoming[$target['y']], true);
        $sourceBottom = $source['y'] + $source['height'];
        $targetBottom = $target['y'] + $target['height'];
        $lane = DG_LANE_X + $laneRanks[$index] * DG_LANE_GAP;
        $targetX = $target['x'] + 12 + $targetRank * 12;
        $targetY = $targetBottom + 6 + $targetRank * 4;
        $sourceY = $sourceBottom + 6 + count($incoming[$source['y']] ?? []) * 4 + 4 + $sourceRank * 5;
        $routes[] = [
            'foreignKey' => $foreignKey,
            'points' => [
                ['x' => $source['x'] + intdiv($source['width'], 2), 'y' => $sourceBottom],
                ['x' => $source['x'] + intdiv($source['width'], 2), 'y' => $sourceY],
                ['x' => $lane, 'y' => $sourceY],
                ['x' => $lane, 'y' => $targetY],
                ['x' => $targetX, 'y' => $targetY],
                ['x' => $targetX, 'y' => $targetBottom],
            ],
        ];
    }
    return $routes;
}

function dg_path_data(array $points): string
{
    return sprintf(
        'M %d %d V %d H %d V %d H %d V %d',
        $points[0]['x'],
        $points[0]['y'],
        $points[1]['y'],
        $points[2]['x'],
        $points[3]['y'],
        $points[4]['x'],
        $points[5]['y']
    );
}

function dg_svg(array $schema, array $model, array $layout, string $locale, string $theme): string
{
    $words = $model[$locale];
    $palette = DG_PALETTES[$theme];
    $out = [];
    $out[] = sprintf(
        '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" role="img">',
        $layout['width'],
        $layout['height'],
        $layout['width'],
        $layout['height']
    );
    $out[] = '  <title>' . dg_escape($words['title']) . '</title>';
    $out[] = '  <defs>';
    $out[] = sprintf(
        '    <marker id="arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto">'
    );
    $out[] = sprintf('      <path d="M 0 0 L 10 5 L 0 10 z" fill="%s"/>', $palette['line']);
    $out[] = '    </marker>';
    $out[] = '  </defs>';
    $out[] = sprintf(
        '  <rect data-kind="background" x="0" y="0" width="%d" height="%d" fill="%s"/>',
        $layout['width'],
        $layout['height'],
        $palette['background']
    );
    $out[] = sprintf(
        '  <text data-kind="caption" x="%d" y="%d" fill="%s" font-family="%s" font-size="%d">%s</text>',
        DG_MARGIN,
        38,
        $palette['foreground'],
        DG_TITLE_FONT,
        DG_TITLE_SIZE,
        dg_escape($words['caption'])
    );
    foreach ($model['tableOrder'] as $tableName) {
        $table = $layout['tables'][$tableName];
        $out[] = sprintf(
            '  <rect data-kind="table" data-table="%s" x="%d" y="%d" width="%d" height="%d" '
                . 'fill="%s" stroke="%s" stroke-width="1"/>',
            $tableName,
            $table['x'],
            $table['y'],
            $table['labelWidth'],
            $table['height'],
            $palette['label'],
            $palette['line']
        );
        $out[] = sprintf(
            '  <text data-kind="table-name" data-table="%s" x="%d" y="%d" text-anchor="middle" '
                . 'fill="%s" font-family="%s" font-size="%d">%s</text>',
            $tableName,
            $table['x'] + intdiv($table['labelWidth'], 2),
            $table['y'] + intdiv($table['height'], 2) + 4,
            $palette['foreground'],
            DG_MONO_FONT,
            DG_FIELD_SIZE,
            dg_escape($tableName)
        );
        foreach ($schema['tables'][$tableName]['columns'] as $column) {
            $name = (string) $column['name'];
            $field = $layout['fields']["$tableName.$name"];
            $out[] = sprintf(
                '  <rect data-kind="field" data-table="%s" data-column="%s" x="%d" y="%d" width="%d" '
                    . 'height="%d" fill="%s" stroke="%s" stroke-width="1"/>',
                $tableName,
                $name,
                $field['x'],
                $field['y'],
                $field['width'],
                $field['height'],
                $palette['background'],
                $palette['line']
            );
            $underline = $field['pk'] ? ' text-decoration="underline"' : '';
            $out[] = sprintf(
                '  <text data-kind="field-name" data-table="%s" data-column="%s" x="%d" y="%d" '
                    . 'text-anchor="middle" fill="%s" font-family="%s" font-size="%d"%s>%s</text>',
                $tableName,
                $name,
                $field['x'] + intdiv($field['width'], 2),
                $field['y'] + 22,
                $palette['foreground'],
                DG_MONO_FONT,
                DG_FIELD_SIZE,
                $underline,
                dg_escape($name)
            );
        }
    }
    if ($layout['separator'] !== null) {
        $out[] = sprintf(
            '  <line data-kind="separator" x1="%d" y1="%d" x2="%d" y2="%d" stroke="%s" stroke-width="1"/>',
            DG_MARGIN,
            $layout['separator'],
            $layout['width'] - DG_MARGIN,
            $layout['separator'],
            $palette['line']
        );
        $out[] = sprintf(
            '  <text data-kind="isolated-note" x="%d" y="%d" fill="%s" font-family="%s" font-size="%d">%s</text>',
            DG_MARGIN,
            $layout['separator'] - 7,
            $palette['foreground'],
            DG_TITLE_FONT,
            DG_FIELD_SIZE,
            dg_escape($words['isolated'])
        );
    }
    foreach (dg_route_foreign_keys($schema['foreignKeys'], $layout) as $route) {
        $foreignKey = $route['foreignKey'];
        $dash = $foreignKey['onDelete'] === 'RESTRICT' ? ' stroke-dasharray="5 3"' : '';
        $out[] = sprintf(
            '  <path data-kind="fk" data-from="%s.%s" data-to="%s.%s" data-delete="%s" d="%s" '
                . 'fill="none" stroke="%s" stroke-width="1.3" marker-end="url(#arrow)"%s/>',
            $foreignKey['fromTable'],
            $foreignKey['fromColumn'],
            $foreignKey['toTable'],
            $foreignKey['toColumn'],
            $foreignKey['onDelete'],
            dg_path_data($route['points']),
            $palette['line'],
            $dash
        );
    }
    $legendY = $layout['height'] - 28;
    $out[] = sprintf(
        '  <line data-kind="legend-cascade" x1="%d" y1="%d" x2="%d" y2="%d" stroke="%s" '
            . 'stroke-width="1.3" marker-end="url(#arrow)"/>',
        DG_MARGIN,
        $legendY - 4,
        DG_MARGIN + 32,
        $legendY - 4,
        $palette['line']
    );
    $out[] = sprintf(
        '  <text data-kind="legend" x="%d" y="%d" fill="%s" font-family="%s" font-size="%d">%s</text>',
        DG_MARGIN + 40,
        $legendY,
        $palette['foreground'],
        DG_TITLE_FONT,
        DG_FIELD_SIZE,
        dg_escape($words['cascade'])
    );
    $out[] = sprintf(
        '  <line data-kind="legend-restrict" x1="%d" y1="%d" x2="%d" y2="%d" stroke="%s" '
            . 'stroke-width="1.3" stroke-dasharray="5 3" marker-end="url(#arrow)"/>',
        250,
        $legendY - 4,
        282,
        $legendY - 4,
        $palette['line']
    );
    $out[] = sprintf(
        '  <text data-kind="legend" x="%d" y="%d" fill="%s" font-family="%s" font-size="%d">%s%s</text>',
        290,
        $legendY,
        $palette['foreground'],
        DG_TITLE_FONT,
        DG_FIELD_SIZE,
        dg_escape($words['restrict']),
        dg_escape($words['legendSeparator'] . $words['primaryKey'])
    );
    $out[] = '</svg>';
    return implode("\n", $out) . "\n";
}

function dg_dom(string $svg): DOMDocument
{
    $old = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $ok = $dom->loadXML($svg, LIBXML_NONET);
    $errors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($old);
    if (!$ok || $errors !== []) {
        $message = $errors === [] ? '無法解析 SVG' : trim($errors[0]->message);
        dg_fail('R-XML', $message);
    }
    return $dom;
}

function dg_xpath(DOMDocument $dom, string $query): DOMNodeList
{
    $nodes = (new DOMXPath($dom))->query($query);
    if ($nodes === false) {
        throw new RuntimeException("Invalid SVG XPath: $query");
    }
    return $nodes;
}

function dg_element_bounds(DOMElement $element): array
{
    return dg_bounds(
        (float) $element->getAttribute('x'),
        (float) $element->getAttribute('y'),
        (float) $element->getAttribute('width'),
        (float) $element->getAttribute('height')
    );
}

function dg_path_points(string $path): array
{
    if (!preg_match('/^M (-?\d+) (-?\d+) V (-?\d+) H (-?\d+) V (-?\d+) H (-?\d+) V (-?\d+)$/', $path, $m)) {
        dg_fail('R-FK', "無法解析外鍵 path: $path");
    }
    return [
        ['x' => (float) $m[1], 'y' => (float) $m[2]],
        ['x' => (float) $m[1], 'y' => (float) $m[3]],
        ['x' => (float) $m[4], 'y' => (float) $m[3]],
        ['x' => (float) $m[4], 'y' => (float) $m[5]],
        ['x' => (float) $m[6], 'y' => (float) $m[5]],
        ['x' => (float) $m[6], 'y' => (float) $m[7]],
    ];
}

function dg_independent_text_width(string $text, float $fontSize): float
{
    $units = 0.0;
    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
        $units += preg_match('/\s/u', $character) ? 0.32
            : (preg_match('/[\x{3000}-\x{303f}\x{3400}-\x{9fff}\x{f900}-\x{faff}\x{ff00}-\x{ffef}]/u', $character)
                ? 1.02 : 0.64);
    }
    return $units * $fontSize;
}

function dg_ranges_overlap(float $leftA, float $rightA, float $leftB, float $rightB): bool
{
    return max(min($leftA, $rightA), min($leftB, $rightB))
        < min(max($leftA, $rightA), max($leftB, $rightB));
}

function dg_visible_svg_extents(DOMDocument $dom, array $routePoints): array
{
    $extents = ['left' => INF, 'top' => INF, 'right' => -INF, 'bottom' => -INF];
    $include = static function (float $left, float $top, float $right, float $bottom) use (&$extents): void {
        $extents['left'] = min($extents['left'], $left);
        $extents['top'] = min($extents['top'], $top);
        $extents['right'] = max($extents['right'], $right);
        $extents['bottom'] = max($extents['bottom'], $bottom);
    };
    foreach (dg_xpath($dom, '//*[@data-kind]') as $element) {
        $kind = $element->getAttribute('data-kind');
        if ($kind === 'background' || $kind === 'fk') {
            continue;
        }
        if ($element->localName === 'rect') {
            $bounds = dg_element_bounds($element);
            $include($bounds['x'], $bounds['y'], $bounds['x'] + $bounds['width'], $bounds['y'] + $bounds['height']);
        } elseif ($element->localName === 'line') {
            $include(
                min((float) $element->getAttribute('x1'), (float) $element->getAttribute('x2')),
                min((float) $element->getAttribute('y1'), (float) $element->getAttribute('y2')),
                max((float) $element->getAttribute('x1'), (float) $element->getAttribute('x2')),
                max((float) $element->getAttribute('y1'), (float) $element->getAttribute('y2'))
            );
        } elseif ($element->localName === 'text') {
            $size = (float) $element->getAttribute('font-size');
            $width = dg_independent_text_width($element->textContent, $size);
            $x = (float) $element->getAttribute('x');
            if ($element->getAttribute('text-anchor') === 'middle') {
                $x -= $width / 2;
            }
            $y = (float) $element->getAttribute('y');
            $include($x, $y - $size, $x + $width, $y + 3);
        }
    }
    foreach ($routePoints as $points) {
        foreach ($points as $point) {
            $include($point['x'], $point['y'], $point['x'], $point['y']);
        }
    }
    return $extents;
}

function dg_assert_variant(string $svg, array $schema, array $model, string $locale): void
{
    $dom = dg_dom($svg);
    $root = $dom->documentElement;
    if ($root === null || !$root->hasAttribute('width') || !$root->hasAttribute('height')
        || !$root->hasAttribute('viewBox')) {
        dg_fail('R-CANVAS', 'SVG 缺少明確 width、height 或 viewBox');
    }
    $fields = dg_xpath($dom, '//*[@data-kind="field"]');
    $fieldNames = dg_xpath($dom, '//*[@data-kind="field-name"]');
    $tables = dg_xpath($dom, '//*[@data-kind="table"]');
    $tableNames = array_keys($schema['tables']);
    if ($tables->length !== count($tableNames)) {
        dg_fail('R-COVER', "表名框 {$tables->length} 張，預期 " . count($tableNames) . ' 張');
    }
    $seenTables = [];
    foreach ($tables as $table) {
        $seenTables[] = $table->attributes->getNamedItem('data-table')->nodeValue;
    }
    sort($seenTables, SORT_STRING);
    sort($tableNames, SORT_STRING);
    if ($seenTables !== $tableNames) {
        dg_fail('R-COVER', 'SVG 的表名框與 SQLite 資料表不一致');
    }
    $tableBounds = [];
    foreach ($tables as $table) {
        $tableBounds[$table->getAttribute('data-table')] = dg_element_bounds($table);
    }
    $expectedFields = [];
    foreach ($schema['tables'] as $tableName => $table) {
        foreach ($table['columns'] as $column) {
            $expectedFields[] = "$tableName.{$column['name']}";
        }
    }
    $seenFields = [];
    $fieldBounds = [];
    foreach ($fields as $field) {
        $key = $field->getAttribute('data-table') . '.' . $field->getAttribute('data-column');
        $seenFields[] = $key;
        $fieldBounds[$key] = dg_element_bounds($field);
    }
    sort($seenFields, SORT_STRING);
    sort($expectedFields, SORT_STRING);
    if ($seenFields !== $expectedFields) {
        dg_fail('R-COVER', '欄位格與 SQLite 欄位不一致');
    }
    $textOrder = [];
    $underlined = [];
    foreach ($fieldNames as $name) {
        $key = $name->getAttribute('data-table') . '.' . $name->getAttribute('data-column');
        $textOrder[$name->getAttribute('data-table')][] = $name->getAttribute('data-column');
        if ($name->hasAttribute('text-decoration')) {
            $underlined[] = $key;
        }
        $fieldBounds[$key]['text'] = $name->textContent;
        $fieldBounds[$key]['fontSize'] = (float) $name->getAttribute('font-size');
    }
    foreach ($schema['tables'] as $tableName => $table) {
        $expectedOrder = array_map(static fn(array $column): string => $column['name'], $table['columns']);
        if (($textOrder[$tableName] ?? []) !== $expectedOrder) {
            dg_fail('R-COVER', "$tableName 的欄位順序與 SQLite 不一致");
        }
    }
    $expectedPrimary = [];
    foreach ($schema['tables'] as $tableName => $table) {
        foreach ($table['columns'] as $column) {
            if ((int) $column['pk'] > 0) {
                $expectedPrimary[] = "$tableName.{$column['name']}";
            }
        }
    }
    sort($underlined, SORT_STRING);
    sort($expectedPrimary, SORT_STRING);
    if ($underlined !== $expectedPrimary) {
        dg_fail('R-PK', '帶底線格與 SQLite 主鍵欄位不一致（含多畫或少畫）');
    }
    $paths = dg_xpath($dom, '//*[@data-kind="fk"]');
    if ($paths->length !== count($schema['foreignKeys'])) {
        dg_fail('R-FK', "外鍵箭頭 {$paths->length} 條，預期 " . count($schema['foreignKeys']) . ' 條');
    }
    $actualForeignKeys = [];
    $routePoints = [];
    foreach ($paths as $path) {
        $from = $path->getAttribute('data-from');
        $to = $path->getAttribute('data-to');
        $actualForeignKeys[] = "$from>$to";
        $points = dg_path_points($path->getAttribute('d'));
        $routePoints[] = $points;
        $ends = [[$points[0], $fieldBounds[$from], '起點'], [$points[5], $fieldBounds[$to], '終點']];
        foreach ($ends as [$point, $bounds, $label]) {
            $onBottom = abs($point['y'] - ($bounds['y'] + $bounds['height'])) <= 0.5;
            $inWidth = $point['x'] >= $bounds['x'] - 0.5 && $point['x'] <= $bounds['x'] + $bounds['width'] + 0.5;
            if (!$onBottom || !$inWidth) {
                dg_fail('R-FK', "$from>$to 的$label 未落在欄位格下緣（誤差 > 0.5px）");
            }
        }
        $shouldDash = $path->getAttribute('data-delete') === 'RESTRICT';
        if ($shouldDash !== $path->hasAttribute('stroke-dasharray')) {
            dg_fail('R-DELETE', "$from>$to 的 ON DELETE 虛實線錯誤");
        }
    }
    $expectedForeignKeys = array_map(
        static fn(array $key): string => $key['fromTable'] . '.' . $key['fromColumn']
            . '>' . $key['toTable'] . '.' . $key['toColumn'],
        $schema['foreignKeys']
    );
    sort($actualForeignKeys, SORT_STRING);
    sort($expectedForeignKeys, SORT_STRING);
    if ($actualForeignKeys !== $expectedForeignKeys) {
        dg_fail('R-FK', 'SVG 外鍵箭頭與 SQLite 外鍵不一致');
    }
    foreach ($routePoints as $tipIndex => $tipRoute) {
        $tip = $tipRoute[5];
        $clearance = dg_bounds($tip['x'] - 3, $tip['y'] - 9, 6, 9);
        foreach ($routePoints as $routeIndex => $route) {
            if ($routeIndex === $tipIndex) {
                continue;
            }
            for ($segment = 0; $segment < count($route) - 1; $segment++) {
                if (dg_segment_intersects_bounds($route[$segment], $route[$segment + 1], $clearance)) {
                    $from = $paths->item($routeIndex)->getAttribute('data-from');
                    $target = $paths->item($tipIndex)->getAttribute('data-to');
                    dg_fail('R-ARROW', "$from 進入 $target 箭頭尖端下方淨空");
                }
            }
        }
    }
    if (dg_xpath($dom, '//*[@data-kind="legend-cascade"]')->length !== 1
        || dg_xpath($dom, '//*[@data-kind="legend-restrict"]')->length !== 1) {
        dg_fail('R-DELETE', '缺少 CASCADE 或 RESTRICT 圖例');
    }
    foreach ($routePoints as $pathIndex => $points) {
        $path = $paths->item($pathIndex);
        $except = [$path->getAttribute('data-from') => true, $path->getAttribute('data-to') => true];
        foreach ($fieldBounds as $key => $bounds) {
            if (isset($except[$key])) {
                continue;
            }
            for ($i = 0; $i < count($points) - 1; $i++) {
                if (dg_segment_intersects_bounds($points[$i], $points[$i + 1], $bounds)) {
                    dg_fail('R-ROUTE', "箭頭 {$path->getAttribute('data-from')} 穿過 $key 欄位格");
                }
            }
        }
        foreach ($tableBounds as $tableName => $bounds) {
            for ($i = 0; $i < count($points) - 1; $i++) {
                if (dg_segment_intersects_bounds($points[$i], $points[$i + 1], $bounds)) {
                    dg_fail('R-ROUTE', "箭頭 {$path->getAttribute('data-from')} 穿過 $tableName 表名框");
                }
            }
        }
    }
    $lanes = [];
    foreach ($routePoints as $points) {
        $lanes[] = $points[2]['x'];
    }
    sort($lanes, SORT_NUMERIC);
    for ($i = 1; $i < count($lanes); $i++) {
        if ($lanes[$i] - $lanes[$i - 1] < DG_LANE_GAP) {
            dg_fail('R-ROUTE', '外鍵專用走道間距小於 12px');
        }
    }
    for ($i = 0; $i < count($routePoints); $i++) {
        for ($j = $i + 1; $j < count($routePoints); $j++) {
            for ($a = 0; $a < 5; $a++) {
                for ($b = 0; $b < 5; $b++) {
                    $p = $routePoints[$i][$a];
                    $q = $routePoints[$i][$a + 1];
                    $r = $routePoints[$j][$b];
                    $s = $routePoints[$j][$b + 1];
                    $horizontal = $p['y'] === $q['y'] && $r['y'] === $s['y'] && $p['y'] === $r['y'];
                    $vertical = $p['x'] === $q['x'] && $r['x'] === $s['x'] && $p['x'] === $r['x'];
                    $overlap = $horizontal
                        ? dg_ranges_overlap($p['x'], $q['x'], $r['x'], $s['x'])
                        : ($vertical && dg_ranges_overlap($p['y'], $q['y'], $r['y'], $s['y']));
                    if ($overlap) {
                        $left = $paths->item($i)->getAttribute('data-from');
                        $right = $paths->item($j)->getAttribute('data-from');
                        dg_fail('R-ROUTE', "$left 與 $right 有重疊的共線線段");
                    }
                }
            }
        }
    }
    foreach ($fieldBounds as $key => $bounds) {
        $available = $bounds['width'] - 16;
        $zhWidth = dg_independent_text_width((string) $bounds['text'], (float) $bounds['fontSize']);
        $enWidth = dg_independent_text_width((string) $bounds['text'], (float) $bounds['fontSize']);
        if ($zhWidth > $available || $enWidth > $available) {
            dg_fail('R-FIT', "$key 的文字寬度超過格子可用寬度");
        }
    }
    $width = (float) $root->getAttribute('width');
    $height = (float) $root->getAttribute('height');
    if ($width > DG_CANVAS_MAX_WIDTH) {
        dg_fail('R-CANVAS', "畫布寬度 $width 超過 1400px");
    }
    $extents = dg_visible_svg_extents($dom, $routePoints);
    if (abs($extents['left'] - DG_MARGIN) > 1 || abs($extents['top'] - DG_MARGIN) > 1
        || abs($width - $extents['right'] - DG_MARGIN) > 1
        || abs($height - $extents['bottom'] - DG_MARGIN) > 1) {
        dg_fail('R-CANVAS', '可見內容未維持四邊 24±1px 留白');
    }
    foreach ($fieldBounds as $key => $bounds) {
        if ($bounds['x'] < DG_MARGIN - 1 || $bounds['y'] < DG_MARGIN - 1
            || $bounds['x'] + $bounds['width'] > $width - DG_MARGIN + 1
            || $bounds['y'] + $bounds['height'] > $height - DG_MARGIN + 1) {
            dg_fail('R-CANVAS', "$key 不在畫布 24±1px 留白內");
        }
    }
    foreach ($routePoints as $points) {
        foreach ($points as $point) {
            if ($point['x'] < DG_MARGIN - 1 || $point['x'] > $width - DG_MARGIN + 1
                || $point['y'] < DG_MARGIN - 1 || $point['y'] > $height - DG_MARGIN + 1) {
                dg_fail('R-CANVAS', '外鍵路徑超出畫布 24±1px 留白');
            }
        }
    }
    foreach (dg_xpath($dom, '//*[local-name()="text"]') as $text) {
        $font = $text->getAttribute('font-family');
        $size = (float) $text->getAttribute('font-size');
        $titleFont = html_entity_decode(DG_TITLE_FONT, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $monoFont = html_entity_decode(DG_MONO_FONT, ENT_QUOTES | ENT_XML1, 'UTF-8');
        if (($font !== $titleFont && $font !== $monoFont) || $size < 12) {
            dg_fail('R-FONT', '每個 text 必須使用指定字型且字級至少 12px');
        }
    }
    $cjk = '/[\x{3000}-\x{303f}\x{3400}-\x{9fff}\x{f900}-\x{faff}\x{ff00}-\x{ffef}]/u';
    if ($locale === 'en' && preg_match($cjk, $svg, $match)) {
        dg_fail('R-VARIANT', '英文 SVG 含有 CJK 字元：' . $match[0]);
    }
}

function dg_strip_colors(string $svg): string
{
    return preg_replace('/ (fill|stroke)="#[0-9a-fA-F]{6}"/', ' $1="COLOR"', $svg) ?? $svg;
}

function dg_assert_variants(array $variants): void
{
    foreach (['zh', 'en'] as $locale) {
        if (dg_strip_colors($variants["$locale-light"]) !== dg_strip_colors($variants["$locale-dark"])) {
            dg_fail('R-VARIANT', "$locale 亮暗版移除顏色後不相同");
        }
        foreach (['foreground', 'line', 'label'] as $color) {
            if (str_contains($variants["$locale-dark"], DG_PALETTES['light'][$color])) {
                dg_fail('R-VARIANT', "深色版出現亮色前景 {$color}");
            }
        }
    }
    foreach (['light', 'dark'] as $theme) {
        $zhCount = preg_match_all('/data-kind="(?:table|field|fk)"/', $variants["zh-$theme"]);
        $enCount = preg_match_all('/data-kind="(?:table|field|fk)"/', $variants["en-$theme"]);
        if ($zhCount !== $enCount) {
            dg_fail('R-VARIANT', "$theme 中英文圖形數量不一致");
        }
    }
}

function dg_self_test(): void
{
    $center = ['x' => 100.0, 'y' => 100.0];
    $tests = [
        [dg_shape_boundary_point('ellipse', $center, 50, 20, ['x' => 200, 'y' => 100]), ['x' => 150, 'y' => 100]],
        [dg_shape_boundary_point('diamond', $center, 50, 20, ['x' => 150, 'y' => 100]), ['x' => 150, 'y' => 100]],
        [dg_shape_boundary_point('diamond', $center, 50, 20, ['x' => 100, 'y' => 140]), ['x' => 100, 'y' => 120]],
        [dg_shape_boundary_point('rectangle', $center, 50, 20, ['x' => 200, 'y' => 140]), ['x' => 150, 'y' => 120]],
        [dg_shape_boundary_point('rectangle', $center, 50, 20, ['x' => 50, 'y' => 100]), ['x' => 50, 'y' => 100]],
    ];
    foreach ($tests as [$actual, $expected]) {
        if (abs($actual['x'] - $expected['x']) > 0.001 || abs($actual['y'] - $expected['y']) > 0.001) {
            throw new RuntimeException('self-test boundary point failed');
        }
    }
    $crosses = dg_segments_intersect(
        ['x' => 0, 'y' => 0], ['x' => 10, 'y' => 10], ['x' => 0, 'y' => 10], ['x' => 10, 'y' => 0]
    );
    if (!$crosses) {
        throw new RuntimeException('self-test crossing segments failed');
    }
    $parallel = dg_segments_intersect(
        ['x' => 0, 'y' => 0], ['x' => 10, 'y' => 0], ['x' => 0, 'y' => 2], ['x' => 10, 'y' => 2]
    );
    if ($parallel) {
        throw new RuntimeException('self-test parallel segments failed');
    }
    if (!dg_bounds_overlap(dg_bounds(0, 0, 10, 10), dg_bounds(9, 9, 10, 10))) {
        throw new RuntimeException('self-test bounds overlap failed');
    }
    if (dg_bounds_overlap(dg_bounds(0, 0, 10, 10), dg_bounds(10, 0, 10, 10))) {
        throw new RuntimeException('self-test touching bounds failed');
    }
    if (dg_estimated_text_width('中 A', 10) !== 19.2) {
        throw new RuntimeException('self-test text width failed');
    }
    echo "DIAGRAMS SELF-TEST PASS\n";
}

function dg_main(array $arguments): void
{
    if (in_array('--self-test', $arguments, true)) {
        dg_self_test();
        return;
    }
    $outputDir = dg_root() . '/docs/diagrams';
    $outputIndex = array_search('--output-dir', $arguments, true);
    if ($outputIndex !== false && isset($arguments[$outputIndex + 1])) {
        $outputDir = $arguments[$outputIndex + 1];
    }
    $schema = dg_read_schema(dg_open_database());
    $model = dg_load_model();
    $order = dg_validate_order($schema, $model);
    $layout = dg_layout($schema, $order);
    $variants = [];
    foreach (['zh', 'en'] as $locale) {
        foreach (['light', 'dark'] as $theme) {
            $key = "$locale-$theme";
            $variants[$key] = dg_svg($schema, $model, $layout, $locale, $theme);
            dg_assert_variant($variants[$key], $schema, $model, $locale);
        }
    }
    dg_assert_variants($variants);
    if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
        throw new RuntimeException("Cannot create output directory: $outputDir");
    }
    foreach ($variants as $key => $svg) {
        file_put_contents("$outputDir/relational-schema-$key.svg", $svg);
    }
    $chen = chen_generate(chen_model(), $schema);
    foreach ($chen as $file => $svg) {
        file_put_contents("$outputDir/$file", $svg);
    }
    printf("DIAGRAMS PASS tables=%d fields=%d primaryKeys=%d foreignKeys=%d width=%d height=%d\n",
        count($schema['tables']),
        count($layout['fields']),
        count(array_filter($layout['fields'], static fn(array $field): bool => $field['pk'])),
        count($schema['foreignKeys']),
        $layout['width'],
        $layout['height']);
}

try {
    dg_main($argv);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
