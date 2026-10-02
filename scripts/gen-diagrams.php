<?php
declare(strict_types=1);

/* Generates SVGs from the live SQLite schema.  Do not hand-edit generated SVGs. */
const ROOT = __DIR__ . '/..';
const FONT_SIZE = 13;
const OUTPUT_NAMES = ['er-transactions', 'er-identity', 'er-system', 'relational-schema'];

function fail(string $message): never { fwrite(STDERR, "DIAGRAMS FAIL: $message\n"); exit(1); }
function xml(string $s): string { return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
function rectPoint(array $a, array $b): array {
    $dx=$b['x']-$a['x']; $dy=$b['y']-$a['y']; $hx=$a['w']/2; $hy=$a['h']/2;
    $scale = 1 / max(abs($dx)/$hx ?: 0.0001, abs($dy)/$hy ?: 0.0001);
    return ['x'=>(int)round($a['x']+$dx*$scale), 'y'=>(int)round($a['y']+$dy*$scale)];
}
function estimate(string $s): int { return max(42, (int)ceil(mb_strlen($s, 'UTF-8') * 8.2 + 26)); }
function line(array $a, array $b, string $kind, array $meta=[]): array { return compact('a','b','kind','meta'); }
function palette(string $theme): array { return $theme==='dark' ? ['bg'=>'#101820','fg'=>'#f2f6fa','line'=>'#c1d4e5','fill'=>'#1d2a35','accent'=>'#8dcbff','muted'=>'#263744'] : ['bg'=>'#ffffff','fg'=>'#17212b','line'=>'#29445b','fill'=>'#f2f6fa','accent'=>'#075ca8','muted'=>'#dce6f0']; }
function loadSchema(PDO $db): array {
    $tables=[]; $fks=[];
    foreach ($db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name") as $row) {
        $name=$row['name']; $cols=[];
        foreach ($db->query('PRAGMA table_info("'.str_replace('"','""',$name).'")') as $c) $cols[]=['name'=>$c['name'],'pk'=>(int)$c['pk'],'type'=>$c['type']];
        $tables[$name]=$cols;
        foreach ($db->query('PRAGMA foreign_key_list("'.str_replace('"','""',$name).'")') as $f) $fks[]=['from'=>$name,'column'=>$f['from'],'to'=>$f['table'],'toColumn'=>$f['to'],'delete'=>strtoupper($f['on_delete'])];
    }
    return [$tables,$fks];
}
function validateModel(array $model, array $tables, array $fks): void {
    foreach ($model['entities'] as $table=>$entity) {
        isset($tables[$table]) || fail("conceptual model entity $table is not a database table");
        $actual=array_map(fn($x)=>$x['name'],$tables[$table]); $wanted=array_map(fn($x)=>$x[0],$entity['attributes']);
        $actual===$wanted || fail("attribute order for $table does not equal conceptual-model.json");
    }
    foreach ($tables as $table=>$_) isset($model['entities'][$table]) || fail("database table $table has no conceptual model entry");
    $expected=[]; foreach ($model['relationships'] as $r) $expected[$r['from'].'.'.$r['fromColumn'].'>'.$r['to'].'.'.$r['toColumn']]=true;
    $actual=[]; foreach ($fks as $f) $actual[$f['from'].'.'.$f['column'].'>'.$f['to'].'.'.$f['toColumn']]=true;
    foreach ($actual as $fk=>$_) isset($expected[$fk]) || fail("foreign key $fk has no conceptual relationship");
    foreach ($expected as $fk=>$_) isset($actual[$fk]) || fail("conceptual relationship $fk is not a real foreign key");
}
function entityPositions(string $name, array $entities): array {
    $system=['User'=>[170,260],'Employee'=>[580,260],'admin'=>[990,260],'Customer'=>[170,950],'Orders'=>[580,950],'Shipment'=>[990,950],'orderandinvoice'=>[170,1640],'Contain'=>[580,1640],'Product'=>[990,1640]];
    $transaction=['Customer'=>[160,300],'Orders'=>[550,300],'Shipment'=>[940,300],'orderandinvoice'=>[160,930],'Contain'=>[550,930],'Product'=>[940,930],'Employee'=>[940,1180]];
    $identity=['User'=>[210,400],'Employee'=>[580,400],'admin'=>[950,400]];
    $base=$name==='er-system'?$system:($name==='er-transactions'?$transaction:$identity); $out=[];
    // 230px is derived from the longest bilingual entity label; every locale reuses this geometry.
    foreach ($entities as $entity) { [$x,$y]=$base[$entity]; $out[$entity]=['x'=>$x,'y'=>$y,'w'=>230,'h'=>48]; }
    return $out;
}
function chenDiagram(string $name, array $model, array $tables): array {
    $entities=$model['diagrams'][$name]; $positions=entityPositions($name,$entities); $shapes=[]; $lines=[]; $labels=[];
    foreach ($entities as $entity) {
        $p=$positions[$entity]; $external=$name==='er-transactions' && $entity==='Employee';
        $shapes[]=['type'=>'entity','id'=>$entity,'x'=>$p['x'],'y'=>$p['y'],'w'=>$p['w'],'h'=>$p['h'],'external'=>$external];
        $labels[]=['id'=>$entity,'x'=>$p['x'],'y'=>$p['y']+5,'w'=>$p['w']-12,'h'=>18,'text'=>$entity,'kind'=>'entity'];
        $attrs=$model['entities'][$entity]['attributes'];
        foreach ($attrs as $i=>$attribute) {
            if ($external && $i>0) continue;
            $side=$i%2===0?-1:1; $row=intdiv($i,2); $x=$p['x']+$side*(145+($i%4===0?12:0)); $y=$p['y']-155+$row*42;
            $w=max(104,estimate($attribute[1]),estimate($attribute[2])); $shape=['type'=>'attribute','id'=>$entity.'.'.$attribute[0],'x'=>$x,'y'=>$y,'w'=>$w,'h'=>32,'pk'=>in_array($attribute[0],array_map(fn($c)=>$c['name'],$tables[$entity]) ? array_map(fn($c)=>$c['name'],$tables[$entity]) : [],true) && false];
            $shape['pk']=in_array($attribute[0],array_map(fn($c)=>$c['name'],$tables[$entity]),true) && (bool)array_filter($tables[$entity],fn($c)=>$c['name']===$attribute[0] && $c['pk']>0);
            $shapes[]=$shape; $labels[]=['id'=>$shape['id'],'x'=>$x,'y'=>$y+5,'w'=>$w,'h'=>18,'text'=>$attribute[0],'zh'=>$attribute[1],'en'=>$attribute[2],'kind'=>'attribute','pk'=>$shape['pk']];
            $from=rectPoint($shape,$p); $to=rectPoint($p,$shape); $lines[]=line($from,$to,'attribute',['from'=>$shape['id'],'to'=>$entity]);
        }
    }
    if ($name!=='er-identity') foreach ($model['relationships'] as $r) if (isset($positions[$r['from']],$positions[$r['to']])) {
        $a=$positions[$r['from']]; $b=$positions[$r['to']]; $cx=(int)round(($a['x']+$b['x'])/2); $cy=(int)round(($a['y']+$b['y'])/2); $rw=max(82,estimate($r['zh']),estimate($r['en'])); $rel=['type'=>'relationship','id'=>$r['id'],'x'=>$cx,'y'=>$cy,'w'=>$rw,'h'=>52];
        $shapes[]=$rel; $labels[]=['id'=>$r['id'],'x'=>$cx,'y'=>$cy+5,'w'=>$rw,'h'=>18,'text'=>$r['id'],'zh'=>$r['zh'],'en'=>$r['en'],'kind'=>'relationship'];
        $lines[]=line(rectPoint($a,$rel),rectPoint($rel,$a),'relationship',['from'=>$r['from'],'to'=>$r['id'],'cardinality'=>$r['fromCardinality'],'total'=>$r['total']===$r['from']]);
        $lines[]=line(rectPoint($rel,$b),rectPoint($b,$rel),'relationship',['from'=>$r['id'],'to'=>$r['to'],'cardinality'=>$r['toCardinality'],'total'=>$r['total']===$r['to']]);
    }
    return ['name'=>$name,'width'=>1160,'height'=>$name==='er-system'?2010:($name==='er-transactions'?1450:720),'shapes'=>$shapes,'lines'=>$lines,'labels'=>$labels];
}
function relationalDiagram(array $model, array $tables, array $fks): array {
    $positions=entityPositions('er-system',array_keys($tables)); $shapes=[];$labels=[];$lines=[];
    foreach ($positions as $table=>$p) { $h=50+count($tables[$table])*25; $p['h']=$h; $positions[$table]=$p; $shapes[]=['type'=>'table','id'=>$table]+$p; $labels[]=['id'=>$table,'x'=>$p['x'],'y'=>$p['y']-$h/2+20,'w'=>$p['w']-12,'h'=>18,'text'=>$table,'kind'=>'table']; foreach($tables[$table] as $i=>$c) $labels[]=['id'=>$table.'.'.$c['name'],'x'=>$p['x'],'y'=>$p['y']-$h/2+47+$i*25,'w'=>$p['w']-12,'h'=>18,'text'=>$c['name'],'kind'=>'column','pk'=>$c['pk']>0]; }
    foreach ($fks as $f) { $a=$positions[$f['from']];$b=$positions[$f['to']];$lines[]=line(rectPoint($a,$b),rectPoint($b,$a),'foreign-key',['from'=>$f['from'].'.'.$f['column'],'to'=>$f['to'].'.'.$f['toColumn'],'delete'=>$f['delete']]); }
    return ['name'=>'relational-schema','width'=>1160,'height'=>2010,'shapes'=>$shapes,'lines'=>$lines,'labels'=>$labels];
}
function validateDiagram(array &$d, string $lang, string $theme, array $tables): void {
    $mutate=getenv('DIAGRAM_TEST_MUTATE') ?: '';
    if ($mutate==='endpoint' && $d['lines']) { $d['lines'][0]['a']['x']+=2; $d['lines'][0]['a']['y']+=2; }
    if ($mutate==='underline') { foreach($d['labels'] as &$l) if($l['kind']==='attribute' && empty($l['pk'])) {$l['pk']=true;break;} unset($l); }
    $d['width']<=1200 || fail("{$d['name']} width exceeds 1200px");
    foreach($d['labels'] as $l) { FONT_SIZE>=12 || fail('font is smaller than 12px'); $text=$l[$lang]??$l['text']; estimate($text)<=$l['w'] || fail("label $text does not fit {$l['id']}"); if(!empty($l['pk'])) { [$t,$c]=explode('.', $l['id'],2)+[null,null]; if($c!==null && !array_filter($tables[$t]??[],fn($x)=>$x['name']===$c && $x['pk']>0)) fail("underlined text {$l['id']} is not a primary key"); } }
    foreach($d['lines'] as $i=>$l) { $from=null;$to=null; foreach($d['shapes'] as $s){if($s['id']===$l['meta']['from'])$from=$s;if($s['id']===$l['meta']['to'])$to=$s;} if($from && $to){ foreach([['p'=>$l['a'],'s'=>$from],['p'=>$l['b'],'s'=>$to]] as $edge){$p=$edge['p'];$s=$edge['s'];$on=abs(abs($p['x']-$s['x'])-$s['w']/2)<0.6 || abs(abs($p['y']-$s['y'])-$s['h']/2)<0.6; $on || fail("line $i endpoint does not land on {$s['id']} boundary");} } }
}
function render(array $d,string $lang,string $theme,array $model): string {
    $p=palette($theme); $out=['<?xml version="1.0" encoding="UTF-8"?>','<!-- Generated by scripts/gen-diagrams.php; do not hand-edit. -->',sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" role="img" aria-labelledby="title desc">',$d['width'],$d['height'],$d['width'],$d['height']),'<title id="title">'.xml($d['name'].' '.$lang).'</title>','<desc id="desc">Generated SQLite data model diagram</desc>',"<style>.bg{fill:{$p['bg']}}.entity,.table{fill:{$p['fill']};stroke:{$p['line']};stroke-width:2}.attribute{fill:{$p['muted']};stroke:{$p['line']};stroke-width:2}.relationship{fill:{$p['fill']};stroke:{$p['accent']};stroke-width:2}.line{stroke:{$p['line']};stroke-width:2;fill:none}.text{fill:{$p['fg']};font-family:{$model['fontFamily']};font-size:13px}.pk{text-decoration:underline}.external{stroke-dasharray:7 4}</style>","<rect class=\"bg\" x=\"0\" y=\"0\" width=\"{$d['width']}\" height=\"{$d['height']}\" fill=\"{$p['bg']}\"/>"];
    foreach($d['lines'] as $l){$dash=($l['kind']==='foreign-key' && ($l['meta']['delete']??'')!=='CASCADE')?' stroke-dasharray="7 4"':'';$arrow=$l['kind']==='foreign-key'?' marker-end="url(#arrow)':'';$out[]=sprintf('<line class="line" x1="%d" y1="%d" x2="%d" y2="%d" stroke="%s"%s%s/>',$l['a']['x'],$l['a']['y'],$l['b']['x'],$l['b']['y'],$p['line'],$dash,$arrow);}
    $out[]="<defs><marker id=\"arrow\" markerWidth=\"8\" markerHeight=\"8\" refX=\"7\" refY=\"4\" orient=\"auto\"><path d=\"M0,0 L8,4 L0,8 z\" fill=\"{$p['line']}\"/></marker></defs>";
    foreach($d['shapes'] as $s){$class=$s['type']; if(!empty($s['external']))$class.=' external';if($s['type']==='attribute')$out[]=sprintf('<ellipse class="%s" cx="%d" cy="%d" rx="%d" ry="%d" fill="%s" stroke="%s"/>',$class,$s['x'],$s['y'],(int)($s['w']/2),(int)($s['h']/2),$p['muted'],$p['line']);elseif($s['type']==='relationship'){$x=$s['x'];$y=$s['y'];$w=(int)($s['w']/2);$h=(int)($s['h']/2);$out[]="<polygon class=\"$class\" points=\"$x,".($y-$h).' '.($x+$w).",$y $x,".($y+$h).' '.($x-$w).",$y\" fill=\"{$p['fill']}\" stroke=\"{$p['accent']}\"/>";}else $out[]=sprintf('<rect class="%s" x="%d" y="%d" width="%d" height="%d" rx="6" fill="%s" stroke="%s"/>',$class,(int)($s['x']-$s['w']/2),(int)($s['y']-$s['h']/2),$s['w'],$s['h'],$p['fill'],$p['line']);}
    foreach($d['labels'] as $l){$text=$l[$lang]??$l['text']; $klass='text'.(!empty($l['pk'])?' pk':'');$out[]=sprintf('<text class="%s" x="%d" y="%d" text-anchor="middle" fill="%s" font-family="%s" font-size="13">%s</text>',$klass,$l['x'],$l['y'],$p['fg'],xml($model['fontFamily']),xml($text));}
    $out[]='</svg>'; return implode("\n",$out)."\n";
}
function stripColours(string $s): string { return preg_replace('/#[0-9a-fA-F]{6}/','COLOR',$s); }
$dbPath=getenv('DIAGRAM_DB') ?: ROOT.'/data/fiance2024.sqlite'; $outDir=getenv('DIAGRAM_OUTPUT') ?: ROOT.'/docs/diagrams'; is_file($dbPath)||fail("SQLite database unavailable: $dbPath");
$model=json_decode((string)file_get_contents(ROOT.'/docs/diagrams/conceptual-model.json'),true,512,JSON_THROW_ON_ERROR); $db=new PDO('sqlite:'.$dbPath); [$tables,$fks]=loadSchema($db); validateModel($model,$tables,$fks);
$diagrams=[]; foreach(['er-transactions','er-identity','er-system'] as $name)$diagrams[$name]=chenDiagram($name,$model,$tables); $diagrams['relational-schema']=relationalDiagram($model,$tables,$fks);
$outputs=[]; foreach($diagrams as $name=>$diagram) foreach(['zh','en'] as $lang) foreach(['light','dark'] as $theme){$copy=$diagram;validateDiagram($copy,$lang,$theme,$tables);$outputs["$name-$lang-$theme.svg"]=render($copy,$lang,$theme,$model);} foreach(OUTPUT_NAMES as $name) foreach(['zh','en'] as $lang){$light=$outputs["$name-$lang-light.svg"];$dark=$outputs["$name-$lang-dark.svg"];stripColours($light)===stripColours($dark)||fail("$name $lang light/dark structure differs");} foreach(OUTPUT_NAMES as $name) foreach(['light','dark'] as $theme){preg_match_all('/<(rect|ellipse|polygon|line)\b/',$outputs["$name-zh-$theme.svg"],$a);preg_match_all('/<(rect|ellipse|polygon|line)\b/',$outputs["$name-en-$theme.svg"],$b);count($a[0])===count($b[0])||fail("$name $theme zh/en shape count differs");} is_dir($outDir)||mkdir($outDir,0777,true);foreach($outputs as $file=>$content)file_put_contents($outDir.'/'.$file,$content);echo 'DIAGRAMS PASS: '.count($outputs).' SVGs; '.count($tables).' tables; '.count($fks)." foreign keys\n";
