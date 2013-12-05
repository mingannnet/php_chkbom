<?php

if(isset($_GET['rw']) && 0){
	$GLOBALS['RW']=1;
}
else{
	$GLOBALS['RW']=0;
}
$GLOBALS['RW']=0;

$GLOBALS['DIR']=dirname(__FILE__).'/';
$baseDir='./';

$GLOBALS['report']=array(
	'bad-directory-name'=>0,
	'bom'=>0,
	'bad-file-starter'=>0,
	'short-open-tag'=>0,
	'tag-not-match'=>0,
	'tag-not-lowercase'=>0,
	'bad-end'=>0,
	'bad-file-name'=>0,
	'multibytes-string'=>0,
);

checkBOM($baseDir);

?><p>
<h3>Scanner Report:</h3>
<div>Bad directory name: <?php echo $GLOBALS['report']['bad-directory-name']; ?></div>
<div>BOM: <?php echo $GLOBALS['report']['bom']; ?></div>
<div>Bad file starter: <?php echo $GLOBALS['report']['bad-file-starter']; ?></div>
<div>Short open tag: <?php echo $GLOBALS['report']['short-open-tag']; ?></div>
<div>Tag amount not match: <?php echo $GLOBALS['report']['tag-not-match']; ?></div>
<div>Tag not lowercase: <?php echo $GLOBALS['report']['tag-not-lowercase']; ?></div>
<div>Bad end: <?php echo $GLOBALS['report']['bad-end']; ?></div>
<div>Bad file name: <?php echo $GLOBALS['report']['bad-file-name']; ?></div>
<div>Multibytes string: <?php echo $GLOBALS['report']['multibytes-string']; ?></div>

</p><?php
function checkBOM($path){
	$d=opendir($GLOBALS['DIR'].$path);
	echo '<div style="color:#999;">Check Dir:'.$path.'</div>'."\n";
	if(!preg_match('/^[\w_\.\-\/]+$/',$path)){
		$GLOBALS['report']['bad-directory-name']++;
		echo '<strong style="color:#CD853F;">Warning: Bad Directory Name: '.$path.'</strong><br />'."\n";
	}
	while(($f=readdir($d))!=false){
		if(!strcmp($f,'.') || !strcmp($f,'..')){
			continue;
		}
		if(is_dir($GLOBALS['DIR'].$path.$f)){
			checkBOM($path.$f.'/');
		}
		else{
			if(!preg_match('/^[\w_\.\-]+$/',$f)){
				$GLOBALS['report']['bad-file-name']++;
				echo '<strong style="color:#FFA500;">Warning: Bad File Name: '.$path.$f.'</strong><br />'."\n";
			}
			$subname=explode('.',$f);
			$subname=strtolower($subname[count($subname)-1]);
			if(in_array($subname,array('php','htm','html','phtml','pharos','inc','php4','php5','php3','asp'))){
				$data=str_replace("\r",'',join('',file($path.$f)));
				if(strcmp('<',substr($data,0,1))){
					$c[0]=ord(substr($data,0,1));
					$c[1]=ord(substr($data,1,1));
					$c[2]=ord(substr($data,2,1));
					$c[3]=ord(substr($data,3,1));
					if ($c[0] == 239 && $c[1] == 187 && $c[2] == 191) {
						$GLOBALS['report']['bom']++;
						echo '<strong style="color:red">Error: BOM Detect: '.$path.$f.'</strong><br />'."\n";
					}
					else if($c[0]==254 && $c[1]==255){
						$GLOBALS['report']['bom']++;
						echo '<strong style="color:red">Error: UTF-16 BOM Detect: '.$path.$f.'</strong><br />'."\n";
					}
					else if($c[0]==255 && $c[1]==254){
						$GLOBALS['report']['bom']++;
						echo '<strong style="color:red">Error: UTF-16 BOM Detect: '.$path.$f.'</strong><br />'."\n";
					}
					else{
						$GLOBALS['report']['bad-file-starter']++;
						echo '<strong style="color:#a30;">Warning: Queer File Starter(Not by open angle bracket &lt;): '.$path.$f.'</strong><br />'."\n";
					}
				}
				if(preg_match_all('/<\?(.{2,4})/',$data,$m)){
					for($i=0,$n=count($m[0]);$i<$n;$i++){
						if(!strcmp('<?php ',$m[0][$i]) && !strcmp('<?xml ',$m[0][$i]) && !strcmp('<??>',$m[0][$i]) && !strcmp('<? ?>',$m[0][$i])){
							continue;
						}
						else{
							$GLOBALS['report']['short-open-tag']++;
							echo '<strong style="color:red;">Error: short_open_tag: '.$path.$f.'</strong><br />'."\n";
							break;
						}
					}
				}
				if(preg_match_all('/<([a-zA-Z0-9:])[\s>]/',$data,$m)){
					$tags=array();
					for($i=0,$n=count($m[0]);$i<$n;$i++){
						if(!in_array($m[1][$i],array('img','input','hr','br'))){
							if(!isset($tags[$m[1][$i]])) $tags[$m[1][$i]]=0;
							$tags[$m[1][$i]]++;
						}
					}
					foreach($tags as $tag => $n1){
						if(strcmp($tag,strtolower($tag))){
							$GLOBALS['report']['tag-not-lowercase']++;
							echo '<div style="color:#09b;">Notice: Tag Must LowerCase &lt;'.$tag.'&gt; in '.$path.$f.'</div>'."\n";
						}
						$n2=substr_count($data,'</'.$tag.'>');
						if($n1!=$n2){
							$GLOBALS['report']['tag-not-match']++;
							echo '<strong style="color:#00a;">Warning: Tag Amount Not Match: &lt;'.$tag.'&gt;('.$n1.') and &lt;/'.$tag.'&gt;('.$n2.') in '.$path.$f.'</strong><br />'."\n";
						}
					}
				}
				if(strcmp('>',substr($data,strlen($data)-1,1))){
					$GLOBALS['report']['bad-end']++;
					echo '<strong style="color:green">Warning: Bad End(Not by close angle bracket &gt;): '.$path.$f.'</strong><br />'."\n";
				}
				$data=str_replace('/\/\*[^(\*\/)]+\*\//','',$data);
				$data=str_replace('[^:]/\/\/[^\n]+\n/','\\1',$data);
				if(preg_match('/[\x8f-\xff]+/',$data)){
					$GLOBALS['report']['multibytes-string']++;
					echo '<code style="color:#99f;">Notice: Detect Multibytes String: '.$path.$f.'</code><br />'."\n";
				}
				if($GLOBALS['RW'] && strcmp('drop_',substr($f,0,5)) && is_writable($path.$f)){
					rename($path.$f,$path.'drop_'.$f);
					$data=trim($data);
					if(strcmp('<',substr($data,0,1))){
						$data=substr($data,strpos($data,'<'));
					}
					$data=preg_replace('/\s+\n/','',$data);
					$data=preg_replace('/\)\s+\{/','){',$data);
					$data=preg_replace('/\n\s+\{/','',$data);
					$data=str_replace('<?','<?php',$data);
					$data=str_replace('<?phpphp','<?php',$data);
					$data=str_replace('<?phpxml','<?xml',$data);
					$fo=fopen($path.$f,"w");
					fputs($fo,$data);
					fclose($fo);
					echo '<code>Update File: '.$path.$f.'</code><br />'."\n";
				}
			}
		}
	}
	closedir($d);
}
?>
