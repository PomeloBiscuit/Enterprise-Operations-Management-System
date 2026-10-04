<?php
declare(strict_types=1);

/** Shared, dependency-free geometry helpers for generated data-model diagrams. */

function dg_bounds(float $x, float $y, float $width, float $height): array
{
    return ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height];
}

function dg_bounds_overlap(array $left, array $right): bool
{
    return $left['x'] < $right['x'] + $right['width']
        && $right['x'] < $left['x'] + $left['width']
        && $left['y'] < $right['y'] + $right['height']
        && $right['y'] < $left['y'] + $left['height'];
}

function dg_orientation(array $a, array $b, array $c): float
{
    return ($b['x'] - $a['x']) * ($c['y'] - $a['y'])
        - ($b['y'] - $a['y']) * ($c['x'] - $a['x']);
}

function dg_point_on_segment(array $point, array $start, array $end): bool
{
    return abs(dg_orientation($start, $end, $point)) < 0.00001
        && $point['x'] >= min($start['x'], $end['x']) - 0.00001
        && $point['x'] <= max($start['x'], $end['x']) + 0.00001
        && $point['y'] >= min($start['y'], $end['y']) - 0.00001
        && $point['y'] <= max($start['y'], $end['y']) + 0.00001;
}

function dg_segments_intersect(array $a, array $b, array $c, array $d): bool
{
    $abC = dg_orientation($a, $b, $c);
    $abD = dg_orientation($a, $b, $d);
    $cdA = dg_orientation($c, $d, $a);
    $cdB = dg_orientation($c, $d, $b);
    if (($abC > 0) !== ($abD > 0) && ($cdA > 0) !== ($cdB > 0)) {
        return true;
    }
    return (abs($abC) < 0.00001 && dg_point_on_segment($c, $a, $b))
        || (abs($abD) < 0.00001 && dg_point_on_segment($d, $a, $b))
        || (abs($cdA) < 0.00001 && dg_point_on_segment($a, $c, $d))
        || (abs($cdB) < 0.00001 && dg_point_on_segment($b, $c, $d));
}

function dg_segment_intersects_bounds(array $start, array $end, array $bounds): bool
{
    if ($start['x'] > $bounds['x'] && $start['x'] < $bounds['x'] + $bounds['width']
        && $start['y'] > $bounds['y'] && $start['y'] < $bounds['y'] + $bounds['height']) {
        return true;
    }
    $x = $bounds['x'];
    $y = $bounds['y'];
    $w = $bounds['width'];
    $h = $bounds['height'];
    $edges = [
        [['x' => $x, 'y' => $y], ['x' => $x + $w, 'y' => $y]],
        [['x' => $x + $w, 'y' => $y], ['x' => $x + $w, 'y' => $y + $h]],
        [['x' => $x + $w, 'y' => $y + $h], ['x' => $x, 'y' => $y + $h]],
        [['x' => $x, 'y' => $y + $h], ['x' => $x, 'y' => $y]],
    ];
    foreach ($edges as [$edgeStart, $edgeEnd]) {
        if (dg_segments_intersect($start, $end, $edgeStart, $edgeEnd)) {
            return true;
        }
    }
    return false;
}

function dg_shape_boundary_point(string $shape, array $center, float $rx, float $ry, array $toward): array
{
    $dx = $toward['x'] - $center['x'];
    $dy = $toward['y'] - $center['y'];
    if (abs($dx) < 0.00001 && abs($dy) < 0.00001) {
        return $center;
    }
    if ($shape === 'ellipse') {
        $scale = 1 / sqrt(($dx * $dx) / ($rx * $rx) + ($dy * $dy) / ($ry * $ry));
    } elseif ($shape === 'diamond') {
        $scale = 1 / (abs($dx) / $rx + abs($dy) / $ry);
    } elseif ($shape === 'rectangle') {
        $scale = min($rx / abs($dx ?: 0.00001), $ry / abs($dy ?: 0.00001));
    } else {
        throw new InvalidArgumentException("Unknown diagram shape: $shape");
    }
    return ['x' => $center['x'] + $dx * $scale, 'y' => $center['y'] + $dy * $scale];
}

function dg_estimated_text_width(string $text, float $fontSize): float
{
    $width = 0.0;
    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
        if (preg_match('/\s/u', $character)) {
            $width += 0.3;
        } elseif (preg_match('/[\x{3000}-\x{303f}\x{3400}-\x{9fff}\x{f900}-\x{faff}\x{ff00}-\x{ffef}]/u', $character)) {
            $width += 1.0;
        } else {
            $width += 0.62;
        }
    }
    return $width * $fontSize;
}

function dg_segment_bounds_distance(array $start, array $end, array $bounds): float
{
    if (dg_segment_intersects_bounds($start, $end, $bounds)) {
        return 0.0;
    }
    $nearestX = max($bounds['x'], min(max($start['x'], $end['x']), $bounds['x'] + $bounds['width']));
    $nearestY = max($bounds['y'], min(max($start['y'], $end['y']), $bounds['y'] + $bounds['height']));
    $candidates = [
        ['x' => $bounds['x'], 'y' => $bounds['y']],
        ['x' => $bounds['x'] + $bounds['width'], 'y' => $bounds['y']],
        ['x' => $bounds['x'], 'y' => $bounds['y'] + $bounds['height']],
        ['x' => $bounds['x'] + $bounds['width'], 'y' => $bounds['y'] + $bounds['height']],
        ['x' => $nearestX, 'y' => $nearestY],
    ];
    $best = INF;
    foreach ($candidates as $point) {
        $dx = $end['x'] - $start['x'];
        $dy = $end['y'] - $start['y'];
        $lengthSquared = $dx * $dx + $dy * $dy;
        $dot = ($point['x'] - $start['x']) * $dx + ($point['y'] - $start['y']) * $dy;
        $ratio = $lengthSquared === 0.0 ? 0.0 : $dot / $lengthSquared;
        $ratio = max(0.0, min(1.0, $ratio));
        $px = $start['x'] + $ratio * $dx;
        $py = $start['y'] + $ratio * $dy;
        $best = min($best, hypot($point['x'] - $px, $point['y'] - $py));
    }
    return $best;
}
